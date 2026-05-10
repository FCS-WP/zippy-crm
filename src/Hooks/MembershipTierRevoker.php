<?php
namespace ZippyCrm\Hooks;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Models\VoucherCode;
use ZippyCrm\Services\ClaimHandler;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Tier-restricted vouchers (v1.11.0) need to revoke claims when a customer's
 * tier moves below the requirement. Listening to `crm_membership_level_changed`
 * (fired by MembershipService::set_for_user and the auto-evaluator) walks the
 * user's open claims and expires any whose voucher's allowed_tiers no longer
 * include the new slug.
 *
 * Why expire and not delete:
 *   - The claim history stays auditable (admin can still see "Sue claimed
 *     QA-VIP-ONLY at 2026-01-15, revoked 2026-03-10 due to tier downgrade").
 *   - The UNIQUE (voucher_id,user_id) on crm_voucher_claims would otherwise
 *     let the customer re-claim the same voucher post-downgrade if they
 *     upgraded back — and they shouldn't, that voucher was already a single
 *     "give" event.
 *
 * Multi-code claims also release their assigned crm_voucher_codes row back to
 * 'available' so the slot can be claimed by another qualifying customer.
 */
final class MembershipTierRevoker {

	public static function register(): void {
		add_action( 'crm_membership_level_changed', [ self::class, 'on_level_changed' ], 10, 3 );
	}

	/**
	 * @param int    $user_id
	 * @param string $previous_slug
	 * @param string $new_slug
	 */
	public static function on_level_changed( int $user_id, string $previous_slug, string $new_slug ): void {
		if ( $previous_slug === $new_slug ) {
			return;
		}

		global $wpdb;

		// Find the user's currently-claimed (status='claimed') tier-restricted
		// vouchers in one query — only those whose allowed_tiers list does NOT
		// include the new slug need revoking. Public and email-restricted
		// vouchers are unaffected.
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT c.id AS claim_id, c.code_id, v.id AS voucher_id, v.allowed_tiers
			 FROM ' . $wpdb->prefix . VoucherClaim::TABLE . ' c
			 JOIN ' . $wpdb->prefix . Voucher::TABLE . ' v ON v.id = c.voucher_id
			 WHERE c.user_id = %d
			   AND c.status  = %s
			   AND v.audience_mode = %s',
			$user_id,
			'claimed',
			'tier'
		), ARRAY_A );

		if ( ! $rows ) {
			return;
		}

		$revoked = 0;
		foreach ( $rows as $row ) {
			$allowed = json_decode( (string) ( $row['allowed_tiers'] ?? '' ), true );
			if ( ! is_array( $allowed ) ) {
				continue; // mode='tier' but no list = degenerate row, leave alone
			}
			if ( in_array( $new_slug, array_map( 'strval', $allowed ), true ) ) {
				continue; // user still qualifies
			}

			self::revoke_claim( (int) $row['claim_id'], (int) $row['voucher_id'], $row['code_id'] !== null ? (int) $row['code_id'] : null );
			$revoked++;
		}

		if ( $revoked > 0 ) {
			ClaimHandler::invalidate_user_cache( $user_id );
		}
	}

	/**
	 * Mark a single claim 'expired' and (for multi-code campaigns) release the
	 * assigned code back to the pool. Idempotent — safe to run twice.
	 */
	private static function revoke_claim( int $claim_id, int $voucher_id, ?int $code_id ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . VoucherClaim::TABLE,
			[ 'status' => 'expired' ],
			[ 'id' => $claim_id, 'status' => 'claimed' ],
			[ '%s' ],
			[ '%d', '%s' ]
		);

		// Multi-code: free the code row so another qualifying customer can claim.
		if ( $code_id !== null ) {
			$wpdb->update(
				$wpdb->prefix . VoucherCode::TABLE,
				[
					'status'           => 'available',
					'assigned_to_user' => null,
					'assigned_at'      => null,
				],
				[ 'id' => $code_id, 'status' => 'assigned' ],
				[ '%s', '%d', '%s' ],
				[ '%d', '%s' ]
			);
		}
	}
}
