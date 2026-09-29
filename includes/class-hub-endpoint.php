<?php
/**
 * Inbound route the hub calls for a setting only this site can apply
 * (namespace lutecia/v1, route /hub/settings).
 *
 * Authentication is the shared webhook secret: the hub signs the raw body
 * with HMAC-SHA256 (hex) in X-Lutecia-Signature, the scheme this plugin
 * uses towards the hub. The body carries a timestamp and a request older
 * than five minutes is refused, so a captured request cannot be replayed
 * later. The action is idempotent, a replay inside the window changes
 * nothing.
 *
 * Two channels are accepted, both about write access on the store key,
 * which only code running on this site can change: checkout, and stripe
 * (the Stripe channel creates paid orders, so the key is held in write
 * mode while it is connected). Every other flag is written hub-side.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class Hub_Endpoint {

	/** Seconds a signed request stays valid. */
	private const REPLAY_WINDOW = 300;

	/** @var Connection */
	private $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'lutecia/v1',
			'/hub/settings',
			array(
				'methods'             => 'POST',
				// The caller is the hub, not a WordPress user: the handler
				// authenticates the request with the shared secret.
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'handle_settings' ),
			)
		);
	}

	public function handle_settings( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->connection->is_connected() || Site_Guard::in_duplicate_mode() ) {
			return new \WP_REST_Response( array( 'message' => 'This site is not connected.' ), 409 );
		}

		$raw      = (string) $request->get_body();
		$secret   = (string) get_option( Plugin::OPT_WEBHOOK_SECRET, '' );
		$received = (string) $request->get_header( 'X-Lutecia-Signature' );
		if ( '' === $secret || '' === $received || ! hash_equals( hash_hmac( 'sha256', $raw, $secret ), $received ) ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid signature.' ), 401 );
		}

		$body = json_decode( $raw, true );
		if ( ! is_array( $body ) || (string) ( $body['client_id'] ?? '' ) !== $this->connection->client_id() ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid signature.' ), 401 );
		}
		if ( abs( time() - (int) ( $body['ts'] ?? 0 ) ) > self::REPLAY_WINDOW ) {
			return new \WP_REST_Response( array( 'message' => 'Stale request.' ), 401 );
		}
		$channel = (string) ( $body['channel'] ?? '' );
		if ( 'stripe' === $channel ) {
			return $this->apply_stripe( ! empty( $body['enabled'] ) );
		}
		if ( 'checkout' !== $channel ) {
			return new \WP_REST_Response( array( 'message' => 'Unknown channel.' ), 400 );
		}

		$enabled = ! empty( $body['enabled'] );
		// The Stripe channel also creates orders: Checkout off keeps write
		// access while the channel holds it.
		$permissions = ( $enabled || $this->connection->stripe_write_since() > 0 ) ? 'read_write' : 'read';
		if ( ! $this->connection->set_key_permissions( $permissions ) ) {
			return new \WP_REST_Response( array( 'message' => 'Could not change the key permissions on the site.' ), 500 );
		}

		// The manifest served on /.well-known/ucp reflects channel flags.
		Discovery::flush_cache();

		return new \WP_REST_Response( array( 'ok' => true, 'channel' => 'checkout', 'enabled' => $enabled ), 200 );
	}

	/**
	 * Holds or releases write access for the Stripe channel, as the plugin
	 * screen does when the merchant connects Stripe there. Releasing keeps
	 * write access while Checkout is on (or unknown).
	 */
	private function apply_stripe( bool $enabled ): \WP_REST_Response {
		if ( $enabled ) {
			if ( ! $this->connection->grant_stripe_write() ) {
				return new \WP_REST_Response( array( 'message' => 'Could not change the key permissions on the site.' ), 500 );
			}
		} else {
			$this->connection->release_stripe_write( Rest::checkout_on( $this->connection->hub_settings() ) );
		}

		Discovery::flush_cache();

		return new \WP_REST_Response( array( 'ok' => true, 'channel' => 'stripe', 'enabled' => $enabled ), 200 );
	}
}
