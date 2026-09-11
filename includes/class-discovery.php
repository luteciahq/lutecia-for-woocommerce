<?php
/**
 * UCP discovery: serves /.well-known/ucp on the merchant's domain.
 *
 * The manifest is NOT built here. It is fetched from the Lutecia hub
 * (single source of truth, always current with the UCP spec) and cached
 * in a transient. The plugin stays a thin relay: protocol changes never
 * require a plugin update.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class Discovery {

	private const CACHE_PREFIX      = 'lutecia_ucp_manifest';
	private const CACHE_SALT_OPTION = 'lutecia_ucp_cache_salt';
	private const CACHE_TTL         = HOUR_IN_SECONDS;
	private const QUERY_VAR         = 'lutecia_ucp';
	private const VERSION_VAR       = 'lutecia_ucp_version';

	public function register(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_manifest' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'skip_canonical_redirect' ) );
	}

	/**
	 * WordPress canonical redirect would 301 /.well-known/ucp to a
	 * trailing-slash URL before we can answer. Agents expect a 200 on the
	 * exact well-known path, so canonical handling is disabled for it.
	 *
	 * @param string|false $redirect_url URL WordPress wants to redirect to.
	 * @return string|false
	 */
	public static function skip_canonical_redirect( $redirect_url ) {
		if ( get_query_var( self::QUERY_VAR ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Also matches versioned discovery paths (/.well-known/ucp/2026-08-25),
	 * which agents use to request a specific protocol version.
	 */
	public static function add_rewrite_rule(): void {
		add_rewrite_rule(
			'^\.well-known/ucp(?:/([0-9]{4}-[0-9]{2}-[0-9]{2}))?/?$',
			'index.php?' . self::QUERY_VAR . '=1&' . self::VERSION_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([0-9]+)' );
		add_rewrite_tag( '%' . self::VERSION_VAR . '%', '([0-9-]+)' );
	}

	public function maybe_serve_manifest(): void {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		if ( Site_Guard::in_duplicate_mode() ) {
			return; // a copy must not answer agents for the real store
		}

		// No opt-in, no phoning home: until the merchant connects the store
		// (which is the explicit opt-in), the discovery endpoint does not
		// exist and never contacts the Lutecia service. Let WordPress 404.
		if ( '' === (string) get_option( Plugin::OPT_CLIENT_ID, '' ) ) {
			return;
		}

		// The rewrite rule only matches dated versions, but the query var is
		// public: re-validate so an arbitrary value can neither reach the hub
		// nor create its own cache entry.
		$version = (string) get_query_var( self::VERSION_VAR );
		if ( ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $version ) ) {
			$version = '';
		}
		$cache_key = self::cache_key( $version );
		$manifest  = get_transient( $cache_key );

		if ( false === $manifest ) {
			$manifest = $this->fetch_manifest( $version );
			if ( null === $manifest ) {
				status_header( 503 );
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Retry-After: 300' );
				echo wp_json_encode( array( 'error' => 'UCP manifest temporarily unavailable' ) );
				exit;
			}
			set_transient( $cache_key, $manifest, self::CACHE_TTL );
		}

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'Access-Control-Allow-Origin: *' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw JSON relayed from the hub.
		echo $manifest;
		exit;
	}

	/**
	 * Fetches the manifest from the hub for this shop's domain.
	 *
	 * @param string $version Optional dated spec version requested by the agent.
	 * @return string|null Raw JSON body, or null on failure.
	 */
	private function fetch_manifest( string $version = '' ): ?string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url  = LUTECIA_WC_HUB_URL . '/v1/ucp/manifest/by-domain?host=' . rawurlencode( $host );
		if ( $version ) {
			$url .= '&version=' . rawurlencode( $version );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'LuteciaForWooCommerce/' . LUTECIA_WC_VERSION . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		// Only relay (and cache) a JSON document: an HTML error page from a
		// proxy must not be served for an hour as the manifest.
		if ( null === json_decode( $body ) ) {
			return null;
		}
		return $body;
	}

	/**
	 * Transient key for a manifest, namespaced by a cache salt. Bumping the
	 * salt (see flush_cache) invalidates every cached manifest at once,
	 * versioned entries included: transient keys are dynamic, so a single
	 * delete_transient could only ever clear one of them.
	 *
	 * @param string $version Optional dated spec version.
	 * @return string
	 */
	private static function cache_key( string $version = '' ): string {
		$salt = (string) get_option( self::CACHE_SALT_OPTION, '1' );
		return self::CACHE_PREFIX . '_' . $salt . ( $version ? '_' . $version : '' );
	}

	/**
	 * Invalidates all cached manifests (base and every versioned entry) by
	 * bumping the cache salt. Called whenever the served manifest may have
	 * changed: channel flags updated, connect, disconnect. Orphaned
	 * transients expire on their own TTL.
	 */
	public static function flush_cache(): void {
		$salt = (int) get_option( self::CACHE_SALT_OPTION, '1' );
		update_option( self::CACHE_SALT_OPTION, (string) ( $salt + 1 ), false );
	}
}
