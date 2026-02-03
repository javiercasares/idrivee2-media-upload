<?php
/**
 * Admin Page handler for iDrivee2 Media Upload.
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
 * Admin page handler for iDrivee2 Media Upload.
 *
 * Manages the admin interface under Media → iDrivee2, including settings display,
 * AJAX handlers for connection testing and file uploads, and script enqueuing.
 *
 * @since 0.3.0
 */
class Admin_Page {
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
	 * Plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Config            $config         Configuration instance.
	 * @param S3_Client_Factory $client_factory S3 client factory.
	 * @param string            $plugin_file    Path to the main plugin file.
	 */
	public function __construct( Config $config, S3_Client_Factory $client_factory, string $plugin_file ) {
		$this->config         = $config;
		$this->client_factory = $client_factory;
		$this->plugin_file    = $plugin_file;
	}

	/**
	 * Register hooks for the admin page.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_media_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 20 );
		add_action( 'wp_ajax_idrivee2_test_connection', array( $this, 'ajax_test_connection' ), 10 );
		add_action( 'wp_ajax_idrivee2_upload_test_file', array( $this, 'ajax_upload_test_file' ), 10 );
	}

	/**
	 * Register the iDrivee2 settings page under the Media menu.
	 *
	 * Adds a submenu page to the Media section of the admin where users
	 * can view and test their iDrivee2 configuration.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register_media_page(): void {
		add_media_page(
			/* translators: Admin menu title. */
			__( 'iDrivee2', 'idrivee2-media-upload' ),
			/* translators: Admin menu label. */
			__( 'iDrivee2', 'idrivee2-media-upload' ),
			'manage_options',
			'idrivee2-media-upload',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue the admin JavaScript and localize script data for AJAX.
	 *
	 * Loads the iDrivee2 admin script on the Media → iDrivee2 settings page
	 * and passes dynamic data such as the AJAX URL, nonces, and button labels.
	 *
	 * @since 0.3.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		// Only load script on our settings page.
		if ( 'media_page_idrivee2-media-upload' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'idrivee2-media-admin',
			plugin_dir_url( $this->plugin_file ) . 'assets/js/admin.js',
			array( 'jquery' ),
			'0.3.0',
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
				'domain'            => $this->config->get_domain(),
			)
		);
	}

	/**
	 * AJAX handler for testing connectivity to the iDrivee2 S3 bucket.
	 *
	 * Verifies that all required constants are defined, then attempts to
	 * perform a HeadBucket call. Returns a JSON success or error message.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'idrivee2_test_nonce', 'nonce' );

		if ( ! $this->config->is_configured() ) {
			wp_send_json_error(
				__( 'Configuration is incomplete. Please check wp-config.php.', 'idrivee2-media-upload' )
			);
		}

		try {
			$client = $this->client_factory->create();
			$client->headBucket( array( 'Bucket' => $this->config->get_bucket() ) );
			wp_send_json_success( __( 'Connection successful.', 'idrivee2-media-upload' ) );
		} catch ( \Aws\Exception\AwsException $e ) {
			wp_send_json_error( $e->getAwsErrorMessage() );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for uploading a test file to iDrivee2.
	 *
	 * Validates the AJAX nonce and the uploaded file, then attempts to
	 * upload it to the configured S3 bucket. Returns the full object
	 * metadata on success or an error message on failure.
	 *
	 * @since 0.3.0
	 *
	 * @throws \Exception If file cannot be read or uploaded.
	 * @return void
	 */
	public function ajax_upload_test_file(): void {
		// Verify nonce for security.
		check_ajax_referer( 'idrivee2_test_nonce', 'nonce' );

		// Ensure a file was provided.
		if ( empty( $_FILES['file'] ) || ( isset( $_FILES['file']['name'] ) && isset( $_FILES['file']['tmp_name'] ) && ! is_uploaded_file( sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) ) ) ) ) {
			/* translators: Error message when no file is provided. */
			wp_send_json_error( __( 'No file provided.', 'idrivee2-media-upload' ) );
		}

		try {
			// Initialize the S3 client.
			$client = $this->client_factory->create();

			// Prepare file parameters.
			$tmp_path  = sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) );
			$file_name = sanitize_file_name( wp_basename( sanitize_text_field( wp_unslash( $_FILES['file']['name'] ) ) ) );

			// Load WP_Filesystem if needed.
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			global $wp_filesystem;

			// Read file contents via WP_Filesystem.
			$content = $wp_filesystem->get_contents( $tmp_path );
			if ( false === $content ) {
				throw new \Exception( sprintf( 'Unable to read temporary file: %s', $tmp_path ) );
			}

			// Upload to S3 from memory.
			$result = $client->putObject(
				array(
					'Bucket' => $this->config->get_bucket(),
					'Key'    => $file_name,
					'Body'   => $content,
					'ACL'    => 'public-read',
				)
			);

			// Send back the AWS SDK response.
			wp_send_json_success( $result->toArray() );

		} catch ( \Aws\Exception\AwsException $e ) {
			// AWS-specific error.
			wp_send_json_error( $e->getAwsErrorMessage() );
		} catch ( \Exception $e ) {
			// General error.
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Render the iDrivee2 Media Upload settings page.
	 *
	 * Displays the current configuration constants (host, access key, secret key,
	 * bucket, region, and optional domain) and provides buttons to test the
	 * S3 connection or upload a test file.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$host       = $this->config->get_host();
		$access_key = $this->config->get_key();
		$secret     = $this->config->get_secret();
		$bucket     = $this->config->get_bucket();
		$region     = $this->config->get_region();
		$domain     = $this->config->get_domain();

		$is_configured = $this->config->is_configured();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'iDrivee2 Media Upload Settings', 'idrivee2-media-upload' ); ?></h1>

			<?php if ( ! $is_configured ) : ?>
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
							echo $domain
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
}
