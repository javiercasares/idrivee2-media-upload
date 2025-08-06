<?php
/**
 * Plugin Name:       iDrivee2 Media Upload
 * Plugin URI:        https://github.com/javiercasares/idrivee2-media-upload
 * Description:       Uploads media files to iDrivee2 (S3-compatible).
 * Version:           0.3.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Javier Casares
 * Author URI:        https://www.javiercasares.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       idrivee2-media-upload
 * Domain Path:       /languages
 *
 * @package iDrivee2Media
 */

declare(strict_types=1);
namespace iDrivee2Media;

/**
 * Prevent direct access to this file.
 *
 * If this file is called directly, abort execution for security.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load Composer autoloader if available.
 *
 * @since 0.1.13
 */
$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

/**
 * Load the plugin text domain for translations.
 *
 * Registers the plugin’s text domain so that translation files in the
 * /languages directory are loaded on the front end and in the admin.
 *
 * @since 0.1.13
 *
 * @return void
 */
function load_textdomain(): void {
	load_plugin_textdomain(
		'idrivee2-media-upload',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action(
	'plugins_loaded',
	__NAMESPACE__ . '\load_textdomain',
	20
);

/**
 * Register the iDrivee2 settings page under the Media menu.
 *
 * Adds a submenu page to the Media section of the admin where users
 * can view and test their iDrivee2 configuration.
 *
 * @since 0.1.13
 *
 * @return void
 */
function register_media_page(): void {
	add_media_page(
		/* translators: Admin menu title. */
		__( 'iDrivee2', 'idrivee2-media-upload' ),
		/* translators: Admin menu label. */
		__( 'iDrivee2', 'idrivee2-media-upload' ),
		'manage_options',
		'idrivee2-media-upload',
		__NAMESPACE__ . '\render_settings_page'
	);
}
add_action(
	'admin_menu',
	__NAMESPACE__ . '\register_media_page',
	20
);

/**
 * Enqueue the admin JavaScript and localize script data for AJAX.
 *
 * Loads the iDrivee2 admin script on the Media → iDrivee2 settings page
 * and passes dynamic data such as the AJAX URL, nonces, and button labels.
 *
 * @since 0.1.13
 *
 * @param string $hook The current admin page hook suffix.
 * @return void
 */
function enqueue_admin_scripts( string $hook ): void {
	// Only load script on our settings page.
	if ( 'media_page_idrivee2-media' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'idrivee2-media-admin',
		plugin_dir_url( __FILE__ ) . 'assets/admin.js',
		array( 'jquery' ),
		'0.1.13',
		true
	);

	wp_localize_script(
		'idrivee2-media-admin',
		'iDrivee2Media',
		array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'idrivee2_test_nonce' ),
			'buttonLabel'       => __( 'Test S3 Connection', 'idrivee2-media-upload' ),
			'testingLabel'      => __( 'Testing...', 'idrivee2-media-upload' ),
			'uploadButtonLabel' => __( 'Upload Test File', 'idrivee2-media-upload' ),
			'uploadingLabel'    => __( 'Uploading...', 'idrivee2-media-upload' ),
			'domain'            => defined( 'IDRIVEE2_MEDIA_DOMAIN' ) ? IDRIVEE2_MEDIA_DOMAIN : '',
		)
	);
}
add_action(
	'admin_enqueue_scripts',
	__NAMESPACE__ . '\enqueue_admin_scripts',
	20
);

/**
 * AJAX handler for testing connectivity to the iDrivee2 S3 bucket.
 *
 * Verifies that all required constants are defined, then attempts to
 * perform a HeadBucket call. Returns a JSON success or error message.
 *
 * @since 0.1.13
 *
 * @return void
 */
