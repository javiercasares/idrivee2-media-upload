<?php
/**
 * PHPUnit bootstrap file for iDrivee2 Media Upload.
 *
 * @package iDrivee2Media
 * @since   0.3.0
 */

declare(strict_types=1);

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'IDRIVEE2_MEDIA_HOST' ) ) {
	define( 'IDRIVEE2_MEDIA_HOST', 'https://test-s3.example.com' );
}

if ( ! defined( 'IDRIVEE2_MEDIA_KEY' ) ) {
	define( 'IDRIVEE2_MEDIA_KEY', 'TEST_ACCESS_KEY' );
}

if ( ! defined( 'IDRIVEE2_MEDIA_SECRET' ) ) {
	define( 'IDRIVEE2_MEDIA_SECRET', 'TEST_SECRET_KEY' );
}

if ( ! defined( 'IDRIVEE2_MEDIA_BUCKET' ) ) {
	define( 'IDRIVEE2_MEDIA_BUCKET', 'test-bucket' );
}

if ( ! defined( 'IDRIVEE2_MEDIA_REGION' ) ) {
	define( 'IDRIVEE2_MEDIA_REGION', 'us-east-1' );
}

if ( ! defined( 'IDRIVEE2_MEDIA_DOMAIN' ) ) {
	define( 'IDRIVEE2_MEDIA_DOMAIN', 'https://cdn.example.com' );
}

// Load plugin classes.
require_once dirname( __DIR__ ) . '/includes/class-config.php';
require_once dirname( __DIR__ ) . '/includes/class-s3-client-factory.php';
require_once dirname( __DIR__ ) . '/includes/class-url-rewriter.php';
require_once dirname( __DIR__ ) . '/includes/class-media-uploader.php';
require_once dirname( __DIR__ ) . '/includes/class-admin-page.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';

echo "PHPUnit bootstrap loaded.\n";
