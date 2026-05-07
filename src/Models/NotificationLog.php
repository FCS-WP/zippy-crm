<?php
namespace ZippyCrm\Models;

defined( 'ABSPATH' ) || exit;

final class NotificationLog {
	public const TABLE = 'crm_notification_log';

	// UNIQUE (voucher_id, user_id) — idempotency guard for batch sends.
	// TODO: log_sent(), log_failed(), find_failed_for_retry().
}
