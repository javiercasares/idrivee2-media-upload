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
 * Manages the admin interface under Settings → iDrivee2, including settings display,
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
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Rate limiter instance.
	 *
	 * @var Rate_Limiter
	 */
	private $rate_limiter;

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
	 * @param Logger            $logger         Logger instance.
	 * @param Rate_Limiter      $rate_limiter   Rate limiter instance.
	 * @param string            $plugin_file    Path to the main plugin file.
	 */
	public function __construct( Config $config, S3_Client_Factory $client_factory, Logger $logger, Rate_Limiter $rate_limiter, string $plugin_file ) {
		$this->config         = $config;
		$this->client_factory = $client_factory;
		$this->logger         = $logger;
		$this->rate_limiter   = $rate_limiter;
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
		add_action( 'admin_menu', array( $this, 'register_settings_page' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_test_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 20 );
	}

	/**
	 * Register the iDrivee2 settings page under the Settings menu.
	 *
	 * Adds a submenu page to the Settings section of the admin where users
	 * can configure and test their iDrivee2 setup.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register_settings_page(): void {
		add_options_page(
			/* translators: Admin menu title. */
			__( 'iDrivee2 Media Upload Settings', 'idrivee2-media-upload' ),
			/* translators: Admin menu label. */
			__( 'iDrivee2', 'idrivee2-media-upload' ),
			'manage_options',
			'idrivee2-media-upload',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings with WordPress Settings API.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'idrivee2_media_settings_group',
			'idrivee2_media_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Handle test action POST requests.
	 *
	 * Processes test connection, upload test file, and delete test file actions.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function handle_test_actions(): void {
		// Only process on settings page.
		if ( ! isset( $_GET['page'] ) || 'idrivee2-media-upload' !== $_GET['page'] ) {
			return;
		}

		// Handle test connection.
		if ( isset( $_POST['idrivee2_test_connection'] ) ) {
			check_admin_referer( 'idrivee2_test_connection' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'idrivee2-media-upload' ) );
			}

			$this->handle_test_connection();
			return;
		}

		// Handle upload test file.
		if ( isset( $_POST['idrivee2_upload_test'] ) ) {
			check_admin_referer( 'idrivee2_upload_test' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'idrivee2-media-upload' ) );
			}

			$this->handle_upload_test();
			return;
		}

		// Handle delete test file.
		if ( isset( $_POST['idrivee2_delete_test'] ) && isset( $_POST['test_file'] ) ) {
			check_admin_referer( 'idrivee2_delete_test' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'idrivee2-media-upload' ) );
			}

			$this->handle_delete_test( sanitize_file_name( wp_unslash( $_POST['test_file'] ) ) );
			return;
		}
	}

	/**
	 * Handle test connection action.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	private function handle_test_connection(): void {
		// Check rate limit.
		if ( $this->rate_limiter->is_rate_limited( 'test_connection', 60 ) ) {
			$remaining = $this->rate_limiter->get_remaining_time( 'test_connection', 60 );
			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %d is the number of seconds to wait. */
						__( 'Please wait %d seconds before testing again.', 'idrivee2-media-upload' ),
						$remaining
					),
				),
				30
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=idrivee2-media-upload' ) );
			exit;
		}

		if ( ! $this->config->is_configured() ) {
			$this->logger->warning( 'Test connection attempted with incomplete configuration' );
			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => __( 'Configuration is incomplete. Please check your settings.', 'idrivee2-media-upload' ),
				),
				30
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=idrivee2-media-upload' ) );
			exit;
		}

		// Record this action for rate limiting.
		$this->rate_limiter->record_action( 'test_connection', 60 );

		try {
			$client = $this->client_factory->create();
			$client->headBucket( array( 'Bucket' => $this->config->get_bucket() ) );

			$this->logger->s3_operation( 'headBucket', true );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'success',
					'message' => __( 'Connection successful! Your S3 configuration is working correctly.', 'idrivee2-media-upload' ),
				),
				30
			);
		} catch ( \Aws\Exception\AwsException $e ) {
			$this->logger->s3_operation( 'headBucket', false, '', $e->getAwsErrorMessage() ?? '' );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %s is the error message from AWS. */
						__( 'AWS Error: %s', 'idrivee2-media-upload' ),
						$e->getAwsErrorMessage() ?? __( 'Unknown error', 'idrivee2-media-upload' )
					),
				),
				30
			);
		} catch ( \Exception $e ) {
			$this->logger->error( 'Test connection failed: ' . $e->getMessage() );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %s is the error message. */
						__( 'Error: %s', 'idrivee2-media-upload' ),
						$e->getMessage()
					),
				),
				30
			);
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=idrivee2-media-upload' ) );
		exit;
	}

	/**
	 * Handle upload test file action.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	private function handle_upload_test(): void {
		// Check rate limit.
		if ( $this->rate_limiter->is_rate_limited( 'upload_test', 60 ) ) {
			$remaining = $this->rate_limiter->get_remaining_time( 'upload_test', 60 );
			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %d is the number of seconds to wait. */
						__( 'Please wait %d seconds before uploading again.', 'idrivee2-media-upload' ),
						$remaining
					),
				),
				30
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=idrivee2-media-upload' ) );
			exit;
		}

		// Record this action for rate limiting.
		$this->rate_limiter->record_action( 'upload_test', 60 );

		try {
			// Generate timestamp for filename (YYYYMMDDHHMMSS).
			$timestamp = gmdate( 'YmdHis' );
			$file_name = 'test-' . $timestamp . '.txt';

			// Create file content.
			$content = 'Test file uploaded at ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';

			// Initialize the S3 client.
			$client = $this->client_factory->create();

			// Upload to S3.
			$result = $client->putObject(
				array(
					'Bucket' => $this->config->get_bucket(),
					'Key'    => $file_name,
					'Body'   => $content,
					'ACL'    => 'public-read',
				)
			);

			// Log successful upload.
			$this->logger->s3_operation( 'putObject', true, $file_name );

			// Get the public URL - use CDN domain if configured, otherwise S3 ObjectURL.
			$object_url = $this->get_public_url( $file_name, $result['ObjectURL'] ?? '' );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'       => 'success',
					'message'    => __( 'File uploaded successfully!', 'idrivee2-media-upload' ),
					'file_name'  => $file_name,
					'object_url' => $object_url,
				),
				30
			);

		} catch ( \Aws\Exception\AwsException $e ) {
			$this->logger->s3_operation( 'putObject', false, $file_name, $e->getAwsErrorMessage() ?? '' );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %s is the error message from AWS. */
						__( 'AWS Error: %s', 'idrivee2-media-upload' ),
						$e->getAwsErrorMessage() ?? __( 'Unknown error', 'idrivee2-media-upload' )
					),
				),
				30
			);
		} catch ( \Exception $e ) {
			$this->logger->error( 'Test upload failed: ' . $e->getMessage() );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %s is the error message. */
						__( 'Error: %s', 'idrivee2-media-upload' ),
						$e->getMessage()
					),
				),
				30
			);
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=idrivee2-media-upload' ) );
		exit;
	}

	/**
	 * Get the public URL for an uploaded file.
	 *
	 * Uses CDN domain if configured, otherwise falls back to S3 ObjectURL.
	 *
	 * @since 0.3.0
	 *
	 * @param string $file_name  The file name/key in S3.
	 * @param string $object_url The S3 ObjectURL returned by AWS SDK.
	 * @return string The public URL to access the file.
	 */
	private function get_public_url( string $file_name, string $object_url ): string {
		// If custom domain is configured, use it.
		if ( $this->config->has_domain() ) {
			return trailingslashit( $this->config->get_domain() ) . $file_name;
		}

		// Otherwise, use the S3 ObjectURL.
		return $object_url;
	}

	/**
	 * Handle delete test file action.
	 *
	 * @since 0.3.0
	 *
	 * @param string $file_name The test file name to delete.
	 * @return void
	 */
	private function handle_delete_test( string $file_name ): void {
		// Validate file name pattern (must be test-YYYYMMDDHHMMSS.txt).
		if ( ! preg_match( '/^test-\d{14}\.txt$/', $file_name ) ) {
			$this->logger->warning( 'Invalid test file name format attempted: ' . $file_name );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => __( 'Invalid test file name format.', 'idrivee2-media-upload' ),
				),
				30
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=idrivee2-media-upload' ) );
			exit;
		}

		try {
			// Initialize the S3 client.
			$client = $this->client_factory->create();

			// Delete object from S3.
			$client->deleteObject(
				array(
					'Bucket' => $this->config->get_bucket(),
					'Key'    => $file_name,
				)
			);

			// Log successful deletion.
			$this->logger->s3_operation( 'deleteObject', true, $file_name );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'info',
					'message' => sprintf(
						/* translators: %s is the file name. */
						__( 'File %s deleted successfully.', 'idrivee2-media-upload' ),
						'<code>' . esc_html( $file_name ) . '</code>'
					),
				),
				30
			);

		} catch ( \Aws\Exception\AwsException $e ) {
			$this->logger->s3_operation( 'deleteObject', false, $file_name, $e->getAwsErrorMessage() ?? '' );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %s is the error message from AWS. */
						__( 'AWS Error: %s', 'idrivee2-media-upload' ),
						$e->getAwsErrorMessage() ?? __( 'Unknown error', 'idrivee2-media-upload' )
					),
				),
				30
			);
		} catch ( \Exception $e ) {
			$this->logger->error( 'Test file deletion failed: ' . $e->getMessage() );

			set_transient(
				'idrivee2_test_result',
				array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %s is the error message. */
						__( 'Error: %s', 'idrivee2-media-upload' ),
						$e->getMessage()
					),
				),
				30
			);
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=idrivee2-media-upload' ) );
		exit;
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, string> $input Input settings.
	 * @return array<string, string> Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Sanitize host.
		if ( isset( $input['host'] ) ) {
			$sanitized['host'] = sanitize_text_field( $input['host'] );
			// Validate that host starts with https://.
			if ( ! empty( $sanitized['host'] ) && ! str_starts_with( $sanitized['host'], 'https://' ) ) {
				add_settings_error(
					'idrivee2_media_settings',
					'invalid_host',
					__( 'Host URL must start with "https://".', 'idrivee2-media-upload' )
				);
			}
		}

		// Sanitize other fields.
		$sanitized['key']    = isset( $input['key'] ) ? sanitize_text_field( $input['key'] ) : '';
		$sanitized['secret'] = isset( $input['secret'] ) ? sanitize_text_field( $input['secret'] ) : '';
		$sanitized['bucket'] = isset( $input['bucket'] ) ? sanitize_text_field( $input['bucket'] ) : '';
		$sanitized['region'] = isset( $input['region'] ) ? sanitize_text_field( $input['region'] ) : '';
		$sanitized['domain'] = isset( $input['domain'] ) ? sanitize_text_field( $input['domain'] ) : '';

		return $sanitized;
	}

	/**
	 * Enqueue the admin JavaScript for enhanced UX.
	 *
	 * Loads the iDrivee2 admin script on the Settings → iDrivee2 page.
	 *
	 * @since 0.3.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		// Only load script on our settings page.
		if ( 'settings_page_idrivee2-media-upload' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'idrivee2-media-admin',
			plugin_dir_url( $this->plugin_file ) . 'assets/js/admin.js',
			array( 'jquery' ),
			'0.3.0',
			true
		);
	}

	/**
	 * Render the iDrivee2 Media Upload settings page.
	 *
	 * Displays configuration form with fields for S3 settings. Fields are
	 * read-only if values are defined in wp-config.php.
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

		// Check which fields are defined in wp-config.php.
		$host_in_config   = $this->config->is_defined_in_wp_config( 'host' );
		$key_in_config    = $this->config->is_defined_in_wp_config( 'key' );
		$secret_in_config = $this->config->is_defined_in_wp_config( 'secret' );
		$bucket_in_config = $this->config->is_defined_in_wp_config( 'bucket' );
		$region_in_config = $this->config->is_defined_in_wp_config( 'region' );
		$domain_in_config = $this->config->is_defined_in_wp_config( 'domain' );

		$any_in_config = $host_in_config || $key_in_config || $secret_in_config || $bucket_in_config || $region_in_config || $domain_in_config;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'iDrivee2 Media Upload Settings', 'idrivee2-media-upload' ); ?></h1>

			<?php settings_errors(); ?>

			<?php if ( $any_in_config ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'Some settings are defined in wp-config.php and cannot be changed here. These fields are shown as read-only.', 'idrivee2-media-upload' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'idrivee2_media_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="idrivee2_host"><?php esc_html_e( 'S3 Host', 'idrivee2-media-upload' ); ?></label>
							</th>
							<td>
								<input
									type="url"
									id="idrivee2_host"
									name="idrivee2_media_settings[host]"
									value="<?php echo esc_attr( $host ); ?>"
									class="regular-text"
									<?php echo $host_in_config ? 'readonly' : ''; ?>
									<?php echo ! $host_in_config ? 'required' : ''; ?>
								/>
								<p class="description">
									<?php
									if ( $host_in_config ) {
										esc_html_e( 'Defined in wp-config.php (IDRIVEE2_MEDIA_HOST)', 'idrivee2-media-upload' );
									} else {
										esc_html_e( 'S3-compatible endpoint URL. Must start with "https://".', 'idrivee2-media-upload' );
									}
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="idrivee2_key"><?php esc_html_e( 'Access Key ID', 'idrivee2-media-upload' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="idrivee2_key"
									name="idrivee2_media_settings[key]"
									value="<?php echo esc_attr( $access_key ); ?>"
									class="regular-text"
									<?php echo $key_in_config ? 'readonly' : ''; ?>
									<?php echo ! $key_in_config ? 'required' : ''; ?>
								/>
								<p class="description">
									<?php
									if ( $key_in_config ) {
										esc_html_e( 'Defined in wp-config.php (IDRIVEE2_MEDIA_KEY)', 'idrivee2-media-upload' );
									} else {
										esc_html_e( 'Your S3 access key ID.', 'idrivee2-media-upload' );
									}
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="idrivee2_secret"><?php esc_html_e( 'Secret Access Key', 'idrivee2-media-upload' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									id="idrivee2_secret"
									name="idrivee2_media_settings[secret]"
									value="<?php echo esc_attr( $secret ); ?>"
									class="regular-text"
									<?php echo $secret_in_config ? 'readonly' : ''; ?>
									<?php echo ! $secret_in_config ? 'required' : ''; ?>
								/>
								<p class="description">
									<?php
									if ( $secret_in_config ) {
										esc_html_e( 'Defined in wp-config.php (IDRIVEE2_MEDIA_SECRET)', 'idrivee2-media-upload' );
									} else {
										esc_html_e( 'Your S3 secret access key.', 'idrivee2-media-upload' );
									}
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="idrivee2_bucket"><?php esc_html_e( 'Bucket Name', 'idrivee2-media-upload' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="idrivee2_bucket"
									name="idrivee2_media_settings[bucket]"
									value="<?php echo esc_attr( $bucket ); ?>"
									class="regular-text"
									<?php echo $bucket_in_config ? 'readonly' : ''; ?>
									<?php echo ! $bucket_in_config ? 'required' : ''; ?>
								/>
								<p class="description">
									<?php
									if ( $bucket_in_config ) {
										esc_html_e( 'Defined in wp-config.php (IDRIVEE2_MEDIA_BUCKET)', 'idrivee2-media-upload' );
									} else {
										esc_html_e( 'The name of your S3 bucket.', 'idrivee2-media-upload' );
									}
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="idrivee2_region"><?php esc_html_e( 'Region', 'idrivee2-media-upload' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="idrivee2_region"
									name="idrivee2_media_settings[region]"
									value="<?php echo esc_attr( $region ); ?>"
									class="regular-text"
									placeholder="us-east-1"
									<?php echo $region_in_config ? 'readonly' : ''; ?>
									<?php echo ! $region_in_config ? 'required' : ''; ?>
								/>
								<p class="description">
									<?php
									if ( $region_in_config ) {
										esc_html_e( 'Defined in wp-config.php (IDRIVEE2_MEDIA_REGION)', 'idrivee2-media-upload' );
									} else {
										esc_html_e( 'AWS region (e.g., us-east-1, eu-west-1).', 'idrivee2-media-upload' );
									}
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="idrivee2_domain"><?php esc_html_e( 'Custom CDN Domain', 'idrivee2-media-upload' ); ?></label>
							</th>
							<td>
								<input
									type="url"
									id="idrivee2_domain"
									name="idrivee2_media_settings[domain]"
									value="<?php echo esc_attr( $domain ); ?>"
									class="regular-text"
									placeholder="https://cdn.example.com"
									<?php echo $domain_in_config ? 'readonly' : ''; ?>
								/>
								<p class="description">
									<?php
									if ( $domain_in_config ) {
										esc_html_e( 'Defined in wp-config.php (IDRIVEE2_MEDIA_DOMAIN)', 'idrivee2-media-upload' );
									} else {
										esc_html_e( 'Optional: Custom domain for serving media files.', 'idrivee2-media-upload' );
									}
									?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php if ( ! $any_in_config ) : ?>
					<?php submit_button( __( 'Save Settings', 'idrivee2-media-upload' ) ); ?>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'To modify settings defined in wp-config.php, please edit your wp-config.php file directly.', 'idrivee2-media-upload' ); ?>
					</p>
					<?php
					// Show submit button only if at least one field can be edited.
					$can_edit = ! $host_in_config || ! $key_in_config || ! $secret_in_config || ! $bucket_in_config || ! $region_in_config || ! $domain_in_config;
					if ( $can_edit ) :
						?>
						<?php submit_button( __( 'Save Settings', 'idrivee2-media-upload' ) ); ?>
					<?php endif; ?>
				<?php endif; ?>
			</form>

			<?php if ( $is_configured ) : ?>
				<hr />

				<h2><?php esc_html_e( 'Test Connection', 'idrivee2-media-upload' ); ?></h2>

				<p><?php esc_html_e( 'Test your S3 configuration to ensure everything is working correctly.', 'idrivee2-media-upload' ); ?></p>

				<?php
				// Display test results if available.
				$test_result = get_transient( 'idrivee2_test_result' );
				if ( $test_result ) {
					delete_transient( 'idrivee2_test_result' );
					$notice_class = 'notice-' . $test_result['type'];
					?>
					<div class="notice <?php echo esc_attr( $notice_class ); ?>">
						<p><strong><?php echo wp_kses_post( $test_result['message'] ); ?></strong></p>
						<?php if ( isset( $test_result['file_name'] ) && isset( $test_result['object_url'] ) ) : ?>
							<p>
								<?php
								printf(
									/* translators: %s is the file name. */
									esc_html__( 'File: %s', 'idrivee2-media-upload' ),
									'<code>' . esc_html( $test_result['file_name'] ) . '</code>'
								);
								?>
							</p>
							<p>
								<?php esc_html_e( 'URL:', 'idrivee2-media-upload' ); ?>
								<a href="<?php echo esc_url( $test_result['object_url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $test_result['object_url'] ); ?>
								</a>
							</p>
							<form method="post" action="" style="margin-top: 10px;">
								<?php wp_nonce_field( 'idrivee2_delete_test' ); ?>
								<input type="hidden" name="test_file" value="<?php echo esc_attr( $test_result['file_name'] ); ?>" />
								<button type="submit" name="idrivee2_delete_test" class="button button-small">
									<?php esc_html_e( 'Delete this file', 'idrivee2-media-upload' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div>
					<?php
				}
				?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Test Connection', 'idrivee2-media-upload' ); ?></th>
							<td>
								<form method="post" action="">
									<?php wp_nonce_field( 'idrivee2_test_connection' ); ?>
									<button type="submit" name="idrivee2_test_connection" class="button button-primary">
										<?php esc_html_e( 'Test S3 Connection', 'idrivee2-media-upload' ); ?>
									</button>
									<p class="description">
										<?php esc_html_e( 'Verify that your S3 bucket is accessible with the configured credentials.', 'idrivee2-media-upload' ); ?>
									</p>
								</form>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Upload Test File', 'idrivee2-media-upload' ); ?></th>
							<td>
								<form method="post" action="">
									<?php wp_nonce_field( 'idrivee2_upload_test' ); ?>
									<button type="submit" name="idrivee2_upload_test" class="button button-secondary">
										<?php esc_html_e( 'Upload Test File', 'idrivee2-media-upload' ); ?>
									</button>
									<p class="description">
										<?php esc_html_e( 'Upload a test file to S3 with a timestamped name (test-YYYYMMDDHHMMSS.txt). The file will remain in S3 until manually deleted.', 'idrivee2-media-upload' ); ?>
									</p>
								</form>
							</td>
						</tr>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
