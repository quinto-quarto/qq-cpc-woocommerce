<?php
namespace QQCPC\Base;

class Enqueue {
    public function register() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin'));
        add_action('init', array($this, 'load_textdomain'));
    }

    public function enqueue_admin() {
        // Only enqueue on our plugin pages
        $screen = get_current_screen();
        if (strpos($screen->base, 'qq-cpc') !== false) {
            wp_enqueue_style(
                'qq-cpc-admin',
                QQ_CPC_PLUGIN_URL . 'assets/css/qq-cpc-admin.css',
                array(),
                '1.0.0'
            );
        }
    }

    function load_textdomain() {
        load_plugin_textdomain(
            'qq-cpc-woocommerce',
            false,
            dirname(plugin_basename(dirname(__FILE__, 3))) . '/languages/'
        );
    }
}
