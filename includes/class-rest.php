<?php
/**
 * REST routes backing the admin screen (namespace lutecia/v1).
 *
 * All routes require the manage_woocommerce capability and the standard
 * X-WP-Nonce header (cookie auth): they are for the store admin only.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class Rest {

	/** @var Connection */
	private $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$permission = static function () {
			return current_user_can( 'manage_woocommerce' );
		};

		register_rest_route(
			'lutecia/v1',
			'/connect',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => array( $this, 'handle_connect' ),
			)
		);

		register_rest_route(
			'lutecia/v1',
			'/disconnect',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => array( $this, 'handle_disconnect' ),
			)
		);

		register_rest_route(
			'lutecia/v1',
			'/channels',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $permission,
					'callback'            => array( $this, 'handle_get_channels' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $permission,
					'callback'            => array( $this, 'handle_set_channel' ),
				),
			)
		);

		register_rest_route(
			'lutecia/v1',
			'/channel-access',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => array( $this, 'handle_channel_access' ),
			)
		);

		register_rest_route(
			'lutecia/v1',
			'/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_reset' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			'lutecia/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'permission_callback' => $permission,
				'callback'            => array( $this, 'handle_status' ),
			)
		);
	}

	/**
	 * Merchant-facing channels and the hub flags each one drives.
	 * One toggle can flip several protocol flags: merchants think in
	 * channels, not in protocols.
	 */
	private const CHANNELS = array(
		'chatgpt'   => array( 'openai.enabled' ),
		'realtime'  => array( 'mcp.enabled', 'ucp.enabled' ),
		'bulk_feed' => array( 'ucp.feed_enabled', 'google_shopping.enabled' ),
		'checkout'  => array( 'ucp.checkout_enabled' ),
	);

	/**
	 * Sales attribution is a store-side setting (a WordPress option), not a
	 * hub flag: it decides whether this store sets the ref cookie and
	 * reports paid orders. Off by default, never touched by the hub.
	 */
	private const LOCAL_ATTRIBUTION = 'attribution';

	public function handle_get_channels(): \WP_REST_Response {
		// Never contact the hub before the store is connected: connecting is
		// the merchant's opt-in, and there is nothing to fetch without it.
		if ( ! $this->connection->is_connected() ) {
			return new \WP_REST_Response( array( 'message' => __( 'Connect the store first.', 'lutecia-for-woocommerce' ) ), 409 );
		}
		$data = $this->connection->hub_settings();
		if ( is_wp_error( $data ) ) {
			return new \WP_REST_Response( array( 'message' => $data->get_error_message() ), 502 );
		}
		return new \WP_REST_Response( $this->settings_payload( $data ), 200 );
	}

	/**
	 * Shapes the hub settings payload for the admin screen: channel
	 * toggles plus the data the channel cards render (catalog readiness,
	 * access receipts, statuses verification date).
	 */
	private function settings_payload( array $data ): array {
		$channels                            = $this->flags_to_channels( isset( $data['flags'] ) ? (array) $data['flags'] : array() );
		$channels[ self::LOCAL_ATTRIBUTION ] = Attribution::is_enabled();
		return array(
			'channels'             => $channels,
			'catalog'              => isset( $data['catalog'] ) ? (array) $data['catalog'] : null,
			'channel_access'       => isset( $data['channel_access'] ) ? (array) $data['channel_access'] : array(),
			'statuses_verified_on' => isset( $data['statuses_verified_on'] ) ? (string) $data['statuses_verified_on'] : '',
		);
	}

	public function handle_channel_access( \WP_REST_Request $request ): \WP_REST_Response {
		$channel = (string) $request->get_param( 'channel' );
		$access  = trim( (string) $request->get_param( 'access' ) );
		$allowed = array( 'chatgpt', 'google', 'microsoft', 'perplexity' );
		if ( ! in_array( $channel, $allowed, true ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'Unknown channel.', 'lutecia-for-woocommerce' ) ), 400 );
		}
		if ( '' === $access ) {
			return new \WP_REST_Response( array( 'message' => __( 'Paste the access you received first.', 'lutecia-for-woocommerce' ) ), 400 );
		}
		$result = $this->connection->hub_channel_access( $channel, $access );
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'message' => $result->get_error_message() ), 502 );
		}
		return new \WP_REST_Response(
			array(
				'channel'     => $channel,
				'received_at' => isset( $result['received_at'] ) ? (string) $result['received_at'] : '',
			),
			200
		);
	}

	public function handle_set_channel( \WP_REST_Request $request ): \WP_REST_Response {
		$channel = (string) $request->get_param( 'channel' );
		$enabled = rest_sanitize_boolean( $request->get_param( 'enabled' ) );

		if ( self::LOCAL_ATTRIBUTION === $channel ) {
			// Local option only: no hub call, nothing leaves the store.
			update_option( Plugin::OPT_ATTRIBUTION, $enabled ? '1' : '', false );
			return new \WP_REST_Response( array( 'channels' => array( self::LOCAL_ATTRIBUTION => $enabled ) ), 200 );
		}

		if ( ! isset( self::CHANNELS[ $channel ] ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'Unknown channel.', 'lutecia-for-woocommerce' ) ), 400 );
		}

		// Direct purchase needs write access on our key; every other channel
		// is read-only. Upgrade before enabling, downgrade after disabling.
		if ( 'checkout' === $channel && $enabled && ! $this->connection->set_key_permissions( 'read_write' ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Could not grant order-creation access.', 'lutecia-for-woocommerce' ) ),
				500
			);
		}

		$patch = array();
		foreach ( self::CHANNELS[ $channel ] as $dotted ) {
			list( $dest, $flag )     = explode( '.', $dotted, 2 );
			$patch[ $dest ][ $flag ] = $enabled;
		}
		$data = $this->connection->hub_settings( $patch );
		if ( is_wp_error( $data ) ) {
			if ( 'checkout' === $channel && $enabled ) {
				$this->connection->set_key_permissions( 'read' );
			}
			return new \WP_REST_Response( array( 'message' => $data->get_error_message() ), 502 );
		}

		if ( 'checkout' === $channel && ! $enabled ) {
			$this->connection->set_key_permissions( 'read' );
		}

		// The manifest served on /.well-known/ucp reflects channel flags:
		// bust the local cache so the change is visible immediately.
		Discovery::flush_cache();

		return new \WP_REST_Response( $this->settings_payload( $data ), 200 );
	}

	private function flags_to_channels( array $flags ): array {
		$channels = array();
		foreach ( self::CHANNELS as $name => $dotted_flags ) {
			$enabled = true;
			foreach ( $dotted_flags as $dotted ) {
				$enabled = $enabled && ! empty( $flags[ $dotted ] );
			}
			$channels[ $name ] = $enabled;
		}
		return $channels;
	}

	public function handle_connect( \WP_REST_Request $request ): \WP_REST_Response {
		if ( Site_Guard::in_duplicate_mode() ) {
			return new \WP_REST_Response( array( 'message' => __( 'This site is a copy of a connected store. Choose what to do on the Lutecia screen first.', 'lutecia-for-woocommerce' ) ), 409 );
		}
		$result = $this->connection->connect(
			array(
				'agency_code' => sanitize_text_field( (string) $request->get_param( 'agency_code' ) ),
				'source'      => 'screen',
			)
		);
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new \WP_REST_Response(
			array(
				'connected' => true,
				'client_id' => $this->connection->client_id(),
				'agency'    => $this->connection->last_agency,
			),
			200
		);
	}

	public function handle_disconnect(): \WP_REST_Response {
		$this->connection->disconnect();
		return new \WP_REST_Response( array( 'connected' => false ), 200 );
	}

	/** "This site has moved": forget the old link locally, then the admin reconnects. */
	public function handle_reset(): \WP_REST_Response {
		if ( ! Site_Guard::in_duplicate_mode() ) {
			return new \WP_REST_Response( array( 'message' => __( 'Nothing to reset.', 'lutecia-for-woocommerce' ) ), 400 );
		}
		// The key copied along with the database would otherwise stay valid
		// next to the one the reconnect creates.
		$this->connection->revoke_wc_api_key();
		Site_Guard::reset_for_reconnect();
		return new \WP_REST_Response( array( 'connected' => false, 'reset' => true ), 200 );
	}

	/**
	 * Local status plus a health probe of the discovery endpoint, so the
	 * admin screen can show "agents can see your store" with proof.
	 */
	public function handle_status(): \WP_REST_Response {
		$connected = $this->connection->is_connected() && ! Site_Guard::in_duplicate_mode();

		$discovery_ok = false;
		if ( $connected ) {
			$probe        = wp_remote_get( home_url( '/.well-known/ucp' ), array( 'timeout' => 5 ) );
			$discovery_ok = ! is_wp_error( $probe ) && 200 === wp_remote_retrieve_response_code( $probe );

			// Some hosts cannot loop back to their own public URL. The hub
			// handshake is an equally meaningful signal: the store's domain
			// resolves to a known, discoverable client.
			if ( ! $discovery_ok ) {
				$host      = wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
				$handshake = wp_remote_get(
					LUTECIA_WC_HUB_URL . '/v1/ucp/handshake?host=' . rawurlencode( (string) $host ),
					array( 'timeout' => 5 )
				);
				$discovery_ok = ! is_wp_error( $handshake ) && 200 === wp_remote_retrieve_response_code( $handshake );
			}
		}

		return new \WP_REST_Response(
			array(
				'connected'     => $connected,
				'duplicate'     => Site_Guard::in_duplicate_mode() ? Site_Guard::duplicate_details() : null,
				'client_id'     => $this->connection->client_id(),
				'connected_at'  => (int) get_option( Plugin::OPT_CONNECTED_AT, 0 ),
				'discovery_ok'  => $discovery_ok,
				'discovery_url' => home_url( '/.well-known/ucp' ),
				'product_count' => (int) wp_count_posts( 'product' )->publish,
			),
			200
		);
	}
}
