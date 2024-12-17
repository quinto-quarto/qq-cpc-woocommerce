<?php
namespace QQCPC\Base;

class Deactivate {
    public static function deactivate() {
        flush_rewrite_rules();
        
        // Clear scheduled cron job
        $timestamp = wp_next_scheduled('qq_cpc_check_order_status');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'qq_cpc_check_order_status');
        }
    }
}
