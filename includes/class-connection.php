<?php
/**
 * One-click connection between the store and the Lutecia hub.
 *
 * Flow (no copy-paste, everything happens inside wp-admin):
 *   1. Merchant clicks "Connect" -> connect() runs with admin rights.
 *   2. The plugin creates a WooCommerce REST API key pair (read-only).
 *   3. It POSTs {shop_url, keys, meta} to the hub registration endpoint.
 *   4. The hub tests the connection, creates the client, and returns
 *      {client_id, webhook_secret}.
 *   5. The plugin stores both and the store is live.
 *
 * Disconnect revokes the WooCommerce key locally and notifies the hub.
 *
 * Hub endpoints used by this class (every call after register is signed
 * with an X-Lutecia-Signature header: HMAC-SHA256 of the body with the
 * webhook secret):
 *   POST /api/plugin/register        -> 200 {client_id, webhook_secret} | 4xx {detail}
 *   POST /api/plugin/disconnect      -> 200
 *   POST /api/plugin/settings        -> 200 {flags, catalog, channel_access, ...}
 *   POST /api/plugin/channel-access  -> 200 {ok, channel, received_at}
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class Connection {

	private const KEY_DESCRIPTION = 'Lutecia (AI shopping assistants), created by the Lutecia plugin';

	public const OPT_TERMS_ACCEPTED = 'lutecia_terms_accepted';

	/** @var array|null {name, status, message?} returned by the hub on the last connect. */
	public $last_agency = null;

	public function is_connected(): bool {
		return '' !== (string) get_option( Plugin::OPT_CLIENT_ID, '' );
	}

	public function client_id(): string {
		return (string) get_option( Plugin::OPT_CLIENT_ID, '' );
	}

	/**
	 * Runs the full one-click connect. Returns a WP_Error on failure so the
	 * REST layer can surface a human-readable message.
	 *
	 * @return true|\WP_Error
	 */
	public function connect( array $options = array() ) {
		if ( $this->is_connected() ) {
			return new \WP_Error( 'lutecia_already_connected', __( 'This store is already connected.', 'lutecia-for-woocommerce' ) );
		}
		if ( 0 === get_current_user_id() ) {
			return new \WP_Error( 'lutecia_no_user', __( 'The WooCommerce API key must be created in the name of an administrator. From WP-CLI, run the command with --user=<login>.', 'lutecia-for-woocommerce' ) );
		}
		$agency_code = isset( $options['agency_code'] ) ? trim( (string) $options['agency_code'] ) : '';
		if ( '' === $agency_code && defined( 'LUTECIA_AGENCY_CODE' ) ) {
			$agency_code = trim( (string) LUTECIA_AGENCY_CODE );
		}
		$contact_email = isset( $options['contact_email'] ) ? sanitize_email( (string) $options['contact_email'] ) : '';
		$source        = isset( $options['source'] ) ? (string) $options['source'] : 'screen';

		$keys = $this->create_wc_api_key();
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		$response = wp_remote_post(
			LUTECIA_WC_HUB_URL . '/api/plugin/register',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						// Raw stored URL: home_url() is context-sensitive (its
						// scheme flips with is_ssl()), the option is canonical.
						'shop_url'           => get_option( 'home' ),
						'shop_name'          => get_bloginfo( 'name' ),
						'consumer_key'       => $keys['consumer_key'],
						'consumer_secret'    => $keys['consumer_secret'],
						'admin_email'        => '' !== $contact_email ? $contact_email : get_option( 'admin_email' ),
						'agency_code'        => $agency_code,
						'language'           => substr( get_locale(), 0, 2 ),
						'currency'           => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
						// Real legal page URLs from the store's own settings, so
						// the hub serves them at checkout instead of guessing
						// paths that may not exist. Empty string when unset.
						'privacy_policy_url' => get_privacy_policy_url(),
						'terms_url'          => $this->terms_page_url(),
						'plugin_version'     => LUTECIA_WC_VERSION,
						'wc_version'         => defined( 'WC_VERSION' ) ? WC_VERSION : '',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->revoke_wc_api_key();
			$reason = strtolower( $response->get_error_message() );
			if ( false !== strpos( $reason, 'timed out' ) || false !== strpos( $reason, 'curl error 28' ) ) {
				return new \WP_Error( 'lutecia_timeout', __( 'The Lutecia service did not answer in time. This is usually temporary: try again in a minute.', 'lutecia-for-woocommerce' ) );
			}
			return new \WP_Error( 'lutecia_network', __( 'Your store could not reach the Lutecia service. Some hosts block outgoing requests: ask your hosting support whether outbound HTTPS is allowed.', 'lutecia-for-woocommerce' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['client_id'] ) || empty( $body['webhook_secret'] ) ) {
			$this->revoke_wc_api_key();
			$detail = is_array( $body ) && ! empty( $body['detail'] ) ? (string) $body['detail'] : '';
			if ( '' === $detail ) {
				/* translators: %d: HTTP status code returned by the service. */
				$detail = sprintf( __( 'The Lutecia service answered with an unexpected error (HTTP %d). Try again in a minute.', 'lutecia-for-woocommerce' ), (int) $code );
			}
			return new \WP_Error( 'lutecia_register_failed', $detail );
		}

		update_option( Plugin::OPT_CLIENT_ID, sanitize_text_field( $body['client_id'] ), false );
		update_option( Plugin::OPT_WEBHOOK_SECRET, sanitize_text_field( $body['webhook_secret'] ), false );
		update_option( Plugin::OPT_CONNECTED_AT, time(), false );
		Site_Guard::remember();
		update_option(
			self::OPT_TERMS_ACCEPTED,
			array(
				'by'        => (string) wp_get_current_user()->user_login,
				'at'        => time(),
				'source'    => $source,
				'terms_url' => 'https://lutecia.app/legal',
			),
			false
		);
		$this->last_agency = isset( $body['agency'] ) && is_array( $body['agency'] ) ? $body['agency'] : null;

		// Fresh manifest right away: agents see the store as soon as it exists.
		Discovery::flush_cache();
		flush_rewrite_rules();

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function disconnect() {
		if ( ! $this->is_connected() ) {
			return true;
		}

		// A copy of a connected store never talks to the hub: local cleanup only.
		if ( Site_Guard::in_duplicate_mode() ) {
			$this->revoke_wc_api_key();
			Site_Guard::reset_for_reconnect();
			return true;
		}

		// Best effort: tell the hub. Local cleanup happens regardless, the
		// merchant must always be able to sever the link from their side.
		$secret = (string) get_option( Plugin::OPT_WEBHOOK_SECRET, '' );
		$body   = wp_json_encode(
			array(
				'client_id' => $this->client_id(),
				'site_url'  => get_option( 'home' ),
			)
		);
		wp_remote_post(
			LUTECIA_WC_HUB_URL . '/api/plugin/disconnect',
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'       => 'application/json',
					'X-Lutecia-Signature' => hash_hmac( 'sha256', $body, $secret ),
				),
				'body'    => $body,
			)
		);

		$this->revoke_wc_api_key();
		delete_option( Plugin::OPT_CLIENT_ID );
		delete_option( Plugin::OPT_WEBHOOK_SECRET );
		delete_option( Plugin::OPT_CONNECTED_AT );
		// Attribution is an opt-in per connection: a reconnect starts off again.
		delete_option( Plugin::OPT_ATTRIBUTION );
		delete_option( Site_Guard::OPT_SITE_URL );
		delete_option( Site_Guard::OPT_SITE_ENV );
		delete_option( self::OPT_TERMS_ACCEPTED );
		Discovery::flush_cache();

		return true;
	}

	/**
	 * Creates a read-only WooCommerce REST API key pair for the hub.
	 *
	 * Uses the same storage WooCommerce itself uses (woocommerce_api_keys
	 * table): the key shows up in WooCommerce > Settings > REST API where
	 * the merchant can audit or revoke it at any time.
	 *
	 * @return array{consumer_key: string, consumer_secret: string}|\WP_Error
	 */
	private function create_wc_api_key() {
		global $wpdb;

		$consumer_key    = 'ck_' . wc_rand_hash();
		$consumer_secret = 'cs_' . wc_rand_hash();

		// WooCommerce has no CRUD API for API keys; direct table access is the documented pattern.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			array(
				'user_id'         => get_current_user_id(),
				'description'     => self::KEY_DESCRIPTION,
				'permissions'     => 'read',
				'consumer_key'    => wc_api_hash( $consumer_key ),
				'consumer_secret' => $consumer_secret,
				'truncated_key'   => substr( $consumer_key, -7 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'lutecia_key_creation', __( 'Could not create a WooCommerce API key: your database refused the write. Try again; if it persists, contact your hosting support.', 'lutecia-for-woocommerce' ) );
		}

		update_option( Plugin::OPT_API_KEY_ID, (int) $wpdb->insert_id, false );

		return array(
			'consumer_key'    => $consumer_key,
			'consumer_secret' => $consumer_secret,
		);
	}

	/**
	 * Changes the plugin's API key permissions ('read' or 'read_write').
	 *
	 * Called when the merchant toggles "direct purchase by agent": creating
	 * orders needs write access; every other channel stays read-only.
	 */
	public function set_key_permissions( string $permissions ): bool {
		global $wpdb;

		if ( ! in_array( $permissions, array( 'read', 'read_write' ), true ) ) {
			return false;
		}
		$key_id = (int) get_option( Plugin::OPT_API_KEY_ID, 0 );
		if ( $key_id <= 0 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no CRUD API for WC API keys.
		$updated = $wpdb->update(
			$wpdb->prefix . 'woocommerce_api_keys',
			array( 'permissions' => $permissions ),
			array( 'key_id' => $key_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			return false;
		}
		// Zero affected rows means either "already set" or "key gone" (the
		// merchant revoked it from the REST API screen): read back so a
		// missing key is reported instead of Checkout being enabled on a
		// key that cannot create orders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no CRUD API for WC API keys.
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT permissions FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d", $key_id )
		);
		return $permissions === $current;
	}

	/**
	 * Signed call to the hub settings endpoint. Returns the full payload
	 * (flags, catalog readiness, channel access receipts, verification
	 * date) or a WP_Error.
	 *
	 * @param array|null $destinations_patch Optional whitelisted patch.
	 * @return array|\WP_Error
	 */
	public function hub_settings( ?array $destinations_patch = null ) {
		if ( Site_Guard::in_duplicate_mode() ) {
			return new \WP_Error( 'lutecia_duplicate_site', __( 'This site is a copy of a connected store. Nothing is sent from a copy.', 'lutecia-for-woocommerce' ) );
		}
		$secret  = (string) get_option( Plugin::OPT_WEBHOOK_SECRET, '' );
		$payload = array(
			'client_id' => $this->client_id(),
			'site_url'  => get_option( 'home' ),
		);
		if ( null !== $destinations_patch ) {
			$payload['destinations'] = $destinations_patch;
		}
		$body = wp_json_encode( $payload );

		$response = wp_remote_post(
			LUTECIA_WC_HUB_URL . '/api/plugin/settings',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'        => 'application/json',
					'X-Lutecia-Signature' => hash_hmac( 'sha256', $body, $secret ),
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'lutecia_network', __( 'Could not reach the Lutecia service.', 'lutecia-for-woocommerce' ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! isset( $data['flags'] ) ) {
			return new \WP_Error( 'lutecia_settings', __( 'Could not load channel settings.', 'lutecia-for-woocommerce' ) );
		}
		return $data;
	}

	/**
	 * Signed hand-over of the access a channel granted this merchant
	 * (feed credentials, Merchant Center id...). Returns the hub response
	 * ({ok, channel, received_at}) or a WP_Error.
	 *
	 * @param string $channel One of chatgpt|google|microsoft|perplexity.
	 * @param string $access  The access blob to forward, stored encrypted hub-side.
	 * @return array|\WP_Error
	 */
	public function hub_channel_access( string $channel, string $access ) {
		if ( Site_Guard::in_duplicate_mode() ) {
			return new \WP_Error( 'lutecia_duplicate_site', __( 'This site is a copy of a connected store. Nothing is sent from a copy.', 'lutecia-for-woocommerce' ) );
		}
		$secret = (string) get_option( Plugin::OPT_WEBHOOK_SECRET, '' );
		$body   = wp_json_encode(
			array(
				'client_id' => $this->client_id(),
				'site_url'  => get_option( 'home' ),
				'channel'   => $channel,
				'access'    => $access,
			)
		);

		$response = wp_remote_post(
			LUTECIA_WC_HUB_URL . '/api/plugin/channel-access',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'        => 'application/json',
					'X-Lutecia-Signature' => hash_hmac( 'sha256', $body, $secret ),
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'lutecia_network', __( 'Could not reach the Lutecia service.', 'lutecia-for-woocommerce' ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $data['ok'] ) ) {
			$message = is_array( $data ) && ! empty( $data['detail'] ) ? (string) $data['detail'] : __( 'Could not send the access.', 'lutecia-for-woocommerce' );
			return new \WP_Error( 'lutecia_channel_access', $message );
		}
		return $data;
	}

	public function revoke_wc_api_key(): void {
		global $wpdb;

		$key_id = (int) get_option( Plugin::OPT_API_KEY_ID, 0 );
		if ( $key_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no CRUD API for WC API keys.
			$wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => $key_id ), array( '%d' ) );
		}
		delete_option( Plugin::OPT_API_KEY_ID );
	}

	/**
	 * URL of the store's WooCommerce Terms and Conditions page, or '' if the
	 * store has not set one. Read from the store's own settings so the hub
	 * serves the real page rather than a guessed path.
	 */
	private function terms_page_url(): string {
		if ( ! function_exists( 'wc_terms_and_conditions_page_id' ) ) {
			return '';
		}
		$page_id = (int) wc_terms_and_conditions_page_id();
		if ( $page_id <= 0 ) {
			return '';
		}
		$url = get_permalink( $page_id );
		return is_string( $url ) ? $url : '';
	}
}
