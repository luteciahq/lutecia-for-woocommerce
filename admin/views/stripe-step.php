<?php
/**
 * The Stripe step of the setup wizard: two blocks opened one at a time,
 * Account then Agentic commerce. Both are done on what the hub detects
 * (account connected, catalog accepted by Stripe); nothing is ticked by
 * hand and no hook address or secret is asked. Included by setup.php.
 *
 * @var array $lutecia_stripe   Stripe_Strings::all().
 * @var array $lutecia_screen_t Screen_Strings::all().
 * @var array $lutecia_bold     Allowed tags of a path line (b).
 * @package Lutecia\WC
 */

defined( 'ABSPATH' ) || exit;
?>
		<!-- Step 3: Stripe -->
		<div class="lutecia-stage" data-lutecia-step="3" hidden>
			<div class="lutecia-stage-body">
				<h2 tabindex="-1">Stripe</h2>

				<div class="lutecia-blk" data-lutecia-blk="account" data-state="current">
					<div class="lutecia-blk-head">
						<span class="lutecia-dot" aria-hidden="true"></span>
						<h3><?php echo esc_html( $lutecia_stripe['block_account'] ); ?></h3>
						<span class="lutecia-blk-sum" data-lutecia-account-name hidden></span>
						<a class="lutecia-link" href="<?php echo esc_url( \Lutecia\WC\Admin::STRIPE_ACTIVATION_URL ); ?>" target="_blank" rel="noopener" data-lutecia-activation hidden>
							<?php echo esc_html( $lutecia_stripe['open_activation'] ); ?> <span aria-hidden="true">&#8599;</span><span class="lutecia-sr"><?php echo esc_html( $lutecia_screen_t['new_tab'] ); ?></span>
						</a>
						<button type="button" class="lutecia-link" data-lutecia-reopen hidden><?php echo esc_html( $lutecia_stripe['change'] ); ?></button>
					</div>
					<div class="lutecia-blk-content">
						<button type="button" class="lutecia-btn lutecia-btn-stripe" data-lutecia-stripe-connect><?php echo esc_html( $lutecia_stripe['connect'] ); ?></button>
						<p class="lutecia-error" data-lutecia-stripe-error role="alert" hidden></p>
					</div>
				</div>

				<div class="lutecia-blk" data-lutecia-blk="agentic" data-state="later">
					<div class="lutecia-blk-head">
						<span class="lutecia-dot" aria-hidden="true"></span>
						<h3><?php echo esc_html( $lutecia_stripe['block_agentic'] ); ?></h3>
					</div>
					<div class="lutecia-blk-content">
						<a class="lutecia-btn lutecia-btn-stripe" href="<?php echo esc_url( \Lutecia\WC\Admin::STRIPE_ASSISTANTS_URL ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $lutecia_stripe['open_agentic'] ); ?> <span aria-hidden="true">&#8599;</span><span class="lutecia-sr"><?php echo esc_html( $lutecia_screen_t['new_tab'] ); ?></span>
						</a>
						<p class="lutecia-path"><?php echo wp_kses( \Lutecia\WC\Stripe_Strings::path( 'agentic_path', array( 'Get started' ) ), $lutecia_bold ); ?></p>
						<p class="lutecia-error" data-lutecia-agentic-off hidden>
							<?php echo esc_html( $lutecia_stripe['agentic_off'] ); ?>
							<button type="button" class="lutecia-link" data-lutecia-send-again><?php echo esc_html( $lutecia_stripe['send_again'] ); ?></button>
						</p>
					</div>
				</div>
			</div>
			<div class="lutecia-actions">
				<div class="lutecia-actions-left">
					<button type="button" class="lutecia-link" data-lutecia-goto="2"><?php echo esc_html( $lutecia_screen_t['back'] ); ?></button>
					<button type="button" class="lutecia-link" data-lutecia-finish-later><?php echo esc_html( $lutecia_screen_t['finish_later'] ); ?></button>
				</div>
				<div class="lutecia-actions-right">
					<span class="lutecia-sync lutecia-small lutecia-muted" data-lutecia-stripe-wait hidden><span class="lutecia-spinner" aria-hidden="true"></span><?php echo esc_html( $lutecia_stripe['waiting'] ); ?></span>
					<button type="button" class="lutecia-btn" data-lutecia-stripe-next disabled><?php echo esc_html( $lutecia_screen_t['continue'] ); ?></button>
				</div>
			</div>
		</div>
