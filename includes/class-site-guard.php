<?php
/**
 * Detects a copy of a connected store (staging, clone, restore under another
 * URL) and puts the plugin in "duplicate site" mode: no outbound call to the
 * hub, local secret erased, discovery relay off, until the merchant either
 * leaves the copy alone or reconnects a store that really moved.
 *
 * The URL is stored with a marker inside it so the search-and-replace run by
 * cloning tools does not rewrite it (the technique WooCommerce Subscriptions
 * uses). A site that declares itself staging or development through
 * WP_ENVIRONMENT_TYPE is treated as a copy as well.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class Site_Guard {

	public const OPT_SITE_URL       = 'lutecia_site_url';
	public const OPT_DUPLICATE_SITE = 'lutecia_duplicate_site';
	public const OPT_SITE_ENV       = 'lutecia_site_env';

	private const MARKER = '_[lutecia_siteurl]_';

	/**
	 * Stores the current home URL, marked, and the environment type, right
	 * after a successful connect. Both are the baseline the guard compares
	 * against on every load.
	 */
	public static function remember(): void {
		update_option( self::OPT_SITE_URL, self::obfuscate( (string) get_option( 'home' ) ), false );
		update_option( self::OPT_SITE_ENV, self::environment(), false );
		delete_option( self::OPT_DUPLICATE_SITE );
	}

	/** The URL the connection belongs to ('' when nothing was stored). */
	public static function expected_url(): string {
		return self::deobfuscate( (string) get_option( self::OPT_SITE_URL, '' ) );
	}

	/** True while the plugin is paused as a duplicate site. */
	public static function in_duplicate_mode(): bool {
		return is_array( get_option( self::OPT_DUPLICATE_SITE ) );
	}

	/** Details of the pause for the admin screen and the CLI. */
	public static function duplicate_details(): array {
		$details = get_option( self::OPT_DUPLICATE_SITE );
		return is_array( $details ) ? $details : array();
	}

	/**
	 * Runs on every load. Returns true when the plugin must stay silent.
	 * Cheap: a few option reads, no HTTP.
	 */
	public static function check(): bool {
		if ( self::in_duplicate_mode() ) {
			return true;
		}
		if ( '' === (string) get_option( Plugin::OPT_CLIENT_ID, '' ) ) {
			return false;
		}
		$expected = self::expected_url();
		$current  = (string) get_option( 'home' );
		if ( '' === $expected ) {
			// Connected before this guard existed: adopt the current URL once.
			self::remember();
			return false;
		}
		$reason = '';
		if ( ! self::same_site( $current, $expected ) ) {
			$reason = 'url';
		} elseif ( self::is_copy_environment( self::environment() ) && ! self::is_copy_environment( self::connected_environment() ) ) {
			// Connected on a production site, now running as staging or
			// development: a restore or a clone. A store connected while
			// already flagged staging keeps working; otherwise every
			// reconnect would land straight back in duplicate mode.
			$reason = 'environment';
		}
		if ( '' === $reason ) {
			return false;
		}
		self::enter_duplicate_mode( $expected, $current, $reason );
		return true;
	}

	/** Cuts the connection locally: secret erased, relay cache flushed. */
	public static function enter_duplicate_mode( string $expected, string $seen, string $reason ): void {
		update_option(
			self::OPT_DUPLICATE_SITE,
			array(
				'expected'    => $expected,
				'seen'        => $seen,
				'reason'      => $reason,
				'detected_at' => time(),
			),
			false
		);
		delete_option( Plugin::OPT_WEBHOOK_SECRET );
		Discovery::flush_cache();
	}

	/**
	 * "This site has moved": forget the old connection locally so the
	 * merchant can click Connect again. Never talks to the hub (no secret).
	 */
	public static function reset_for_reconnect(): void {
		delete_option( Plugin::OPT_CLIENT_ID );
		delete_option( Plugin::OPT_WEBHOOK_SECRET );
		delete_option( Plugin::OPT_CONNECTED_AT );
		delete_option( Plugin::OPT_ATTRIBUTION );
		delete_option( self::OPT_SITE_URL );
		delete_option( self::OPT_SITE_ENV );
		delete_option( self::OPT_DUPLICATE_SITE );
		Discovery::flush_cache();
	}

	/** Scheme ignored, host lower-cased, trailing slash dropped. */
	public static function same_site( string $a, string $b ): bool {
		return '' !== self::key( $a ) && self::key( $a ) === self::key( $b );
	}

	public static function environment(): string {
		return function_exists( 'wp_get_environment_type' ) ? (string) wp_get_environment_type() : 'production';
	}

	/** Environment type recorded at connect ('production' for stores connected before it was recorded). */
	public static function connected_environment(): string {
		return (string) get_option( self::OPT_SITE_ENV, 'production' );
	}

	private static function is_copy_environment( string $environment ): bool {
		return in_array( $environment, array( 'staging', 'development' ), true );
	}

	private static function key( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		$path = isset( $parts['path'] ) ? rtrim( $parts['path'], '/' ) : '';
		return strtolower( $parts['host'] ) . $path;
	}

	private static function obfuscate( string $url ): string {
		return preg_replace( '#^(https?://)#i', '$1' . self::MARKER, $url, 1 );
	}

	private static function deobfuscate( string $stored ): string {
		return str_replace( self::MARKER, '', $stored );
	}
}
