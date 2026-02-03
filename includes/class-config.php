<?php
/**
 * Configuration handler for iDrivee2 Media Upload.
 *
 * @package iDrivee2Media
 * @since   0.3.0
 */

declare(strict_types=1);
namespace iDrivee2Media;

/**
 * Prevent direct access to this file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configuration validator and accessor for iDrivee2 constants.
 *
 * Validates and provides access to the five required configuration constants
 * and the optional CDN domain constant.
 *
 * @since 0.3.0
 */
class Config {
	/**
	 * Required configuration constants.
	 *
	 * @var array<string>
	 */
	private const REQUIRED_CONSTANTS = array(
		'IDRIVEE2_MEDIA_HOST',
		'IDRIVEE2_MEDIA_KEY',
		'IDRIVEE2_MEDIA_SECRET',
		'IDRIVEE2_MEDIA_BUCKET',
		'IDRIVEE2_MEDIA_REGION',
	);

	/**
	 * Check if all required constants are defined.
	 *
	 * @since 0.3.0
	 *
	 * @return bool True if all required constants are defined, false otherwise.
	 */
	public function is_configured(): bool {
		foreach ( self::REQUIRED_CONSTANTS as $constant ) {
			if ( ! defined( $constant ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the S3 endpoint host URL.
	 *
	 * @since 0.3.0
	 *
	 * @return string The S3 endpoint URL.
	 */
	public function get_host(): string {
		return defined( 'IDRIVEE2_MEDIA_HOST' ) ? IDRIVEE2_MEDIA_HOST : '';
	}

	/**
	 * Get the S3 access key ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string The access key ID.
	 */
	public function get_key(): string {
		return defined( 'IDRIVEE2_MEDIA_KEY' ) ? IDRIVEE2_MEDIA_KEY : '';
	}

	/**
	 * Get the S3 secret access key.
	 *
	 * @since 0.3.0
	 *
	 * @return string The secret access key.
	 */
	public function get_secret(): string {
		return defined( 'IDRIVEE2_MEDIA_SECRET' ) ? IDRIVEE2_MEDIA_SECRET : '';
	}

	/**
	 * Get the S3 bucket name.
	 *
	 * @since 0.3.0
	 *
	 * @return string The bucket name.
	 */
	public function get_bucket(): string {
		return defined( 'IDRIVEE2_MEDIA_BUCKET' ) ? IDRIVEE2_MEDIA_BUCKET : '';
	}

	/**
	 * Get the AWS region.
	 *
	 * @since 0.3.0
	 *
	 * @return string The AWS region.
	 */
	public function get_region(): string {
		return defined( 'IDRIVEE2_MEDIA_REGION' ) ? IDRIVEE2_MEDIA_REGION : '';
	}

	/**
	 * Get the custom CDN domain, if defined.
	 *
	 * @since 0.3.0
	 *
	 * @return string The custom domain, or empty string if not defined.
	 */
	public function get_domain(): string {
		return defined( 'IDRIVEE2_MEDIA_DOMAIN' ) ? IDRIVEE2_MEDIA_DOMAIN : '';
	}

	/**
	 * Check if a custom CDN domain is defined.
	 *
	 * @since 0.3.0
	 *
	 * @return bool True if domain is defined and non-empty.
	 */
	public function has_domain(): bool {
		return defined( 'IDRIVEE2_MEDIA_DOMAIN' ) && ! empty( IDRIVEE2_MEDIA_DOMAIN );
	}
}
