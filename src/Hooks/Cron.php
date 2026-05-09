<?php
namespace ZippyCrm\Hooks;

use ZippyCrm\Services\NotifEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Scheduled events. Two flavors:
 *   - Single events (HOOK_DISPATCH) scheduled by NotifEngine on publish
 *   - Recurring events (HOOK_RETRY, ...) registered once at boot
 *
 * The voucher-published listener also lives here so all action wiring is in
 * one place.
 */
final class Cron {

	public const HOOK_DISPATCH       = 'crm_dispatch_voucher_notifications';
	public const HOOK_RETRY          = 'crm_retry_failed_notifications';
	public const HOOK_CHECK_UPGRADES = 'crm_check_membership_upgrades';
	public const HOOK_EXPIRE_CLAIMS  = 'crm_expire_old_voucher_claims';

	public static function register(): void {
		// One-shot: scheduled per-batch by NotifEngine::on_voucher_published.
		add_action( self::HOOK_DISPATCH, [ self::class, 'dispatch_batch' ], 10, 2 );

		// Recurring: registered once at boot.
		add_action( self::HOOK_RETRY,          [ self::class, 'retry_failed' ] );
		add_action( self::HOOK_CHECK_UPGRADES, [ self::class, 'check_upgrades' ] );
		add_action( self::HOOK_EXPIRE_CLAIMS,  [ self::class, 'expire_old_claims' ] );

		// Voucher publish → queue notifications.
		add_action( 'crm_voucher_published', [ NotifEngine::class, 'on_voucher_published' ] );

		add_action( 'init', [ self::class, 'schedule_recurring' ] );
	}

	public static function schedule_recurring(): void {
		if ( ! wp_next_scheduled( self::HOOK_RETRY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK_RETRY );
		}
		if ( ! wp_next_scheduled( self::HOOK_CHECK_UPGRADES ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::HOOK_CHECK_UPGRADES );
		}
		if ( ! wp_next_scheduled( self::HOOK_EXPIRE_CLAIMS ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::HOOK_EXPIRE_CLAIMS );
		}
	}

	/**
	 * Schedules a dispatch event for one batch, staggered by the configured
	 * interval to avoid hammering the SMTP provider on a big publish.
	 *
	 * Called only from NotifEngine::on_voucher_published.
	 */
	public static function schedule_dispatch_batch( int $voucher_id, int $batch_index ): void {
		$delay = $batch_index * NotifEngine::batch_interval();
		wp_schedule_single_event(
			time() + $delay,
			self::HOOK_DISPATCH,
			[ $voucher_id, $batch_index ]
		);
	}

	public static function dispatch_batch( int $voucher_id, int $batch_index ): void {
		NotifEngine::dispatch_batch( $voucher_id, $batch_index );
	}

	public static function retry_failed(): void {
		NotifEngine::retry_failed();
	}

	public static function check_upgrades(): void {
		// TODO (membership backfill) — daily sweep of orders → tier eval.
		// Read endpoint already self-heals on access, so this is a low-priority cleanup.
	}

	public static function expire_old_claims(): void {
		// TODO (vouchers cleanup) — flip claim status to 'expired' for vouchers
		// past their expires_at. Customer UI already filters by voucher.expires_at,
		// so this is cosmetic for the My Claims tab.
	}
}
