<?php
namespace ZippyCrm\Hooks;

defined( 'ABSPATH' ) || exit;

final class Cron {

	public const HOOK_DISPATCH       = 'crm_dispatch_voucher_notifications';
	public const HOOK_RETRY          = 'crm_retry_failed_notifications';
	public const HOOK_CHECK_UPGRADES = 'crm_check_membership_upgrades';
	public const HOOK_EXPIRE_CLAIMS  = 'crm_expire_old_voucher_claims';

	public static function register(): void {
		add_action( self::HOOK_DISPATCH,       [ self::class, 'dispatch_batch' ], 10, 2 );
		add_action( self::HOOK_RETRY,          [ self::class, 'retry_failed' ] );
		add_action( self::HOOK_CHECK_UPGRADES, [ self::class, 'check_upgrades' ] );
		add_action( self::HOOK_EXPIRE_CLAIMS,  [ self::class, 'expire_old_claims' ] );

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

	public static function dispatch_batch( int $voucher_id, int $offset ): void { /* TODO */ }
	public static function retry_failed(): void                                    { /* TODO */ }
	public static function check_upgrades(): void                                  { /* TODO */ }
	public static function expire_old_claims(): void                               { /* TODO */ }
}
