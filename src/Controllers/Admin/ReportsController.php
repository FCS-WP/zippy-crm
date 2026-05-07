<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class ReportsController {
	public static function render(): void {
		echo '<div id="zippy-crm-admin-reports" class="wrap"></div>';
	}
}
