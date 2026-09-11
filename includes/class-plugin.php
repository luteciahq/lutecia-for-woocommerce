<?php
/**
 * Main plugin singleton: wires every component together.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

/**
 * Central bootstrap. Each concern lives in its own class; this only wires them.
 */
final class Plugin {

	/** Option keys. uninstall.php repeats them literally: it runs without the autoloader. */
	public const OPT_CLIENT_ID      = 'lutecia_client_id';
	public const OPT_WEBHOOK_SECRET = 'lutecia_webhook_secret';
	public const OPT_API_KEY_ID     = 'lutecia_wc_api_key_id';
	public const OPT_CONNECTED_AT   = 'lutecia_connected_at';
	/** Sales attribution opt-in. Off by default: no cookie, no conversion call. */
	public const OPT_ATTRIBUTION    = 'lutecia_attribution_enabled';

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Discovery */
	public $discovery;

	/** @var Connection */
	public $connection;

	/** @var Webhooks */
	public $webhooks;

	/** @var Attribution */
	public $attribution;

	/** @var Rest */
	public $rest;

	/** @var Hub_Endpoint */
	public $hub_endpoint;

	/** @var Admin */
	public $admin;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->discovery    = new Discovery();
		$this->connection   = new Connection();
		$this->webhooks     = new Webhooks( $this->connection );
		$this->attribution  = new Attribution( $this->connection );
		$this->rest         = new Rest( $this->connection );
		$this->hub_endpoint = new Hub_Endpoint( $this->connection );
		$this->admin        = new Admin( $this->connection );
	}

	public function init(): void {
		$this->discovery->register();
		$this->rest->register();
		$this->hub_endpoint->register();
		$this->admin->register();

		// A copy of a connected store (staging, clone) stays silent: no
		// catalog pings, no attribution, nothing signed leaves this site.
		if ( Site_Guard::check() ) {
			return;
		}

		// Webhooks only fire once the store is connected. Sales attribution
		// is a separate opt-in on top of that, off by default: while it is
		// off the plugin sets no cookie and reports no order.
		if ( $this->connection->is_connected() ) {
			$this->webhooks->register();
			if ( Attribution::is_enabled() ) {
				$this->attribution->register();
			} else {
				$this->attribution->register_cleanup();
			}
		}
	}
}
