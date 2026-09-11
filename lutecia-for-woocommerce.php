<?php
/**
 * Plugin Name:       Lutecia for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/lutecia-for-woocommerce/
 * Description:       Let AI agents shop your store. Lutecia connects your WooCommerce store to AI shopping assistants, so they can place the order in it.
 * Version:           0.1.5
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Lutecia
 * Author URI:        https://lutecia.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lutecia-for-woocommerce
 * WC requires at least: 9.6
 * WC tested up to:   11.0
 */

defined( 'ABSPATH' ) || exit;

define( 'LUTECIA_WC_VERSION', '0.1.5' );
define( 'LUTECIA_WC_FILE', __FILE__ );
define( 'LUTECIA_WC_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUTECIA_WC_URL', plugin_dir_url( __FILE__ ) );

// Hub base URL. Overridable via wp-config.php for staging/dev environments.
if ( ! defined( 'LUTECIA_WC_HUB_URL' ) ) {
	define( 'LUTECIA_WC_HUB_URL', 'https://api.lutecia.app' );
}

// Minimal class autoloader: Lutecia\WC\Foo_Bar -> includes/class-foo-bar.php.
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'Lutecia\\WC\\' ) ) {
			return;
		}
		$name = strtolower( str_replace( '_', '-', substr( $class, strlen( 'Lutecia\\WC\\' ) ) ) );
		$file = LUTECIA_WC_DIR . 'includes/class-' . $name . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Boots the plugin once all plugins are loaded (WooCommerce must be active).
 */
function lutecia_wc_boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Lutecia for WooCommerce requires WooCommerce to be installed and active.', 'lutecia-for-woocommerce' );
				echo '</p></div>';
			}
		);
		return;
	}
	\Lutecia\WC\Plugin::instance()->init();
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::add_command( 'lutecia', \Lutecia\WC\CLI::class );
	}
}
add_action( 'plugins_loaded', 'lutecia_wc_boot' );

register_activation_hook(
	__FILE__,
	static function () {
		\Lutecia\WC\Discovery::add_rewrite_rule();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
		\Lutecia\WC\Discovery::flush_cache();
		wp_clear_scheduled_hook( 'lutecia_notify_hub' );
		wp_unschedule_hook( 'lutecia_report_conversion' );
	}
);
