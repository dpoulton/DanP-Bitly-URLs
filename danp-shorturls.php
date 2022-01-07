<?php

/**
  * Plugin Name:        DanP Bitly URLs
  * Plugin URI:         https://github.com/dpoulton/danp-bitly-urls
  * Description:        Automatically generate short Bitly URLs for your WordPress posts and pages.
  * Version:            1.0.0
  * Author:             Dan Poulton
  * Author URI:         https://dan-p.net
  * License:            GPL v2 or later
  * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
  * Requires at least:  5.2
  * Requires PHP:       7.2
  * Domain:             danpurls
  */

// Prevent file being called directly
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define version
define( 'PLUGIN_NAME_VERSION', '1.0.0' );

// Include the plugin class
require plugin_dir_path( __FILE__ ) . '/class-danp-shorturls.php';

// New instance of plugin class
$danp_shorturls_class = new danp_shorturls();

// Return Bitly URL if it exists, else do nothing
add_filter('pre_get_shortlink', 'danp_shortlink_filter', 10, 5 );
function danp_shortlink_filter($status, $id, $context, $allow_slugs ){
	// If ID is zero, use get_queried_object_id
	if($id === 0) {
		$id = get_queried_object_id();
	}
	// Only try if ID is above zero
	if($id > 0) {
		// If the PHP class isn't available
		if(!isset($danp_shorturls_class)) {
			$danp_shorturls_class = new danp_shorturls();
		}
		// Attempt to retrieve the shortlink from the WordPress database using the post ID
		$shortlink = $danp_shorturls_class->get_shortlink($id);
		// $shortlink is false on failure, only return if not false
		if ($shortlink !== false) {
			return $shortlink;
		}
	}
	// Failed: return WordPress default shortlink
	return $status;
}

// Include admin code (not available from public site)
if ( is_admin() ) {
  require_once plugin_dir_path( __FILE__ ) . '/danp-shorturls-admin.php';
}

?>
