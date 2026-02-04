<?php
/**
 * Main Plugin class for iDrivee2 Media Upload.
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
 * Main plugin orchestrator (singleton).
 *
 * Coordinates all plugin components with dependency injection and manages
 * the plugin lifecycle.
 *
 * @since 0.3.0
 */
class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

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
	 * Admin page handler.
	 *
	 * @var Admin_Page
	 */
	private $admin_page;

	/**
	 * Media uploader.
	 *
	 * @var Media_Uploader
	 */
	private $media_uploader;

	/**
	 * URL rewriter.
	 *
	 * @var URL_Rewriter
	 */
	private $url_rewriter;

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Private constructor (singleton pattern).
	 *
	 * @since 0.3.0
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 */
	private function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;

		// Initialize logger and rate limiter.
		$this->logger       = new Logger();
		$this->rate_limiter = new Rate_Limiter( $this->logger );

		// Initialize configuration and factory.
		$this->config         = new Config( $this->logger );
		$this->client_factory = new S3_Client_Factory( $this->config );

		// Initialize components with dependency injection.
		$this->admin_page     = new Admin_Page( $this->config, $this->client_factory, $this->logger, $this->rate_limiter, $plugin_file );
		$this->media_uploader = new Media_Uploader( $this->config, $this->client_factory, $this->logger );
		$this->url_rewriter   = new URL_Rewriter( $this->config );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @since 0.3.0
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 * @return Plugin The singleton instance.
	 */
	public static function get_instance( string $plugin_file ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_file );
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin by registering all hooks.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Load text domain for translations.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 20 );

		// Add custom cron interval.
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );

		// Register component hooks.
		$this->admin_page->register();
		$this->media_uploader->register();
		$this->url_rewriter->register();
	}

	/**
	 * Add custom cron intervals.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>> Modified schedules.
	 */
	public function add_cron_intervals( array $schedules ): array {
		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'idrivee2-media-upload' ),
		);
		return $schedules;
	}

	/**
	 * Load the plugin text domain for translations.
	 *
	 * Registers the plugin's text domain so that translation files in the
	 * /languages directory are loaded on the front end and in the admin.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'idrivee2-media-upload',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages'
		);
	}
}
