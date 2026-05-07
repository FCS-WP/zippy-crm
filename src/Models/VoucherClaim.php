<?php
namespace ZippyCrm\Models;

defined( 'ABSPATH' ) || exit;

final class VoucherClaim {
	public const TABLE = 'crm_voucher_claims';

	// UNIQUE (voucher_id, user_id) — re-claim attempts must surface as an error.
	// TODO: claim(), find_for_user(), mark_used().
}
