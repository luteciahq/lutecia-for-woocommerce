<?php
/**
 * Sentences of the Stripe step and of Stripe on the Home screen, once, by
 * key. The views render them and the scripts receive them as
 * luteciaAdmin.stripe. The words were chosen with the user from
 * docs/textes-publics.md and are recorded in
 * merchant-plugins/shared/strings.json as WooCommerce only.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

final class Stripe_Strings {

	/**
	 * Every Stripe string of the screen, keyed for the views and the script.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			'connect'             => __( 'Connect with Stripe', 'lutecia-for-woocommerce' ),
			'connecting'          => __( 'Opening Stripe…', 'lutecia-for-woocommerce' ),
			'disconnect'          => __( 'Disconnect Stripe', 'lutecia-for-woocommerce' ),
			'change'              => __( 'Use another account', 'lutecia-for-woocommerce' ),
			'returned_connected'  => __( 'Stripe is connected.', 'lutecia-for-woocommerce' ),
			'block_account'       => __( 'Account', 'lutecia-for-woocommerce' ),
			'open_activation'     => __( 'Continue in Stripe', 'lutecia-for-woocommerce' ),
			'block_agentic'       => __( 'Agentic commerce', 'lutecia-for-woocommerce' ),
			'open_agentic'        => __( 'Open Agentic commerce', 'lutecia-for-woocommerce' ),
			'agentic_off'         => __( 'Stripe has not accepted the catalog yet.', 'lutecia-for-woocommerce' ),
			/* translators: %s: a button in Stripe's dashboard, kept in English. */
			'agentic_path'        => __( 'Agentic commerce › %s.', 'lutecia-for-woocommerce' ),
			'waiting'             => __( 'Waiting for Stripe…', 'lutecia-for-woocommerce' ),
			'send_again'          => __( 'Send again', 'lutecia-for-woocommerce' ),
			'retry_not_connected' => __( 'Stripe is not connected.', 'lutecia-for-woocommerce' ),
			'retry_pending'       => __( 'Stripe is still reading the catalog.', 'lutecia-for-woocommerce' ),
			'retry_cooldown'      => __( 'Stripe refused the catalog a moment ago. Try again in a minute.', 'lutecia-for-woocommerce' ),
			'catalog_accepted'    => __( 'Catalog accepted by Stripe.', 'lutecia-for-woocommerce' ),
			'step_assistants'     => __( 'Agents', 'lutecia-for-woocommerce' ),
			/* translators: 1: a menu symbol in Stripe's dashboard, 2: a menu item in Stripe's dashboard, kept in English. */
			'assistants_path'     => __( 'Agentic commerce › %1$s › %2$s', 'lutecia-for-woocommerce' ),
			'request_agents'      => __( 'Request the agents', 'lutecia-for-woocommerce' ),
			'set_policies'        => __( 'Set your policies', 'lutecia-for-woocommerce' ),
			'open_stripe'         => __( 'Open Stripe', 'lutecia-for-woocommerce' ),
			'policies'            => __( 'Terms, privacy, returns', 'lutecia-for-woocommerce' ),
			'banner'              => __( 'Finish setting up Stripe', 'lutecia-for-woocommerce' ),
			/* translators: %s: number of products. */
			'not_sent'            => __( '%s not sent', 'lutecia-for-woocommerce' ),
			'refused_intro'       => __( 'Not sent to Stripe. Fix the reason shown.', 'lutecia-for-woocommerce' ),
			'refusal_brand'       => __( 'No brand', 'lutecia-for-woocommerce' ),
			'refusal_image_link'  => __( 'No image', 'lutecia-for-woocommerce' ),
			'refusal_price'       => __( 'No price', 'lutecia-for-woocommerce' ),
			'refusal_shipping'    => __( 'No flat shipping rate', 'lutecia-for-woocommerce' ),
			'refusal_gtin'        => __( 'Barcode or SKU invalid', 'lutecia-for-woocommerce' ),
			'refusal_other'       => __( 'Refused by Stripe', 'lutecia-for-woocommerce' ),
			'manage_in_stripe'    => __( 'Manage in Stripe', 'lutecia-for-woocommerce' ),
			'error_denied'        => __( 'No account was connected. Try again.', 'lutecia-for-woocommerce' ),
			'error_expired'       => __( 'The link expired. Try again.', 'lutecia-for-woocommerce' ),
			'error_account_taken' => __( 'This Stripe account is connected to another store. Connect another account.', 'lutecia-for-woocommerce' ),
			'error_stripe'        => __( 'Stripe did not complete the connection. Try again.', 'lutecia-for-woocommerce' ),
			'error_unavailable'   => __( 'Stripe cannot be connected right now. Try again later.', 'lutecia-for-woocommerce' ),
			'confirm_disconnect'  => __( 'Disconnect Stripe? AI agents stop selling your products.', 'lutecia-for-woocommerce' ),
		);
	}

	/** One string by key, '' for an unknown key. */
	public static function get( string $key ): string {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : '';
	}

	/**
	 * A path through Stripe's dashboard, safe to print: the sentence escaped,
	 * each Stripe label in bold. Labels are Stripe's own words, in English.
	 * Print it with wp_kses( ..., array( 'b' => array() ) ).
	 *
	 * @param string   $key    Key of a sentence with %1$s, %2$s... placeholders.
	 * @param string[] $labels Stripe labels, in placeholder order.
	 */
	public static function path( string $key, array $labels ): string {
		$bold = array_map(
			static function ( string $label ): string {
				return '<b>' . esc_html( $label ) . '</b>';
			},
			$labels
		);
		$sentence = esc_html( self::get( $key ) );
		try {
			return vsprintf( $sentence, $bold );
		} catch ( \ValueError $e ) {
			// A translation with the wrong placeholders never breaks the screen.
			return $sentence;
		}
	}
}
