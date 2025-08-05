<?php
namespace QQCPC\Api;

use QQCPC\Utils\Logger;

class OrderStatus {
    private $token;

    public function __construct() {
        $this->token = get_option('qq_cpc_api_token', '');
    }

    public function register() {
        add_action('woocommerce_order_status_processing', array($this, 'store_cpc_order_code'));
        add_action('init', array($this, 'schedule_status_check'));
        add_action('qq_cpc_check_order_status', array($this, 'check_orders_status'));
    }

    public function schedule_status_check() {
        if (!wp_next_scheduled('qq_cpc_check_order_status')) {
            wp_schedule_event(time(), 'hourly', 'qq_cpc_check_order_status');
        }
    }

    public function store_cpc_order_code($order_id) {
        $order = wc_get_order($order_id);
        $cpc_code = $order->get_meta('_qq_cpc_order_code');
        
        if (empty($cpc_code)) {
            // Store the CPC order code when it's received from the other plugin
            $order->add_meta_data('_qq_cpc_order_code', $order->get_order_key(), true);
            $order->save();
        }
    }

    public function check_orders_status() {
        // Get all orders in processing status
        $orders = wc_get_orders(array(
            'status' => 'processing',
            'limit' => 50
        ));

        if (empty($orders)) {
            Logger::log('cron_check', 'N/A', 'No processing orders found', 'success');
            return;
        }

        $order_codes = array();
        foreach ($orders as $order) {
            $cpc_code = $order->get_meta('_qq_cpc_order_code');
            if (!empty($cpc_code)) {
                $order_codes[] = $cpc_code;
            }
        }

        if (empty($order_codes)) {
            Logger::log('cron_check', 'N/A', 'No orders with CPC codes found', 'success');
            return;
        }

        $response = $this->call_cpc_api($order_codes);
        if (empty($response)) {
            Logger::log('cron_check', 'N/A', 'API request failed for ' . count($order_codes) . ' orders', 'error');
            return;
        }

        Logger::log('cron_check', 'N/A', 'Checked ' . count($order_codes) . ' orders, found ' . count($response) . ' responses', 'success');

        foreach ($response as $status_info) {
            $this->update_order_status($status_info);
        }
    }

    public function check_single_order($order_code) {
        if (empty($this->token)) {
            error_log('QQ CPC: No API token configured');
            Logger::log('api_error', 'N/A', 'No API token configured', 'error');
            return null;
        }

        error_log('QQ CPC: Checking order ' . $order_code);

        // Try different formats of the order code
        $variations = [
            $order_code,                    // Original
            sprintf('%013d', $order_code),  // Padded to 13 digits
            'QQ' . $order_code,             // With QQ prefix
            'CPC' . $order_code,            // With CPC prefix
        ];

        foreach ($variations as $code) {
            error_log('QQ CPC: Trying code format: ' . $code);
            $response = $this->call_cpc_api([$code]);
            
            if (!empty($response) && is_array($response)) {
                error_log('QQ CPC: Found valid response with format: ' . $code);
                Logger::log('api_success', 'N/A', 'Found order data with code format: ' . $code, 'success');
                return reset($response);
            }
        }

        error_log('QQ CPC: No valid response found with any format');
        Logger::log('api_error', 'N/A', 'No valid response found with any code format for: ' . $order_code, 'error');
        return null;
    }

