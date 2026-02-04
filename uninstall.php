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
 * This removes all custom post meta, options, and scheduled cron events
 * created by the plugin.
 *
 * WARNING: This action cannot be undone.
 */

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Delete all _idrivee2_last_upload post meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key = '_idrivee2_last_upload'"
);

// Delete all _idrivee2_s3_base_url post meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key = '_idrivee2_s3_base_url'"
);

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Delete the deletion queue option.
delete_option( 'idrivee2_deletion_queue' );

// Unschedule the cleanup cron event.
$timestamp = wp_next_scheduled( 'idrivee2_cleanup_local_files' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'idrivee2_cleanup_local_files' );
}

// Clear all hooks for this action to prevent any remaining schedules.
wp_clear_scheduled_hook( 'idrivee2_cleanup_local_files' );

/**
 * Note: Files uploaded to S3 are NOT deleted by this uninstall script.
 * If you want to delete files from S3, you must do so manually using
 * your S3 management console or AWS CLI.
 *
 * Local files in wp-content/uploads/ are also NOT deleted, as they are
 * part of WordPress's standard media library structure.
 */