function ajax_test_connection(): void {
	check_ajax_referer( 'idrivee2_test_nonce', 'nonce' );

	$required_constants = array(
		'IDRIVEE2_MEDIA_HOST',
		'IDRIVEE2_MEDIA_KEY',
		'IDRIVEE2_MEDIA_SECRET',
		'IDRIVEE2_MEDIA_BUCKET',
		'IDRIVEE2_MEDIA_REGION',
	);

	foreach ( $required_constants as $constant ) {
		if ( ! defined( $constant ) ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s is the name of the missing constant. */
					__( 'Missing constant %s.', 'idrivee2-media-upload' ),
					esc_html( $constant )
				)
			);
		}
	}

	try {
		$client = new \Aws\S3\S3Client(
			array(
				'version'                 => 'latest',
				'region'                  => IDRIVEE2_MEDIA_REGION,
				'endpoint'                => IDRIVEE2_MEDIA_HOST,
				'use_path_style_endpoint' => true,
				'credentials'             => array(
					'key'    => IDRIVEE2_MEDIA_KEY,
					'secret' => IDRIVEE2_MEDIA_SECRET,
				),
			)
		);

		$client->headBucket( array( 'Bucket' => IDRIVEE2_MEDIA_BUCKET ) );
		wp_send_json_success( __( 'Connection successful.', 'idrivee2-media-upload' ) );
	} catch ( \Aws\Exception\AwsException $e ) {
		wp_send_json_error( $e->getAwsErrorMessage() );
	} catch ( \Exception $e ) {
		wp_send_json_error( $e->getMessage() );
	}
}
add_action(
	'wp_ajax_idrivee2_test_connection',
	__NAMESPACE__ . '\ajax_test_connection',
	10
);

/**
 * AJAX handler for uploading a test file to iDrivee2.
 *
 * Validates the AJAX nonce and the uploaded file, then attempts to
 * upload it to the configured S3 bucket. Returns the full object
 * metadata on success or an error message on failure.
 *
 * @since 0.1.14
 *
 * @return void
 */
function ajax_upload_test_file(): void {
	// Verify nonce for security.
	check_ajax_referer( 'idrivee2_test_nonce', 'nonce' );

	// Ensure a file was provided.
	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
		/** translators: Error message when no file is provided. */
		wp_send_json_error( __( 'No file provided.', 'idrivee2-media-upload' ) );
	}

	try {
		// Initialize the S3 client.
		$client = new \Aws\S3\S3Client(
			array(
				'version'                 => 'latest',
				'region'                  => IDRIVEE2_MEDIA_REGION,
				'endpoint'                => IDRIVEE2_MEDIA_HOST,
				'use_path_style_endpoint' => true,
				'credentials'             => array(
					'key'    => IDRIVEE2_MEDIA_KEY,
					'secret' => IDRIVEE2_MEDIA_SECRET,
				),
			)
		);

		// Prepare file for upload.
		$tmp_path  = $_FILES['file']['tmp_name'];
		$file_name = sanitize_file_name( $_FILES['file']['name'] );

		// Upload to S3.
		$result = $client->putObject(
			array(
				'Bucket' => IDRIVEE2_MEDIA_BUCKET,
				'Key'    => $file_name,
				'Body'   => fopen( $tmp_path, 'rb' ),
				'ACL'    => 'public-read',
			)
		);

		// Return the full AWS SDK response.
		wp_send_json_success( $result->toArray() );
	} catch ( \Aws\Exception\AwsException $e ) {
		// AWS-specific exception.
		wp_send_json_error( $e->getAwsErrorMessage() );
	} catch ( \Exception $e ) {
		// General exception.
		wp_send_json_error( $e->getMessage() );
	}
}
add_action(
	'wp_ajax_idrivee2_upload_test_file',
	__NAMESPACE__ . '\ajax_upload_test_file',
	10
);

/**
 * Override the upload URL path with the configured iDrivee2 domain.
 *
 * If the IDRIVEE2_MEDIA_DOMAIN constant is defined and non-empty, this
 * filter replaces the default uploads base URL so all new media items
 * use the custom domain.
 *
 * @since 0.1.14
 *
 * @param string $value The original value of the upload_url_path option.
 * @return string The upload_url_path, overridden to the custom domain when set.
 */
add_filter(
	'pre_option_upload_url_path',
	function ( string $value ): string {
		if ( defined( 'IDRIVEE2_MEDIA_DOMAIN' ) && IDRIVEE2_MEDIA_DOMAIN ) {
			return untrailingslashit( IDRIVEE2_MEDIA_DOMAIN );
		}
		return $value;
	}
);

/**
 * Rewrite attachment URLs to use the configured iDrivee2 domain.
 *
 * Intercepts all calls to wp_get_attachment_url() and replaces the
 * default uploads base URL with the custom IDRIVEE2_MEDIA_DOMAIN,
 * ensuring front-end and AJAX previews load from the S3-compatible host.
 *
 * @since 0.1.14
 *
 * @param string $url     The original attachment URL.
 * @param int    $post_id The attachment post ID.
 * @return string The filtered URL, using the iDrivee2 domain if defined.
 */
