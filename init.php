<?php
/*
Plugin Name: Blowhorn Logistics Same Day Delivery
Description: Blowhorn Shipment Logistics Management
Version: 1.0.0
Author: Blowhorn
Author URI: https://blowhorn.com/
License: GPLv2
@author Blowhorn
@package Blowhorn Logistics
@version 1.0.0
*/

/*

Copyright (C) 2021  Blowhorn (email : tech@blowhorn.net)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

function blsdd_install_woocommerce_admin_notice() {
	?>
	<div class="error">
		<p><?php _e( 'Blowhorn Logistics Same Day Delivery is enabled but not effective. It requires Woocommerce in order to work.', 'yit' ); ?></p>
	</div>
<?php
}

register_activation_hook( __FILE__, 'blsdd_plugin_registration_hook' );

//region    ****    Define constants
if ( ! defined( 'blsddFreeInit' ) ) {
	define( 'blsddFreeInit', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'blsddVersion' ) ) {
	define( 'blsddVersion', '1.0.0' );
}

if ( ! defined( 'blsddFile' ) ) {
	define( 'blsddFile', __FILE__ );
}

if ( ! defined( 'blsddDir' ) ) {
	define( 'blsddDir', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'blsddUrl' ) ) {
	define( 'blsddUrl', plugins_url( '/', __FILE__ ) );
}

if ( ! defined( 'blsddAssetsUrl' ) ) {
	define( 'blsddAssetsUrl', blsddUrl . 'assets' );
}

if ( ! defined( 'blsddTemplatePath' ) ) {
	define( 'blsddTempaltePath', blsddDir . 'templates' );
}

if ( ! defined( 'blsddAssetsImagesUrl' ) ) {
	define( 'blsddAssetsImagesUrl', blsddAssetsUrl . '/images/' );
}
//endregion

function blsdd_init() {
	// Load required classes and functions
	require_once( blsddDir . 'class.bh-shipments.php' );

	global $wbt_Instance;
	$wbt_Instance = new BLSDD_Tracking();
}

add_action( 'blsdd_init', 'blsdd_init' );


global $jal_db_version;
$jal_db_version = '1.0';


function blsdd_install() {

	if ( ! function_exists( 'WC' ) ) {
		add_action( 'admin_notices', 'blsdd_install_woocommerce_admin_notice' );
	} else {
		do_action( 'blsdd_init' );
	}
	}

	global $wpdb;
	global $jal_db_version;

	$table_name = $wpdb->prefix . 'bh_logistics';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
						  `api_key` varchar(255) NOT NULL,
						  `auto_push` boolean,
						  `validate_pincode` boolean
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'jal_db_version', $jal_db_version );

	add_action( 'plugins_loaded', 'blsdd_install', 11 );
?>
