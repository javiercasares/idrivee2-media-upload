<?php
/**
 * S3 Client Factory for iDrivee2 Media Upload.
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
 * Factory for creating configured AWS S3 clients.
 *
 * Creates S3Client instances with the proper configuration from the Config class.
 *
 * @since 0.3.0
 */
class S3_Client_Factory {
	/**
	 * Configuration instance.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Config $config Configuration instance.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Create and return a configured S3Client instance.
	 *
	 * @since 0.3.0
	 *
	 * @return \Aws\S3\S3Client The configured S3 client.
	 */
	public function create(): \Aws\S3\S3Client {
		return new \Aws\S3\S3Client(
			array(
				'version'                 => 'latest',
				'region'                  => $this->config->get_region(),
				'endpoint'                => $this->config->get_host(),
				'use_path_style_endpoint' => true,
				'credentials'             => array(
					'key'    => $this->config->get_key(),
					'secret' => $this->config->get_secret(),
				),
			)
		);
	}
}
