<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class UsersController {
	public static function render(): void {
		echo '<div id="zippy-crm-admin-users" class="wrap"></div>';
	}
}
