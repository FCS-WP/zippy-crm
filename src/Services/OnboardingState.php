<?php
namespace ZippyCrm\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Per-admin onboarding state, backed by `user_meta`. Two values:
 *
 *   step      — int 1..N, where the admin is in the guide.
 *   dismissed — bool, true if the admin clicked "Skip for now" or finished
 *               the guide. Once dismissed, the auto-redirect never fires
 *               for this admin again (the activation flag is one-shot
 *               anyway, but this gates the Phase 3 "View setup guide"
 *               revisit link from re-arming auto-behaviour).
 *
 * Per-admin (vs site-wide global) so a new staff member joining months
 * later still gets the tour. Cost: a couple of user_meta rows per admin
 * who's seen the guide — negligible.
 */
final class OnboardingState {

	private const META_STEP      = '_zc_onboarding_step';
	private const META_DISMISSED = '_zc_onboarding_dismissed';

	public const TOTAL_STEPS = 7;

	/**
	 * Read state for one admin. Missing rows yield sensible defaults
	 * (step=1, dismissed=false) — first-time readers see "you're at step 1".
	 *
	 * @return array{step:int, dismissed:bool}
	 */
	public static function get_for_user( int $user_id ): array {
		$step = (int) get_user_meta( $user_id, self::META_STEP, true );
		if ( $step < 1 ) {
			$step = 1;
		}
		if ( $step > self::TOTAL_STEPS ) {
			$step = self::TOTAL_STEPS;
		}
		return [
			'step'      => $step,
			'dismissed' => (bool) get_user_meta( $user_id, self::META_DISMISSED, true ),
		];
	}

	/** Clamp + persist the step. Returns the clamped value. */
	public static function set_step( int $user_id, int $step ): int {
		$step = max( 1, min( self::TOTAL_STEPS, $step ) );
		update_user_meta( $user_id, self::META_STEP, $step );
		return $step;
	}

	/**
	 * Mark dismissed. One-way operation; revisits from the Settings link
	 * (Phase 3) reset `step` only, not the dismissed flag, so the
	 * activation auto-redirect can never re-arm.
	 */
	public static function dismiss( int $user_id ): void {
		update_user_meta( $user_id, self::META_DISMISSED, '1' );
	}

	/**
	 * Reset step to 1 without clearing dismissed. Used by the Phase 3
	 * "View setup guide" revisit link so admins re-take the tour without
	 * un-arming the auto-redirect protection.
	 */
	public static function reset_step( int $user_id ): void {
		update_user_meta( $user_id, self::META_STEP, 1 );
	}
}
