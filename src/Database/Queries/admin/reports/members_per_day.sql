-- Returns: count of new memberships per day in [from, to].
-- Day bucket = DATE(joined_at) interpreted as UTC (joined_at is stored UTC).
-- Caller is responsible for filling missing days with zeros (PHP-side).
-- Args: from (%s, 'Y-m-d 00:00:00'), to (%s, 'Y-m-d 23:59:59').
SELECT DATE(joined_at) AS day,
       COUNT(*)        AS total
FROM   {prefix}crm_memberships
WHERE  joined_at BETWEEN %s AND %s
GROUP  BY DATE(joined_at)
ORDER  BY day
