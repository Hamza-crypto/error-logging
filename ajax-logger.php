<?php
/*
Plugin Name: AJAX Logger
Description: Logs AJAX requests, JavaScript errors, and custom alerts on a specific page.
Version: 1.0
Author: Hamza Siddique
*/

if (!defined('ABSPATH')) {
    exit;
}

define('AJAX_LOGGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AJAX_LOGGER_LOG_FILE', WP_CONTENT_DIR . '/ajax-logger.log');

register_activation_hook(__FILE__, 'ajax_logger_activate');

function ajax_logger_activate() {
    if (!file_exists(AJAX_LOGGER_LOG_FILE)) {
        file_put_contents(AJAX_LOGGER_LOG_FILE, "=== AJAX Logger Log File ===\n");
    }
}

add_action('admin_menu', 'ajax_logger_add_settings_page');
function ajax_logger_add_settings_page() {
    add_options_page(
        'AJAX Logger Settings',
        'AJAX Logger',
        'manage_options',
        'ajax-logger',
        'ajax_logger_render_settings_page'
    );
}

function ajax_logger_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['ajax_logger_page_id'])) {
        check_admin_referer('ajax_logger_settings');
        update_option('ajax_logger_page_id', intval($_POST['ajax_logger_page_id']));
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $page_id = get_option('ajax_logger_page_id', '');

    ?>
    <div class="wrap">
        <h1>AJAX Logger Settings</h1>
        <form method="post">
            <?php wp_nonce_field('ajax_logger_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="ajax_logger_page_id">Target Page ID</label></th>
                    <td>
                        <input type="number" name="ajax_logger_page_id" id="ajax_logger_page_id" value="<?php echo esc_attr($page_id); ?>" required>
                        <p class="description">Enter the ID of the page where logging should be active.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('wp_enqueue_scripts', 'ajax_logger_enqueue_scripts');
function ajax_logger_enqueue_scripts() {
    $target_page_id = get_option('ajax_logger_page_id');
    if (is_page($target_page_id)) {
        wp_enqueue_script(
            'ajax-logger',
            plugins_url('assets/js/logger.js', __FILE__),
            array('jquery'),
            '1.0',
            true
        );

        wp_localize_script(
            'ajax-logger',
            'ajax_logger_data',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ajax_logger_nonce'),
                'page_id'  => $target_page_id,
            )
        );
    }
}

add_action('wp_ajax_ajax_logger_save_logs', 'ajax_logger_save_logs');
function ajax_logger_save_logs() {
    check_ajax_referer('ajax_logger_nonce', 'nonce');

    $logs = isset($_POST['logs']) ? json_decode(stripslashes($_POST['logs']), true) : array();
    
    $user_id = get_current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'user_id'   => $user_id,
        'ip'       => $ip_address,
        'logs'     => $logs,
    );

    file_put_contents(AJAX_LOGGER_LOG_FILE, print_r($log_entry, true) . "\n", FILE_APPEND);

    wp_send_json_success('Logs saved.');
}