    private function call_cpc_api($order_codes) {
        if (empty($this->token)) {
            error_log('QQ CPC: No API token for request');
            Logger::log('api_error', 'N/A', 'No API token configured for automated check', 'error');
            return false;
        }

        error_log('QQ CPC: Making API request with codes: ' . print_r($order_codes, true));
        error_log('QQ CPC: Using endpoint: ' . QQ_CPC_API_ENDPOINT);

        // Try both with and without quotes in JSON
        $body_with_quotes = json_encode($order_codes);
        $body_without_quotes = str_replace('"', '', $body_with_quotes);

        error_log('QQ CPC: Trying with quoted JSON: ' . $body_with_quotes);
        $response_quoted = $this->make_api_request($body_with_quotes);
        if (!empty($response_quoted) && is_array($response_quoted)) {
            return $response_quoted;
        }

        error_log('QQ CPC: Trying with unquoted JSON: ' . $body_without_quotes);
        $response_unquoted = $this->make_api_request($body_without_quotes);
        if (!empty($response_unquoted) && is_array($response_unquoted)) {
            return $response_unquoted;
        }

        error_log('QQ CPC: Both API request formats failed');
        Logger::log('api_error', 'N/A', 'Both quoted and unquoted JSON API requests failed for ' . count($order_codes) . ' orders', 'error');
        return null;
    }

    private function make_api_request($body) {
        $args = array(
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $this->token
            ),
            'timeout' => 30,
            'sslverify' => false // For local development
        );

        error_log('QQ CPC: Request args - ' . print_r($args, true));
        $response = wp_remote_post(QQ_CPC_API_ENDPOINT, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('QQ CPC API Error: ' . $error_message);
            Logger::log('api_error', 'N/A', 'API request failed: ' . $error_message, 'error');
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        error_log('QQ CPC: Response status code - ' . $status_code);

        if ($status_code !== 200) {
            error_log('QQ CPC: Non-200 status code received: ' . $status_code);
            Logger::log('api_error', 'N/A', 'API returned status code: ' . $status_code, 'error');
        }

        $body = wp_remote_retrieve_body($response);
        error_log('QQ CPC: Response body - ' . $body);

        $decoded = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('QQ CPC: JSON decode error - ' . $json_error);
            Logger::log('api_error', 'N/A', 'JSON decode failed: ' . $json_error . ' | Response: ' . substr($body, 0, 100), 'error');
            return null;
        }

        return $decoded;
    }

    private function update_order_status($status_info) {
        if (empty($status_info->Codice)) {
            return;
        }

        // Find order by CPC code
        $orders = wc_get_orders(array(
            'meta_key' => '_qq_cpc_order_code',
            'meta_value' => $status_info->Codice,
            'limit' => 1
        ));

        if (empty($orders)) {
            return;
        }

        $order = reset($orders);
        
        // Update order meta with shipping info
        if (!empty($status_info->NumSped)) {
            $order->update_meta_data('_qq_cpc_shipping_number', $status_info->NumSped);
        }
        if (!empty($status_info->StatoSpedizione)) {
            $order->update_meta_data('_qq_cpc_shipping_status', $status_info->StatoSpedizione);
        }
        if (!empty($status_info->StatoOrdine)) {
            $order->update_meta_data('_qq_cpc_order_status', $status_info->StatoOrdine);
        }
        if (!empty($status_info->InfoUrl)) {
            $order->update_meta_data('_qq_cpc_tracking_url', $status_info->InfoUrl);
        }

        $order->save();

        // Check if automatic updates are enabled and tracking is available
        $auto_update_enabled = get_option('qq_cpc_auto_update', '0');
        if ($auto_update_enabled && !empty($status_info->InfoUrl) && $order->get_status() === 'processing') {
            $this->automatically_complete_order($order, $status_info->InfoUrl);
        }
    }

    private function automatically_complete_order($order, $tracking_url) {
        // Add tracking note to customer
        $note = sprintf(
            'La spedizione per l\'ordine è in corso. Trovate il tracking del corriere a questo link: %s',
            $tracking_url
        );
        $order->add_order_note($note, true); // true means send to customer

        // Update order status to completed
        $order->update_status('completed');
        
        error_log('QQ CPC: Automatically completed order ' . $order->get_id() . ' with tracking URL');
        Logger::log('order_completed', $order->get_id(), 'Order automatically completed with tracking URL', 'success');
        Logger::log('tracking_sent', $order->get_id(), 'Tracking notification sent to customer', 'success');
    }
}
