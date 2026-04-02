<?php
/**
 * WP Wikipedia Fact-Check uninstall handler.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin.
 *
 * @package WP_Wikipedia_Factcheck
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'wp_wikipedia_factcheck_username' );
delete_option( 'wp_wikipedia_factcheck_password' );
delete_option( 'wp_wikipedia_factcheck_language' );

// Delete all plugin transients.
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpwfc_%' OR option_name LIKE '_transient_timeout_wpwfc_%'"
);
