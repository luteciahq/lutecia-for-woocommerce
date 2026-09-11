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
 * Only the checkout channel is accepted: turning it on gives the store key
 * write access, which only code running on this site can do. Every other
 * flag is written hub-side.
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
		if ( 'checkout' !== ( $body['channel'] ?? '' ) ) {
			return new \WP_REST_Response( array( 'message' => 'Unknown channel.' ), 400 );
		}

		$enabled = ! empty( $body['enabled'] );
		if ( ! $this->connection->set_key_permissions( $enabled ? 'read_write' : 'read' ) ) {
			return new \WP_REST_Response( array( 'message' => 'Could not change the key permissions on the site.' ), 500 );
		}

		// The manifest served on /.well-known/ucp reflects channel flags.
		Discovery::flush_cache();

		return new \WP_REST_Response( array( 'ok' => true, 'channel' => 'checkout', 'enabled' => $enabled ), 200 );
	}
}
