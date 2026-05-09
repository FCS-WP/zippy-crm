-- Returns: failed log rows with attempts < RETRY_MAX, eligible for the hourly
-- retry cron. Capped per-run so a giant backlog doesn't block the cron.
--
-- Index used: idx_status_attempts (status, attempts).
-- Args: max_attempts (%d), limit (%d).
SELECT id, voucher_id, user_id, attempts
FROM   {prefix}crm_notification_log
WHERE  status   = 'failed'
  AND  attempts < %d
ORDER  BY id ASC
LIMIT  %d
