<?php
/**
 * URL Rewriter for iDrivee2 Media Upload.
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
 * URL rewriter for serving media from iDrivee2 CDN.
 *
 * Rewrites WordPress media URLs to use the custom CDN domain when configured.
 *
 * @since 0.3.0
 */
class URL_Rewriter {
	/**
	 * Configuration instance.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Config $config Configuration instance.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Register hooks for URL rewriting.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'pre_option_upload_url_path', array( $this, 'filter_upload_url_path' ) );
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_attachment_url' ), 1, 2 );
	}

	/**
	 * Override the upload URL path with the configured iDrivee2 domain.
	 *
	 * If the IDRIVEE2_MEDIA_DOMAIN constant is defined and non-empty, this
	 * filter replaces the default uploads base URL so all new media items
	 * use the custom domain.
	 *
	 * @since 0.3.0
	 *
	 * @param string $value The original value of the upload_url_path option.
	 * @return string The upload_url_path, overridden to the custom domain when set.
	 */
	public function filter_upload_url_path( string $value ): string {
		if ( $this->config->has_domain() ) {
			return untrailingslashit( $this->config->get_domain() );
		}
		return $value;
	}

	/**
	 * Rewrite attachment URLs to use the configured iDrivee2 domain.
	 *
	 * Intercepts all calls to wp_get_attachment_url() and replaces the
	 * default uploads base URL with the custom IDRIVEE2_MEDIA_DOMAIN,
	 * ensuring front-end and AJAX previews load from the S3-compatible host.
	 *
	 * @since 0.3.0
	 *
	 * @param string $url     The original attachment URL.
	 * @param int    $post_id The attachment post ID (required by filter signature, unused).
	 * @return string The filtered URL, using the iDrivee2 domain if defined.
	 */
	public function filter_attachment_url( string $url, int $post_id ): string {
		if ( $this->config->has_domain() ) {
			$uploads  = wp_upload_dir();
			$old_base = untrailingslashit( $uploads['baseurl'] );
			$new_base = untrailingslashit( $this->config->get_domain() );
			return str_replace( $old_base, $new_base, $url );
		}
		return $url;
	}
}
