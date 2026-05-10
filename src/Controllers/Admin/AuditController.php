<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class AuditController {
	public static function render(): void {
		echo '<div id="zippy-crm-admin-audit" class="wrap"></div>';
	}
}
