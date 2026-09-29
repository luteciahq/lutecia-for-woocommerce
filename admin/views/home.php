<?php
/**
 * Home screen: the to-do row while the Stripe setup is unfinished, then
 * flat lists: Stripe, Channels,
 * Controls (three switches) and Settings, which the side nav
 * scrolls to. State is a coloured dot, never a word. No sentence is shown on hover only: the Controls
 * descriptions are the only title attributes, and each is also the
 * switch's screen-reader description.
 * assets/js/lutecia-home.js fills the states from the store and the hub.
 * Rendered by dashboard.php.
 *
 * Program details (links, requirements, regions) are re-verified on each
 * program's public page before every release (docs/canaux.md). No
 * assistant is ever presented as already sending traffic.
 *
 * @var string $lutecia_open      Screen the page opens on.
 * @var array  $lutecia_stripe    Stripe_Strings::all().
 * @var array  $lutecia_screen_t  Screen_Strings::all().
 * @var string $lutecia_shop_name The store's name.
 * @var string $lutecia_host      The store's web address, host only.
 * @var array  $lutecia_link_tags Tags allowed in sentences with a link.
 * @package Lutecia\WC
 */

defined( 'ABSPATH' ) || exit;

$lutecia_ucp_url     = home_url( '/.well-known/ucp' );
$lutecia_privacy_url = get_privacy_policy_url();
$lutecia_terms_id    = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
$lutecia_programs    = array(
	array(
		'key'    => 'chatgpt',
		'name'   => 'ChatGPT',
		'apply'  => array( __( 'Apply on the OpenAI merchant portal', 'lutecia-for-woocommerce' ), 'https://chatgpt.com/merchants' ),
		'desc'   => __( "ChatGPT recommends products from catalogs that merchants submit to OpenAI. We keep your catalog ready in OpenAI's required format, so it can be submitted as soon as your application is accepted.", 'lutecia-for-woocommerce' ),
		'links'  => array(),
		'accept' => __( 'OpenAI gives you feed delivery credentials; we plug your feed into them.', 'lutecia-for-woocommerce' ),
	),
	array(
		'key'    => 'google',
		'name'   => 'Google',
		'apply'  => array( __( 'Express interest with Google', 'lutecia-for-woocommerce' ), 'https://support.google.com/merchants/contact/ucp_integration_interest' ),
		'desc'   => __( "Google's AI Mode and Gemini use UCP, the same standard as your store's address above. Rollout covers the US, Canada and Australia, with the UK announced. A Google Merchant Center account is required.", 'lutecia-for-woocommerce' ),
		'links'  => array(
			array( __( 'Onboarding guide', 'lutecia-for-woocommerce' ), 'https://support.google.com/merchants/answer/16992327' ),
		),
		'accept' => __( 'Paste your Merchant Center ID and what Google sent you. Lutecia then emails you the addresses to enter in Merchant Center.', 'lutecia-for-woocommerce' ),
	),
	array(
		'key'    => 'microsoft',
		'name'   => 'Microsoft',
		'apply'  => array( __( 'Apply to the Copilot program', 'lutecia-for-woocommerce' ), 'https://forms.microsoft.com/r/jvcqWsMGBu' ),
		'desc'   => __( 'Copilot reads stores through UCP. You need a Microsoft Merchant Center account. Purchases inside Copilot are limited to merchants selling to the US; products can be shown more widely through Merchant Center.', 'lutecia-for-woocommerce' ),
		'links'  => array(),
		'accept' => __( 'Paste your Merchant Center ID and what Microsoft sent you. Lutecia then emails you the address to enter in Merchant Center.', 'lutecia-for-woocommerce' ),
	),
	array(
		'key'    => 'perplexity',
		'name'   => 'Perplexity',
		'apply'  => array( __( 'Apply with the merchant form', 'lutecia-for-woocommerce' ), 'https://perplexity.typeform.com/to/oIcfT8U3' ),
		'desc'   => __( 'Perplexity has a merchant program open to merchants who sell and ship to the US: apply with their online form, answers take 3 to 7 business days. Products need GTIN codes, and Perplexity requires real-time price and stock, which we keep updated.', 'lutecia-for-woocommerce' ),
		'links'  => array(),
		'accept' => __( 'Paste here what Perplexity sent you. Lutecia then sets up the delivery of your catalog and confirms it by email.', 'lutecia-for-woocommerce' ),
	),
);
// The three switches of Controls, in this order, with their existing names
// and descriptions. The description is the name's title and, for screen
// readers, the switch's description: the only title attributes of the screen.
$lutecia_controls = array(
	array(
		'channel' => 'realtime',
		'name'    => __( 'Discovery', 'lutecia-for-woocommerce' ),
		'desc'    => __( 'On by default. Your products, searchable by AI agents. Turning this off stops your store from answering AI agents through Lutecia.', 'lutecia-for-woocommerce' ),
	),
	array(
		'channel' => 'checkout',
		'name'    => __( 'Checkout', 'lutecia-for-woocommerce' ),
		'desc'    => __( 'Off by default. On: an agent can create a standard WooCommerce order with the customer\'s details. It appears in your Orders list awaiting payment, and you handle it as usual. This lets Lutecia create orders in your store.', 'lutecia-for-woocommerce' ),
	),
	array(
		'channel' => 'bulk_feed',
		'name'    => __( 'Catalog file', 'lutecia-for-woocommerce' ),
		'desc'    => __( 'On by default. A daily file of your full catalog in Google Shopping format, for the programs that fetch a file rather than query live.', 'lutecia-for-woocommerce' ),
	),
);
?>
<section class="lutecia-screen" data-lutecia-screen="home" <?php if ( 'home' !== $lutecia_open ) : ?>hidden<?php endif; ?>>
	<div class="lutecia-home">
		<aside class="lutecia-side">
			<div class="lutecia-brand">Lutecia</div>
			<nav class="lutecia-nav" aria-label="Lutecia">
				<a href="#lutecia-top" class="is-on" aria-current="true" data-lutecia-nav="lutecia-top"><?php echo esc_html( $lutecia_screen_t['nav_home'] ); ?></a>
				<a href="#lutecia-channels" data-lutecia-nav="lutecia-channels"><?php esc_html_e( 'Channels', 'lutecia-for-woocommerce' ); ?></a>
				<a href="#lutecia-controls" data-lutecia-nav="lutecia-controls"><?php esc_html_e( 'Controls', 'lutecia-for-woocommerce' ); ?></a>
				<a href="#lutecia-settings" data-lutecia-nav="lutecia-settings"><?php echo esc_html( $lutecia_screen_t['nav_settings'] ); ?></a>
			</nav>
		</aside>

		<div class="lutecia-main">
			<div class="lutecia-topline" id="lutecia-top" tabindex="-1">
				<h2><?php echo esc_html( $lutecia_shop_name ); ?></h2>
			</div>

			<ul class="lutecia-list lutecia-todo" data-lutecia-banner hidden>
				<li>
					<span class="lutecia-dot is-warn" aria-hidden="true"></span>
					<span class="lutecia-name"><?php echo esc_html( $lutecia_stripe['banner'] ); ?></span>
					<span class="lutecia-kvline"></span>
					<button type="button" class="lutecia-btn lutecia-btn-small" data-lutecia-banner-go><?php echo esc_html( $lutecia_screen_t['continue'] ); ?></button>
				</li>
			</ul>

			<p class="lutecia-return" data-lutecia-stripe-return role="status" hidden></p>

			<section class="lutecia-section" data-lutecia-stripe-section hidden aria-labelledby="lutecia-h-stripe">
				<h3 id="lutecia-h-stripe">Stripe</h3>
				<ul class="lutecia-list">
					<li>
						<span class="lutecia-name"><?php echo esc_html( $lutecia_shop_name ); ?></span>
						<span class="lutecia-kvline">
							<button type="button" class="lutecia-chip is-warn" data-lutecia-not-sent aria-expanded="false" aria-controls="lutecia-not-sent" hidden></button>
						</span>
						<span class="lutecia-dot" data-lutecia-stripe-dot aria-hidden="true"></span>
					</li>
					<li data-lutecia-stripe-agents>
						<span class="lutecia-name">Copilot, Meta, Google, Wizard</span>
						<span class="lutecia-kvline"></span>
						<a class="lutecia-stripe-link" href="<?php echo esc_url( \Lutecia\WC\Admin::STRIPE_ASSISTANTS_URL ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $lutecia_stripe['manage_in_stripe'] ); ?> <span aria-hidden="true">&#8599;</span><span class="lutecia-sr"><?php echo esc_html( $lutecia_screen_t['new_tab'] ); ?></span>
						</a>
					</li>
				</ul>
				<div class="lutecia-refused" id="lutecia-not-sent" data-lutecia-refused hidden>
					<p class="lutecia-muted lutecia-small"><?php echo esc_html( $lutecia_stripe['refused_intro'] ); ?></p>
					<ul class="lutecia-list lutecia-refusals" data-lutecia-refusals></ul>
				</div>
			</section>


			<section class="lutecia-section" id="lutecia-channels" tabindex="-1" aria-labelledby="lutecia-h-channels">
				<h3 id="lutecia-h-channels"><?php esc_html_e( 'Channels', 'lutecia-for-woocommerce' ); ?></h3>
				<ul class="lutecia-list">
					<li>
						<div class="lutecia-row-details" data-lutecia-unfold>
							<button type="button" class="lutecia-name lutecia-unfold" aria-expanded="false" aria-controls="lutecia-more-discovery"><?php esc_html_e( 'Discovery', 'lutecia-for-woocommerce' ); ?></button>
							<div class="lutecia-row-body" id="lutecia-more-discovery">
								<div>
									<p><?php esc_html_e( 'Your products, searchable by AI agents through UCP (Universal Commerce Protocol), the open standard behind Google AI Mode, Gemini and Copilot. Each agent also runs its own merchant program, listed below.', 'lutecia-for-woocommerce' ); ?></p>
									<p class="lutecia-row-links">
										<a href="<?php echo esc_url( $lutecia_ucp_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open your UCP address (technical file)', 'lutecia-for-woocommerce' ); ?>&nbsp;&#8599;</a>
										<a href="https://ucp.dev" target="_blank" rel="noopener"><?php esc_html_e( 'What is UCP?', 'lutecia-for-woocommerce' ); ?>&nbsp;&#8599;</a>
									</p>
								</div>
							</div>
						</div>
						<span class="lutecia-kvline"></span>
						<span class="lutecia-dot" data-lutecia-discovery aria-hidden="true"></span>
					</li>
					<?php foreach ( $lutecia_programs as $lutecia_ch ) : ?>
						<li data-lutecia-access="<?php echo esc_attr( $lutecia_ch['key'] ); ?>">
							<div class="lutecia-row-details" data-lutecia-unfold>
								<button type="button" class="lutecia-name lutecia-unfold" aria-expanded="false" aria-controls="lutecia-more-<?php echo esc_attr( $lutecia_ch['key'] ); ?>"><?php echo \Lutecia\WC\Brand_Icons::svg( $lutecia_ch['key'] ); // Inline SVG built by the plugin, escaped there. ?><?php echo esc_html( $lutecia_ch['name'] ); ?></button>
								<div class="lutecia-row-body" id="lutecia-more-<?php echo esc_attr( $lutecia_ch['key'] ); ?>">
									<div>
										<p><?php echo esc_html( $lutecia_ch['desc'] ); ?></p>
										<?php if ( ! empty( $lutecia_ch['links'] ) ) : ?>
											<p class="lutecia-row-links">
												<?php foreach ( $lutecia_ch['links'] as $lutecia_link ) : ?>
													<a href="<?php echo esc_url( $lutecia_link[1] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $lutecia_link[0] ); ?>&nbsp;&#8599;</a>
												<?php endforeach; ?>
											</p>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<span class="lutecia-kvline">
								<a class="lutecia-link" href="<?php echo esc_url( $lutecia_ch['apply'][1] ); ?>" target="_blank" rel="noopener" data-lutecia-apply>
									<?php echo esc_html( $lutecia_screen_t['apply'] ); ?> <span aria-hidden="true">&#8599;</span><span class="lutecia-sr"><?php echo esc_html( $lutecia_ch['apply'][0] . ' ' . $lutecia_screen_t['new_tab'] ); ?></span>
								</a>
								<button type="button" class="lutecia-link" data-lutecia-accepted><?php esc_html_e( "I've been accepted", 'lutecia-for-woocommerce' ); ?></button>
								<button type="button" class="lutecia-link" data-lutecia-replace hidden><?php esc_html_e( 'Send different credentials', 'lutecia-for-woocommerce' ); ?></button>
							</span>
							<span class="lutecia-dot is-ok" data-lutecia-received aria-hidden="true" hidden></span>
							<div class="lutecia-row-extra">
								<form class="lutecia-access-form" data-lutecia-access-form hidden>
									<label>
										<span class="lutecia-muted lutecia-small"><?php echo esc_html( $lutecia_ch['accept'] ); ?>
											<?php esc_html_e( 'Paste what you received. It is sent encrypted, stored encrypted, and never shown again on this screen.', 'lutecia-for-woocommerce' ); ?></span>
										<textarea rows="3" required></textarea>
									</label>
									<div class="lutecia-access-actions">
										<button type="submit" class="lutecia-btn lutecia-btn-small"><?php esc_html_e( 'Send to Lutecia', 'lutecia-for-woocommerce' ); ?></button>
										<button type="button" class="lutecia-btn lutecia-btn-small lutecia-btn-ghost" data-lutecia-access-cancel><?php esc_html_e( 'Cancel', 'lutecia-for-woocommerce' ); ?></button>
									</div>
									<p class="lutecia-error" data-lutecia-access-error role="alert" hidden></p>
								</form>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="lutecia-section" id="lutecia-controls" tabindex="-1" aria-labelledby="lutecia-h-controls">
				<h3 id="lutecia-h-controls"><?php esc_html_e( 'Controls', 'lutecia-for-woocommerce' ); ?></h3>
				<ul class="lutecia-list">
					<?php foreach ( $lutecia_controls as $lutecia_ctl ) : ?>
						<li>
							<span class="lutecia-name" id="lutecia-l-<?php echo esc_attr( $lutecia_ctl['channel'] ); ?>" title="<?php echo esc_attr( $lutecia_ctl['desc'] ); ?>"><?php echo esc_html( $lutecia_ctl['name'] ); ?></span>
							<span class="lutecia-sr" id="lutecia-d-<?php echo esc_attr( $lutecia_ctl['channel'] ); ?>"><?php echo esc_html( $lutecia_ctl['desc'] ); ?></span>
							<span class="lutecia-kvline"></span>
							<button type="button" class="lutecia-switch" role="switch" aria-checked="false" aria-labelledby="lutecia-l-<?php echo esc_attr( $lutecia_ctl['channel'] ); ?>" aria-describedby="lutecia-d-<?php echo esc_attr( $lutecia_ctl['channel'] ); ?>" data-lutecia-channel="<?php echo esc_attr( $lutecia_ctl['channel'] ); ?>" disabled></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="lutecia-section" id="lutecia-settings" tabindex="-1" aria-labelledby="lutecia-h-settings">
				<h3 id="lutecia-h-settings"><?php echo esc_html( $lutecia_screen_t['nav_settings'] ); ?></h3>
				<ul class="lutecia-list">
					<li>
						<span class="lutecia-name"><?php echo esc_html( $lutecia_screen_t['address'] ); ?></span>
						<span class="lutecia-kvline"><?php echo esc_html( $lutecia_host ); ?></span>
					</li>
					<li data-lutecia-stripe-settings hidden>
						<span class="lutecia-name">Stripe</span>
						<span class="lutecia-kvline"></span>
						<button type="button" class="lutecia-link" data-lutecia-stripe-disconnect><?php echo esc_html( $lutecia_stripe['disconnect'] ); ?></button>
					</li>
					<li>
						<span class="lutecia-name"><?php esc_html_e( 'Privacy policy', 'lutecia-for-woocommerce' ); ?></span>
						<span class="lutecia-kvline">
							<?php if ( '' !== $lutecia_privacy_url ) : ?>
								<a class="lutecia-link" href="<?php echo esc_url( $lutecia_privacy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'lutecia-for-woocommerce' ); ?></a>
							<?php else : ?>
								<a class="lutecia-link" href="<?php echo esc_url( admin_url( 'options-privacy.php' ) ); ?>"><?php esc_html_e( 'Set up', 'lutecia-for-woocommerce' ); ?></a>
							<?php endif; ?>
						</span>
						<span class="lutecia-dot <?php echo '' !== $lutecia_privacy_url ? 'is-ok' : ''; ?>" aria-hidden="true"></span>
					</li>
					<li>
						<span class="lutecia-name"><?php esc_html_e( 'Terms and conditions', 'lutecia-for-woocommerce' ); ?></span>
						<span class="lutecia-kvline">
							<?php if ( $lutecia_terms_id > 0 ) : ?>
								<a class="lutecia-link" href="<?php echo esc_url( (string) get_permalink( $lutecia_terms_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'lutecia-for-woocommerce' ); ?></a>
							<?php else : ?>
								<a class="lutecia-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced' ) ); ?>"><?php esc_html_e( 'Set up', 'lutecia-for-woocommerce' ); ?></a>
							<?php endif; ?>
						</span>
						<span class="lutecia-dot <?php echo $lutecia_terms_id > 0 ? 'is-ok' : ''; ?>" aria-hidden="true"></span>
					</li>
					<li>
						<span class="lutecia-name"><?php echo esc_html( $lutecia_screen_t['step_store'] ); ?></span>
						<span class="lutecia-kvline"></span>
						<button type="button" class="lutecia-link" data-lutecia-disconnect><?php esc_html_e( 'Disconnect this store', 'lutecia-for-woocommerce' ); ?></button>
					</li>
				</ul>
			</section>
		</div>
	</div>
</section>
