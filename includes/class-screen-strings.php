<?php
/**
 * Sentences of the Lutecia screen outside the Stripe step, once, by key.
 * The views render them and the scripts receive them as
 * luteciaAdmin.screen. The words come from the validated mockup
 * (docs/superpowers/specs/2026-09-28-plugin-screen-mockup.html) and are
 * recorded in merchant-plugins/shared/strings.json as WooCommerce only
 * while the PrestaShop module keeps its own screen.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

final class Screen_Strings {

	/**
	 * Every string of the screen, keyed for the views and the scripts.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			'what_is_shared'    => __( 'What is shared', 'lutecia-for-woocommerce' ),
			'step_store'        => __( 'Store', 'lutecia-for-woocommerce' ),
			'step_catalog'      => __( 'Catalog', 'lutecia-for-woocommerce' ),
			'address'           => __( 'Address', 'lutecia-for-woocommerce' ),
			'currency'          => __( 'Currency', 'lutecia-for-woocommerce' ),
			'language'          => __( 'Language', 'lutecia-for-woocommerce' ),
			/* translators: %s: number of products. */
			'products'          => __( '%s products', 'lutecia-for-woocommerce' ),
			'products_one'      => __( '1 product', 'lutecia-for-woocommerce' ),
			'with_brand'        => __( 'Products with a brand', 'lutecia-for-woocommerce' ),
			'with_gtin'         => __( 'Products with a GTIN', 'lutecia-for-woocommerce' ),
			'with_identifier'   => __( 'Products with a barcode or SKU', 'lutecia-for-woocommerce' ),
			/* translators: 1: number of products that have the field, 2: number of products. */
			'part_of_total'     => __( '%1$s of %2$s', 'lutecia-for-woocommerce' ),
			'continue'          => __( 'Continue', 'lutecia-for-woocommerce' ),
			'back'              => __( 'Back', 'lutecia-for-woocommerce' ),
			'done'              => __( 'Done', 'lutecia-for-woocommerce' ),
			'finish_later'      => __( 'Finish later', 'lutecia-for-woocommerce' ),
			'new_tab'           => __( '(opens in a new tab)', 'lutecia-for-woocommerce' ),
			'nav_home'          => __( 'Home', 'lutecia-for-woocommerce' ),
			'nav_settings'      => __( 'Settings', 'lutecia-for-woocommerce' ),
			'apply'             => __( 'Apply', 'lutecia-for-woocommerce' ),
		);
	}
}
