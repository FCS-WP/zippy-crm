<?php
namespace ZippyCrm\Models;

defined( 'ABSPATH' ) || exit;

final class PointsSummary {
	public const TABLE = 'crm_points_summary';

	// Cache. Must always equal SUM(points) FROM crm_points_ledger WHERE user_id = X.
	// TODO: get_balance(), upsert(), recalculate_from_ledger().
}
