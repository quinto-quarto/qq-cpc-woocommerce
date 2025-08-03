<?php
/**
 * PHPUnit bootstrap file for QQ CPC WooCommerce plugin tests
 */

// Composer autoloader
require_once dirname(__DIR__) . '/autoload.php';

// WordPress test functions
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    require dirname(__DIR__) . '/qq-cpc-woocommerce.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

/**
 * Activate WooCommerce for tests
 */
function _manually_activate_woocommerce() {
    // Check if WooCommerce is available
    if (class_exists('WooCommerce')) {
        return;
    }
    
    // Try to activate WooCommerce if plugin file exists
    $woocommerce_plugin = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    if (file_exists($woocommerce_plugin)) {
        require_once $woocommerce_plugin;
    }
}
tests_add_filter('plugins_loaded', '_manually_activate_woocommerce', 0);

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test utilities
require_once __DIR__ . '/utilities/class-test-utilities.php';