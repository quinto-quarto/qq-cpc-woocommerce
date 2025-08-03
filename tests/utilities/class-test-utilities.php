<?php
/**
 * Test utilities and helper functions
 */

class QQ_CPC_Test_Utilities {

    /**
     * Create a mock WooCommerce order for testing
     */
    public static function create_mock_wc_order($order_id = 123, $order_key = 'wc_order_123') {
        $order = \Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn($order_id);
        $order->shouldReceive('get_order_key')->andReturn($order_key);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('get_formatted_billing_full_name')->andReturn('John Doe');
        $order->shouldReceive('get_meta')->with('_qq_cpc_order_code')->andReturn('CPC123456');
        $order->shouldReceive('update_meta_data')->andReturn(true);
        $order->shouldReceive('add_meta_data')->andReturn(true);
        $order->shouldReceive('save')->andReturn(true);
        $order->shouldReceive('add_order_note')->andReturn(true);
        $order->shouldReceive('update_status')->andReturn(true);
        
        return $order;
    }

    /**
     * Create mock CPC API response data
     */
    public static function create_mock_cpc_response($with_tracking = true) {
        $response = new stdClass();
        $response->Codice = 'CPC123456';
        $response->NumSped = 'TRACK123456789';
        $response->StatoSpedizione = 'In transito';
        $response->StatoOrdine = 'Spedito';
        
        if ($with_tracking) {
            $response->InfoUrl = 'https://tracking.example.com/TRACK123456789';
        }
        
        return $response;
    }

    /**
     * Mock WordPress functions
     */
    public static function mock_wp_functions() {
        // Mock WordPress options functions
        \Brain\Monkey\Functions\when('get_option')->justReturn('test_token');
        \Brain\Monkey\Functions\when('update_option')->justReturn(true);
        
        // Mock WordPress HTTP functions
        \Brain\Monkey\Functions\when('wp_remote_post')->justReturn([
            'response' => ['code' => 200],
            'body' => json_encode([self::create_mock_cpc_response()])
        ]);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([self::create_mock_cpc_response()]));
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        
        // Mock WordPress scheduling functions
        \Brain\Monkey\Functions\when('wp_next_scheduled')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_schedule_event')->justReturn(true);
        
        // Mock error logging (using alias to avoid internal function issues)
        \Brain\Monkey\Functions\when('error_log')->alias(function($message) {
            // Silent mock or log to test output if needed
            return true;
        });
        
        // Mock WooCommerce functions
        \Brain\Monkey\Functions\when('wc_get_orders')->justReturn([self::create_mock_wc_order()]);
        \Brain\Monkey\Functions\when('wc_get_order')->justReturn(self::create_mock_wc_order());
        \Brain\Monkey\Functions\when('wc_get_order_notes')->justReturn([]);
    }

    /**
     * Reset mocks between tests
     */
    public static function reset_mocks() {
        \Mockery::close();
        \Brain\Monkey\tearDown();
    }
}