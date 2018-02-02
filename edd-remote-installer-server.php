<?php
/**
 * Plugin Name: EDD Remote Installer Server
 * Plugin URI: https://presscodes.com
 * Author: Aristeides Stathopoulos
 * Author URI: http://aristath.github.io
 * Version: 1.0
 * Text Domain: eddri-server
 *
 * @package EDD Remote Installer Server
 * @category Core
 * @author Aristeides Stathopoulos
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EDD_RI' ) ) {
	require( dirname( __FILE__ ) . '/inc/class-edd-ri.php' );
}
new EDD_RI();
