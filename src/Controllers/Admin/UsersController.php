<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class UsersController {
	public static function render(): void {
		// Wrap the React mount in a `.wrap` div but DON'T put `.wrap` on the
		// mount itself. wp-admin and JQMIGRATE inject admin-notice nodes into
		// `.wrap` automatically — when those land inside our React root, the
		// reconciler sees children it didn't render and removeChild crashes
		// during the first commit. The outer .wrap absorbs those injections.
		echo '<div class="wrap"><div id="zippy-crm-admin-users"></div></div>';
	}
}
