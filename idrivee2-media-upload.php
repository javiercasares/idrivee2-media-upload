<?php
/**
 * Plugin Name:       iDrivee2 Media Upload
 * Plugin URI:        https://github.com/javiercasares/idrivee2-media-upload
 * Description:       Uploads media files to iDrivee2 (S3-compatible).
 * Version:           0.1.13
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
 * Enqueue admin scripts and localize labels for AJAX.
 */
function enqueue_admin_scripts(string $hook): void
{
    if ($hook !== 'media_page_idrivee2-media') {
        return;
    }

    wp_enqueue_script(
        'idrivee2-media-admin',
        plugin_dir_url(__FILE__) . 'assets/admin.js',
        ['jquery'],
        '0.1.12',
        true
    );
    wp_localize_script('idrivee2-media-admin', 'iDrivee2Media', [
        'ajaxUrl'           => admin_url('admin-ajax.php'),
        'nonce'             => wp_create_nonce('idrivee2_test_nonce'),
        'buttonLabel'       => __('Test S3 Connection', 'idrivee2-media'),
        'testingLabel'      => __('Testing...', 'idrivee2-media'),
        'uploadButtonLabel' => __('Upload Test File', 'idrivee2-media'),
        'uploadingLabel'    => __('Uploading…', 'idrivee2-media'),
        'domain'            => defined('IDRIVEE2_MEDIA_DOMAIN') ? IDRIVEE2_MEDIA_DOMAIN : '',
    ]);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_scripts');

/**
 * AJAX handler for testing S3 connection.
 */
function ajax_test_connection(): void
{
    check_ajax_referer('idrivee2_test_nonce', 'nonce');
    foreach (['IDRIVEE2_MEDIA_HOST','IDRIVEE2_MEDIA_KEY','IDRIVEE2_MEDIA_SECRET','IDRIVEE2_MEDIA_BUCKET','IDRIVEE2_MEDIA_REGION'] as $c) {
        if (!defined($c)) {
            wp_send_json_error(sprintf(__('Missing constant %s.', 'idrivee2-media'), $c));
        }
    }
    try {
        $client = new \Aws\S3\S3Client([
            'version'                 => 'latest',
            'region'                  => IDRIVEE2_MEDIA_REGION,
            'endpoint'                => IDRIVEE2_MEDIA_HOST,
            'use_path_style_endpoint' => true,
            'credentials'             => ['key'=>IDRIVEE2_MEDIA_KEY,'secret'=>IDRIVEE2_MEDIA_SECRET],
        ]);
        $client->headBucket(['Bucket'=>IDRIVEE2_MEDIA_BUCKET]);
        wp_send_json_success(__('Connection successful.', 'idrivee2-media'));
    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_idrivee2_test_connection', __NAMESPACE__ . '\\ajax_test_connection');

/**
 * AJAX handler for uploading a test file.
 */
function ajax_upload_test_file(): void
{
    check_ajax_referer('idrivee2_test_nonce', 'nonce');
    if (empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name'])) {
        wp_send_json_error(__('No file provided.', 'idrivee2-media'));}
    try {
        $client=new \Aws\S3\S3Client([
            'version'=>'latest','region'=>IDRIVEE2_MEDIA_REGION,'endpoint'=>IDRIVEE2_MEDIA_HOST,
            'use_path_style_endpoint'=>true,'credentials'=>['key'=>IDRIVEE2_MEDIA_KEY,'secret'=>IDRIVEE2_MEDIA_SECRET],
        ]);
        $tmp=$_FILES['file']['tmp_name'];$name=sanitize_file_name($_FILES['file']['name']);
        $res=$client->putObject(['Bucket'=>IDRIVEE2_MEDIA_BUCKET,'Key'=>$name,'Body'=>fopen($tmp,'rb'),'ACL'=>'public-read']);
        wp_send_json_success($res->toArray());
    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_idrivee2_upload_test_file', __NAMESPACE__ . '\\ajax_upload_test_file');

// Hook after metadata generation
add_filter('wp_generate_attachment_metadata',__NAMESPACE__.'\\upload_attachment_to_idrivee2',10,2);
/**
 * Uploads generated attachment files to iDrivee2, uses the returned ObjectURL,
 * and deletes local copies.
 *
 * @param array $meta Metadata array for the attachment.
 * @param int   $id   Attachment ID.
 * @return array      Unchanged metadata.
 */
function upload_attachment_to_idrivee2(array $meta, int $id): array {
    // Bail if not configured
    if (
        ! defined('IDRIVEE2_MEDIA_HOST') ||
        ! defined('IDRIVEE2_MEDIA_KEY') ||
        ! defined('IDRIVEE2_MEDIA_SECRET') ||
        ! defined('IDRIVEE2_MEDIA_BUCKET') ||
        ! defined('IDRIVEE2_MEDIA_REGION')
    ) {
        return $meta;
    }

    // Initialize S3 client
    $client = new \Aws\S3\S3Client([
        'version'                 => 'latest',
        'region'                  => IDRIVEE2_MEDIA_REGION,
        'endpoint'                => IDRIVEE2_MEDIA_HOST,
        'use_path_style_endpoint' => true,
        'credentials'             => [
            'key'    => IDRIVEE2_MEDIA_KEY,
            'secret' => IDRIVEE2_MEDIA_SECRET,
        ],
    ]);

    // Prepare file list: original + sizes
    $upload_dir = wp_upload_dir();
    $base_path  = path_join($upload_dir['basedir'], $meta['file']);
    $files      = ['original' => $base_path];
    foreach ($meta['sizes'] ?? [] as $size) {
        $files[$size['file']] = path_join(dirname($base_path), $size['file']);
    }

    $objectUrl = '';

    // Upload each file
    foreach ($files as $key => $path) {
        if (! file_exists($path)) {
            continue;
        }
        $object_key = $key === 'original'
            ? $meta['file']
            : dirname($meta['file']) . '/' . $key;

        $result = $client->putObject([
            'Bucket' => IDRIVEE2_MEDIA_BUCKET,
            'Key'    => $object_key,
            'Body'   => fopen($path, 'rb'),
            'ACL'    => 'public-read',
        ]);

        // For the original file, grab its ObjectURL
        if ($key === 'original' && isset($result['ObjectURL'])) {
            $objectUrl = $result['ObjectURL'];
        }

        @unlink($path);
    }

    // Ensure WP keeps the relative path
    update_post_meta($id, '_wp_attached_file', $meta['file']);

    // Override the attachment URL to the S3 ObjectURL
    if ($objectUrl) {
        add_filter('wp_get_attachment_url', function($url, $pid) use ($objectUrl, $id) {
            return $pid === $id ? $objectUrl : $url;
        }, 10, 2);
    }

    return $meta;
}

/**
 * Render settings page.
 */
function render_settings_page():void{
    $h=defined('IDRIVEE2_MEDIA_HOST')?IDRIVEE2_MEDIA_HOST:'';
    $k=defined('IDRIVEE2_MEDIA_KEY')?IDRIVEE2_MEDIA_KEY:'';
    $s=defined('IDRIVEE2_MEDIA_SECRET')?IDRIVEE2_MEDIA_SECRET:'';
    $b=defined('IDRIVEE2_MEDIA_BUCKET')?IDRIVEE2_MEDIA_BUCKET:'';
    $r=defined('IDRIVEE2_MEDIA_REGION')?IDRIVEE2_MEDIA_REGION:'';
    $d=defined('IDRIVEE2_MEDIA_DOMAIN')?IDRIVEE2_MEDIA_DOMAIN:'';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('iDrivee2 Media Upload','idrivee2-media');?></h1>
        <?php if(!$h||!$k||!$s||!$b||!$r):?><div class="notice notice-error"><p><?php esc_html_e('To use iDrivee2 Media Upload, add the constants to wp-config.php:','idrivee2-media');?></p><p><?php esc_html_e('HOST must begin with "https://"','idrivee2-media');?></p><pre>define('IDRIVEE2_MEDIA_HOST','https://your-s3-host.amazonaws.com');
define('IDRIVEE2_MEDIA_KEY','YOUR_ACCESS_KEY_ID');
define('IDRIVEE2_MEDIA_SECRET','YOUR_SECRET_ACCESS_KEY');
define('IDRIVEE2_MEDIA_BUCKET','your-bucket-name');
define('IDRIVEE2_MEDIA_REGION','us-east-1');</pre></div><?php endif;?>
        <table class="form-table">
            <tr><th><?php esc_html_e('Host','idrivee2-media');?></th><td><code><?php echo esc_html($h);?></code></td></tr>
            <tr><th><?php esc_html_e('Access Key','idrivee2-media');?></th><td><code><?php echo esc_html($k);?></code></td></tr>
            <tr><th><?php esc_html_e('Secret Key','idrivee2-media');?></th><td><code><?php echo str_repeat('*',strlen($s));?></code></td></tr>
            <tr><th><?php esc_html_e('Bucket','idrivee2-media');?></th><td><code><?php echo esc_html($b);?></code></td></tr>
            <tr><th><?php esc_html_e('Region','idrivee2-media');?></th><td><code><?php echo esc_html($r);?></code></td></tr>
            <tr><th><?php esc_html_e('Domain','idrivee2-media');?></th><td><code><?php echo $d?esc_html($d):__('Not defined','idrivee2-media');?></code></td></tr>
        </table>
        <p><button id="idrivee2-test-button" class="button button-primary"><?php esc_html_e('Test S3 Connection','idrivee2-media');?></button></p>
        <p><button id="idrivee2-upload-button" class="button button-secondary"><?php esc_html_e('Upload Test File','idrivee2-media');?></button></p>
        <div id="idrivee2-test-result" style="margin-top:1em; white-space:pre-wrap;"></div>
    </div>
    <?php
}
