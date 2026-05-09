<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\PointsLedger;
use ZippyCrm\Models\PointsSummary;
use ZippyCrm\Support\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-only points operations: manual adjust, system-wide summary, bulk
 * reconciliation. Customer-flow operations (earn / redeem / consume) live in
 * PointsEngine.
 *
 * Both classes share PointsEngine::CACHE_KEY_SYSTEM — per-user writes blow
 * the system cache via PointsEngine::invalidate(), and writes here do the
 * same via PointsEngine::invalidate() for the affected user.
 */
final class PointsAdmin {

	/**
	 * Admin manual adjust. Positive delta = credit, negative = debit. Writes a
	 * single 'adjust' ledger row and applies the summary delta. Refuses any
	 * debit that would push the balance negative — admins can recalculate if
	 * they really need to bottom out, but routine support adjustments should
	 * never produce a negative balance silently.
	 *
	 * @return int|\WP_Error  The new balance after adjustment, or a WP_Error.
	 */
	public static function adjust( int $user_id, int $delta, string $reason, int $admin_id ) {
		if ( $delta === 0 ) {
			return new \WP_Error( 'adjust_zero', __( 'Adjustment must be non-zero.', 'zippy-crm' ), [ 'status' => 400 ] );
		}
		if ( $reason === '' ) {
			return new \WP_Error( 'adjust_no_reason', __( 'A reason is required for manual adjustments.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		$summary = PointsEngine::get_summary( $user_id );
		if ( $delta < 0 && $summary['balance'] + $delta < 0 ) {
			return new \WP_Error(
				'adjust_would_go_negative',
				sprintf(
					/* translators: 1: current balance, 2: requested debit */
					__( 'Cannot debit %2$d — current balance is only %1$d.', 'zippy-crm' ),
					$summary['balance'],
					-$delta
				),
				[ 'status' => 400, 'balance' => $summary['balance'] ]
			);
		}

		$description = sprintf(
			/* translators: 1: admin user id, 2: reason text */
			__( 'Admin #%1$d: %2$s', 'zippy-crm' ),
			$admin_id,
			$reason
		);

		PointsLedger::insert( $user_id, 'adjust', $delta, $description, null );
		PointsSummary::apply_delta( $user_id, $delta );
		PointsEngine::invalidate( $user_id );

		do_action( 'crm_points_adjusted', $user_id, $delta, $reason, $admin_id );

		return PointsEngine::get_balance( $user_id );
	}

	/**
	 * System-wide totals for the admin Points panel. Cached because the
	 * underlying SUM() reads scale with member count; the cache is invalidated
	 * on every per-user write via PointsEngine::invalidate().
	 *
	 * @return array{issued:int, redeemed:int, outstanding:int, members:int}
	 */
	public static function system_summary(): array {
		return Cache::remember( PointsEngine::CACHE_KEY_SYSTEM, function () {
			return PointsSummary::system_totals();
		} );
	}

	/**
	 * Bulk reconcile every user's summary against their ledger. Idempotent —
	 * safe to run repeatedly. Returns counts so the admin UI can show the
	 * outcome.
	 *
	 * @return array{processed:int, drift_corrected:int, errors:int}
	 */
	public static function recalculate_all(): array {
		$ids       = PointsSummary::all_user_ids();
		$processed = 0;
		$drift     = 0;
		$errors    = 0;

		foreach ( $ids as $user_id ) {
			$before         = PointsSummary::find( $user_id );
			$before_balance = $before ? (int) $before['balance'] : 0;

			try {
				$totals = PointsEngine::recalculate_balance( $user_id );
			} catch ( \Throwable $e ) {
				$errors++;
				continue;
			}

			if ( (int) $totals['balance'] !== $before_balance ) {
				$drift++;
			}
			$processed++;
		}

		Cache::delete( PointsEngine::CACHE_KEY_SYSTEM );

		return [
			'processed'       => $processed,
			'drift_corrected' => $drift,
			'errors'          => $errors,
		];
	}
}
