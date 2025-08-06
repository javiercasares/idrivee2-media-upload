<?php
/**
 * Plugin Name:       iDrivee2 Media Upload
 * Plugin URI:        https://github.com/javiercasares/idrivee2-media-upload
 * Description:       Uploads media files to iDrivee2 (S3-compatible).
 * Version:           0.3.0
 * Author:            Javier Casares
 * Text Domain:       idrivee2-media
 * Domain Path:       /languages
 */

/**
 * Prevent direct access to this file.
 *
 * If this file is called directly, abort execution for security.
 *
 * @package iDrivee2Media
 */
declare(strict_types=1);
namespace iDrivee2Media;

// Security: prevent direct access.
if (!defined('ABSPATH')) {
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
        'idrivee2-media',
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
        __( 'iDrivee2', 'idrivee2-media' ),
        /* translators: Admin menu label. */
        __( 'iDrivee2', 'idrivee2-media' ),
        'manage_options',
        'idrivee2-media',
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
        [ 'jquery' ],
        '0.1.13',
        true
    );

    wp_localize_script(
        'idrivee2-media-admin',
        'iDrivee2Media',
        [
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'idrivee2_test_nonce' ),
            'buttonLabel'       => __( 'Test S3 Connection', 'idrivee2-media' ),
            'testingLabel'      => __( 'Testing...', 'idrivee2-media' ),
            'uploadButtonLabel' => __( 'Upload Test File', 'idrivee2-media' ),
            'uploadingLabel'    => __( 'Uploading...', 'idrivee2-media' ),
            'domain'            => defined( 'IDRIVEE2_MEDIA_DOMAIN' ) ? IDRIVEE2_MEDIA_DOMAIN : '',
        ]
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

    $required_constants = [
        'IDRIVEE2_MEDIA_HOST',
        'IDRIVEE2_MEDIA_KEY',
        'IDRIVEE2_MEDIA_SECRET',
        'IDRIVEE2_MEDIA_BUCKET',
        'IDRIVEE2_MEDIA_REGION',
    ];

    foreach ( $required_constants as $constant ) {
        if ( ! defined( $constant ) ) {
            wp_send_json_error(
                sprintf(
                    /* translators: %s is the name of the missing constant. */
                    __( 'Missing constant %s.', 'idrivee2-media' ),
                    esc_html( $constant )
                )
            );
        }
    }

    try {
        $client = new \Aws\S3\S3Client( [
            'version'                 => 'latest',
            'region'                  => IDRIVEE2_MEDIA_REGION,
            'endpoint'                => IDRIVEE2_MEDIA_HOST,
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => IDRIVEE2_MEDIA_KEY,
                'secret' => IDRIVEE2_MEDIA_SECRET,
            ],
        ] );

        $client->headBucket( [ 'Bucket' => IDRIVEE2_MEDIA_BUCKET ] );
        wp_send_json_success( __( 'Connection successful.', 'idrivee2-media' ) );
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


add_filter( 'pre_option_upload_url_path', function( $value ) {
    return ( defined('IDRIVEE2_MEDIA_DOMAIN') && IDRIVEE2_MEDIA_DOMAIN )
        ? untrailingslashit( IDRIVEE2_MEDIA_DOMAIN )
        : $value;
} );

// 1) Siempre devuelve tu dominio al pedir la URL de un attachment
add_filter( 'wp_get_attachment_url', function( $url, $post_id ) {
    if ( defined( 'IDRIVEE2_MEDIA_DOMAIN' ) && IDRIVEE2_MEDIA_DOMAIN ) {
        $uploads = wp_upload_dir();
        $old_base = untrailingslashit( $uploads['baseurl'] );
        $new_base = untrailingslashit( IDRIVEE2_MEDIA_DOMAIN );
        return str_replace( $old_base, $new_base, $url );
    }
    return $url;
}, 1, 2 );

