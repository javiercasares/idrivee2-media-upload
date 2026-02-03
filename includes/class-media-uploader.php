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
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Config            $config         Configuration instance.
	 * @param S3_Client_Factory $client_factory S3 client factory.
	 */
	public function __construct( Config $config, S3_Client_Factory $client_factory ) {
		$this->config         = $config;
		$this->client_factory = $client_factory;
	}

	/**
	 * Register hooks for media upload handling.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'upload_attachment_to_idrivee2' ), 10, 2 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'upload_attachment_to_idrivee2' ), 10, 2 );
		add_action( 'edit_attachment', array( $this, 'handle_edit_attachment' ) );
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
	 * @param array $meta           Attachment metadata, including 'file' and 'sizes'.
	 * @param int   $attachment_id  Attachment post ID.
	 * @return array                Unchanged metadata array.
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

		$object_url = '';

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
				continue;
			}

			// Determine S3 object key.
			$object_key = ( 'original' === $key )
				? $meta['file']
				: dirname( $meta['file'] ) . '/' . $key;

			// Retrieve file contents via WP_Filesystem.
			$content = $wp_filesystem->get_contents( $local_path );
			if ( false === $content ) {
				// Skip this file if it can't be read.
				continue;
			}

			// Upload to S3 from memory.
			$result = $client->putObject(
				array(
					'Bucket' => $this->config->get_bucket(),
					'Key'    => $object_key,
					'Body'   => $content,
					'ACL'    => 'public-read',
				)
			);

			// Capture the ObjectURL for the original image.
			if ( 'original' === $key && ! empty( $result['ObjectURL'] ) ) {
				$object_url = $result['ObjectURL'];
			}

			// Delete local file via WP_Filesystem.
			$wp_filesystem->delete( $local_path );
		}

		// Preserve relative path in database.
		update_post_meta( $attachment_id, '_wp_attached_file', $meta['file'] );

		if ( $object_url ) {
			// Update the GUID in wp_posts to the S3 URL.
			wp_update_post(
				array(
					'ID'   => $attachment_id,
					'guid' => $object_url,
				)
			);

			// Override front-end URL to use the S3 ObjectURL.
			add_filter(
				'wp_get_attachment_url',
				function ( string $url, int $id ) use ( $object_url, $attachment_id ): string {
					return ( $id === $attachment_id ) ? $object_url : $url;
				},
				10,
				2
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
}
