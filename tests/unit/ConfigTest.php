<?php
/**
 * Tests for Config class.
 *
 * @package iDrivee2Media
 * @since   0.3.0
 */

declare(strict_types=1);

namespace iDrivee2Media\Tests\Unit;

use iDrivee2Media\Config;
use PHPUnit\Framework\TestCase;

/**
 * Config test case.
 *
 * @since 0.3.0
 */
final class ConfigTest extends TestCase {
	/**
	 * Config instance.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = new Config();
	}

	/**
	 * Test that is_configured returns true when all constants are defined.
	 *
	 * @return void
	 */
	public function test_is_configured_returns_true_when_all_constants_defined(): void {
		$this->assertTrue( $this->config->is_configured() );
	}

	/**
	 * Test that get_host returns the correct value.
	 *
	 * @return void
	 */
	public function test_get_host_returns_correct_value(): void {
		$this->assertEquals( 'https://test-s3.example.com', $this->config->get_host() );
	}

	/**
	 * Test that get_key returns the correct value.
	 *
	 * @return void
	 */
	public function test_get_key_returns_correct_value(): void {
		$this->assertEquals( 'TEST_ACCESS_KEY', $this->config->get_key() );
	}

	/**
	 * Test that get_secret returns the correct value.
	 *
	 * @return void
	 */
	public function test_get_secret_returns_correct_value(): void {
		$this->assertEquals( 'TEST_SECRET_KEY', $this->config->get_secret() );
	}

	/**
	 * Test that get_bucket returns the correct value.
	 *
	 * @return void
	 */
	public function test_get_bucket_returns_correct_value(): void {
		$this->assertEquals( 'test-bucket', $this->config->get_bucket() );
	}

	/**
	 * Test that get_region returns the correct value.
	 *
	 * @return void
	 */
	public function test_get_region_returns_correct_value(): void {
		$this->assertEquals( 'us-east-1', $this->config->get_region() );
	}

	/**
	 * Test that get_domain returns the correct value.
	 *
	 * @return void
	 */
	public function test_get_domain_returns_correct_value(): void {
		$this->assertEquals( 'https://cdn.example.com', $this->config->get_domain() );
	}

	/**
	 * Test that has_domain returns true when domain is defined.
	 *
	 * @return void
	 */
	public function test_has_domain_returns_true_when_domain_defined(): void {
		$this->assertTrue( $this->config->has_domain() );
	}
}
