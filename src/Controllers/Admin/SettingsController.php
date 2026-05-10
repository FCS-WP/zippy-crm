<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class SettingsController {
	public static function render(): void {
		echo '<div id="zippy-crm-admin-settings" class="wrap"></div>';
	}
}
