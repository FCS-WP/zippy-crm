<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class VouchersController {
	public static function render(): void {
		echo '<div id="zippy-crm-admin-vouchers" class="wrap"></div>';
	}
}
