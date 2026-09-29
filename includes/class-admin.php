<?php
/**
 * Admin screen: one page under the WooCommerce menu.
 *
 * Design rule: merchant language only. No protocol jargon (no "UCP
 * capability", no "manifest") anywhere a merchant reads. Every claim on
 * the screen must be verifiable with one click (see it yourself links).
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

class Admin {

	private const MENU_SLUG = 'lutecia';

	/** Screen scripts, loaded in this order: each one reads what the ones before set up. */
	private const SCRIPTS = array( 'core', 'stripe', 'setup', 'welcome', 'home', 'boot' );

	/**
	 * Stripe dashboard pages the screen links to, from Stripe's docs. Checked
	 * by hand on a real account before every release (docs/canal-stripe.md).
	 * Assistants: the agentic commerce page, where the merchant finishes
	 * "Get started" and requests each agent. Activation: the account's
	 * onboarding.
	 */
	public const STRIPE_ASSISTANTS_URL = 'https://dashboard.stripe.com/agentic-commerce';
	public const STRIPE_ACTIVATION_URL = 'https://dashboard.stripe.com/account/onboarding';
	/** Stripe's agentic commerce settings, where the merchant sets the pages buyers see at checkout. */
	public const STRIPE_POLICIES_URL = 'https://dashboard.stripe.com/settings/agentic-commerce';

	/** @var Connection */
	private $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'in_admin_header', array( $this, 'clean_notices' ), 99 );
	}

	/**
	 * Removes every admin notice (core nags, other plugins) on our screen
	 * only. The merchant came here for one thing; the page stays focused.
	 */
	public function clean_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, self::MENU_SLUG ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			'Lutecia',
			'Lutecia',
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'lutecia-screen',
			LUTECIA_WC_URL . 'assets/css/lutecia-screen.css',
			array(),
			self::asset_version( 'assets/css/lutecia-screen.css' )
		);
		$previous = array();
		foreach ( self::SCRIPTS as $name ) {
			$handle = 'lutecia-' . $name;
			$file   = 'assets/js/' . $handle . '.js';
			wp_enqueue_script( $handle, LUTECIA_WC_URL . $file, $previous, self::asset_version( $file ), true );
			$previous = array( $handle );
		}
		wp_localize_script(
			'lutecia-core',
			'luteciaAdmin',
			array(
				'restUrl'       => esc_url_raw( rest_url( 'lutecia/v1/' ) ),
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'storeCurrency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
				'stripe'        => Stripe_Strings::all(),
				'screen'        => Screen_Strings::all(),
				'i18n'          => array(
					'connecting'           => __( 'Connecting…', 'lutecia-for-woocommerce' ),
					'connect'              => __( 'Connect my store', 'lutecia-for-woocommerce' ),
					'genericError'         => __( 'Something went wrong. Please try again.', 'lutecia-for-woocommerce' ),
					'sessionExpired'       => __( 'Your session expired. Reload this page and try again.', 'lutecia-for-woocommerce' ),
					'confirmRealtimeOff'   => __( 'Turn off Discovery? Your store stops answering AI agents through Lutecia.', 'lutecia-for-woocommerce' ),
					'confirmCheckoutOn'    => __( 'Turn on Checkout? This lets Lutecia create orders in your store.', 'lutecia-for-woocommerce' ),
					'confirmDisconnect'    => __( 'Disconnect this store from AI agents?', 'lutecia-for-woocommerce' ),
					'confirmReset'         => __( 'Forget the previous connection on this site and connect it as a new store?', 'lutecia-for-woocommerce' ),
					'cancel'               => __( 'Cancel', 'lutecia-for-woocommerce' ),
					'turnOn'               => __( 'Turn on', 'lutecia-for-woocommerce' ),
					'turnOff'              => __( 'Turn off', 'lutecia-for-woocommerce' ),
					'reconnect'            => __( 'Reconnect', 'lutecia-for-woocommerce' ),
				),
			)
		);
	}

	/** The file's modification time as its version: a changed file is never served from the browser's cache. */
	private static function asset_version( string $file ): string {
		$mtime = @filemtime( LUTECIA_WC_DIR . $file );
		return $mtime ? LUTECIA_WC_VERSION . '.' . $mtime : LUTECIA_WC_VERSION;
	}

	public function render_page(): void {
		$view = LUTECIA_WC_DIR . 'admin/views/dashboard.php';
		if ( is_readable( $view ) ) {
			$connection = $this->connection; // Available inside the view.
			include $view;
		}
	}
}
