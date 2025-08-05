<?php
namespace QQCPC\Utils;

class Logger {
    private static $option_name = 'qq_cpc_action_logs';
    private static $max_logs = 50; // Keep last 50 actions

    public static function log($action, $order_id, $details = '', $status = 'success') {
        $logs = get_option(self::$option_name, array());
        
        $log_entry = array(
            'timestamp' => current_time('timestamp'),
            'action' => $action,
            'order_id' => $order_id,
            'details' => $details,
            'status' => $status
        );
        
        // Add to beginning of array
        array_unshift($logs, $log_entry);
        
        // Keep only the most recent logs
        if (count($logs) > self::$max_logs) {
            $logs = array_slice($logs, 0, self::$max_logs);
        }
        
        update_option(self::$option_name, $logs);
    }
    
    public static function get_logs($limit = null) {
        $logs = get_option(self::$option_name, array());
        
        if ($limit && count($logs) > $limit) {
            return array_slice($logs, 0, $limit);
        }
        
        return $logs;
    }
    
    public static function clear_logs() {
        delete_option(self::$option_name);
    }
    
    public static function format_action($action) {
        $actions_map = array(
            'order_checked' => 'Manual Order Check',
            'order_completed' => 'Order Auto-Completed',
            'tracking_sent' => 'Tracking Sent to Customer',
            'api_success' => 'API Request Success',
            'api_error' => 'API Request Failed',
            'cron_check' => 'Automated Status Check'
        );
        
        return isset($actions_map[$action]) ? $actions_map[$action] : $action;
    }
    
    public static function get_status_icon($status) {
        switch ($status) {
            case 'success':
                return '✅';
            case 'error':
                return '❌';
            case 'warning':
                return '⚠️';
            default:
                return 'ℹ️';
        }
    }
}