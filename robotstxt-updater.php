<?php
/**
 * Generic JSON-based updater for ROBOTSTXT plugins.
 *
 * This file is designed to be copied to any ROBOTSTXT plugin.
 * It auto-configures itself by reading the plugin headers.
 *
 * @package ROBOTSTXT
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Robotstxt_Updater' ) ) {
	/**
	 * Class Robotstxt_Updater
	 *
	 * Generic updater that works with any plugin.
	 * Reads plugin headers and constructs update URL automatically.
	 */
	class Robotstxt_Updater {

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file_path;

	/**
	 * Plugin basename (e.g., 'my-plugin/my-plugin.php').
	 *
	 * @var string
	 */
	private string $plugin_basename;

	/**
	 * Plugin slug (directory name).
	 *
	 * @var string
	 */
	private string $plugin_slug;

	/**
	 * Remote JSON URL.
	 *
	 * @var string
	 */
	private string $json_url;

	/**
	 * Cache key.
	 *
	 * @var string
	 */
	private string $cache_key;

	/**
	 * Plugin headers.
	 *
	 * @var array
	 */
	private array $plugin_data;

	/**
	 * Initialize the updater.
	 *
	 * Usage in your main plugin file:
	 * require_once __DIR__ . '/robotstxt-updater.php';
	 * Robotstxt_Updater::init( __FILE__ );
	 *
	 * @param string $plugin_file_path Absolute path to the main plugin file.
	 */
	public static function init( string $plugin_file_path ): void {
		$instance = new self( $plugin_file_path );
		$instance->register();
	}

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file_path Absolute path to the main plugin file.
	 */
	private function __construct( string $plugin_file_path ) {
		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_basename  = plugin_basename( $plugin_file_path );
		$this->plugin_slug      = dirname( $this->plugin_basename );
		$this->plugin_data      = $this->get_plugin_data();
		$this->json_url         = $this->build_json_url();
		$this->cache_key        = 'robotstxt_updater_' . md5( $this->plugin_basename );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update_info' ) );
		add_filter( 'plugins_api', array( $this, 'provide_plugin_details' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'handle_cache_clear' ) );
		add_action( 'robotstxt_updater_clear_cache', array( $this, 'clear_cache' ) );
	}

	/**
	 * Get plugin headers.
	 *
	 * @return array Plugin data.
	 */
	private function get_plugin_data(): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugin_data( $this->plugin_file_path, false, false );
	}

	/**
	 * Build JSON URL from plugin headers.
	 *
	 * Tries to use "Gitea Plugin URI" header to construct the URL.
	 * Falls back to Plugin URI if Gitea URI is not available.
	 *
	 * @return string JSON URL.
	 */
	private function build_json_url(): string {
		// Try Gitea Plugin URI (format: "OWNER/REPO" or full URL).
		if ( ! empty( $this->plugin_data['Gitea Plugin URI'] ) ) {
			$gitea_uri = $this->plugin_data['Gitea Plugin URI'];

			// If it's already a full URL, use it.
			if ( str_starts_with( $gitea_uri, 'http' ) ) {
				// Extract base URL and construct JSON path.
				return rtrim( $gitea_uri, '/' ) . '/raw/branch/main/update.json';
			}

			// If it's in format "OWNER/REPO", construct full URL.
			if ( preg_match( '#^[^/]+/[^/]+$#', $gitea_uri ) ) {
				return "https://git.robotstxt.es/{$gitea_uri}/raw/branch/main/update.json";
			}
		}

		// Fallback: try to extract from Plugin URI.
		if ( ! empty( $this->plugin_data['PluginURI'] ) ) {
			$plugin_uri = $this->plugin_data['PluginURI'];
			if ( str_contains( $plugin_uri, 'git.robotstxt.es' ) ) {
				return rtrim( $plugin_uri, '/' ) . '/raw/branch/main/update.json';
			}
		}

		// Last resort: construct from plugin slug.
		return "https://git.robotstxt.es/ROBOTSTXT/{$this->plugin_slug}/raw/branch/main/update.json";
	}

	/**
	 * Inject update info into WP's plugin update transient.
	 *
	 * @param object|mixed $transient The update_plugins transient.
	 *
	 * @return object The modified transient.
	 */
	public function inject_update_info( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		if ( empty( $transient->checked[ $this->plugin_basename ] ) ) {
			return $transient;
		}

		$current_version = $transient->checked[ $this->plugin_basename ];
		$remote          = $this->get_remote_data();

		if ( empty( $remote['version'] ) || empty( $remote['download_url'] ) ) {
			return $transient;
		}

		if ( ! $this->is_compatible( $remote ) ) {
			return $transient;
		}

		if ( version_compare( $remote['version'], $current_version, '>' ) ) {
			$update = (object) array(
				'slug'         => $remote['slug'] ?? $this->plugin_slug,
				'plugin'       => $this->plugin_basename,
				'new_version'  => $remote['version'],
				'url'          => $remote['homepage'] ?? $this->plugin_data['PluginURI'] ?? '',
				'package'      => $remote['download_url'],
				'tested'       => $remote['tested'] ?? '',
				'requires'     => $remote['requires'] ?? '',
				'requires_php' => $remote['requires_php'] ?? '',
			);

			$transient->response[ $this->plugin_basename ] = $update;
		}

		return $transient;
	}

	/**
	 * Provide "View details" modal content.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 *
	 * @return false|object The plugin information object or false.
	 */
	public function provide_plugin_details( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$remote = $this->get_remote_data();

		if ( empty( $remote['version'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => $remote['name'] ?? $this->plugin_data['Name'] ?? $this->plugin_slug,
			'slug'          => $remote['slug'] ?? $this->plugin_slug,
			'version'       => $remote['version'],
			'author'        => $remote['author'] ?? $this->plugin_data['Author'] ?? '',
			'homepage'      => $remote['homepage'] ?? $this->plugin_data['PluginURI'] ?? '',
			'requires'      => $remote['requires'] ?? '',
			'tested'        => $remote['tested'] ?? '',
			'requires_php'  => $remote['requires_php'] ?? '',
			'sections'      => array(
				'description' => $remote['description'] ?? $this->plugin_data['Description'] ?? '',
				'changelog'   => $remote['changelog'] ?? '',
			),
			'download_link' => $remote['download_url'] ?? '',
		);
	}

	/**
	 * Get remote data with caching and HMAC signature verification.
	 *
	 * @return array Remote data.
	 */
	private function get_remote_data(): array {
		$cached = get_site_transient( $this->cache_key );

		// Verify HMAC signature if AUTH_SALT is defined and cache has signature.
		if ( false !== $cached && defined( 'AUTH_SALT' ) && '' !== AUTH_SALT ) {
			if ( is_array( $cached ) && isset( $cached['signature'], $cached['data'] ) ) {
				$expected_sig = hash_hmac( 'sha256', $this->cache_key . serialize( $cached['data'] ), AUTH_SALT );

				if ( hash_equals( $expected_sig, $cached['signature'] ) ) {
					// Signature valid, return data.
					return is_array( $cached['data'] ) ? $cached['data'] : array();
				}

				// Signature invalid, delete corrupted cache.
				delete_site_transient( $this->cache_key );
				$cached = false;
			}
		}

		// If no valid cache, fetch fresh data.
		if ( false === $cached ) {
			$remote = $this->fetch_json();

			// Store with HMAC signature if AUTH_SALT is available.
			if ( defined( 'AUTH_SALT' ) && '' !== AUTH_SALT ) {
				$payload = array(
					'data'      => $remote ?: array(),
					'timestamp' => time(),
					'signature' => hash_hmac( 'sha256', $this->cache_key . serialize( $remote ?: array() ), AUTH_SALT ),
				);
				set_site_transient( $this->cache_key, $payload, 6 * HOUR_IN_SECONDS );
			} else {
				// Fallback to standard caching.
				set_site_transient( $this->cache_key, $remote ?: array(), 6 * HOUR_IN_SECONDS );
			}

			return is_array( $remote ) ? $remote : array();
		}

		// Legacy cache format without signature (backward compatibility).
		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * Fetch JSON from remote URL.
	 *
	 * @return array Decoded JSON data.
	 */
	private function fetch_json(): array {
		$response = wp_remote_get(
			$this->json_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Check compatibility.
	 *
	 * @param array $remote Remote data.
	 *
	 * @return bool True if compatible.
	 */
	private function is_compatible( array $remote ): bool {
		if ( ! empty( $remote['requires_php'] ) ) {
			if ( version_compare( PHP_VERSION, $remote['requires_php'], '<' ) ) {
				return false;
			}
		}

		if ( ! empty( $remote['requires'] ) ) {
			if ( version_compare( get_bloginfo( 'version' ), $remote['requires'], '<' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Handle manual cache clear via URL parameter.
	 */
	public function handle_cache_clear(): void {
		// Check if this is a cache clear request first.
		$clear_cache = filter_input( INPUT_GET, 'robotstxt_clear_update_cache', FILTER_UNSAFE_RAW );
		if ( null === $clear_cache ) {
			return;
		}

		// This is a cache clear request - now verify nonce.
		$nonce_raw = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
		$nonce     = $nonce_raw ? sanitize_text_field( wp_unslash( $nonce_raw ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'robotstxt_clear_update_cache' ) ) {
			wp_die( esc_html__( 'Security check failed', 'idrivee2-media-upload' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'idrivee2-media-upload' ) );
		}

		$this->clear_cache();
		wp_safe_redirect( remove_query_arg( array( 'robotstxt_clear_update_cache', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Clear update cache.
	 */
	public function clear_cache(): void {
		delete_site_transient( $this->cache_key );
		delete_site_transient( 'update_plugins' );
	}
	}
}
