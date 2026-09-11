<?php
/**
 * The single admin view, as a three-step wizard: 1 connect (hero +
 * button), 2 sync (spinner until discovery answers on the store's own
 * domain), 3 channels (status + channel cards + controls). Visual
 * identity mirrors the Lutecia site: ink on white, Helvetica, hairline
 * rules, one black pill, semantic color only for statuses.
 *
 * Copy rules:
 *  - Protocol names (UCP) are explained in one plain sentence where they
 *    first appear.
 *  - Program details (links, requirements, regions) are re-verified on
 *    each program's public page before every release; the date at the
 *    bottom of the Channels card is that last check.
 *  - No assistant is ever presented as already sending traffic.
 *
 * @var \Lutecia\WC\Connection $connection
 * @package Lutecia\WC
 */

defined( 'ABSPATH' ) || exit;

$lutecia_connected = $connection->is_connected();
$lutecia_ucp_url   = home_url( '/.well-known/ucp' );
$lutecia_link_tags = array(
	'a' => array(
		'href'   => array(),
		'target' => array(),
		'rel'    => array(),
	),
);
?>
<div class="wrap lutecia-wrap" id="lutecia-app"
	data-connected="<?php echo esc_attr( $lutecia_connected ? '1' : '0' ); ?>"
	data-lutecia-step="<?php echo esc_attr( $lutecia_connected ? '3' : '1' ); ?>">

	<h1 class="lutecia-title">Lutecia</h1>
	<p class="lutecia-subtitle"><?php esc_html_e( 'Your store, connected to AI shopping assistants.', 'lutecia-for-woocommerce' ); ?></p>

	<ol class="lutecia-steps">
		<li data-step="1"><?php esc_html_e( 'Connect', 'lutecia-for-woocommerce' ); ?></li>
		<li data-step="2"><?php esc_html_e( 'Sync', 'lutecia-for-woocommerce' ); ?></li>
		<li data-step="3"><?php esc_html_e( 'Channels', 'lutecia-for-woocommerce' ); ?></li>
	</ol>

	<!-- State: duplicate site -->
	<div class="lutecia-card" data-lutecia-view="duplicate" hidden>
		<h2><?php esc_html_e( 'This looks like a copy of a connected store', 'lutecia-for-woocommerce' ); ?></h2>
		<p>
			<?php esc_html_e( 'The connection belongs to another address. Nothing is sent from this site, and AI assistants are not served from it.', 'lutecia-for-woocommerce' ); ?>
		</p>
		<p class="lutecia-muted lutecia-small">
			<?php esc_html_e( 'Connected store:', 'lutecia-for-woocommerce' ); ?> <code data-lutecia-dup-expected></code><br />
			<?php esc_html_e( 'This site:', 'lutecia-for-woocommerce' ); ?> <code data-lutecia-dup-seen></code>
		</p>
		<p><?php esc_html_e( 'If this is a staging or test copy, leave it as it is. If the store really moved to this address, reconnect it here.', 'lutecia-for-woocommerce' ); ?></p>
		<button type="button" class="button button-primary" data-lutecia-reset><?php esc_html_e( 'This store moved here: reconnect', 'lutecia-for-woocommerce' ); ?></button>
	</div>

	<div class="lutecia-card lutecia-hero" data-lutecia-view="disconnected" <?php if ( $lutecia_connected ) : ?>hidden<?php endif; ?>>
		<h2><?php esc_html_e( 'Let AI agents shop your store', 'lutecia-for-woocommerce' ); ?></h2>
		<p>
			<?php esc_html_e( 'AI assistants are starting to buy on people\'s behalf. Lutecia connects your store to them, so they can place the order in it.', 'lutecia-for-woocommerce' ); ?>
		</p>
		<ul class="lutecia-bullets">
			<li><?php esc_html_e( 'Connects in one click, with no settings to fill in.', 'lutecia-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Disconnect from this screen at any time.', 'lutecia-for-woocommerce' ); ?></li>
		</ul>
		<label class="lutecia-terms">
			<input type="checkbox" data-lutecia-terms />
			<span>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: link to the terms of service and privacy policy. */
						__( 'I agree to the Lutecia %s.', 'lutecia-for-woocommerce' ),
						'<a href="https://lutecia.app/legal" target="_blank" rel="noopener">' . esc_html__( 'terms of service and privacy policy', 'lutecia-for-woocommerce' ) . '</a>'
					),
					$lutecia_link_tags
				);
				?>
			</span>
		</label>
		<?php if ( defined( 'LUTECIA_AGENCY_CODE' ) && '' !== trim( (string) LUTECIA_AGENCY_CODE ) ) : ?>
		<p class="lutecia-muted lutecia-agency-set">
			<?php
			printf(
				/* translators: %s: agency code from wp-config.php */
				esc_html__( 'Agency code %s, set in wp-config.php.', 'lutecia-for-woocommerce' ),
				'<code>' . esc_html( trim( (string) LUTECIA_AGENCY_CODE ) ) . '</code>'
			);
			?>
		</p>
		<?php else : ?>
		<p class="lutecia-agency-toggle">
			<button type="button" class="lutecia-linkbtn" data-lutecia-agency-toggle aria-expanded="false"><?php esc_html_e( 'Have an agency code?', 'lutecia-for-woocommerce' ); ?></button>
		</p>
		<label class="lutecia-agency" data-lutecia-agency-field hidden>
			<span><?php esc_html_e( 'Agency code', 'lutecia-for-woocommerce' ); ?></span>
			<input type="text" data-lutecia-agency placeholder="AG-XXXXXXXX" autocomplete="off" spellcheck="false" />
		</label>
		<?php endif; ?>
		<button type="button" class="lutecia-pill" data-lutecia-connect disabled>
			<span data-lutecia-connect-label><?php esc_html_e( 'Connect my store', 'lutecia-for-woocommerce' ); ?></span>
			<span class="lutecia-arrow" aria-hidden="true">&#8594;</span>
		</button>
		<p class="lutecia-muted lutecia-consent">
			<?php esc_html_e( 'By connecting, you agree that your public product data (titles, prices, images, stock status) is shared with the channels you enable, and that your store sends its own details (web address, name, language, currency, admin email) to register. Nothing about your orders, your customers or your visitors leaves your store unless you turn on an option under Controls after connecting.', 'lutecia-for-woocommerce' ); ?>
		</p>
		<p class="lutecia-error" data-lutecia-error hidden></p>
	</div>

	<!-- State: syncing (right after a fresh connect, until discovery answers) -->
	<div class="lutecia-card" data-lutecia-view="syncing" hidden>
		<h3><?php esc_html_e( 'Catalog sync', 'lutecia-for-woocommerce' ); ?></h3>
		<div class="lutecia-sync-row">
			<span class="lutecia-spinner" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
			<p class="lutecia-sync-title"><?php esc_html_e( 'Reading your catalog and making it available to AI assistants…', 'lutecia-for-woocommerce' ); ?></p>
		</div>
		<p class="lutecia-muted" data-lutecia-product-count></p>
		<p class="lutecia-muted lutecia-small"><?php esc_html_e( 'This usually takes under a minute. You can leave this page; syncing continues in the background.', 'lutecia-for-woocommerce' ); ?></p>
		<p class="lutecia-warn-text lutecia-small" data-lutecia-sync-hint hidden>
			<?php esc_html_e( 'Taking longer than a minute? Open Settings > Permalinks: if the structure is set to "Plain", choose any other option and save. The address AI assistants use to read your store needs it.', 'lutecia-for-woocommerce' ); ?>
		</p>
	</div>

	<!-- State: connected -->
	<div data-lutecia-view="connected" <?php if ( ! $lutecia_connected ) : ?>hidden<?php endif; ?>>

		<div class="lutecia-grid">
			<div class="lutecia-card">
				<h3><?php esc_html_e( 'Catalog sync', 'lutecia-for-woocommerce' ); ?></h3>
				<p class="lutecia-status lutecia-ok"><?php esc_html_e( 'Up to date', 'lutecia-for-woocommerce' ); ?></p>
				<p class="lutecia-muted" data-lutecia-product-count></p>
			</div>
		</div>

		<div class="lutecia-card" data-lutecia-data-card hidden>
			<h3><?php esc_html_e( 'Product data', 'lutecia-for-woocommerce' ); ?></h3>
			<p class="lutecia-muted lutecia-card-intro">
				<?php esc_html_e( 'Two product fields matter most for acceptance by the channels. Here is where your catalog stands, and where to fill each one.', 'lutecia-for-woocommerce' ); ?>
			</p>

			<div class="lutecia-data-row">
				<div class="lutecia-data-head">
					<strong><?php esc_html_e( 'Brand', 'lutecia-for-woocommerce' ); ?></strong>
					<span class="lutecia-data-status" data-lutecia-data-status="brand"></span>
				</div>
				<p class="lutecia-muted">
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
			</div>

			<div class="lutecia-data-row">
				<div class="lutecia-data-head">
					<strong><?php esc_html_e( 'GTIN', 'lutecia-for-woocommerce' ); ?></strong>
					<span class="lutecia-data-status" data-lutecia-data-status="gtin"></span>
				</div>
				<p class="lutecia-muted">
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
			</div>
		</div>

		<?php
		// Legal pages: AI shopping surfaces show the buyer your store's
		// privacy policy and terms at checkout. We read the pages your store
		// already has set in WordPress and WooCommerce, so a missing one is
		// shown here rather than guessed. Computed live at render time.
		$lutecia_privacy_url = get_privacy_policy_url();
		$lutecia_terms_id    = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
		$lutecia_has_privacy = '' !== $lutecia_privacy_url;
		$lutecia_has_terms   = $lutecia_terms_id > 0;
		?>
		<div class="lutecia-card">
			<h3><?php esc_html_e( 'Legal pages', 'lutecia-for-woocommerce' ); ?></h3>
			<p class="lutecia-muted lutecia-card-intro">
				<?php esc_html_e( 'When an assistant places an order (see Checkout under Controls), the buyer sees a link to your privacy policy and terms. These are the pages your store has set; add any that are missing.', 'lutecia-for-woocommerce' ); ?>
			</p>

			<div class="lutecia-data-row">
				<div class="lutecia-data-head">
					<strong><?php esc_html_e( 'Privacy policy', 'lutecia-for-woocommerce' ); ?></strong>
					<?php if ( $lutecia_has_privacy ) : ?>
						<span class="lutecia-data-status is-ok"><?php esc_html_e( 'Set', 'lutecia-for-woocommerce' ); ?></span>
					<?php else : ?>
						<span class="lutecia-data-status is-missing"><?php esc_html_e( 'Not set', 'lutecia-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $lutecia_has_privacy ) : ?>
					<p class="lutecia-muted">
						<?php esc_html_e( 'Buyers will see your privacy policy page.', 'lutecia-for-woocommerce' ); ?>
						<a href="<?php echo esc_url( $lutecia_privacy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View it', 'lutecia-for-woocommerce' ); ?></a>
					</p>
				<?php else : ?>
					<p class="lutecia-muted">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: link to the WordPress privacy settings. */
								__( 'Your store has no privacy policy page set. Where to set it: %s, then choose or create a page.', 'lutecia-for-woocommerce' ),
								'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'open WordPress privacy settings', 'lutecia-for-woocommerce' ) . '</a>'
							),
							$lutecia_link_tags
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<div class="lutecia-data-row">
				<div class="lutecia-data-head">
					<strong><?php esc_html_e( 'Terms and conditions', 'lutecia-for-woocommerce' ); ?></strong>
					<?php if ( $lutecia_has_terms ) : ?>
						<span class="lutecia-data-status is-ok"><?php esc_html_e( 'Set', 'lutecia-for-woocommerce' ); ?></span>
					<?php else : ?>
						<span class="lutecia-data-status is-missing"><?php esc_html_e( 'Not set', 'lutecia-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $lutecia_has_terms ) : ?>
					<p class="lutecia-muted">
						<?php esc_html_e( 'Buyers will see your terms page.', 'lutecia-for-woocommerce' ); ?>
						<a href="<?php echo esc_url( (string) get_permalink( $lutecia_terms_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View it', 'lutecia-for-woocommerce' ); ?></a>
					</p>
				<?php else : ?>
					<p class="lutecia-muted">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: link to the WooCommerce advanced settings. */
								__( 'Your store has no terms page set. Where to set it: %s, then set the Terms and conditions page.', 'lutecia-for-woocommerce' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced' ) ) . '">' . esc_html__( 'open WooCommerce advanced settings', 'lutecia-for-woocommerce' ) . '</a>'
							),
							$lutecia_link_tags
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="lutecia-card">
			<h3><?php esc_html_e( 'Channels', 'lutecia-for-woocommerce' ); ?></h3>

			<?php
			// Program details verified on each program's public page on the date
			// shown at the bottom of the card. Re-verify before every release.
			$lutecia_channels_list = array(
				array(
					'name'   => __( 'Discovery', 'lutecia-for-woocommerce' ),
					'badge'  => array( 'live', __( 'Address live', 'lutecia-for-woocommerce' ) ),
					'hook'   => 'discovery',
					'desc'   => __( 'Your products, searchable by AI agents through UCP (Universal Commerce Protocol), the open standard behind Google AI Mode, Gemini and Copilot. Each assistant also runs its own merchant program, listed below.', 'lutecia-for-woocommerce' ),
					'links'  => array(
						array( __( 'Open your UCP address (technical file)', 'lutecia-for-woocommerce' ), $lutecia_ucp_url ),
						array( __( 'What is UCP?', 'lutecia-for-woocommerce' ), 'https://ucp.dev' ),
					),
					'accept' => '',
				),
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
					'name'   => 'Google AI Mode & Gemini',
					'apply'  => array( __( 'Express interest with Google', 'lutecia-for-woocommerce' ), 'https://support.google.com/merchants/contact/ucp_integration_interest' ),
					'desc'   => __( "Google's AI shopping results use UCP, the same standard as your store's address above. Rollout covers the US, Canada and Australia, with the UK announced. A Google Merchant Center account is required.", 'lutecia-for-woocommerce' ),
					'links'  => array(
						array( __( 'Onboarding guide', 'lutecia-for-woocommerce' ), 'https://support.google.com/merchants/answer/16992327' ),
					),
					'accept' => __( 'Paste your Merchant Center ID and what Google sent you. Lutecia then emails you the addresses to enter in Merchant Center.', 'lutecia-for-woocommerce' ),
				),
				array(
					'key'    => 'microsoft',
					'name'   => 'Microsoft Copilot',
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
			foreach ( $lutecia_channels_list as $lutecia_ch ) :
				?>
				<div class="lutecia-row">
					<div class="lutecia-row-head">
						<strong><?php echo esc_html( $lutecia_ch['name'] ); ?></strong>
						<?php if ( ! empty( $lutecia_ch['badge'] ) ) : ?>
							<span
								class="lutecia-badge lutecia-badge-<?php echo esc_attr( $lutecia_ch['badge'][0] ); ?>"
								<?php if ( ! empty( $lutecia_ch['hook'] ) ) : ?>data-lutecia-discovery-badge<?php endif; ?>
							><?php echo esc_html( $lutecia_ch['badge'][1] ); ?></span>
						<?php endif; ?>
					</div>
					<p class="lutecia-muted"><?php echo esc_html( $lutecia_ch['desc'] ); ?></p>
					<?php if ( ! empty( $lutecia_ch['links'] ) ) : ?>
						<p class="lutecia-row-links">
							<?php foreach ( $lutecia_ch['links'] as $lutecia_link ) : ?>
								<a href="<?php echo esc_url( $lutecia_link[1] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $lutecia_link[0] ); ?>&nbsp;↗</a>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $lutecia_ch['accept'] ) ) : ?>
						<div class="lutecia-access"
							data-lutecia-access="<?php echo esc_attr( $lutecia_ch['key'] ); ?>">
							<div class="lutecia-row-foot">
							<ol class="lutecia-track">
									<li class="lutecia-track-step" data-track="apply">
										<a href="<?php echo esc_url( $lutecia_ch['apply'][1] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $lutecia_ch['apply'][0] ); ?>&nbsp;&#8599;</a>
									</li>
									<li class="lutecia-track-step" data-track="accepted">
										<button type="button" class="lutecia-linklike" data-lutecia-accepted><?php esc_html_e( "I've been accepted", 'lutecia-for-woocommerce' ); ?></button>
									</li>
									<li class="lutecia-track-step" data-track="deliver">
										<span><?php esc_html_e( 'We deliver your catalog', 'lutecia-for-woocommerce' ); ?></span>
									</li>
								</ol>
							</div>
							<p class="lutecia-received" data-lutecia-received hidden>
								<span data-lutecia-received-text></span>
								<button type="button" class="lutecia-linklike" data-lutecia-replace><?php esc_html_e( 'Send different credentials', 'lutecia-for-woocommerce' ); ?></button>
							</p>
							<form class="lutecia-access-form" data-lutecia-access-form hidden>
								<label>
									<span class="lutecia-muted lutecia-small"><?php echo esc_html( $lutecia_ch['accept'] ); ?>
										<?php esc_html_e( 'Paste what you received. It is sent encrypted, stored encrypted, and never shown again on this screen.', 'lutecia-for-woocommerce' ); ?></span>
									<textarea rows="3" required></textarea>
								</label>
								<div class="lutecia-access-actions">
									<button type="submit" class="lutecia-pill lutecia-pill-small"><?php esc_html_e( 'Send to Lutecia', 'lutecia-for-woocommerce' ); ?></button>
									<button type="button" class="lutecia-ghost lutecia-ghost-small" data-lutecia-access-cancel><?php esc_html_e( 'Cancel', 'lutecia-for-woocommerce' ); ?></button>
								</div>
								<p class="lutecia-error lutecia-small" data-lutecia-access-error hidden></p>
							</form>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<p class="lutecia-muted lutecia-small" data-lutecia-verified-line hidden>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: date of the last check of each program's public page. */
						__( 'Program details last verified on %s.', 'lutecia-for-woocommerce' ),
						'<span data-lutecia-verified></span>'
					),
					array( 'span' => array( 'data-lutecia-verified' => array() ) )
				);
				?>
			</p>
		</div>

		<div class="lutecia-card">
			<h3><?php esc_html_e( 'Controls', 'lutecia-for-woocommerce' ); ?></h3>

			<label class="lutecia-channel">
				<span class="lutecia-channel-text">
					<strong><?php esc_html_e( 'Discovery', 'lutecia-for-woocommerce' ); ?></strong>
					<span class="lutecia-muted"><?php esc_html_e( 'On by default. Your products, searchable by AI agents. Turning this off stops your store from answering AI assistants through Lutecia.', 'lutecia-for-woocommerce' ); ?></span>
				</span>
				<input type="checkbox" data-lutecia-channel="realtime" disabled />
			</label>

			<label class="lutecia-channel">
				<span class="lutecia-channel-text">
					<strong><?php esc_html_e( 'Checkout', 'lutecia-for-woocommerce' ); ?></strong>
					<span class="lutecia-muted"><?php esc_html_e( 'Off by default. On: an assistant can create a standard WooCommerce order with the customer\'s details. It appears in your Orders list awaiting payment, and you handle it as usual. This lets Lutecia create orders in your store.', 'lutecia-for-woocommerce' ); ?></span>
				</span>
				<input type="checkbox" data-lutecia-channel="checkout" disabled />
			</label>

			<label class="lutecia-channel">
				<span class="lutecia-channel-text">
					<strong><?php esc_html_e( 'Catalog file', 'lutecia-for-woocommerce' ); ?></strong>
					<span class="lutecia-muted"><?php esc_html_e( 'On by default. A daily file of your full catalog in Google Shopping format, for the programs that fetch a file rather than query live.', 'lutecia-for-woocommerce' ); ?></span>
				</span>
				<input type="checkbox" data-lutecia-channel="bulk_feed" disabled />
			</label>

			<label class="lutecia-channel">
				<span class="lutecia-channel-text">
					<strong><?php esc_html_e( 'Sales attribution', 'lutecia-for-woocommerce' ); ?></strong>
					<span class="lutecia-muted"><?php esc_html_e( 'Off (default): your storefront sets no cookie and reports no order. On: a shopper who arrives from a product link served to an AI assistant gets a 30-day cookie, and when that visit ends in a paid order, the order number, total, currency and the tracking code from that link are sent to us to attribute that sale to the assistant that recommended it. No customer details are sent. Mention the cookie in your own cookie notice.', 'lutecia-for-woocommerce' ); ?></span>
				</span>
				<input type="checkbox" data-lutecia-channel="attribution" disabled />
			</label>
		</div>

		<p class="lutecia-footer-actions">
			<button type="button" class="lutecia-ghost" data-lutecia-disconnect>
				<?php esc_html_e( 'Disconnect this store', 'lutecia-for-woocommerce' ); ?>
			</button>
			<span class="lutecia-muted lutecia-small lutecia-contact">
				<?php esc_html_e( 'Questions?', 'lutecia-for-woocommerce' ); ?>
				<a href="mailto:contact@lutecia.app">contact@lutecia.app</a>
			</span>
		</p>
	</div>

</div>
