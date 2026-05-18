<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class TiersController {
	public static function render(): void {
		// See UsersController for why .wrap goes on the outer div, not the mount.
		echo '<div class="wrap"><div id="zippy-crm-admin-tiers"></div></div>';
	}
}
