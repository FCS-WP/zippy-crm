<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class MembersController {
	public static function render(): void {
		echo '<div id="zippy-crm-admin-members" class="wrap"></div>';
	}
}
