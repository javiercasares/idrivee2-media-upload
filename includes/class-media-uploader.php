<?php
/**
 * Media Uploader for iDrivee2 Media Upload.
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
 * Media uploader for handling file uploads to iDrivee2.
 *
 * Uploads attachment files and all generated sizes to S3, deletes local copies,
 * and updates the attachment GUID to point to the S3 URL.
 *
 * @since 0.3.0
 */
class Media_Uploader {
	/**
	 * Configuration instance.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * S3 client factory.
	 *
	 * @var S3_Client_Factory
	 */
	private $client_factory;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Config            $config         Configuration instance.
	 * @param S3_Client_Factory $client_factory S3 client factory.
	 * @param Logger            $logger         Logger instance.
	 */
	public function __construct( Config $config, S3_Client_Factory $client_factory, Logger $logger ) {
		$this->config         = $config;
		$this->client_factory = $client_factory;
		$this->logger         = $logger;
	}

	/**
	 * Register hooks for media upload handling.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Use wp_update_attachment_metadata with high priority to ensure thumbnails are generated.
		// Priority 999 ensures this runs AFTER all thumbnail generation is complete.
		add_filter( 'wp_update_attachment_metadata', array( $this, 'upload_attachment_to_idrivee2' ), 999, 2 );
		add_action( 'edit_attachment', array( $this, 'handle_edit_attachment' ) );

		// Register cron job for cleaning up local files.
		add_action( 'idrivee2_cleanup_local_files', array( $this, 'cleanup_local_files' ) );

		// Schedule cron if not already scheduled.
		if ( ! wp_next_scheduled( 'idrivee2_cleanup_local_files' ) ) {
			wp_schedule_event( time(), 'every_five_minutes', 'idrivee2_cleanup_local_files' );
		}
	}

	/**
	 * Uploads attachment files to iDrivee2 after sizes are generated.
	 *
	 * This function pushes the original file and all generated sizes to the
	 * configured S3-compatible host, captures the returned ObjectURL for the
	 * original file, deletes the local copies, updates the attachment's GUID,
	 * and filters the front-end URL to use the S3 ObjectURL.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, mixed> $meta           Attachment metadata, including 'file' and 'sizes'.
	 * @param int                  $attachment_id  Attachment post ID.
	 * @return array<string, mixed>                Unchanged metadata array.
	 */
	public function upload_attachment_to_idrivee2( array $meta, int $attachment_id ): array {
		// Bail if configuration is incomplete.
		if ( ! $this->config->is_configured() ) {
			return $meta;
		}

		// Initialize the S3 client.
		$client = $this->client_factory->create();

		// Build list of files: original + each image size.
		$upload_dir = wp_upload_dir();
		$base_path  = path_join( $upload_dir['basedir'], $meta['file'] );
		$files      = array(
			'original' => $base_path,
		);

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				$files[ $size['file'] ] = path_join( dirname( $base_path ), $size['file'] );
			}
		}

		// Log file list for debugging.
		$this->logger->info(
			sprintf( 'Preparing to upload %d files to S3', count( $files ) ),
			array(
				'attachment_id' => $attachment_id,
				'original'      => basename( $meta['file'] ),
				'sizes_count'   => count( $meta['sizes'] ?? array() ),
			)
		);

		$object_url   = '';
		$s3_base_url  = '';
		$upload_count = 0;

		// Load and initialise WP_Filesystem.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		// Upload each file via WP_Filesystem.
		foreach ( $files as $key => $local_path ) {
			// Skip if file doesn't exist.
			if ( ! $wp_filesystem->exists( $local_path ) ) {
				$this->logger->warning(
					'File does not exist, skipping upload',
					array(
						'key'  => $key,
						'path' => $local_path,
					)
				);
				continue;
			}

			// Determine S3 object key.
			$object_key = ( 'original' === $key )
				? $meta['file']
				: dirname( $meta['file'] ) . '/' . $key;

			// Check if file already exists in S3.
			try {
				$exists = $client->headObject(
					array(
						'Bucket' => $this->config->get_bucket(),
						'Key'    => $object_key,
					)
				);
				// File exists, skip upload.
				$this->logger->info(
					sprintf( 'File already exists in S3, skipping: %s', basename( $object_key ) ),
					array( 'object_key' => $object_key )
				);
				continue;
			} catch ( \Aws\Exception\AwsException $e ) {
				// File doesn't exist (404), proceed with upload.
				if ( 404 !== $e->getStatusCode() ) {
					// Other error, log and skip.
					$this->logger->warning(
						sprintf( 'Error checking S3 file existence: %s', basename( $object_key ) ),
						array(
							'object_key' => $object_key,
							'error'      => $e->getAwsErrorMessage() ?? '',
						)
					);
					continue;
				}
			}

			// Retrieve file contents via WP_Filesystem.
			$content = $wp_filesystem->get_contents( $local_path );
			if ( false === $content ) {
				$this->logger->warning(
					'Failed to read file contents, skipping upload',
					array(
						'key'        => $key,
						'path'       => $local_path,
						'object_key' => $object_key,
					)
				);
				continue;
			}

			$this->logger->info(
				sprintf( 'Uploading to S3: %s', basename( $object_key ) ),
				array(
					'object_key'  => $object_key,
					'file_size'   => strlen( $content ),
					'is_original' => ( 'original' === $key ),
				)
			);

			// Upload to S3 from memory.
			try {
				$result = $client->putObject(
					array(
						'Bucket' => $this->config->get_bucket(),
						'Key'    => $object_key,
						'Body'   => $content,
						'ACL'    => 'public-read',
					)
				);

				// Log successful upload.
				$this->logger->s3_operation( 'putObject', true, basename( $object_key ) );

				// Capture the base URL for constructing CDN URLs.
				if ( 'original' === $key ) {
					// Build CDN URL if domain configured, otherwise use S3 URL.
					if ( $this->config->has_domain() ) {
						$s3_base_url = trailingslashit( $this->config->get_domain() ) . dirname( $meta['file'] );
					} elseif ( ! empty( $result['ObjectURL'] ) ) {
						$object_url  = $result['ObjectURL'];
						$s3_base_url = dirname( $result['ObjectURL'] );
					}
				}

				$upload_count++;

			} catch ( \Aws\Exception\AwsException $e ) {
				// Log failed upload.
				$this->logger->s3_operation( 'putObject', false, basename( $object_key ), $e->getAwsErrorMessage() ?? '' );
				// Continue to next file on error.
				continue;
			}
		}

		// Log upload summary.
		$this->logger->info(
			sprintf( 'Upload complete: %d of %d files uploaded to S3', $upload_count, count( $files ) ),
			array(
				'attachment_id' => $attachment_id,
				'uploaded'      => $upload_count,
				'expected'      => count( $files ),
			)
		);

		// Update metadata and schedule deletion if files were uploaded.
		if ( $upload_count > 0 ) {
			update_post_meta( $attachment_id, '_idrivee2_s3_base_url', $s3_base_url );
			update_post_meta( $attachment_id, '_idrivee2_last_upload', time() );

			// Schedule local files for deletion after 3 minutes.
			$this->schedule_files_for_deletion( array_values( $files ) );
		}

		// Preserve relative path in database.
		update_post_meta( $attachment_id, '_wp_attached_file', $meta['file'] );

		// Update GUID to use CDN URL if available, otherwise S3 URL.
		if ( $s3_base_url ) {
			$file_name = basename( $meta['file'] );
			if ( $this->config->has_domain() ) {
				// Use CDN domain.
				$public_url = trailingslashit( $this->config->get_domain() ) . $meta['file'];
			} elseif ( $object_url ) {
				// Use S3 ObjectURL.
				$public_url = $object_url;
			} else {
				// Fallback: construct from base URL.
				$public_url = $s3_base_url . '/' . $file_name;
			}

			// Update the GUID to the public URL.
			wp_update_post(
				array(
					'ID'   => $attachment_id,
					'guid' => $public_url,
				)
			);
		}

		return $meta;
	}

	/**
	 * Handle edit_attachment action as a fallback.
	 *
	 * @since 0.3.0
	 *
	 * @param int $post_id Attachment post ID.
	 * @return void
	 */
	public function handle_edit_attachment( int $post_id ): void {
		$meta = wp_get_attachment_metadata( $post_id );
		if ( $meta ) {
			$this->upload_attachment_to_idrivee2( $meta, $post_id );
		}
	}

	/**
	 * Schedule files for deletion.
	 *
	 * Adds files to a deletion queue with timestamp. Files will be deleted
	 * by the cron job after 3 minutes to give WordPress time to process.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string> $files Array of file paths to delete.
	 * @return void
	 */
	private function schedule_files_for_deletion( array $files ): void {
		$queue = get_option( 'idrivee2_deletion_queue', array() );

		foreach ( $files as $file_path ) {
			$queue[] = array(
				'path'      => $file_path,
				'timestamp' => time(),
			);
		}

		update_option( 'idrivee2_deletion_queue', $queue, false );
	}

	/**
	 * Clean up local files that were uploaded to S3.
	 *
	 * This method runs via WP-Cron every 5 minutes. It deletes files that:
	 * - Were uploaded to S3 successfully
	 * - Have been in the queue for at least 3 minutes
	 *
	 * The 3-minute delay ensures WordPress has time to display thumbnails
	 * in the admin before files are removed.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public function cleanup_local_files(): void {
		$queue = get_option( 'idrivee2_deletion_queue', array() );

		if ( empty( $queue ) ) {
			return;
		}

		// Load and initialise WP_Filesystem.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		$current_time = time();
		$new_queue    = array();
		$deleted      = 0;

		foreach ( $queue as $item ) {
			$file_path = $item['path'];
			$timestamp = $item['timestamp'];

			// Only delete files older than 3 minutes.
			if ( ( $current_time - $timestamp ) < 180 ) {
				$new_queue[] = $item;
				continue;
			}

			// Delete the file if it exists.
			if ( $wp_filesystem->exists( $file_path ) ) {
				$result = $wp_filesystem->delete( $file_path );
				if ( $result ) {
					$deleted++;
					$this->logger->info(
						'Local file deleted after S3 upload',
						array( 'path' => basename( $file_path ) )
					);
				} else {
					// Keep in queue to retry later.
					$new_queue[] = $item;
					$this->logger->warning(
						'Failed to delete local file, will retry',
						array( 'path' => $file_path )
					);
				}
			}
			// If file doesn't exist, consider it successfully cleaned up (don't re-add to queue).
		}

		// Update the queue.
		update_option( 'idrivee2_deletion_queue', $new_queue, false );

		// Log cleanup summary if any files were deleted.
		if ( $deleted > 0 ) {
			$this->logger->info(
				sprintf( 'Cleanup completed: %d files deleted', $deleted ),
				array( 'remaining' => count( $new_queue ) )
			);
		}
	}
}
