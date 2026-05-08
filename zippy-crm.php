<?php
/**
 * Plugin Name: Zippy CRM
 * Plugin URI:  https://zippy-crm.com
 * Description: Membership, Points and Voucher CRM for WooCommerce.
 * Version:     1.0.0
 * Author:      Zippy Team
 * Author URI:  https://zippy-crm.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zippy-crm
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * WC requires at least: 8.0
 * WC tested up to:      9.4
 */

defined( 'ABSPATH' ) || exit;

define( 'ZIPPY_CRM_VERSION',  '1.4.0' );
define( 'ZIPPY_CRM_FILE',     __FILE__ );
define( 'ZIPPY_CRM_DIR',      plugin_dir_path( __FILE__ ) );
define( 'ZIPPY_CRM_URL',      plugin_dir_url( __FILE__ ) );
define( 'ZIPPY_CRM_BASENAME', plugin_basename( __FILE__ ) );

define( 'ZIPPY_CRM_REST_NAMESPACE',   'zippy-crm/v1' );
define( 'ZIPPY_CRM_POINTS_RATE',      20 );    // 20 points = $1
define( 'ZIPPY_CRM_MIN_REDEMPTION',   20 );
define( 'ZIPPY_CRM_EMAIL_BATCH_SIZE', 50 );

require_once ZIPPY_CRM_DIR . 'src/Core/Autoloader.php';
\ZippyCrm\Core\Autoloader::register();

require_once ZIPPY_CRM_DIR . 'src/Core/Plugin.php';

// Declare HPOS (Custom Order Tables) compatibility — required by WC 8+.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_activation_hook( __FILE__,   [ \ZippyCrm\Core\Plugin::class, 'on_activate' ] );
register_deactivation_hook( __FILE__, [ \ZippyCrm\Core\Plugin::class, 'on_deactivate' ] );

add_action( 'plugins_loaded', [ \ZippyCrm\Core\Plugin::class, 'boot' ] );
