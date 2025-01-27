<?php
namespace QQCPC\Api;

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
            return;
        }

        $response = $this->call_cpc_api($order_codes);
        if (empty($response)) {
            return;
        }

        foreach ($response as $status_info) {
            $this->update_order_status($status_info);
        }
    }

    public function check_single_order($order_code) {
        if (empty($this->token)) {
            error_log('QQ CPC: No API token configured');
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
                return reset($response);
            }
        }

        error_log('QQ CPC: No valid response found with any format');
        return null;
    }

    private function call_cpc_api($order_codes) {
        if (empty($this->token)) {
            error_log('QQ CPC: No API token for request');
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
            error_log('QQ CPC API Error: ' . $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        error_log('QQ CPC: Response status code - ' . $status_code);

        $body = wp_remote_retrieve_body($response);
        error_log('QQ CPC: Response body - ' . $body);

        $decoded = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('QQ CPC: JSON decode error - ' . json_last_error_msg());
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
    }
}