add_filter(
	'wp_get_attachment_url',
	function ( string $url, int $post_id ): string {
		if ( defined( 'IDRIVEE2_MEDIA_DOMAIN' ) && IDRIVEE2_MEDIA_DOMAIN ) {
			$uploads  = wp_upload_dir();
			$old_base = untrailingslashit( $uploads['baseurl'] );
			$new_base = untrailingslashit( IDRIVEE2_MEDIA_DOMAIN );
			return str_replace( $old_base, $new_base, $url );
		}
		return $url;
	},
	1,
	2
);

/**
 * Uploads attachment files to iDrivee2 after sizes are generated.
 *
 * This function pushes the original file and all generated sizes to the
 * configured S3-compatible host, captures the returned ObjectURL for the
 * original file, deletes the local copies, updates the attachment’s GUID,
 * and filters the front-end URL to use the S3 ObjectURL.
 *
 * @since 0.1.14
 *
 * @param array $meta           Attachment metadata, including 'file' and 'sizes'.
 * @param int   $attachment_id  Attachment post ID.
 * @return array                Unchanged metadata array.
 */
function upload_attachment_to_idrivee2( array $meta, int $attachment_id ): array {
	// Bail if configuration is incomplete.
	if (
		! defined( 'IDRIVEE2_MEDIA_HOST' )
		|| ! defined( 'IDRIVEE2_MEDIA_KEY' )
		|| ! defined( 'IDRIVEE2_MEDIA_SECRET' )
		|| ! defined( 'IDRIVEE2_MEDIA_BUCKET' )
		|| ! defined( 'IDRIVEE2_MEDIA_REGION' )
	) {
		return $meta;
	}

	// Initialize the S3 client.
	$client = new \Aws\S3\S3Client(
		array(
			'version'                 => 'latest',
			'region'                  => IDRIVEE2_MEDIA_REGION,
			'endpoint'                => IDRIVEE2_MEDIA_HOST,
			'use_path_style_endpoint' => true,
			'credentials'             => array(
				'key'    => IDRIVEE2_MEDIA_KEY,
				'secret' => IDRIVEE2_MEDIA_SECRET,
			),
		)
	);

	// Build list of files: original + each size.
	$upload_dir = wp_upload_dir();
	$base_path  = path_join( $upload_dir['basedir'], $meta['file'] );
	$files      = array( 'original' => $base_path );

	if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size ) {
			$files[ $size['file'] ] = path_join( dirname( $base_path ), $size['file'] );
		}
	}

	$object_url = '';

	// Upload each file and capture the ObjectURL of the original.
	foreach ( $files as $key => $local_path ) {
		if ( ! file_exists( $local_path ) ) {
			continue;
		}

		$object_key = 'original' === $key
			? $meta['file']
			: dirname( $meta['file'] ) . '/' . $key;

		$result = $client->putObject(
			array(
				'Bucket' => IDRIVEE2_MEDIA_BUCKET,
				'Key'    => $object_key,
				'Body'   => fopen( $local_path, 'rb' ),
				'ACL'    => 'public-read',
			)
		);

		if ( 'original' === $key && ! empty( $result['ObjectURL'] ) ) {
			$object_url = $result['ObjectURL'];
		}

		@unlink( $local_path );
	}

	// Preserve the relative path in the database.
	update_post_meta( $attachment_id, '_wp_attached_file', $meta['file'] );

	if ( $object_url ) {
		// Update the GUID in wp_posts to the S3 URL.
		wp_update_post(
			array(
				'ID'   => $attachment_id,
				'guid' => $object_url,
			)
		);

		// Override the front-end URL to use the S3 ObjectURL.
		add_filter(
			'wp_get_attachment_url',
			function ( string $url, int $id ) use ( $object_url, $attachment_id ): string {
				return $id === $attachment_id ? $object_url : $url;
			},
			10,
			2
		);
	}

	return $meta;
}

// Initial metadata generation hook.
add_filter(
	'wp_generate_attachment_metadata',
	__NAMESPACE__ . '\upload_attachment_to_idrivee2',
	10,
	2
);

