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
			'lutecia-admin',
			LUTECIA_WC_URL . 'assets/admin.css',
			array(),
			LUTECIA_WC_VERSION
		);
		wp_enqueue_script(
			'lutecia-admin',
			LUTECIA_WC_URL . 'assets/admin.js',
			array(),
			LUTECIA_WC_VERSION,
			true
		);
		wp_localize_script(
			'lutecia-admin',
			'luteciaAdmin',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'lutecia/v1/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'connecting'            => __( 'Connecting…', 'lutecia-for-woocommerce' ),
					'activeNow'             => __( 'Address live', 'lutecia-for-woocommerce' ),
					'checking'              => __( 'Checking…', 'lutecia-for-woocommerce' ),
					/* translators: %s: number of products. */
					'productsSynced'        => __( '%s products synced', 'lutecia-for-woocommerce' ),
					'productSyncedOne'      => __( '1 product synced', 'lutecia-for-woocommerce' ),
					'connect'               => __( 'Connect my store', 'lutecia-for-woocommerce' ),
					'disconnected'          => __( 'Store disconnected.', 'lutecia-for-woocommerce' ),
					'genericError'          => __( 'Something went wrong. Please try again.', 'lutecia-for-woocommerce' ),
					'sessionExpired'        => __( 'Your session expired. Reload this page and try again.', 'lutecia-for-woocommerce' ),
					/* translators: %s: date the access was received. */
					'accessReceived'        => __( 'Access received on %s. We take it from here.', 'lutecia-for-woocommerce' ),
					'accessReceivedNoDate'  => __( 'Access received. We take it from here.', 'lutecia-for-woocommerce' ),
					'confirmRealtimeOff'    => __( 'Turn off Discovery? Your store stops answering AI assistants through Lutecia.', 'lutecia-for-woocommerce' ),
					'confirmCheckoutOn'     => __( 'Turn on Checkout? This lets Lutecia create orders in your store.', 'lutecia-for-woocommerce' ),
					'confirmDisconnect'     => __( 'Disconnect this store from AI shopping assistants?', 'lutecia-for-woocommerce' ),
					'confirmReset'          => __( 'Forget the previous connection on this site and connect it as a new store?', 'lutecia-for-woocommerce' ),
					/* translators: 1: number of products missing the field, 2: total number of products. */
					'dataMissing'           => __( '%1$s of %2$s products missing it', 'lutecia-for-woocommerce' ),
					/* translators: 1: number of products missing the field, 2: total number of products. */
					'dataMissingOne'        => __( '%1$s of %2$s product missing it', 'lutecia-for-woocommerce' ),
					/* translators: %s: total number of products. */
					'dataCovered'           => __( 'All %s products have it', 'lutecia-for-woocommerce' ),
					'dataCoveredOne'        => __( 'The product has it', 'lutecia-for-woocommerce' ),
				),
			)
		);
	}

	public function render_page(): void {
		$view = LUTECIA_WC_DIR . 'admin/views/dashboard.php';
		if ( is_readable( $view ) ) {
			$connection = $this->connection; // Available inside the view.
			include $view;
		}
	}
}
