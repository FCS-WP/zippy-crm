<?php
namespace ZippyCrm\Services;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Hooks\Cron;
use ZippyCrm\Models\NotificationLog;
use ZippyCrm\Models\Voucher;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Voucher email dispatcher.
 *
 * Lifecycle:
 *   crm_voucher_published action
 *     → NotifEngine::on_voucher_published
 *       → walks crm_notif_subs in batches of ZIPPY_CRM_EMAIL_BATCH_SIZE
 *       → INSERT one queued row per (voucher,user) (UNIQUE collisions are skipped)
 *       → wp_schedule_single_event per batch, staggered by EMAIL_BATCH_INTERVAL
 *
 *   Cron::HOOK_DISPATCH ($voucher_id, $batch_offset)
 *     → NotifEngine::dispatch_batch
 *       → SELECT log rows in this batch (status=queued)
 *       → wp_mail each, transition queued → sent | failed
 *
 *   Cron::HOOK_RETRY (hourly)
 *     → NotifEngine::retry_failed
 *       → find rows status=failed AND attempts<RETRY_MAX
 *       → wp_mail each, transition failed → sent | failed (++attempts)
 *
 * Three idempotency guards (mirror the points/voucher patterns):
 *   1. UNIQUE (voucher_id, user_id) on INSERT — replay-safe queue
 *   2. status='queued' check before send — won't re-send a row already in flight
 *   3. attempts < RETRY_MAX cap — bounded retry loop
 */
final class NotifEngine {

	private const BATCH_SIZE_FALLBACK     = 50;
	private const BATCH_INTERVAL_FALLBACK = 300; // 5 min
	private const RETRY_BATCH_SIZE        = 100;

	/* ============================================================
	 * Publish path: queue subscribers + schedule batches
	 * ============================================================ */

	/**
	 * Hook target: crm_voucher_published.
	 * Inserts queued log rows for every eligible subscriber, then schedules
	 * a dispatch event per batch so wp_mail never runs in the request lifecycle.
	 */
	public static function on_voucher_published( int $voucher_id ): void {
		$voucher = Voucher::find( $voucher_id );
		if ( ! $voucher || $voucher['status'] !== 'active' ) {
			return;
		}

		$batch_size  = self::batch_size();
		$batch_index = 0;
		$queued_now  = 0;

		// Walk subscribers in pages until we run out. Each page becomes one
		// scheduled dispatch event — the batch_offset stored on the event is
		// the SUBSCRIBER offset, which we re-query at dispatch time.
		while ( true ) {
			$user_ids = self::query_unsent_subscribers( $voucher_id, $batch_size, $batch_index * $batch_size );
			if ( empty( $user_ids ) ) {
				break;
			}

			// Pre-insert the queue rows so subsequent passes (if the publish
			// replays) skip these users. Collisions on UNIQUE are tolerated.
			foreach ( $user_ids as $user_id ) {
				if ( NotificationLog::insert_queued( $voucher_id, $user_id ) > 0 ) {
					$queued_now++;
				}
			}

			Cron::schedule_dispatch_batch( $voucher_id, $batch_index );

			$batch_index++;
		}

		do_action( 'crm_voucher_notifications_queued', $voucher_id, $queued_now, $batch_index );
	}

	/* ============================================================
	 * Dispatch path: send queued rows for one batch
	 * ============================================================ */