// Regeneration hook (e.g., when saving edits in the image editor).
add_filter(
	'wp_update_attachment_metadata',
	__NAMESPACE__ . '\upload_attachment_to_idrivee2',
	10,
	2
);

// Edit attachment hook as a fallback.
add_action(
	'edit_attachment',
	function ( int $post_id ): void {
		$meta = wp_get_attachment_metadata( $post_id );
		if ( $meta ) {
			upload_attachment_to_idrivee2( $meta, $post_id );
		}
	}
);

/**
 * Render the iDrivee2 Media Upload settings page.
 *
 * Displays the current configuration constants (host, access key, secret key,
 * bucket, region, and optional domain) and provides buttons to test the
 * S3 connection or upload a test file.
 *
 * @since 0.1.15
 *
 * @return void
 */
function render_settings_page(): void {
	$host_defined       = defined( 'IDRIVEE2_MEDIA_HOST' );
	$access_key_defined = defined( 'IDRIVEE2_MEDIA_KEY' );
	$secret_defined     = defined( 'IDRIVEE2_MEDIA_SECRET' );
	$bucket_defined     = defined( 'IDRIVEE2_MEDIA_BUCKET' );
	$region_defined     = defined( 'IDRIVEE2_MEDIA_REGION' );
	$domain_defined     = defined( 'IDRIVEE2_MEDIA_DOMAIN' );

	$host       = $host_defined ? IDRIVEE2_MEDIA_HOST : '';
	$access_key = $access_key_defined ? IDRIVEE2_MEDIA_KEY : '';
	$secret     = $secret_defined ? IDRIVEE2_MEDIA_SECRET : '';
	$bucket     = $bucket_defined ? IDRIVEE2_MEDIA_BUCKET : '';
	$region     = $region_defined ? IDRIVEE2_MEDIA_REGION : '';
	$domain     = $domain_defined ? IDRIVEE2_MEDIA_DOMAIN : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'iDrivee2 Media Upload Settings', 'idrivee2-media-upload' ); ?></h1>

		<?php if ( ! $host_defined || ! $access_key_defined || ! $secret_defined || ! $bucket_defined || ! $region_defined ) : ?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'To use iDrivee2 Media Upload, please add these constants to wp-config.php:', 'idrivee2-media-upload' ); ?></p>
				<p><?php esc_html_e( 'HOST must begin with "https://".', 'idrivee2-media-upload' ); ?></p>
				<pre>
define('IDRIVEE2_MEDIA_HOST',   'https://your-s3-host.amazonaws.com');
define('IDRIVEE2_MEDIA_KEY',    'YOUR_ACCESS_KEY_ID');
define('IDRIVEE2_MEDIA_SECRET', 'YOUR_SECRET_ACCESS_KEY');
define('IDRIVEE2_MEDIA_BUCKET', 'your-bucket-name');
define('IDRIVEE2_MEDIA_REGION', 'us-east-1');</pre>
			</div>
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Host', 'idrivee2-media-upload' ); ?></th>
				<td><code><?php echo esc_html( $host ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Access Key', 'idrivee2-media-upload' ); ?></th>
				<td><code><?php echo esc_html( $access_key ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Secret Key', 'idrivee2-media-upload' ); ?></th>
				<td><code><?php echo esc_html( str_repeat( '*', strlen( $secret ) ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Bucket', 'idrivee2-media-upload' ); ?></th>
				<td><code><?php echo esc_html( $bucket ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Region', 'idrivee2-media-upload' ); ?></th>
				<td><code><?php echo esc_html( $region ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Domain', 'idrivee2-media-upload' ); ?></th>
				<td>
					<code>
						<?php
						echo $domain_defined
							? esc_html( $domain )
							: esc_html__( 'Not defined', 'idrivee2-media-upload' );
						?>
					</code>
				</td>
			</tr>
		</table>

		<p>
			<button id="idrivee2-test-button" class="button button-primary">
				<?php esc_html_e( 'Test S3 Connection', 'idrivee2-media-upload' ); ?>
			</button>
		</p>
		<p>
			<button id="idrivee2-upload-button" class="button button-secondary">
				<?php esc_html_e( 'Upload Test File', 'idrivee2-media-upload' ); ?>
			</button>
		</p>

		<div
			id="idrivee2-test-result"
			style="margin-top: 1em; white-space: pre-wrap;"
		></div>
	</div>
	<?php
}
