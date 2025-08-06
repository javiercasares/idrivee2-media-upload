<?php
/**
 * Plugin Name:       iDrivee2 Media Upload
 * Plugin URI:        https://github.com/javiercasares/idrivee2-media-upload
 * Description:       Uploads media files to iDrivee2 (S3-compatible).
 * Version:           0.1.2
 * Author:            Javier Casares
 * Text Domain:       idrivee2-media
 * Domain Path:       /languages
 */

declare(strict_types=1);

namespace iDrivee2Media;

// Security: prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader if available.
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Load plugin textdomain for translations.
 */
function load_textdomain(): void
{
    load_plugin_textdomain(
        'idrivee2-media',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', __NAMESPACE__ . '\\load_textdomain');

/**
 * Register settings page under Media menu.
 * Always register the page, even if constants are missing.
 */
function register_media_page(): void
{
    add_media_page(
        __('iDrivee2', 'idrivee2-media'),
        __('iDrivee2', 'idrivee2-media'),
        'manage_options',
        'idrivee2-media',
        __NAMESPACE__ . '\\render_settings_page'
    );
}
add_action('admin_menu', __NAMESPACE__ . '\\register_media_page');

/**
 * Enqueue admin scripts for connection test.
 */
function enqueue_admin_scripts(string $hook): void
{
    // Only load on our settings page
    if ($hook !== 'media_page_idrivee2-media') {
        return;
    }

    wp_enqueue_script(
        'idrivee2-media-admin',
        plugin_dir_url(__FILE__) . 'assets/admin.js',
        ['jquery'],
        '0.1.1',
        true
    );
    wp_localize_script('idrivee2-media-admin', 'iDrivee2Media', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('idrivee2_test_nonce'),
    ]);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_scripts');

/**
 * AJAX handler for testing S3 connection.
 */
function ajax_test_connection(): void
{
    check_ajax_referer('idrivee2_test_nonce', 'nonce');

    if (!defined('IDRIVEE2_MEDIA_HOST') || !defined('IDRIVEE2_MEDIA_KEY') || !defined('IDRIVEE2_MEDIA_SECRET')) {
        wp_send_json_error(__('Configuration constants missing.', 'idrivee2-media'));
    }

    try {
        // Instantiate S3 client
        $client = new \Aws\S3\S3Client([
            'version'     => 'latest',
            'endpoint'    => IDRIVEE2_MEDIA_HOST,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => IDRIVEE2_MEDIA_KEY,
                'secret' => IDRIVEE2_MEDIA_SECRET,
            ],
        ]);
        // Attempt list buckets
        $client->listBuckets();
        wp_send_json_success(__('Connection successful.', 'idrivee2-media'));
    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_idrivee2_test_connection', __NAMESPACE__ . '\\ajax_test_connection');

/**
 * Render the settings page.
 * Checks for required constants and displays appropriate UI.
 */
function render_settings_page(): void
{
    $hostDefined   = defined('IDRIVEE2_MEDIA_HOST');
    $keyDefined    = defined('IDRIVEE2_MEDIA_KEY');
    $secretDefined = defined('IDRIVEE2_MEDIA_SECRET');

    $host   = $hostDefined   ? IDRIVEE2_MEDIA_HOST   : '';
    $key    = $keyDefined    ? IDRIVEE2_MEDIA_KEY    : '';
    $secret = $secretDefined ? IDRIVEE2_MEDIA_SECRET : '';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('iDrivee2 Media Upload', 'idrivee2-media'); ?></h1>

        <?php if (!$hostDefined || !$keyDefined || !$secretDefined) : ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('To use iDrivee2 Media Upload, add the following constants to your wp-config.php:', 'idrivee2-media'); ?></p>
                <pre>
define('IDRIVEE2_MEDIA_HOST',  'your-s3-host.amazonaws.com');
define('IDRIVEE2_MEDIA_KEY',   'YOUR_ACCESS_KEY_ID');
define('IDRIVEE2_MEDIA_SECRET','YOUR_SECRET_ACCESS_KEY');
                </pre>
            </div>
        <?php else : ?>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Host', 'idrivee2-media'); ?></th>
                    <td><code><?php echo esc_html($host); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Access Key', 'idrivee2-media'); ?></th>
                    <td><code><?php echo esc_html($key); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Secret Key', 'idrivee2-media'); ?></th>
                    <td><code><?php echo str_repeat('*', strlen($secret)); ?></code></td>
                </tr>
            </table>
            <p>
                <button id="idrivee2-test-button" class="button button-primary">
                    <?php esc_html_e('Test S3 Connection', 'idrivee2-media'); ?>
                </button>
            </p>
            <div id="idrivee2-test-result" style="margin-top:1em;"></div>
        <?php endif; ?>
    </div>
    <?php
}
