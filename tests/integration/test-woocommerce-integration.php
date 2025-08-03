<?php
/**
 * Integration tests for WooCommerce integration
 */

use QQCPC\Api\OrderStatus;
use QQCPC\Pages\Admin;

class WooCommerceIntegrationTest extends WP_UnitTestCase {

    private $order_status;
    private $admin;

    public function setUp(): void {
        parent::setUp();
        
        // Only run if WooCommerce is available
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not available');
        }
        
        $this->order_status = new OrderStatus();
        $this->admin = new Admin();
    }

    /**
     * Test full workflow: order creation -> CPC code storage -> status check -> completion
     */
    public function test_complete_order_workflow() {
        // Create a real WooCommerce order
        $order = wc_create_order();
        $order->set_status('processing');
        $order->save();
        
        // Simulate CPC code being added to order notes
        $order->add_order_note('Ordine inviato a CPC con codice CPC123456');
        
        // Enable automatic updates
        update_option('qq_cpc_auto_update', '1');
        update_option('qq_cpc_api_token', 'test_token');
        
        // Mock successful API response
        add_filter('pre_http_request', function($response, $args, $url) {
            if (strpos($url, 'cpcapi.erpmanagement.it') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        (object)[
                            'Codice' => 'CPC123456',
                            'NumSped' => 'TRACK123456789',
                            'StatoSpedizione' => 'In transito',
                            'StatoOrdine' => 'Spedito',
                            'InfoUrl' => 'https://tracking.example.com/TRACK123456789'
                        ]
                    ])
                ];
            }
            return $response;
        }, 10, 3);
        
        // Test order code storage
        $this->order_status->store_cpc_order_code($order->get_id());
        
        // Verify CPC code was stored
        $stored_code = $order->get_meta('_qq_cpc_order_code');
        $this->assertNotEmpty($stored_code);
        
        // Test status checking and automatic completion
        $this->order_status->check_orders_status();
        
        // Refresh order data
        $order = wc_get_order($order->get_id());
        
        // Verify tracking data was stored
        $this->assertEquals('TRACK123456789', $order->get_meta('_qq_cpc_shipping_number'));
        $this->assertEquals('In transito', $order->get_meta('_qq_cpc_shipping_status'));
        $this->assertEquals('Spedito', $order->get_meta('_qq_cpc_order_status'));
        $this->assertEquals('https://tracking.example.com/TRACK123456789', $order->get_meta('_qq_cpc_tracking_url'));
        
        // Verify order was completed automatically
        $this->assertEquals('completed', $order->get_status());
        
        // Verify customer notification was added
        $notes = wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'customer']);
        $tracking_note_found = false;
        foreach ($notes as $note) {
            if (strpos($note->content, 'La spedizione per l\'ordine è in corso') !== false) {
                $tracking_note_found = true;
                break;
            }
        }
        $this->assertTrue($tracking_note_found, 'Customer tracking notification not found');
    }

    /**
     * Test that automatic completion is skipped when setting is disabled
     */
    public function test_manual_mode_workflow() {
        // Create a real WooCommerce order
        $order = wc_create_order();
        $order->set_status('processing');
        $order->save();
        
        // Simulate CPC code being added to order notes
        $order->add_order_note('Ordine inviato a CPC con codice CPC789012');
        
        // Disable automatic updates
        update_option('qq_cpc_auto_update', '0');
        update_option('qq_cpc_api_token', 'test_token');
        
        // Mock successful API response
        add_filter('pre_http_request', function($response, $args, $url) {
            if (strpos($url, 'cpcapi.erpmanagement.it') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        (object)[
                            'Codice' => 'CPC789012',
                            'NumSped' => 'TRACK789012345',
                            'StatoSpedizione' => 'In transito',
                            'StatoOrdine' => 'Spedito',
                            'InfoUrl' => 'https://tracking.example.com/TRACK789012345'
                        ]
                    ])
                ];
            }
            return $response;
        }, 10, 3);
        
        // Test status checking without automatic completion
        $this->order_status->check_orders_status();
        
        // Refresh order data
        $order = wc_get_order($order->get_id());
        
        // Verify tracking data was stored
        $this->assertEquals('TRACK789012345', $order->get_meta('_qq_cpc_shipping_number'));
        $this->assertEquals('https://tracking.example.com/TRACK789012345', $order->get_meta('_qq_cpc_tracking_url'));
        
        // Verify order was NOT completed automatically
        $this->assertEquals('processing', $order->get_status());
        
        // Verify no customer notification was added automatically
        $notes = wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'customer']);
        $tracking_note_found = false;
        foreach ($notes as $note) {
            if (strpos($note->content, 'La spedizione per l\'ordine è in corso') !== false) {
                $tracking_note_found = true;
                break;
            }
        }
        $this->assertFalse($tracking_note_found, 'Customer tracking notification should not be sent in manual mode');
    }

    /**
     * Test WP-Cron scheduling
     */
    public function test_wp_cron_scheduling() {
        // Clear any existing scheduled events
        wp_clear_scheduled_hook('qq_cpc_check_order_status');
        
        // Test scheduling
        $this->order_status->schedule_status_check();
        
        // Verify event was scheduled
        $next_scheduled = wp_next_scheduled('qq_cpc_check_order_status');
        $this->assertNotFalse($next_scheduled, 'WP-Cron event should be scheduled');
        
        // Test that duplicate scheduling is prevented
        $this->order_status->schedule_status_check();
        $next_scheduled_again = wp_next_scheduled('qq_cpc_check_order_status');
        $this->assertEquals($next_scheduled, $next_scheduled_again, 'Duplicate scheduling should be prevented');
    }

    /**
     * Test CPC code extraction from order notes
     */
    public function test_cpc_code_extraction() {
        // Create order with various note formats
        $order = wc_create_order();
        $order->add_order_note('Regular order note without CPC code');
        $order->add_order_note('Ordine inviato a CPC con codice ABC123DEF');
        $order->add_order_note('Another regular note');
        $order->save();
        
        // Use reflection to access private method for testing
        $reflection = new ReflectionClass($this->admin);
        $get_order_info_method = $reflection->getMethod('get_order_info');
        $get_order_info_method->setAccessible(true);
        
        // Mock API response for the extracted code
        add_filter('pre_http_request', function($response, $args, $url) {
            if (strpos($url, 'cpcapi.erpmanagement.it') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        (object)[
                            'Codice' => 'ABC123DEF',
                            'NumSped' => 'TRACK_ABC123',
                            'StatoSpedizione' => 'Consegnato',
                            'StatoOrdine' => 'Completato',
                            'InfoUrl' => 'https://tracking.example.com/ABC123'
                        ]
                    ])
                ];
            }
            return $response;
        }, 10, 3);
        
        update_option('qq_cpc_api_token', 'test_token');
        
        $order_info = $get_order_info_method->invoke($this->admin, $order->get_id());
        
        $this->assertNotNull($order_info);
        $this->assertEquals('ABC123DEF', $order_info->Codice);
        $this->assertEquals($order->get_id(), $order_info->WooOrderNumber);
    }

    /**
     * Test error handling when API is unavailable
     */
    public function test_api_error_handling() {
        // Create order
        $order = wc_create_order();
        $order->set_status('processing');
        $order->add_order_note('Ordine inviato a CPC con codice ERROR123');
        $order->save();
        
        update_option('qq_cpc_api_token', 'test_token');
        
        // Mock API error response
        add_filter('pre_http_request', function($response, $args, $url) {
            if (strpos($url, 'cpcapi.erpmanagement.it') !== false) {
                return new WP_Error('http_request_failed', 'Connection timeout');
            }
            return $response;
        }, 10, 3);
        
        // Test that errors are handled gracefully
        $this->order_status->check_orders_status();
        
        // Refresh order - should remain unchanged
        $order = wc_get_order($order->get_id());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEmpty($order->get_meta('_qq_cpc_tracking_url'));
    }

    public function tearDown(): void {
        // Clean up options
        delete_option('qq_cpc_auto_update');
        delete_option('qq_cpc_api_token');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('qq_cpc_check_order_status');
        
        // Remove HTTP filters
        remove_all_filters('pre_http_request');
        
        parent::tearDown();
    }
}