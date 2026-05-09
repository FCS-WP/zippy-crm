<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Audit trail for admin write actions.
 *
 * Two ways to record:
 *   1. Listen to existing `crm_*` action hooks (preferred — service code that
 *      already does `do_action(...)` gets logged automatically).
 *   2. Explicit `AuditLogger::record(...)` calls from service methods that
 *      don't (yet) fire an action.
 *
 * Both paths gate on `is_admin_context()` so automated tier evaluations from
 * customer order completion are NOT logged here. The only events that land
 * in this table are ones initiated by an admin user.
 *
 * Event name constants live here so callers don't typo. Adding a new event:
 *   1. Add a public const for the name (e.g. `EVENT_NEW_THING = 'thing.did'`)
 *   2. Either listen to an existing action in `register()`, or add a
 *      `record_thing_did()` helper for explicit callers.
 */
final class AuditLogger {

	/* ============================================================
	 * Event names. Format: `domain.verb` — domain matches the table family,
	 * verb is past-tense.
	 * ============================================================ */

	public const EVENT_MEMBERSHIP_LEVEL_CHANGED  = 'membership.level_changed';
	public const EVENT_MEMBERSHIP_STATUS_CHANGED = 'membership.status_changed';

	public const EVENT_POINTS_ADJUSTED      = 'points.adjusted';
	public const EVENT_POINTS_RECALCULATED  = 'points.recalculated';

	public const EVENT_VOUCHER_CREATED   = 'voucher.created';
	public const EVENT_VOUCHER_UPDATED   = 'voucher.updated';
	public const EVENT_VOUCHER_PUBLISHED = 'voucher.published';
	public const EVENT_VOUCHER_PAUSED    = 'voucher.paused';
	public const EVENT_VOUCHER_RESUMED   = 'voucher.resumed';
	public const EVENT_VOUCHER_DELETED   = 'voucher.deleted';
	public const EVENT_VOUCHER_DUPLICATED = 'voucher.duplicated';

	/**
	 * Hook target: Plugin::boot.
	 * Subscribes to existing crm_* actions so admin writes get logged
	 * automatically. The is_admin_context() guard inside each handler skips
	 * customer + system fires.
	 */
	public static function register(): void {
		add_action( 'crm_membership_level_changed',  [ self::class, 'on_level_changed' ],  10, 3 );
		add_action( 'crm_membership_status_changed', [ self::class, 'on_status_changed' ], 10, 3 );

		// Voucher publish action exists already. The other voucher actions
		// (created/updated/paused/...) are explicit-call territory — see record_*.
		add_action( 'crm_voucher_published', [ self::class, 'on_voucher_published' ] );
	}

	/* ============================================================
	 * Action listeners
	 * ============================================================ */

	public static function on_level_changed( int $user_id, string $from, string $to ): void {
		if ( ! self::is_admin_context() ) {
			return; // automated tier evaluation, not an admin action
		}
		self::record(
			self::EVENT_MEMBERSHIP_LEVEL_CHANGED,
			$user_id,
			[ 'from' => $from, 'to' => $to ]
		);
	}

	public static function on_status_changed( int $user_id, string $from, string $to ): void {
		if ( ! self::is_admin_context() ) {
			return;
		}
		self::record(
			self::EVENT_MEMBERSHIP_STATUS_CHANGED,
			$user_id,
			[ 'from' => $from, 'to' => $to ]
		);
	}

	public static function on_voucher_published( int $voucher_id ): void {
		if ( ! self::is_admin_context() ) {
			return;
		}
		self::record(
			self::EVENT_VOUCHER_PUBLISHED,
			null, // voucher events have no target user
			[ 'voucher_id' => $voucher_id ]
		);
	}

	/* ============================================================
	 * Explicit recorders. Call these from admin write paths that don't
	 * already fire an action. The parallel admin session can wire these
	 * into their VoucherService::create_draft / update / pause / resume /
	 * delete / duplicate methods, and into their points-adjust handler.
	 * ============================================================ */

	public static function record_voucher_created( int $voucher_id, array $payload ): void {
		self::record(
			self::EVENT_VOUCHER_CREATED,
			null,
			[ 'voucher_id' => $voucher_id, 'code' => $payload['code'] ?? '' ]
		);
	}

	public static function record_voucher_updated( int $voucher_id, array $changed_fields ): void {
		self::record(
			self::EVENT_VOUCHER_UPDATED,
			null,
			[ 'voucher_id' => $voucher_id, 'fields' => array_keys( $changed_fields ) ]
		);
	}

	public static function record_voucher_paused( int $voucher_id ): void {
		self::record( self::EVENT_VOUCHER_PAUSED, null, [ 'voucher_id' => $voucher_id ] );
	}

	public static function record_voucher_resumed( int $voucher_id ): void {
		self::record( self::EVENT_VOUCHER_RESUMED, null, [ 'voucher_id' => $voucher_id ] );
	}

	public static function record_voucher_deleted( int $voucher_id, string $code ): void {
		self::record(
			self::EVENT_VOUCHER_DELETED,
			null,
			[ 'voucher_id' => $voucher_id, 'code' => $code ]
		);
	}

	public static function record_voucher_duplicated( int $source_id, int $new_id ): void {
		self::record(
			self::EVENT_VOUCHER_DUPLICATED,
			null,
			[ 'source_id' => $source_id, 'new_id' => $new_id ]
		);
	}

	public static function record_points_adjusted( int $target_user_id, int $delta, string $reason ): void {
		self::record(
			self::EVENT_POINTS_ADJUSTED,
			$target_user_id,
			[ 'delta' => $delta, 'reason' => $reason ]
		);
	}

	public static function record_points_recalculated( int $target_user_id, int $before, int $after ): void {
		self::record(
			self::EVENT_POINTS_RECALCULATED,
			$target_user_id,
			[ 'before' => $before, 'after' => $after ]
		);
	}

	/* ============================================================
	 * Core
	 * ============================================================ */

	/**
	 * Low-level recorder. Caller MUST already be in admin context — this
	 * doesn't gate further; it trusts you. The action listeners above are
	 * the gated path.
	 */
	public static function record( string $event, ?int $target_id, array $meta = [] ): int {
		$actor_id = get_current_user_id();
		if ( $actor_id <= 0 ) {
			// No user → don't log. Avoids polluting the table with fixture seeds
			// run from CLI without wp_set_current_user.
			return 0;
		}
		return AuditLog::insert( $event, $actor_id, $target_id, $meta );
	}

	/**
	 * Distinguishes admin-initiated writes from automated/customer-initiated
	 * ones. The two filters together catch every realistic admin path:
	 *   - is_admin() is true during admin-screen requests
	 *   - REST_REQUEST is true during /wp-json/* — and we only log when the
	 *     current user has manage_woocommerce, which customer routes never
	 *     run as
	 *
	 * False during checkout-driven hook firings (no logged-in admin), CLI
	 * (no user) and customer REST calls.
	 */
	private static function is_admin_context(): bool {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			return false;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}
		// Pass on either: wp-admin screen OR REST request from an admin user.
		// The capability check above already does the heavy lifting; this
		// extra branch just means we don't log if (somehow) an admin browses
		// to a customer page and triggers a non-admin action.
		return is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}
}