// 1) Covers AJAX async-uploads
add_action('add_attachment', function(int $post_id) {
    $meta = wp_get_attachment_metadata($post_id);
    if ($meta) {
        upload_attachment_to_idrivee2($meta, $post_id);
    }
});

// 2) Initial metadata generation
add_filter(
    'wp_generate_attachment_metadata',
    __NAMESPACE__ . '\\upload_attachment_to_idrivee2',
    10, 2
);

// 3) Regenerations / editor “Save”
add_filter(
    'wp_update_attachment_metadata',
    __NAMESPACE__ . '\\upload_attachment_to_idrivee2',
    10, 2
);

// 4) Any edit to the attachment
add_action('edit_attachment', function(int $post_id) {
    $meta = wp_get_attachment_metadata($post_id);
    if ($meta) {
        upload_attachment_to_idrivee2($meta, $post_id);
    }
});

// Hook para reescribir la URL nada más subir (incluye async-upload AJAX)
add_filter( 'wp_handle_upload', function( array $upload ): array {
    if (
        defined('IDRIVEE2_MEDIA_DOMAIN') &&
        IDRIVEE2_MEDIA_DOMAIN &&
        ! empty( $upload['url'] )
    ) {
        $uploads  = wp_upload_dir();
        $old_base = untrailingslashit( $uploads['baseurl'] );
        $new_base = untrailingslashit( IDRIVEE2_MEDIA_DOMAIN );
        // Sustituye la base local por tu dominio
        $upload['url'] = str_replace( $old_base, $new_base, $upload['url'] );
    }
    return $upload;
} );



/**
 * After WP generates attachment sizes, upload them to iDrivee2,
 * capture the returned ObjectURL for the original file,
 * delete the local copies, update the post GUID, and override WP’s URL.
 *
 * @param array $meta Attachment metadata (sizes, file path).
 * @param int   $id   Attachment post ID.
 * @return array      Unchanged metadata.
 */
function upload_attachment_to_idrivee2(array $meta, int $id): array {
    // Comprueba configuración
    if (
        ! defined('IDRIVEE2_MEDIA_HOST') ||
        ! defined('IDRIVEE2_MEDIA_KEY') ||
        ! defined('IDRIVEE2_MEDIA_SECRET') ||
        ! defined('IDRIVEE2_MEDIA_BUCKET') ||
        ! defined('IDRIVEE2_MEDIA_REGION')
    ) {
        return $meta;
    }

    // Cliente S3
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

    // Lista de ficheros: original + tamaños
    $u = wp_upload_dir();
    $base = path_join($u['basedir'], $meta['file']);
    $files = ['original' => $base];
    foreach ($meta['sizes'] ?? [] as $size) {
        $files[$size['file']] = path_join(dirname($base), $size['file']);
    }

    $objectUrl = '';

    // Subir cada fichero y capturar ObjectURL del original
    foreach ($files as $key => $path) {
        if (! file_exists($path)) {
            continue;
        }
        $object_key = ($key === 'original')
            ? $meta['file']
            : dirname($meta['file']) . '/' . $key;

        $result = $client->putObject([
            'Bucket' => IDRIVEE2_MEDIA_BUCKET,
            'Key'    => $object_key,
            'Body'   => fopen($path, 'rb'),
            'ACL'    => 'public-read',
        ]);

        if ($key === 'original' && ! empty($result['ObjectURL'])) {
            $objectUrl = $result['ObjectURL'];
        }

        @unlink($path);
    }

    // Mantén WP con ruta relativa en meta
    update_post_meta($id, '_wp_attached_file', $meta['file']);





    // Si tenemos ObjectURL, actualiza el GUID y ajusta wp_get_attachment_url
    if ($objectUrl) {
        // 1) GUID en wp_posts
        wp_update_post([
            'ID'   => $id,
            'guid' => $objectUrl,
        ]);
        // 2) URL en el front-end
        add_filter(
            'wp_get_attachment_url',
            function($url, $pid) use ($objectUrl, $id) {
                return ($pid === $id) ? $objectUrl : $url;
            },
            10,
            2
        );
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
