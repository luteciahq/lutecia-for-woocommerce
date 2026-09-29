<?php
/**
 * Welcome screen: the store is not connected yet, or this site is a copy of
 * a connected store (Site_Guard) and the merchant chooses what to do.
 * Rendered by dashboard.php, which defines the variables below.
 *
 * @var string $lutecia_open      Screen the page opens on.
 * @var bool   $lutecia_duplicate Whether this site is a copy of a connected store.
 * @var array  $lutecia_dup       Site_Guard::duplicate_details().
 * @var array  $lutecia_screen_t  Screen_Strings::all().
 * @var array  $lutecia_link_tags Tags allowed in sentences with a link.
 * @package Lutecia\WC
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="lutecia-screen" data-lutecia-screen="welcome" <?php if ( 'welcome' !== $lutecia_open ) : ?>hidden<?php endif; ?>>

	<div class="lutecia-welcome" data-lutecia-welcome="connect" <?php if ( $lutecia_duplicate ) : ?>hidden<?php endif; ?>>
		<div class="lutecia-welcome-body">
			<h2 class="lutecia-display"><?php esc_html_e( 'Your store, connected to AI agents.', 'lutecia-for-woocommerce' ); ?></h2>
			<p>
				<button type="button" class="lutecia-btn" data-lutecia-connect>
					<span data-lutecia-connect-label><?php esc_html_e( 'Connect my store', 'lutecia-for-woocommerce' ); ?></span>
				</button>
			</p>
			<p class="lutecia-consent">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: link to the terms of service and privacy policy. */
						__( 'By connecting you agree to the Lutecia %s.', 'lutecia-for-woocommerce' ),
						'<a href="https://lutecia.app/legal" target="_blank" rel="noopener">' . esc_html__( 'terms of service and privacy policy', 'lutecia-for-woocommerce' ) . '</a>'
					),
					$lutecia_link_tags
				);
				?>
			</p>
			<p class="lutecia-error" data-lutecia-error role="alert" hidden></p>
			<?php if ( ! ( defined( 'LUTECIA_AGENCY_CODE' ) && '' !== trim( (string) LUTECIA_AGENCY_CODE ) ) ) : ?>
				<label class="lutecia-agency" data-lutecia-agency-field hidden>
					<span><?php esc_html_e( 'Agency code', 'lutecia-for-woocommerce' ); ?></span>
					<input type="text" data-lutecia-agency placeholder="AG-XXXXXXXX" autocomplete="off" spellcheck="false" />
				</label>
			<?php endif; ?>
		</div>
		<div class="lutecia-welcome-foot">
			<?php if ( defined( 'LUTECIA_AGENCY_CODE' ) && '' !== trim( (string) LUTECIA_AGENCY_CODE ) ) : ?>
				<p class="lutecia-muted lutecia-small">
					<?php
					printf(
						/* translators: %s: agency code from wp-config.php */
						esc_html__( 'Agency code %s, set in wp-config.php.', 'lutecia-for-woocommerce' ),
						'<code>' . esc_html( trim( (string) LUTECIA_AGENCY_CODE ) ) . '</code>'
					);
					?>
				</p>
			<?php else : ?>
				<p class="lutecia-small">
					<button type="button" class="lutecia-link" data-lutecia-agency-toggle aria-expanded="false"><?php esc_html_e( 'Have an agency code?', 'lutecia-for-woocommerce' ); ?></button>
				</p>
			<?php endif; ?>
			<details class="lutecia-disclosure">
				<summary><?php echo esc_html( $lutecia_screen_t['what_is_shared'] ); ?></summary>
				<p><?php esc_html_e( 'By connecting, you agree that your public product data (titles, prices, images, stock status) is shared with the channels you enable, and that your store sends its own details (web address, name, language, currency, admin email) to register. Nothing about your orders, your customers or your visitors leaves your store unless you turn on an option under Controls after connecting.', 'lutecia-for-woocommerce' ); ?></p>
			</details>
		</div>
	</div>

	<div class="lutecia-welcome" data-lutecia-welcome="duplicate" <?php if ( ! $lutecia_duplicate ) : ?>hidden<?php endif; ?>>
		<h2 class="lutecia-display"><?php esc_html_e( 'This looks like a copy of a connected store', 'lutecia-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'The connection belongs to another address. Nothing is sent from this site, and AI agents are not served from it.', 'lutecia-for-woocommerce' ); ?></p>
		<p class="lutecia-muted lutecia-small">
			<?php esc_html_e( 'Connected store:', 'lutecia-for-woocommerce' ); ?> <code data-lutecia-dup-expected><?php echo esc_html( (string) ( $lutecia_dup['expected'] ?? '' ) ); ?></code><br />
			<?php esc_html_e( 'This site:', 'lutecia-for-woocommerce' ); ?> <code data-lutecia-dup-seen><?php echo esc_html( (string) ( $lutecia_dup['seen'] ?? '' ) ); ?></code>
		</p>
		<p><?php esc_html_e( 'If this is a staging or test copy, leave it as it is. If the store really moved to this address, reconnect it here.', 'lutecia-for-woocommerce' ); ?></p>
		<p><button type="button" class="lutecia-btn" data-lutecia-reset><?php esc_html_e( 'This store moved here: reconnect', 'lutecia-for-woocommerce' ); ?></button></p>
	</div>

</section>