	/**
	 * Hook target: Cron::HOOK_DISPATCH.
	 *
	 * Re-queries the queue rows for this voucher (status=queued) ordered by
	 * id, takes a slice of $batch_size starting at $batch_index*$batch_size.
	 * Sends each one and transitions the row.
	 *
	 * We don't trust the offset to point at the same rows as when the batch
	 * was scheduled — between scheduling and firing, retries could have flipped
	 * other rows. So we always work the oldest queued rows first. That makes
	 * batches commutative: it doesn't matter which fires first.
	 */
	public static function dispatch_batch( int $voucher_id, int $batch_index ): int {
		$voucher = Voucher::find( $voucher_id );
		if ( ! $voucher ) {
			return 0;
		}

		global $wpdb;
		$batch_size = self::batch_size();

		// Take the next $batch_size queued rows, oldest first. The (status,id)
		// pair is selective enough that we don't need a custom .sql file —
		// this is one query, used in one place, easy to read inline.
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, user_id FROM ' . $wpdb->prefix . NotificationLog::TABLE . '
			 WHERE  voucher_id = %d AND status = %s
			 ORDER  BY id ASC
			 LIMIT  %d',
			$voucher_id,
			NotificationLog::STATUS_QUEUED,
			$batch_size
		), ARRAY_A );

		if ( ! $rows ) {
			return 0;
		}

		$sent = 0;
		foreach ( $rows as $row ) {
			$ok = self::send_one( (int) $row['id'], (int) $row['user_id'], $voucher );
			if ( $ok ) {
				$sent++;
			}
		}

		do_action( 'crm_voucher_notifications_dispatched', $voucher_id, $sent, $batch_index );
		return $sent;
	}

	/* ============================================================
	 * Retry path
	 * ============================================================ */

	/**
	 * Hook target: Cron::HOOK_RETRY (hourly).
	 * Walks failed rows under attempts cap, retries each.
	 */
	public static function retry_failed(): int {
		$rows = NotificationLog::find_failed_for_retry( self::RETRY_BATCH_SIZE );
		if ( empty( $rows ) ) {
			return 0;
		}

		$sent = 0;
		foreach ( $rows as $row ) {
			$voucher = Voucher::find( $row['voucher_id'] );
			if ( ! $voucher ) {
				// Voucher deleted — mark failed permanently by exhausting attempts.
				NotificationLog::mark_failed( $row['id'], 'voucher missing' );
				continue;
			}
			if ( self::send_one( $row['id'], $row['user_id'], $voucher ) ) {
				$sent++;
			}
		}
		return $sent;
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	/**
	 * Sends one email and transitions the log row. Returns true on send success.
	 *
	 * Wrapped in try/catch — wp_mail can throw via PHPMailer, especially if
	 * SMTP is misconfigured. Any failure routes to mark_failed, never crashes
	 * the cron run.
	 */
	private static function send_one( int $log_id, int $user_id, array $voucher ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			NotificationLog::mark_failed( $log_id, 'user has no email' );
			return false;
		}

		$subject = self::build_subject( $voucher );
		$body    = self::render_template( $user, $voucher );
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		try {
			$ok = wp_mail( $user->user_email, $subject, $body, $headers );
		} catch ( \Throwable $e ) {
			NotificationLog::mark_failed( $log_id, $e->getMessage() );
			return false;
		}

		if ( $ok ) {
			NotificationLog::mark_sent( $log_id );
			return true;
		}

		NotificationLog::mark_failed( $log_id, 'wp_mail returned false' );
		return false;
	}

	private static function build_subject( array $voucher ): string {
		$is_percent = in_array( (string) ( $voucher['discount_type'] ?? '' ), \ZippyCrm\Models\Voucher::PERCENT_DISCOUNT_TYPES, true );
		$value = $is_percent
			? round( (float) $voucher['discount_value'] ) . '%'
			: '$' . number_format( (float) $voucher['discount_value'], 2 );

		return apply_filters(
			'crm_voucher_notification_subject',
			sprintf(
				/* translators: 1: voucher title, 2: discount value */
				__( 'New Voucher: %1$s — Save %2$s on your next order', 'zippy-crm' ),
				$voucher['title'],
				$value
			),
			$voucher
		);
	}

	private static function render_template( \WP_User $user, array $voucher ): string {
		$claim_url       = wc_get_account_endpoint_url( 'crm-vouchers' );
		$unsubscribe_url = wc_get_account_endpoint_url( 'crm-notifications' );

		ob_start();
		// Template variables consumed inside the include — declared here so a
		// linter can see what the template gets (and grep finds them).
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$site_name = get_bloginfo( 'name' );

		include ZIPPY_CRM_DIR . 'src/Views/emails/voucher-notification.php';
		$html = (string) ob_get_clean();

		return (string) apply_filters(
			'crm_voucher_notification_content',
			$html,
			$voucher,
			$user->ID
		);
	}

	private static function query_unsent_subscribers( int $voucher_id, int $limit, int $offset ): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'notifications/select_unsent_subscribers.sql' );
		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $voucher_id, $voucher_id, $limit, $offset ) );
		$user_ids = array_map( 'intval', $rows ?: [] );

		if ( empty( $user_ids ) ) {
			return $user_ids;
		}

		// audience_mode='email' rows are returned unfiltered by the SQL — apply
		// the email-list match in PHP. Public + tier modes are already filtered
		// at the DB layer.
		$voucher = Voucher::find( $voucher_id );
		if ( ! $voucher || ( $voucher['audience_mode'] ?? 'public' ) !== 'email' ) {
			return $user_ids;
		}

		$entries = json_decode( (string) ( $voucher['email_restrictions'] ?? '' ), true );
		if ( ! is_array( $entries ) ) {
			// audience_mode='email' but no list = nobody qualifies. Defensive
			// guard; the validator should have caught this on create.
			return [];
		}
		$allowed = [];
		foreach ( $entries as $entry ) {
			$email = is_array( $entry ) ? (string) ( $entry['email'] ?? '' ) : (string) $entry;
			$email = strtolower( trim( $email ) );
			if ( $email !== '' ) {
				$allowed[ $email ] = true;
			}
		}

		$kept = [];
		foreach ( $user_ids as $uid ) {
			$user = get_user_by( 'id', $uid );
			if ( ! $user ) {
				continue;
			}
			if ( isset( $allowed[ strtolower( $user->user_email ) ] ) ) {
				$kept[] = $uid;
			}
		}
		return $kept;
	}

	private static function batch_size(): int {
		return defined( 'ZIPPY_CRM_EMAIL_BATCH_SIZE' )
			? (int) ZIPPY_CRM_EMAIL_BATCH_SIZE
			: self::BATCH_SIZE_FALLBACK;
	}

	public static function batch_interval(): int {
		return defined( 'ZIPPY_CRM_EMAIL_BATCH_INTERVAL' )
			? (int) ZIPPY_CRM_EMAIL_BATCH_INTERVAL
			: self::BATCH_INTERVAL_FALLBACK;
	}
}
