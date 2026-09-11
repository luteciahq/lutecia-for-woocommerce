<?php
/**
 * Agent-traffic attribution: captures the ?ref=lutecia_... parameter that
 * Lutecia puts on every product link served to AI agents, and reports the
 * sale to the hub once the order is paid.
 *
 * Opt-in, off by default: nothing in this class runs unless the merchant
 * turned on "Sales attribution" on the dashboard (Plugin::OPT_ATTRIBUTION).
 * While it is off, the only hook is forget_cookie(), which expires a cookie
 * left over from an earlier opt-in.
 *
 * Flow:
 *   1. A visitor lands with ?ref=lutecia_... -> stored in a 30-day cookie
 *      (last click wins).
 *   2. When an order is placed, the ref is copied into order meta
 *      (classic checkout and Store API/blocks checkout both covered).
 *   3. When the order reaches a paid status, a single scheduled event is
 *      queued (the status change often runs inside a payment gateway
 *      callback, which must not wait on an outbound request). The event
 *      sends a signed HMAC report to POST {hub}/api/plugin/conversion. The
 *      hub deduplicates per order, so retries are safe; the order is only
 *      marked reported on a 2xx.
 *
 * Orders created by the Lutecia agent checkout itself (meta
 * _lutecia_checkout_id) are skipped: those are already attributed through
 * the checkout pipeline, reporting them here would double count.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class Attribution {

	private const COOKIE_NAME   = 'lutecia_ref';
	private const COOKIE_TTL_S  = 30 * DAY_IN_SECONDS;
	private const META_REF      = '_lutecia_ref';
	private const META_REPORTED = '_lutecia_ref_reported';
	private const META_ATTEMPTS = '_lutecia_ref_attempts';
	private const MAX_ATTEMPTS  = 6;
	private const RETRY_BASE_S  = 10 * MINUTE_IN_SECONDS;
	private const REF_PATTERN   = '/^lutecia_[A-Za-z0-9_\-]{1,190}$/';
	private const EVENT_HOOK    = 'lutecia_report_conversion';

	/** Order statuses considered paid. */
	private const PAID_STATUSES = array( 'processing', 'completed' );

	/** @var Connection */
	private $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	/** True when the merchant opted in to sales attribution. */
	public static function is_enabled(): bool {
		return '1' === (string) get_option( Plugin::OPT_ATTRIBUTION, '' );
	}

	public function register(): void {
		add_action( 'init', array( $this, 'capture_ref' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_order' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'stamp_order' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_report' ), 10, 3 );
		add_action( self::EVENT_HOOK, array( $this, 'report_order' ) );
	}

	/** While attribution is off: expire any cookie left from an earlier opt-in. */
	public function register_cleanup(): void {
		add_action( 'init', array( $this, 'forget_cookie' ) );
	}

	public function forget_cookie(): void {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) || headers_sent() ) {
			return;
		}
		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires'  => time() - DAY_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	/** Stores a valid ?ref= in a cookie. Last click wins. */
	public function capture_ref(): void {
		if ( ! isset( $_GET['ref'] ) || headers_sent() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$ref = sanitize_text_field( wp_unslash( $_GET['ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! preg_match( self::REF_PATTERN, $ref ) ) {
			return;
		}
		setcookie(
			self::COOKIE_NAME,
			$ref,
			array(
				'expires'  => time() + self::COOKIE_TTL_S,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE_NAME ] = $ref; // Visible to this same request.
	}

	/** Copies the cookie ref onto the order at checkout time. */
	public function stamp_order( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}
		$ref = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		if ( ! preg_match( self::REF_PATTERN, $ref ) ) {
			return;
		}
		$order->update_meta_data( self::META_REF, $ref );
	}

	/**
	 * Queues the report once the order reaches a paid status. Cheap checks
	 * only: the request itself runs from the scheduled event.
	 *
	 * @param int    $order_id   Order id.
	 * @param string $old_status Previous status (unused).
	 * @param string $new_status New status.
	 */
	public function maybe_report( $order_id, $old_status, $new_status ): void {
		if ( ! in_array( $new_status, self::PAID_STATUSES, true ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->is_reportable( $order ) ) {
			return;
		}
		$args = array( (int) $order_id );
		if ( ! wp_next_scheduled( self::EVENT_HOOK, $args ) ) {
			wp_schedule_single_event( time(), self::EVENT_HOOK, $args );
		}
	}

	/** An order carries a ref, was not placed by the agent checkout, and is not reported yet. */
	private function is_reportable( \WC_Order $order ): bool {
		if ( '' === (string) $order->get_meta( self::META_REF ) ) {
			return false;
		}
		if ( '' !== (string) $order->get_meta( '_lutecia_checkout_id' ) ) {
			return false; // Agent-checkout order: already attributed by the hub.
		}
		return '' === (string) $order->get_meta( self::META_REPORTED );
	}

	/**
	 * Sends the signed report for one order (scheduled event handler).
	 *
	 * @param int $order_id Order id.
	 */
	public function report_order( $order_id ): void {
		if ( Site_Guard::in_duplicate_mode() ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->is_reportable( $order ) ) {
			return;
		}
		$ref = (string) $order->get_meta( self::META_REF );

		$client_id = $this->connection->client_id();
		$secret    = (string) get_option( Plugin::OPT_WEBHOOK_SECRET, '' );
		if ( '' === $client_id || '' === $secret ) {
			return;
		}

		$payload = array(
			'client_id'         => $client_id,
			'site_url'          => get_option( 'home' ),
			'order_id'          => (string) $order->get_id(),
			'ref_code'          => $ref,
			'order_total_cents' => (int) round( (float) $order->get_total() * 100 ),
			'currency'          => $order->get_currency(),
			'status'            => $order->get_status(),
			'timestamp'         => time(),
		);
		$body = wp_json_encode( $payload );

		$response = wp_remote_post(
			LUTECIA_WC_HUB_URL . '/api/plugin/conversion',
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'        => 'application/json',
					'X-Lutecia-Signature' => hash_hmac( 'sha256', $body, $secret ),
				),
				'body'    => $body,
			)
		);

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			$order->update_meta_data( self::META_REPORTED, gmdate( 'c' ) );
			$order->save();
			return;
		}

		// The hub was unreachable or refused: try again later, spacing the
		// attempts out, so a sale paid during an outage is still reported.
		// The hub deduplicates per order, a late duplicate is harmless.
		$attempts = (int) $order->get_meta( self::META_ATTEMPTS ) + 1;
		$order->update_meta_data( self::META_ATTEMPTS, $attempts );
		$order->save();
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return;
		}
		$args = array( (int) $order->get_id() );
		if ( ! wp_next_scheduled( self::EVENT_HOOK, $args ) ) {
			wp_schedule_single_event( time() + self::RETRY_BASE_S * $attempts, self::EVENT_HOOK, $args );
		}
	}
}
