<?php
/**
 * Notifies the hub when the catalog changes, so syncs happen within
 * minutes instead of waiting for the next scheduled pull.
 *
 * Payloads are HMAC-SHA256 signed with the webhook secret issued at
 * connection time and sent to POST {hub}/api/plugin/ping.
 *
 * Notifications are debounced through a single scheduled event: bulk
 * edits of 500 products produce one ping, not 500.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class Webhooks {

	private const EVENT_HOOK = 'lutecia_notify_hub';
	private const DEBOUNCE_S = 60;

	/** @var Connection */
	private $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	public function register(): void {
		add_action( 'woocommerce_update_product', array( $this, 'schedule_notify' ) );
		add_action( 'woocommerce_new_product', array( $this, 'schedule_notify' ) );
		add_action( 'woocommerce_delete_product', array( $this, 'schedule_notify' ) );
		// Variations save through their own hooks: a sale that reduces the
		// stock of a variation never fires woocommerce_update_product.
		add_action( 'woocommerce_update_product_variation', array( $this, 'schedule_notify' ) );
		add_action( 'woocommerce_new_product_variation', array( $this, 'schedule_notify' ) );
		add_action( 'woocommerce_delete_product_variation', array( $this, 'schedule_notify' ) );
		add_action( 'wp_trash_post', array( $this, 'maybe_schedule_for_post' ) );
		add_action( 'untrashed_post', array( $this, 'maybe_schedule_for_post' ) );
		add_action( self::EVENT_HOOK, array( $this, 'notify_hub' ) );
	}

	public function maybe_schedule_for_post( $post_id ): void {
		if ( 'product' === get_post_type( $post_id ) ) {
			$this->schedule_notify();
		}
	}

	public function schedule_notify(): void {
		if ( ! wp_next_scheduled( self::EVENT_HOOK ) ) {
			wp_schedule_single_event( time() + self::DEBOUNCE_S, self::EVENT_HOOK );
		}
	}

	/**
	 * Sends a signed "catalog changed" ping. The hub reacts by pulling the
	 * changed data through the REST API: the payload stays minimal on purpose
	 * (no product data travels through this channel).
	 */
	public function notify_hub(): void {
		if ( Site_Guard::in_duplicate_mode() ) {
			return;
		}
		$client_id = $this->connection->client_id();
		$secret    = (string) get_option( Plugin::OPT_WEBHOOK_SECRET, '' );
		if ( '' === $client_id || '' === $secret ) {
			return;
		}

		$payload = array(
			'event_type' => 'catalog.changed',
			'client_id'  => $client_id,
			'shop_url'   => get_option( 'home' ),
			'timestamp'  => time(),
		);
		$body    = wp_json_encode( $payload );

		wp_remote_post(
			LUTECIA_WC_HUB_URL . '/api/plugin/ping',
			array(
				'timeout'  => 10,
				'blocking' => false,
				'headers'  => array(
					'Content-Type'        => 'application/json',
					'X-Lutecia-Signature' => hash_hmac( 'sha256', $body, $secret ),
				),
				'body'     => $body,
			)
		);
	}
}
