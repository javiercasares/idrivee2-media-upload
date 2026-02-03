<?php
/**
 * Rate Limiter for iDrivee2 Media Upload.
 *
 * @package iDrivee2Media
 * @since   0.3.1
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
 * Rate limiter for preventing abuse of test functions.
 *
 * Uses WordPress transients to track operation timestamps per user.
 *
 * @since 0.3.1
 */
class Rate_Limiter {
	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.3.1
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Check if an action is rate limited.
	 *
	 * @since 0.3.1
	 *
	 * @param string $action   Action identifier (e.g., 'test_connection', 'upload_test').
	 * @param int    $seconds  Minimum seconds between actions (default 60).
	 * @return bool True if rate limit exceeded, false if action is allowed.
	 */
	public function is_rate_limited( string $action, int $seconds = 60 ): bool {
		$user_id = get_current_user_id();

		// Users with manage_options capability have a more lenient rate limit (30 seconds instead of 60).
		if ( current_user_can( 'manage_options' ) ) {
			$seconds = max( 30, intval( $seconds / 2 ) );
		}

		$transient_key = $this->get_transient_key( $action, $user_id );
		$last_time     = get_transient( $transient_key );

		if ( false === $last_time ) {
			// No previous action, allow it.
			return false;
		}

		$time_since_last = time() - $last_time;

		if ( $time_since_last < $seconds ) {
			// Rate limit exceeded.
			$this->logger->rate_limit_exceeded( $action );
			return true;
		}

		return false;
	}

	/**
	 * Record an action timestamp.
	 *
	 * @since 0.3.1
	 *
	 * @param string $action  Action identifier.
	 * @param int    $seconds Transient expiration time (default 60).
	 * @return void
	 */
	public function record_action( string $action, int $seconds = 60 ): void {
		$user_id       = get_current_user_id();
		$transient_key = $this->get_transient_key( $action, $user_id );

		set_transient( $transient_key, time(), $seconds );
	}

	/**
	 * Get remaining time until action is allowed.
	 *
	 * @since 0.3.1
	 *
	 * @param string $action  Action identifier.
	 * @param int    $seconds Minimum seconds between actions.
	 * @return int Seconds remaining, or 0 if action is allowed.
	 */
	public function get_remaining_time( string $action, int $seconds = 60 ): int {
		$user_id = get_current_user_id();

		// Users with manage_options capability have a more lenient rate limit.
		if ( current_user_can( 'manage_options' ) ) {
			$seconds = max( 30, intval( $seconds / 2 ) );
		}

		$transient_key = $this->get_transient_key( $action, $user_id );
		$last_time     = get_transient( $transient_key );

		if ( false === $last_time ) {
			return 0;
		}

		$time_since_last = time() - $last_time;
		$remaining       = $seconds - $time_since_last;

		return (int) max( 0, $remaining );
	}

	/**
	 * Get transient key for user and action.
	 *
	 * @since 0.3.1
	 *
	 * @param string $action  Action identifier.
	 * @param int    $user_id User ID.
	 * @return string Transient key.
	 */
	private function get_transient_key( string $action, int $user_id ): string {
		return sprintf( 'idrivee2_ratelimit_%s_%d', $action, $user_id );
	}

	/**
	 * Clear rate limit for a specific action.
	 *
	 * @since 0.3.1
	 *
	 * @param string $action Action identifier.
	 * @return void
	 */
	public function clear_limit( string $action ): void {
		$user_id       = get_current_user_id();
		$transient_key = $this->get_transient_key( $action, $user_id );

		delete_transient( $transient_key );
	}
}
