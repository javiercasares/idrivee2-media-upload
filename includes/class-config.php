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
 * and the optional CDN domain constant. Configuration can come from either
 * wp-config.php constants (priority) or WordPress options.
 *
 * @since 0.3.0
 */
class Config {
	/**
	 * Required configuration keys.
	 *
	 * @var array<string>
	 */
	private const REQUIRED_KEYS = array(
		'host',
		'key',
		'secret',
		'bucket',
		'region',
	);

	/**
	 * WordPress option name for settings.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'idrivee2_media_settings';

	/**
	 * Check if all required configuration values are available.
	 *
	 * Checks both wp-config.php constants and WordPress options.
	 *
	 * @since 0.3.0
	 *
	 * @return bool True if all required values are available, false otherwise.
	 */
	public function is_configured(): bool {
		foreach ( self::REQUIRED_KEYS as $key ) {
			$value = $this->get_value( $key );
			if ( empty( $value ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if a configuration key is defined in wp-config.php.
	 *
	 * @since 0.3.0
	 *
	 * @param string $key Configuration key (host, key, secret, bucket, region, domain).
	 * @return bool True if defined in wp-config.php, false otherwise.
	 */
	public function is_defined_in_wp_config( string $key ): bool {
		$constant_map = array(
			'host'   => 'IDRIVEE2_MEDIA_HOST',
			'key'    => 'IDRIVEE2_MEDIA_KEY',
			'secret' => 'IDRIVEE2_MEDIA_SECRET',
			'bucket' => 'IDRIVEE2_MEDIA_BUCKET',
			'region' => 'IDRIVEE2_MEDIA_REGION',
			'domain' => 'IDRIVEE2_MEDIA_DOMAIN',
		);

		$constant_name = $constant_map[ $key ] ?? '';
		return $constant_name && defined( $constant_name );
	}

	/**
	 * Get a configuration value.
	 *
	 * Priority: wp-config.php constants first, then WordPress options.
	 *
	 * @since 0.3.0
	 *
	 * @param string $key Configuration key (host, key, secret, bucket, region, domain).
	 * @return string The configuration value, or empty string if not set.
	 */
	private function get_value( string $key ): string {
		$constant_map = array(
			'host'   => 'IDRIVEE2_MEDIA_HOST',
			'key'    => 'IDRIVEE2_MEDIA_KEY',
			'secret' => 'IDRIVEE2_MEDIA_SECRET',
			'bucket' => 'IDRIVEE2_MEDIA_BUCKET',
			'region' => 'IDRIVEE2_MEDIA_REGION',
			'domain' => 'IDRIVEE2_MEDIA_DOMAIN',
		);

		// Check wp-config.php constant first (highest priority).
		$constant_name = $constant_map[ $key ] ?? '';
		if ( $constant_name && defined( $constant_name ) ) {
			return (string) constant( $constant_name );
		}

		// Fall back to WordPress option.
		$options = get_option( self::OPTION_NAME, array() );
		return isset( $options[ $key ] ) ? (string) $options[ $key ] : '';
	}

	/**
	 * Get the S3 endpoint host URL.
	 *
	 * @since 0.3.0
	 *
	 * @return string The S3 endpoint URL.
	 */
	public function get_host(): string {
		return $this->get_value( 'host' );
	}

	/**
	 * Get the S3 access key ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string The access key ID.
	 */
	public function get_key(): string {
		return $this->get_value( 'key' );
	}

	/**
	 * Get the S3 secret access key.
	 *
	 * @since 0.3.0
	 *
	 * @return string The secret access key.
	 */
	public function get_secret(): string {
		return $this->get_value( 'secret' );
	}

	/**
	 * Get the S3 bucket name.
	 *
	 * @since 0.3.0
	 *
	 * @return string The bucket name.
	 */
	public function get_bucket(): string {
		return $this->get_value( 'bucket' );
	}

	/**
	 * Get the AWS region.
	 *
	 * @since 0.3.0
	 *
	 * @return string The AWS region.
	 */
	public function get_region(): string {
		return $this->get_value( 'region' );
	}

	/**
	 * Get the custom CDN domain, if defined.
	 *
	 * @since 0.3.0
	 *
	 * @return string The custom domain, or empty string if not defined.
	 */
	public function get_domain(): string {
		return $this->get_value( 'domain' );
	}

	/**
	 * Check if a custom CDN domain is defined.
	 *
	 * @since 0.3.0
	 *
	 * @return bool True if domain is defined and non-empty.
	 */
	public function has_domain(): bool {
		return ! empty( $this->get_domain() );
	}

	/**
	 * Update configuration options in WordPress database.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, string> $data Configuration data to save.
	 * @return bool True on success, false on failure.
	 */
	public function update_options( array $data ): bool {
		// Validate and sanitize data.
		$options = array(
			'host'   => isset( $data['host'] ) ? sanitize_text_field( $data['host'] ) : '',
			'key'    => isset( $data['key'] ) ? sanitize_text_field( $data['key'] ) : '',
			'secret' => isset( $data['secret'] ) ? sanitize_text_field( $data['secret'] ) : '',
			'bucket' => isset( $data['bucket'] ) ? sanitize_text_field( $data['bucket'] ) : '',
			'region' => isset( $data['region'] ) ? sanitize_text_field( $data['region'] ) : '',
			'domain' => isset( $data['domain'] ) ? sanitize_text_field( $data['domain'] ) : '',
		);

		return update_option( self::OPTION_NAME, $options );
	}
}
