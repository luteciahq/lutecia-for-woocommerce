<?php
/**
 * Where the merchant is in the setup wizard, per site: the step to reopen,
 * and whether the Assistants step was closed with Done. Everything else the
 * wizard shows is read from the store and the hub, never stored here.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

final class Wizard {

/** Step to reopen, 1 to 4; 0 or absent: the screen opens on Home. uninstall.php repeats both keys. */
public const OPT_STEP = 'lutecia_wizard_step';
/** '1' once the merchant clicked Done on the Assistants step. */
public const OPT_ASSISTANTS_DONE = 'lutecia_wizard_assistants_done';

/** The Stripe step, opened again when the merchant comes back from Stripe. */
public const STEP_STRIPE = 3;

private const LAST_STEP = 4;

/**
 * The saved state.
 *
 * @return array{step: int, assistants_done: bool}
 */
public static function state(): array {
	$step = (int) get_option( self::OPT_STEP, 0 );
	return array(
		'step'            => ( $step >= 1 && $step <= self::LAST_STEP ) ? $step : 0,
		'assistants_done' => '1' === (string) get_option( self::OPT_ASSISTANTS_DONE, '' ),
	);
}

/**
 * Saves the fields present in $patch (step, assistants_done) and returns
 * the new state. Unknown fields are ignored.
 *
 * @param array $patch Fields to save.
 * @return array{step: int, assistants_done: bool}
 */
public static function save( array $patch ): array {
	if ( array_key_exists( 'step', $patch ) ) {
		$step = (int) $patch['step'];
		update_option( self::OPT_STEP, ( $step >= 1 && $step <= self::LAST_STEP ) ? $step : 0, false );
	}
	if ( array_key_exists( 'assistants_done', $patch ) ) {
		update_option( self::OPT_ASSISTANTS_DONE, rest_sanitize_boolean( $patch['assistants_done'] ) ? '1' : '', false );
	}
	return self::state();
}

/** Stripe disconnected: the Assistants step starts over. */
public static function forget_stripe(): void {
	delete_option( self::OPT_ASSISTANTS_DONE );
}

/** Store disconnected or reset: the wizard starts over. */
public static function forget(): void {
	delete_option( self::OPT_STEP );
	self::forget_stripe();
}
}
