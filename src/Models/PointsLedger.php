<?php
namespace ZippyCrm\Models;

defined( 'ABSPATH' ) || exit;

final class PointsLedger {
	public const TABLE = 'crm_points_ledger';

	// Append-only. Never UPDATE or DELETE rows.
	// TODO: insert(), get_for_user(), get_paginated().
}
