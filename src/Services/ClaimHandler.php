<?php
namespace ZippyCrm\Services;

defined( 'ABSPATH' ) || exit;

final class ClaimHandler {
	// Validation order (per spec):
	//   1. voucher exists and status = active
	//   2. expires_at not passed
	//   3. uses_count < max_uses
	//   4. user has not already claimed
	//   5. membership status = active
	// TODO: validate(), claim(), expire_old_claims().
}
