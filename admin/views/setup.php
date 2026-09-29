<?php
/**
 * Setup screen: the rail and the four steps (Store, Catalog, Stripe,
 * Assistants), one per screen. The Stripe step lives in stripe-step.php.
 * Store and Catalog are done on their own (connected, synced); the rail is
 * painted by assets/js/lutecia-setup.js. Rendered by dashboard.php.
 *
 * @var string $lutecia_open      Screen the page opens on.
 * @var array  $lutecia_stripe    Stripe_Strings::all().
 * @var array  $lutecia_screen_t  Screen_Strings::all().
 * @var string $lutecia_host      The store's web address, host only.
 * @var string $lutecia_count     "24 products", from the published products.
 * @var array  $lutecia_link_tags Tags allowed in sentences with a link.
 * @package Lutecia\WC
 */

defined( 'ABSPATH' ) || exit;

$lutecia_currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
$lutecia_language = class_exists( 'Locale' ) ? (string) \Locale::getDisplayLanguage( get_locale(), get_user_locale() ) : get_locale();
$lutecia_rail     = array(
	1 => $lutecia_screen_t['step_store'],
	2 => $lutecia_screen_t['step_catalog'],
	3 => 'Stripe',
	4 => $lutecia_stripe['step_assistants'],
);
$lutecia_bold     = array( 'b' => array() );
?>
<section class="lutecia-screen" data-lutecia-screen="setup" <?php if ( 'setup' !== $lutecia_open ) : ?>hidden<?php endif; ?>>
	<div class="lutecia-setup">
		<aside class="lutecia-rail">
			<div class="lutecia-brand">Lutecia</div>
			<ol class="lutecia-rail-steps">
				<?php foreach ( $lutecia_rail as $lutecia_n => $lutecia_label ) : ?>
					<li data-lutecia-rail-step="<?php echo esc_attr( (string) $lutecia_n ); ?>" <?php if ( $lutecia_n > 2 ) : ?>hidden<?php endif; ?>>
						<button type="button" data-lutecia-rail-go="<?php echo esc_attr( (string) $lutecia_n ); ?>">
							<span class="lutecia-rail-n" aria-hidden="true"><span><?php echo esc_html( (string) $lutecia_n ); ?></span></span>
							<?php echo esc_html( $lutecia_label ); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ol>
		</aside>

		<!-- Step 1: Store -->
		<div class="lutecia-stage" data-lutecia-step="1" hidden>
			<div class="lutecia-stage-body">
				<h2 tabindex="-1"><?php echo esc_html( $lutecia_screen_t['step_store'] ); ?></h2>
				<ul class="lutecia-list">
					<li><span class="lutecia-name"><?php echo esc_html( $lutecia_screen_t['address'] ); ?></span><span class="lutecia-kvline"><?php echo esc_html( $lutecia_host ); ?></span></li>
					<li><span class="lutecia-name"><?php echo esc_html( $lutecia_screen_t['currency'] ); ?></span><span class="lutecia-kvline"><?php echo esc_html( $lutecia_currency ); ?></span></li>
					<li><span class="lutecia-name"><?php echo esc_html( $lutecia_screen_t['language'] ); ?></span><span class="lutecia-kvline"><?php echo esc_html( $lutecia_language ); ?></span></li>
				</ul>
			</div>
			<div class="lutecia-actions">
				<span></span>
				<div class="lutecia-actions-right">
					<button type="button" class="lutecia-btn" data-lutecia-goto="2"><?php echo esc_html( $lutecia_screen_t['continue'] ); ?></button>
				</div>
			</div>
		</div>

		<!-- Step 2: Catalog -->
		<div class="lutecia-stage" data-lutecia-step="2" hidden>
			<div class="lutecia-stage-body">
				<h2 tabindex="-1"><?php echo esc_html( $lutecia_screen_t['step_catalog'] ); ?></h2>
				<p class="lutecia-lede" data-lutecia-product-total><?php echo esc_html( $lutecia_count ); ?></p>
				<div class="lutecia-sync" data-lutecia-syncing>
					<span class="lutecia-spinner" aria-hidden="true"></span>
					<p><?php esc_html_e( 'Reading your catalog and making it available to AI agents…', 'lutecia-for-woocommerce' ); ?></p>
				</div>
				<p class="lutecia-warn" data-lutecia-sync-hint hidden>
					<?php esc_html_e( 'Taking longer than a minute? Open Settings > Permalinks: if the structure is set to "Plain", choose any other option and save. The address AI agents use to read your store needs it.', 'lutecia-for-woocommerce' ); ?>
				</p>
				<ul class="lutecia-list" data-lutecia-readiness hidden>
					<li>
						<span class="lutecia-name"><?php echo esc_html( $lutecia_screen_t['with_brand'] ); ?></span>
						<span class="lutecia-chip" data-lutecia-ready="brand"></span>
						<p class="lutecia-row-extra lutecia-muted lutecia-small" data-lutecia-ready-help="brand" hidden>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: link to the product list. */
									__( 'The brand or manufacturer name of the product. Required by Google and Microsoft, and used by ChatGPT. Where to fill it: %s and set its brand in the Brands box, or use a product attribute named "Brand".', 'lutecia-for-woocommerce' ),
									'<a href="' . esc_url( admin_url( 'edit.php?post_type=product' ) ) . '">' . esc_html__( 'open a product', 'lutecia-for-woocommerce' ) . '</a>'
								),
								$lutecia_link_tags
							);
							?>
						</p>
					</li>
					<li>
						<span class="lutecia-name"><?php echo esc_html( $lutecia_screen_t['with_gtin'] ); ?></span>
						<span class="lutecia-chip" data-lutecia-ready="gtin"></span>
						<p class="lutecia-row-extra lutecia-muted lutecia-small" data-lutecia-ready-help="gtin" hidden>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: link to the product list. */
									__( 'The barcode number printed on the product: a UPC, EAN or ISBN. Required by Perplexity, Google and Microsoft. Where to fill it: %s, go to the Inventory tab, and fill the field named "GTIN, UPC, EAN, or ISBN".', 'lutecia-for-woocommerce' ),
									'<a href="' . esc_url( admin_url( 'edit.php?post_type=product' ) ) . '">' . esc_html__( 'open a product', 'lutecia-for-woocommerce' ) . '</a>'
								),
								$lutecia_link_tags
							);
							?>
						</p>
					</li>
					<li data-lutecia-ready-row="identifier" hidden>
						<span class="lutecia-name"><?php echo esc_html( $lutecia_screen_t['with_identifier'] ); ?></span>
						<span class="lutecia-chip" data-lutecia-ready="identifier"></span>
					</li>
				</ul>
			</div>
			<div class="lutecia-actions">
				<div class="lutecia-actions-left">
					<button type="button" class="lutecia-link" data-lutecia-goto="1"><?php echo esc_html( $lutecia_screen_t['back'] ); ?></button>
					<button type="button" class="lutecia-link" data-lutecia-finish-later><?php echo esc_html( $lutecia_screen_t['finish_later'] ); ?></button>
				</div>
				<div class="lutecia-actions-right">
					<button type="button" class="lutecia-btn" data-lutecia-catalog-next disabled><?php echo esc_html( $lutecia_screen_t['continue'] ); ?></button>
				</div>
			</div>
		</div>

		<?php include __DIR__ . '/stripe-step.php'; ?>

		<!-- Step 4: Assistants -->
		<div class="lutecia-stage" data-lutecia-step="4" hidden>
			<div class="lutecia-stage-body">
				<h2 tabindex="-1"><?php echo esc_html( $lutecia_stripe['step_assistants'] ); ?></h2>
				<ul class="lutecia-list lutecia-todo-steps">
					<li>
						<div class="lutecia-row-text">
							<span class="lutecia-name"><?php echo esc_html( $lutecia_stripe['request_agents'] ); ?></span>
							<span class="lutecia-path"><?php echo wp_kses( \Lutecia\WC\Stripe_Strings::path( 'assistants_path', array( '⋯', 'Request connection' ) ), $lutecia_bold ); ?></span>
						</div>
						<a class="lutecia-btn lutecia-btn-small lutecia-btn-stripe" href="<?php echo esc_url( \Lutecia\WC\Admin::STRIPE_ASSISTANTS_URL ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $lutecia_stripe['open_stripe'] ); ?> <span aria-hidden="true">&#8599;</span><span class="lutecia-sr"><?php echo esc_html( $lutecia_screen_t['new_tab'] ); ?></span>
						</a>
					</li>
					<li>
						<div class="lutecia-row-text">
							<span class="lutecia-name"><?php echo esc_html( $lutecia_stripe['set_policies'] ); ?></span>
							<span class="lutecia-path"><?php echo esc_html( $lutecia_stripe['policies'] ); ?></span>
						</div>
						<a class="lutecia-btn lutecia-btn-small lutecia-btn-stripe" href="<?php echo esc_url( \Lutecia\WC\Admin::STRIPE_POLICIES_URL ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $lutecia_stripe['open_stripe'] ); ?> <span aria-hidden="true">&#8599;</span><span class="lutecia-sr"><?php echo esc_html( $lutecia_screen_t['new_tab'] ); ?></span>
						</a>
					</li>
				</ul>
			</div>
			<div class="lutecia-actions">
				<div class="lutecia-actions-left">
					<button type="button" class="lutecia-link" data-lutecia-goto="3"><?php echo esc_html( $lutecia_screen_t['back'] ); ?></button>
					<button type="button" class="lutecia-link" data-lutecia-finish-later><?php echo esc_html( $lutecia_screen_t['finish_later'] ); ?></button>
				</div>
				<div class="lutecia-actions-right">
					<button type="button" class="lutecia-btn" data-lutecia-assistants-done><?php echo esc_html( $lutecia_screen_t['done'] ); ?></button>
				</div>
			</div>
		</div>
	</div>
</section>
