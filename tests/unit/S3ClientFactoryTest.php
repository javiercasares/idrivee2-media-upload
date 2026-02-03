<?php
/**
 * Tests for S3_Client_Factory class.
 *
 * @package iDrivee2Media
 * @since   0.3.0
 */

declare(strict_types=1);

namespace iDrivee2Media\Tests\Unit;

use iDrivee2Media\Config;
use iDrivee2Media\S3_Client_Factory;
use PHPUnit\Framework\TestCase;

/**
 * S3_Client_Factory test case.
 *
 * @since 0.3.0
 */
final class S3ClientFactoryTest extends TestCase {
	/**
	 * Config instance.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * S3_Client_Factory instance.
	 *
	 * @var S3_Client_Factory
	 */
	private S3_Client_Factory $factory;

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config  = new Config();
		$this->factory = new S3_Client_Factory( $this->config );
	}

	/**
	 * Test that create returns an S3Client instance.
	 *
	 * @return void
	 */
	public function test_create_returns_s3_client_instance(): void {
		$client = $this->factory->create();
		$this->assertInstanceOf( \Aws\S3\S3Client::class, $client );
	}

	/**
	 * Test that created client has correct configuration.
	 *
	 * @return void
	 */
	public function test_created_client_has_correct_configuration(): void {
		$client = $this->factory->create();

		// Get client configuration.
		$config = $client->getConfig();

		$this->assertEquals( 'https://test-s3.example.com', $config['endpoint'] );
		$this->assertEquals( 'us-east-1', $config['region'] );
		$this->assertTrue( $config['use_path_style_endpoint'] );
	}
}
