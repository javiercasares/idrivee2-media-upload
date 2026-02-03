<?php
/**
 * Plugin Name:       iDrivee2 Media Upload
 * Plugin URI:        https://github.com/javiercasares/idrivee2-media-upload
 * Description:       Uploads media files to iDrivee2 (S3-compatible) with enterprise-grade security and logging.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Javier Casares
 * Author URI:        https://www.javiercasares.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       idrivee2-media-upload
 * Domain Path:       /languages
 * Network:           true
 *
 * @package iDrivee2Media
 */

declare(strict_types=1);
namespace iDrivee2Media;

/**
 * Prevent direct access to this file.
 *
 * If this file is called directly, abort execution for security.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load Composer autoloader if available.
 *
 * @since 0.1.13
 */
$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

/**
 * Load plugin classes.
 *
 * @since 0.3.0
 */
require_once __DIR__ . '/includes/class-logger.php';
require_once __DIR__ . '/includes/class-rate-limiter.php';
require_once __DIR__ . '/includes/class-config.php';
require_once __DIR__ . '/includes/class-s3-client-factory.php';
require_once __DIR__ . '/includes/class-url-rewriter.php';
require_once __DIR__ . '/includes/class-media-uploader.php';
require_once __DIR__ . '/includes/class-admin-page.php';
require_once __DIR__ . '/includes/class-plugin.php';

/**
 * Bootstrap the plugin.
 *
 * @since 0.3.0
 */
Plugin::get_instance( __FILE__ )->init();
