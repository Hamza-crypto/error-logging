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
define('AJAX_LOGGER_LOG_DIR', WP_CONTENT_DIR . '/ajax-logs');

register_activation_hook(__FILE__, 'ajax_logger_activate');

function ajax_logger_activate() {
  if (!file_exists(AJAX_LOGGER_LOG_DIR)) {
    wp_mkdir_p(AJAX_LOGGER_LOG_DIR);
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

  if (isset($_POST['ajax_logger_page_id']) || isset($_POST['ajax_logger_url_path'])) {
      check_admin_referer('ajax_logger_settings');
      update_option('ajax_logger_page_id', intval($_POST['ajax_logger_page_id']));
      update_option('ajax_logger_url_path', sanitize_text_field($_POST['ajax_logger_url_path']));
      echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
  }

  $page_id = get_option('ajax_logger_page_id', '');
  $url_path = get_option('ajax_logger_url_path', '');

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
              <tr>
                  <th scope="row"><label for="ajax_logger_url_path">Target URL Path</label></th>
                  <td>
                      <input type="text" name="ajax_logger_url_path" id="ajax_logger_url_path" value="<?php echo esc_attr($url_path); ?>" placeholder="e.g., panel/create">
                      <p class="description">Enter the URL path where logging should be active (e.g., <code>panel/create</code>).</p>
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
  $target_url_path = get_option('ajax_logger_url_path', '');

  if (is_page($target_page_id)) {
      if (empty($target_url_path) || strpos($_SERVER['REQUEST_URI'], $target_url_path) !== false) {
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
                  'url_path' => $target_url_path,
              )
          );
      }
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

    $log_file = AJAX_LOGGER_LOG_DIR . '/ajax-log-' . date('Y-m-d') . '.log';

    file_put_contents(
      $log_file,
      json_encode($log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
      FILE_APPEND
  );

    wp_send_json_success('Logs saved.');
}