<?php
/**
 * WP-CLI commands: wp lutecia connect | status | disconnect | doctor.
 *
 * Same code paths as the admin screen (Connection, Site_Guard, Discovery),
 * no prompts, machine-readable output on request, non-zero exit on failure.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class CLI {

	private const TERMS_URL = 'https://lutecia.app/legal';

	/**
	 * Connects this store to Lutecia.
	 *
	 * ## OPTIONS
	 *
	 * [--accept-terms]
	 * : Accept the Lutecia terms and privacy policy (https://lutecia.app/legal) on behalf of this store. Required.
	 *
	 * [--agency=<code>]
	 * : Agency code (AG-XXXXXXXX). Defaults to the LUTECIA_AGENCY_CODE constant when defined.
	 *
	 * [--contact-email=<email>]
	 * : Merchant contact email sent to Lutecia. Defaults to the site admin email.
	 *
	 * [--porcelain]
	 * : Print only the client id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lutecia connect --accept-terms --user=admin
	 *     wp lutecia connect --accept-terms --agency=AG-XXXXXXXX --user=admin --porcelain
	 *
	 * @param array $args  Positional arguments (none).
	 * @param array $assoc Associative arguments.
	 */
	public function connect( array $args, array $assoc ): void {
		$connection = Plugin::instance()->connection;
		if ( Site_Guard::in_duplicate_mode() ) {
			\WP_CLI::error( 'This site is a copy of a connected store. Run "wp lutecia disconnect" to start over, or leave the copy as it is.' );
		}
		if ( $connection->is_connected() ) {
			$this->done( $connection->client_id(), isset( $assoc['porcelain'] ), 'Already connected.' );
			return;
		}
		if ( empty( $assoc['accept-terms'] ) ) {
			\WP_CLI::error( 'Add --accept-terms to accept ' . self::TERMS_URL . ' on behalf of this store.' );
		}
		if ( 0 === get_current_user_id() ) {
			\WP_CLI::error( 'Run with --user=<an administrator>: the WooCommerce API key is created in that user\'s name.' );
		}
		$result = $connection->connect(
			array(
				'agency_code'   => isset( $assoc['agency'] ) ? (string) $assoc['agency'] : '',
				'contact_email' => isset( $assoc['contact-email'] ) ? (string) $assoc['contact-email'] : '',
				'source'        => 'cli',
			)
		);
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		$agency = $connection->last_agency;
		if ( $agency && 'conflict' === ( $agency['status'] ?? '' ) ) {
			\WP_CLI::warning( isset( $agency['message'] ) ? $agency['message'] : 'The store is managed by another agency; not attached.' );
			$this->done( $connection->client_id(), isset( $assoc['porcelain'] ), 'Connected, not attached to the agency.' );
			return;
		}
		$note = $agency ? sprintf( 'Connected. Agency: %s (%s).', $agency['name'] ?? '', $agency['status'] ?? '' ) : 'Connected.';
		$this->done( $connection->client_id(), isset( $assoc['porcelain'] ), $note );
	}

	/**
	 * Shows the connection state of this store.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json or csv. Default table.
	 *
	 * @param array $args  Positional arguments (none).
	 * @param array $assoc Associative arguments.
	 */
	public function status( array $args, array $assoc ): void {
		$connection   = Plugin::instance()->connection;
		$terms        = get_option( Connection::OPT_TERMS_ACCEPTED );
		$dup          = Site_Guard::duplicate_details();
		$connected_at = (int) get_option( Plugin::OPT_CONNECTED_AT, 0 );
		$rows         = array(
			array( 'field' => 'connected', 'value' => $connection->is_connected() && ! Site_Guard::in_duplicate_mode() ? 'yes' : 'no' ),
			array( 'field' => 'client_id', 'value' => $connection->client_id() ),
			array( 'field' => 'site_url', 'value' => (string) get_option( 'home' ) ),
			array( 'field' => 'expected_url', 'value' => Site_Guard::expected_url() ),
			array( 'field' => 'duplicate_site', 'value' => $dup ? sprintf( 'yes (%s, seen %s)', $dup['reason'], $dup['seen'] ) : 'no' ),
			array( 'field' => 'environment', 'value' => Site_Guard::environment() ),
			array( 'field' => 'connected_at', 'value' => $connected_at ? gmdate( 'c', $connected_at ) : '' ),
			array( 'field' => 'terms_accepted_by', 'value' => is_array( $terms ) ? sprintf( '%s (%s, %s)', $terms['by'], $terms['source'], gmdate( 'c', (int) $terms['at'] ) ) : '' ),
			array( 'field' => 'discovery_url', 'value' => home_url( '/.well-known/ucp' ) ),
		);
		\WP_CLI\Utils\format_items( isset( $assoc['format'] ) ? $assoc['format'] : 'table', $rows, array( 'field', 'value' ) );
	}

	/**
	 * Disconnects this store from Lutecia (revokes the WooCommerce key, tells the hub).
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 *
	 * @param array $args  Positional arguments (none).
	 * @param array $assoc Associative arguments.
	 */
	public function disconnect( array $args, array $assoc ): void {
		$connection = Plugin::instance()->connection;
		if ( ! $connection->is_connected() && ! Site_Guard::in_duplicate_mode() ) {
			\WP_CLI::success( 'Not connected.' );
			return;
		}
		\WP_CLI::confirm( 'Disconnect this store from AI shopping assistants?', $assoc );
		$connection->disconnect();
		\WP_CLI::success( 'Disconnected.' );
	}

	/**
	 * Checks what a working connection needs, without changing anything.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json or csv. Default table.
	 *
	 * @param array $args  Positional arguments (none).
	 * @param array $assoc Associative arguments.
	 */
	public function doctor( array $args, array $assoc ): void {
		$checks = array();
		$failed = false;
		$add    = static function ( string $check, bool $ok, string $detail, bool $blocking = true ) use ( &$checks, &$failed ) {
			$checks[] = array( 'check' => $check, 'status' => $ok ? 'ok' : ( $blocking ? 'fail' : 'warn' ), 'detail' => $detail );
			if ( ! $ok && $blocking ) {
				$failed = true;
			}
		};
		$home = (string) get_option( 'home' );
		$host = (string) wp_parse_url( $home, PHP_URL_HOST );

		$has_wc = class_exists( 'WooCommerce' );
		$add( 'woocommerce', $has_wc, $has_wc ? 'active' : 'WooCommerce is not active' );
		$local = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) || null !== wp_parse_url( $home, PHP_URL_PORT );
		$add( 'https', 0 === strpos( $home, 'https://' ) || $local, $home, ! $local );
		$pretty = '' !== (string) get_option( 'permalink_structure' );
		$add( 'permalinks', $pretty, $pretty ? 'pretty' : 'Plain permalinks: /.well-known/ucp cannot be served. Choose any other structure.' );
		$env       = Site_Guard::environment();
		$env_ok    = ! in_array( $env, array( 'staging', 'development' ), true )
			|| in_array( Site_Guard::connected_environment(), array( 'staging', 'development' ), true );
		$add( 'environment', $env_ok, $env_ok ? $env : $env . ' (a store connected on a production site is treated as a copy here)', false );
		$add( 'duplicate_site', ! Site_Guard::in_duplicate_mode(), Site_Guard::in_duplicate_mode() ? 'this site is a copy of a connected store' : 'no' );

		$health = wp_remote_get( LUTECIA_WC_HUB_URL . '/health', array( 'timeout' => 8 ) );
		$hub_ok = ! is_wp_error( $health ) && 200 === wp_remote_retrieve_response_code( $health );
		$detail = $hub_ok ? LUTECIA_WC_HUB_URL : ( is_wp_error( $health ) ? $health->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $health ) ) . ' (outbound HTTPS blocked by the host?)';
		$add( 'hub_reachable', $hub_ok, $detail );

		$connection = Plugin::instance()->connection;
		$connected  = $connection->is_connected() && ! Site_Guard::in_duplicate_mode();
		$add( 'connected', $connected, $connected ? $connection->client_id() : 'not connected', false );

		if ( $connected ) {
			$probe  = wp_remote_get( home_url( '/.well-known/ucp' ), array( 'timeout' => 8 ) );
			$served = ! is_wp_error( $probe ) && 200 === wp_remote_retrieve_response_code( $probe )
				&& false !== strpos( (string) wp_remote_retrieve_header( $probe, 'content-type' ), 'json' );
			if ( ! $served && $hub_ok ) {
				$handshake = wp_remote_get( LUTECIA_WC_HUB_URL . '/v1/ucp/handshake?host=' . rawurlencode( $host ), array( 'timeout' => 8 ) );
				$served    = ! is_wp_error( $handshake ) && 200 === wp_remote_retrieve_response_code( $handshake );
			}
			$add( 'discovery', $served, $served ? home_url( '/.well-known/ucp' ) : 'the manifest is not served on this domain (rewrite ignored by the host, or a cache in front)' );
		}

		\WP_CLI\Utils\format_items( isset( $assoc['format'] ) ? $assoc['format'] : 'table', $checks, array( 'check', 'status', 'detail' ) );
		if ( $failed ) {
			\WP_CLI::error( 'Some checks failed.' );
		}
		\WP_CLI::success( 'All checks passed.' );
	}

	private function done( string $client_id, bool $porcelain, string $message ): void {
		if ( $porcelain ) {
			\WP_CLI::line( $client_id );
			return;
		}
		\WP_CLI::success( $message . ' client_id: ' . $client_id );
	}
}
