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
			'/stripe/connect',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => array( $this, 'handle_stripe_connect' ),
			)
		);

		register_rest_route(
			'lutecia/v1',
			'/stripe/disconnect',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => array( $this, 'handle_stripe_disconnect' ),
			)
		);

		register_rest_route(
			'lutecia/v1',
			'/stripe/retry-import',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => array( $this, 'handle_stripe_retry_import' ),
			)
		);

		register_rest_route(
			'lutecia/v1',
			'/wizard',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => array( $this, 'handle_wizard' ),
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

	/** A Stripe connection started and not completed after this long gives write access back. */
	private const STRIPE_ABANDONED_AFTER = 900;

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
		$this->settle_abandoned_stripe_connect( $data );
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
			'stripe_acs'           => isset( $data['stripe_acs'] ) && is_array( $data['stripe_acs'] ) ? $this->stripe_payload( $data['stripe_acs'] ) : null,
		);
	}

	public function handle_channel_access( \WP_REST_Request $request ): \WP_REST_Response {
		$channel = (string) $request->get_param( 'channel' );
		$access  = trim( (string) $request->get_param( 'access' ) );
		$allowed = array( 'chatgpt', 'google', 'microsoft', 'perplexity', 'stripe_acs_hooks' );
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
			if ( 'checkout' === $channel && $enabled && 0 === $this->connection->stripe_write_since() ) {
				$this->connection->set_key_permissions( 'read' );
			}
			return new \WP_REST_Response( array( 'message' => $data->get_error_message() ), 502 );
		}

		// The Stripe channel also creates orders: keep write access while it holds it.
		if ( 'checkout' === $channel && ! $enabled && 0 === $this->connection->stripe_write_since() ) {
			$this->connection->set_key_permissions( 'read' );
		}

		// The manifest served on /.well-known/ucp reflects channel flags:
		// bust the local cache so the change is visible immediately.
		Discovery::flush_cache();

		return new \WP_REST_Response( $this->settings_payload( $data ), 200 );
	}

	/**
	 * "Connect Stripe": write access for the orders of Stripe sales (the
	 * merchant confirmed it), then the Stripe page to open.
	 */
	public function handle_stripe_connect(): \WP_REST_Response {
		if ( ! $this->connection->is_connected() ) {
			return new \WP_REST_Response( array( 'message' => __( 'Connect the store first.', 'lutecia-for-woocommerce' ) ), 409 );
		}
		if ( ! $this->connection->grant_stripe_write() ) {
			return new \WP_REST_Response( array( 'message' => __( 'Could not grant order-creation access.', 'lutecia-for-woocommerce' ) ), 500 );
		}
		$url = $this->connection->hub_stripe_connect_url( admin_url( 'admin.php?page=lutecia' ) );
		if ( is_wp_error( $url ) ) {
			$this->connection->release_stripe_write( self::checkout_on( $this->connection->hub_settings() ) );
			return new \WP_REST_Response( array( 'message' => $url->get_error_message() ), 502 );
		}
		// Back from Stripe, the screen reopens on the Stripe step.
		Wizard::save( array( 'step' => Wizard::STEP_STRIPE ) );
		return new \WP_REST_Response( array( 'url' => $url ), 200 );
	}

	/** "Disconnect Stripe": the channel off on the hub, write access given back unless Checkout needs it. */
	public function handle_stripe_disconnect(): \WP_REST_Response {
		if ( ! $this->connection->is_connected() ) {
			return new \WP_REST_Response( array( 'message' => __( 'Connect the store first.', 'lutecia-for-woocommerce' ) ), 409 );
		}
		$result = $this->connection->hub_stripe_disconnect();
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'message' => $result->get_error_message() ), 502 );
		}
		Wizard::forget_stripe();
		$data = $this->connection->hub_settings();
		$this->connection->release_stripe_write( self::checkout_on( $data ) );
		if ( is_wp_error( $data ) ) {
			return new \WP_REST_Response( array( 'message' => $data->get_error_message() ), 502 );
		}
		return new \WP_REST_Response( $this->settings_payload( $data ), 200 );
	}

	/**
	 * "Send again": the hub imports the catalog into Stripe once more. When
	 * the hub starts nothing (409), the screen gets a 409 and the hub's
	 * sentence, and counts nothing as started.
	 */
	public function handle_stripe_retry_import(): \WP_REST_Response {
		if ( ! $this->connection->is_connected() ) {
			return new \WP_REST_Response( array( 'message' => __( 'Connect the store first.', 'lutecia-for-woocommerce' ) ), 409 );
		}
		$result = $this->connection->hub_stripe_retry_import();
		if ( is_wp_error( $result ) ) {
			$status = 'lutecia_stripe_retry_waiting' === $result->get_error_code() ? 409 : 502;
			return new \WP_REST_Response( array( 'message' => $result->get_error_message() ), $status );
		}
		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/** Saves where the merchant is in the setup wizard: step, and Done on Assistants. */
	public function handle_wizard( \WP_REST_Request $request ): \WP_REST_Response {
		$params = (array) $request->get_json_params();
		$patch  = array_intersect_key( $params, array_flip( array( 'step', 'assistants_done' ) ) );
		return new \WP_REST_Response( Wizard::save( $patch ), 200 );
	}

	/**
	 * Whether Checkout is on in a hub settings answer. Unknown (hub error)
	 * counts as on: write access is kept rather than Checkout broken.
	 *
	 * @param array|\WP_Error $data Result of Connection::hub_settings().
	 */
	public static function checkout_on( $data ): bool {
		return is_wp_error( $data ) || ! empty( $data['flags']['ucp.checkout_enabled'] );
	}

	/** Gives write access back when a Stripe connection was started and never completed. */
	private function settle_abandoned_stripe_connect( array $data ): void {
		$since = $this->connection->stripe_write_since();
		if ( $since <= 0 || ! isset( $data['stripe_acs'] ) || ! empty( $data['stripe_acs']['connected'] ) ) {
			return;
		}
		if ( time() - $since > self::STRIPE_ABANDONED_AFTER ) {
			$this->connection->release_stripe_write( self::checkout_on( $data ) );
		}
	}

	/**
	 * The Stripe block for the screen: hub values cast, and each refused
	 * product given its name and edit link from this store.
	 */
	private function stripe_payload( array $stripe ): array {
		$refusals = array();
		foreach ( isset( $stripe['refusals'] ) ? (array) $stripe['refusals'] : array() as $refusal ) {
			$refusal    = (array) $refusal;
			$product_id = absint( isset( $refusal['product_id'] ) ? $refusal['product_id'] : 0 );
			$refusals[] = array(
				'field'  => sanitize_key( isset( $refusal['field'] ) ? (string) $refusal['field'] : '' ),
				'reason' => sanitize_text_field( isset( $refusal['reason'] ) ? (string) $refusal['reason'] : '' ),
				'title'  => $product_id ? (string) get_post_field( 'post_title', $product_id, 'raw' ) : '',
				'edit'   => $product_id ? (string) get_edit_post_link( $product_id, 'raw' ) : '',
			);
		}
		// Each sale given its order number and edit link from this store.
		$sales = array();
		foreach ( isset( $stripe['sales'] ) ? (array) $stripe['sales'] : array() as $sale ) {
			$sale     = (array) $sale;
			$order_id = absint( isset( $sale['wc_order_id'] ) ? $sale['wc_order_id'] : 0 );
			$order    = ( $order_id && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : false;
			$sales[]  = array(
				'number'       => $order ? (string) $order->get_order_number() : ( $order_id ? (string) $order_id : '' ),
				'edit'         => $order ? (string) $order->get_edit_order_url() : '',
				'agent'        => sanitize_text_field( isset( $sale['agent'] ) ? (string) $sale['agent'] : '' ),
				'amount_cents' => absint( isset( $sale['amount_cents'] ) ? $sale['amount_cents'] : 0 ),
				'currency'     => sanitize_key( isset( $sale['currency'] ) ? (string) $sale['currency'] : '' ),
			);
		}
		// Unknown readiness (null) stays null: the Account block then shows no activation link.
		$ready = isset( $stripe['account_ready'] ) ? (bool) $stripe['account_ready'] : null;
		// Unknown (null) stays null: the Agentic commerce block then waits.
		$agentic = isset( $stripe['agentic_enabled'] ) ? (bool) $stripe['agentic_enabled'] : null;
		return array(
			'available'            => ! empty( $stripe['available'] ),
			'connected'            => ! empty( $stripe['connected'] ),
			'account_name'         => sanitize_text_field( isset( $stripe['account_name'] ) ? (string) $stripe['account_name'] : '' ),
			'account_ready'        => $ready,
			'hook_mode'            => ( isset( $stripe['hook_mode'] ) && 'platform' === $stripe['hook_mode'] ) ? 'platform' : 'merchant',
			'hook_url'             => esc_url_raw( isset( $stripe['hook_url'] ) ? (string) $stripe['hook_url'] : '' ),
			'hook_secret_received' => ! empty( $stripe['hook_secret_received'] ),
			'hooks_verified'       => ! empty( $stripe['hooks_verified'] ),
			'agentic_enabled'      => $agentic,
			'catalog_imported'     => ! empty( $stripe['catalog_imported'] ),
			'products_sent'        => absint( isset( $stripe['products_sent'] ) ? $stripe['products_sent'] : 0 ),
			'products_refused'     => absint( isset( $stripe['products_refused'] ) ? $stripe['products_refused'] : 0 ),
			'agent_sales'          => absint( isset( $stripe['agent_sales'] ) ? $stripe['agent_sales'] : 0 ),
			'refusals'             => $refusals,
			'sales'                => $sales,
		);
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
		// A fresh connection opens the setup wizard on its first step.
		Wizard::save( array( 'step' => 1 ) );
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
