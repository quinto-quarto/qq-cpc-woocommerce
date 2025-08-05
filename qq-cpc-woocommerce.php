<?php
/**
 * Plugin Name: QQ CPC for WooCommerce
 * Description: CPC integration for WooCommerce
 * Version: 0.2.4
 * Author: Quinto Quarto
 * Text Domain: wcosm
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Buffer output during activation to prevent "headers already sent" issues
if (!defined('WP_UNINSTALL_PLUGIN')) {
    ob_start();
}

// Plugin constants
define('QQ_CPC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('QQ_CPC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QQ_CPC_API_ENDPOINT', 'https://cpcapi.erpmanagement.it/API/2.0/Ordine/Stato');

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once QQ_CPC_PLUGIN_PATH . 'inc/Base/Activate.php';
    QQCPC\Base\Activate::activate();
    
    // Clean up any buffered output
    if (ob_get_length()) {
        ob_end_clean();
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    require_once QQ_CPC_PLUGIN_PATH . 'inc/Base/Deactivate.php';
    QQCPC\Base\Deactivate::deactivate();
});

// Initialize plugin
add_action('plugins_loaded', function() {
    // Load autoloader
    require_once QQ_CPC_PLUGIN_PATH . 'autoload.php';

    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>QQ CPC requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    // Initialize all plugin components
    require_once QQ_CPC_PLUGIN_PATH . 'inc/Init.php';
    if (class_exists('QQCPC\\Init')) {
        $plugin = new QQCPC\Init();
        $plugin->register_services();
    }
});

// Load translations
add_action('init', function() {
    load_plugin_textdomain('wcosm', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
