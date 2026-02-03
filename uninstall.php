<?php
/**
 * Uninstall script for iDrivee2 Media Upload.
 *
 * Runs when the plugin is uninstalled. Cleans up any plugin data if needed.
 *
 * @package iDrivee2Media
 * @since   0.3.0
 */

declare(strict_types=1);

/**
 * Prevent direct access to this file.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data.
 *
 * Note: This plugin does not create any custom database tables or options.
 * All data is stored in standard WordPress post meta and attachment metadata.
 *
 * If you want to delete all attachment metadata created by this plugin,
 * uncomment the code below. WARNING: This cannot be undone.
 */

// phpcs:disable Squiz.PHP.CommentedOutCode.Found

/*
// Delete all _wp_attached_file meta that was preserved by this plugin.
// This is optional as WordPress manages this meta normally.

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key = '_wp_attached_file'
	AND post_id IN (
		SELECT ID FROM {$wpdb->posts}
		WHERE post_type = 'attachment'
	)"
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
*/

/**
 * Note: Files uploaded to S3 are NOT deleted by this uninstall script.
 * If you want to delete files from S3, you must do so manually using
 * your S3 management console or AWS CLI.
 */
