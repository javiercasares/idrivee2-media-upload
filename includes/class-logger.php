<?php
/**
 * Security Logger for iDrivee2 Media Upload.
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
 * Security and operational logger.
 *
 * Logs security events, configuration changes, S3 operations, and errors
 * using WordPress debug.log when WP_DEBUG_LOG is enabled.
 *
 * @since 0.3.1
 */
class Logger {
	/**
	 * Log levels.
	 */
	private const LEVEL_INFO     = 'INFO';
	private const LEVEL_WARNING  = 'WARNING';
	private const LEVEL_ERROR    = 'ERROR';
	private const LEVEL_SECURITY = 'SECURITY';

	/**
	 * Check if logging is enabled.
	 *
	 * @since 0.3.1
	 *
	 * @return bool True if WP_DEBUG_LOG is enabled.
	 */
	private function is_enabled(): bool {
		return defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}

	/**
	 * Write a log entry.
	 *
	 * @since 0.3.1
	 *
	 * @param string               $level   Log level (INFO, WARNING, ERROR, SECURITY).
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$user_id   = get_current_user_id();
		$user_info = $user_id ? get_userdata( $user_id ) : null;
		$username  = $user_info ? $user_info->user_login : 'anonymous';

		$log_entry = sprintf(
			'[iDrivee2] [%s] [User: %s] %s',
			$level,
			$username,
			$message
		);

		// Add context if provided.
		if ( ! empty( $context ) ) {
			$log_entry .= ' | Context: ' . wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $log_entry );
	}

	/**
	 * Log an info message.
	 *
	 * @since 0.3.1
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @since 0.3.1
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @since 0.3.1
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a security event.
	 *
	 * @since 0.3.1
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function security( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_SECURITY, $message, $context );
	}

	/**
	 * Log a configuration change.
	 *
	 * @since 0.3.1
	 *
	 * @param string $field   Configuration field that changed.
	 * @param string $old_value Old value (masked if sensitive).
	 * @param string $new_value New value (masked if sensitive).
	 * @return void
	 */
	public function config_change( string $field, string $old_value, string $new_value ): void {
		// Mask sensitive fields.
		$sensitive_fields = array( 'secret', 'key' );
		if ( in_array( $field, $sensitive_fields, true ) ) {
			$old_value = $this->mask_value( $old_value );
			$new_value = $this->mask_value( $new_value );
		}

		$this->security(
			sprintf( 'Configuration changed: %s', $field ),
			array(
				'field'     => $field,
				'old_value' => $old_value,
				'new_value' => $new_value,
			)
		);
	}

	/**
	 * Log an S3 operation.
	 *
	 * @since 0.3.1
	 *
	 * @param string $operation Operation type (upload, delete, headBucket, etc.).
	 * @param bool   $success   Whether the operation succeeded.
	 * @param string $file_name Optional file name.
	 * @param string $error     Optional error message.
	 * @return void
	 */
	public function s3_operation( string $operation, bool $success, string $file_name = '', string $error = '' ): void {
		$level = $success ? self::LEVEL_INFO : self::LEVEL_ERROR;

		$message = sprintf(
			'S3 %s %s',
			$operation,
			$success ? 'succeeded' : 'failed'
		);

		$context = array(
			'operation' => $operation,
			'success'   => $success,
		);

		if ( $file_name ) {
			$context['file'] = $file_name;
		}

		if ( $error ) {
			$context['error'] = $error;
		}

		$this->log( $level, $message, $context );

		// Track operation count.
		$this->track_s3_operation( $operation );
	}

	/**
	 * Log an authentication failure.
	 *
	 * @since 0.3.1
	 *
	 * @param string $reason Reason for authentication failure.
	 * @return void
	 */
	public function auth_failure( string $reason ): void {
		$this->security(
			'Authentication failure: ' . $reason,
			array(
				'ip'         => $this->get_client_ip(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			)
		);
	}

	/**
	 * Log a rate limit violation.
	 *
	 * @since 0.3.1
	 *
	 * @param string $action Action that was rate limited.
	 * @return void
	 */
	public function rate_limit_exceeded( string $action ): void {
		$this->security(
			'Rate limit exceeded: ' . $action,
			array(
				'action' => $action,
				'ip'     => $this->get_client_ip(),
			)
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since 0.3.1
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$ip = '';

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Mask a sensitive value.
	 *
	 * @since 0.3.1
	 *
	 * @param string $value Value to mask.
	 * @return string Masked value.
	 */
	private function mask_value( string $value ): string {
		$length = strlen( $value );

		if ( $length <= 4 ) {
			return str_repeat( '*', $length );
		}

		// Show first 2 and last 2 characters.
		return substr( $value, 0, 2 ) . str_repeat( '*', $length - 4 ) . substr( $value, -2 );
	}

	/**
	 * Track S3 operation count.
	 *
	 * @since 0.3.1
	 *
	 * @param string $operation Operation type.
	 * @return void
	 */
	private function track_s3_operation( string $operation ): void {
		$option_key = 'idrivee2_s3_operations';
		$stats      = get_option( $option_key, array() );

		// Initialize stats for today if not exists.
		$today = gmdate( 'Y-m-d' );
		if ( ! isset( $stats[ $today ] ) ) {
			$stats[ $today ] = array();
		}

		if ( ! isset( $stats[ $today ][ $operation ] ) ) {
			$stats[ $today ][ $operation ] = 0;
		}

		++$stats[ $today ][ $operation ];

		// Keep only last 30 days.
		$cutoff_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		foreach ( array_keys( $stats ) as $date ) {
			if ( $date < $cutoff_date ) {
				unset( $stats[ $date ] );
			}
		}

		update_option( $option_key, $stats );
	}

	/**
	 * Get S3 operation statistics.
	 *
	 * @since 0.3.1
	 *
	 * @param int $days Number of days to retrieve (default 7, max 30).
	 * @return array<string, array<string, int>> Statistics array indexed by date and operation.
	 */
	public function get_s3_stats( int $days = 7 ): array {
		$days  = min( $days, 30 );
		$stats = get_option( 'idrivee2_s3_operations', array() );

		$result = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$timestamp = strtotime( "-$i days" );
			if ( false === $timestamp ) {
				continue;
			}
			$date = gmdate( 'Y-m-d', $timestamp );
			if ( isset( $stats[ $date ] ) ) {
				$result[ $date ] = $stats[ $date ];
			}
		}

		return $result;
	}
}
