-- Returns: codes for one voucher with optional status filter, joined with
-- the assigned user. Used by the admin Codes drawer.
-- Sentinel: '__all__' for status means "any status".
-- Index used: idx_voucher_status.
-- Args: status_token (%s ×2), voucher_id (%d), limit (%d), offset (%d).
SELECT vc.id, vc.code, vc.status, vc.assigned_to_user, vc.assigned_to_email,
       vc.assigned_at, vc.used_at, vc.order_id, vc.created_at,
       u.user_login, u.user_email, u.display_name
FROM   {prefix}crm_voucher_codes vc
LEFT   JOIN {prefix}users u ON u.ID = vc.assigned_to_user
WHERE  vc.voucher_id = %d
  AND  ( %s = '__all__' OR vc.status = %s )
ORDER  BY vc.id ASC
LIMIT  %d OFFSET %d
