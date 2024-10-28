<?php
/**
 * Plugin Name:       Web Accessibility with Max Access
 * Description:       The Future is Accessible
 * Version:           2.0.7
 * Author:            Ability, Inc.
 * Author URI:        https://maxaccess.io/
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.7
 * Requires PHP: 7.4
 */


if (!defined('ABSPATH')) {
    exit;
}

define('MA_DIR', __DIR__);

define('MA_VERSION', '2.0.0');

define('MA_MODE', 'prod');

define('MA_PLUGIN_PATH', plugin_dir_path( __FILE__ ));

define('MA_FILE_PATH', __FILE__);

define('MA_NAMESPACE', __NAMESPACE__);


function loadScripts()
{
    wp_register_script('accessibility-toolbar', '/wp-content/plugins/accessibility-toolbar/src/admin.js');
    wp_localize_script('accessibility-toolbar', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    wp_enqueue_script('accessibility-toolbar', '/wp-content/plugins/accessibility-toolbar/src/admin.js');

    wp_register_style('accessibility-toolbar-styles','/wp-content/plugins/accessibility-toolbar/src/style.css');
    wp_enqueue_style('accessibility-toolbar-styles', '/wp-content/plugins/accessibility-toolbar/src/style.css');
}

add_action('admin_enqueue_scripts', 'loadScripts');

// the vue code needs type=module.
//thx to //https://micksp.medium.com/integrating-vue-in-a-wordpress-plugin-135f875c9913 for this snippet
add_filter('script_loader_tag', function($tag, $handle, $src) {
    if ( 'accessibility-toolbar' === $handle ) {
        // change the script tag by adding type="module" and return it.
        $tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
        return $tag;
    }

    return $tag;
} , 10, 3);

add_action( 'init', 'ma_plugin__activated');

function ma_plugin__activated() {
    $old_license = get_option('ll_at_license');
    if ( isset($old_license) && !empty($old_license) ) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://accounts.onlineada.com' . '/api/ada-toolbar-check/'. $old_license);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $return_value = curl_exec($ch);
        curl_close($ch);
        $return_value = json_decode($return_value);

        if ( isset($return_value->license) && !empty($return_value->license) ) {
            delete_option('ll_at_license');
            update_option('toolbar_license_key', $return_value->license->key);
        } else {
            delete_option('ll_at_license');
            update_option('toolbar_license_key', $old_license);
        }
    }
}

add_filter( 'plugin_row_meta', 'add_links_to_plugin_installed_menu', 10, 2 );

function add_links_to_plugin_installed_menu($plugin_meta, $plugin_file){
    $plugin_root = 'accessibility-toolbar/index.php';
    if ( $plugin_root === $plugin_file ) {
        $plugin_path = get_admin_url() . 'admin.php?page=max-access-plugin-menu';
        $row_meta = [
            'docs' => '<a href="'. $plugin_path .'" aria-label="' . esc_attr( esc_html__( 'Go to Settings Page', '' ) ) . '">' . esc_html__( 'Settings', '' ) . '</a>',
            'ideo' => '<a href="https://maxaccess.io/setup" aria-label="' . esc_attr( esc_html__( 'View MaxAccess Setup Instructions', 'maxaccess' ) ) . '" target="_blank">' . esc_html__( 'Setup Instructions', 'maxaccess' ) . '</a>',
        ];

        $plugin_meta = array_merge( $plugin_meta, $row_meta );
    }

    return $plugin_meta;
}

add_action('admin_menu', 'register_ma_plugin_menu', 9);

function register_ma_plugin_menu(){
    add_menu_page(
        'Web Accessibility',
        'Web Accessibility',
        'manage_options',
        '/max-access-plugin-menu',
        'display_ma_plugin_menu',
        'dashicons-admin-generic',
        null
    );

    add_submenu_page(
        '/max-access-plugin-menu',
        'General Settings',
        'General Settings',
        'manage_options',
        '/admin.php?page=max-access-plugin-menu',
        null
    );

    add_submenu_page(
        '/max-access-plugin-menu',
        'Setup Instructions',
        'Setup Instructions',
        'manage_options',
        '/admin.php?page=max-access-plugin-menu/setup',
        'redirect_to_setup_page',
        2
    );

    add_submenu_page(
        '/max-access-plugin-menu',
        'Pro Features',
        'Pro Features',
        'manage_options',
        '/admin.php?page=max-access-plugin-menu/features',
        'redirect_to_pro_features_page',
        3
    );

    add_submenu_page(
        '/max-access-plugin-menu',
        'Pricing',
        'Pricing',
        'manage_options',
        '/admin.php?page=max-access-plugin-menu/pricing',
        'redirect_to_pricing_page',
        4
    );


    add_submenu_page(
        '/max-access-plugin-menu',
        'Get Certified',
        'Get Certified',
        'manage_options',
        '/admin.php?page=max-access-plugin-menu/get-certified',
        'redirect_to_get_certified_page',
        5
    );

    //remove the redundant top level link...
    global $submenu;
    unset($submenu['max-access-plugin-menu'][0]);
};

function redirect_to_pro_features_page(){
//    echo '<script>window.open("https://maxaccess.io/features", "_blank")</script>';

    wp_redirect( 'https://maxaccess.io/features' );
}

function redirect_to_pricing_page(){
    wp_redirect( 'https://maxaccess.io/pricing' );
}

function redirect_to_setup_page(){
    wp_redirect( 'https://maxaccess.io/setup/wordpress/' );
}

function redirect_to_get_certified_page(){
    wp_redirect( 'https://onlineada.com/certification/' );
}

function display_ma_plugin_menu(){
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    echo '<div id="oada_accessibility_toolbar_admin"></div>';
}

add_action('wp_ajax_get_licenses', 'get_licenses');

function get_licenses(){
    $ch = curl_init();

    if(!defined('OADA_ACCOUNTS_URL')) {
        define('OADA_ACCOUNTS_URL', 'https://accounts.onlineada.com');
    }

    $key = $_GET['license_key'] == 'init' ? get_option('toolbar_license_key') : $_GET['license_key'];

    curl_setopt($ch, CURLOPT_URL, OADA_ACCOUNTS_URL . '/api/ada-toolbar-check/'. $key);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $return_value = curl_exec($ch);
    curl_close($ch);

    $return_value = json_decode($return_value, true);

    if ( isset($return_value) && is_array($return_value) ) {
        //we returned an upgraded license
        if($return_value['key'] !== $return_value['license']['key']) {
            update_option('toolbar_license_key', $return_value['license']['key']);
        } else if ( $_GET['license_key'] != 'init' ) {
            update_option('toolbar_license_key', $_GET['license_key']);
        }

        $return_value = json_encode($return_value);
        echo $return_value;
        wp_die();
    } else {
        wp_die();
    }
}

add_action( 'wp_loaded', 'inject_toolbar_scripts');

function inject_toolbar_scripts()
{

    if ( !is_admin() ) {
        $key = get_option('toolbar_license_key');

        //api.maxaccess.io
        $script = 'var oada_ma_license_key="'. $key .'";var oada_ma_license_url="https://api.maxaccess.io/scripts/toolbar/'. $key .'";(function(s,o,g){a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.src=g;a.setAttribute("async","");a.setAttribute("type","text/javascript");a.setAttribute("crossorigin","anonymous");m.parentNode.insertBefore(a,m)})(document,"script",oada_ma_license_url+oada_ma_license_key);';

        wp_register_script('ma_toolbar_script', '');
        wp_enqueue_script('ma_toolbar_script', '', null, NULL, false);
        wp_add_inline_script('ma_toolbar_script', $script);
    }
}