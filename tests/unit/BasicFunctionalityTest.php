<?php
/**
 * Basic functionality tests that verify core plugin logic
 */

use Brain\Monkey;
use Brain\Monkey\Functions;

class BasicFunctionalityTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        
        // Mock WordPress constants and functions
        if (!defined('QQ_CPC_API_ENDPOINT')) {
            define('QQ_CPC_API_ENDPOINT', 'https://cpcapi.erpmanagement.it/API/2.0/Ordine/Stato');
        }
        
        Functions\when('get_option')->justReturn('test_token');
        Functions\when('error_log')->justReturn(true);
    }

    /**
     * Test that the plugin classes can be instantiated
     */
    public function test_plugin_classes_instantiation() {
        $order_status = new QQCPC\Api\OrderStatus();
        $admin = new QQCPC\Pages\Admin();
        
        $this->assertInstanceOf(QQCPC\Api\OrderStatus::class, $order_status);
        $this->assertInstanceOf(QQCPC\Pages\Admin::class, $admin);
    }

    /**
     * Test API endpoint constant is defined
     */
    public function test_api_endpoint_constant() {
        $this->assertTrue(defined('QQ_CPC_API_ENDPOINT'));
        $this->assertStringContainsString('cpcapi.erpmanagement.it', QQ_CPC_API_ENDPOINT);
    }

    /**
     * Test that plugin autoloader works
     */
    public function test_autoloader_functionality() {
        $this->assertTrue(class_exists('QQCPC\Api\OrderStatus'));
        $this->assertTrue(class_exists('QQCPC\Pages\Admin'));
        $this->assertTrue(class_exists('QQCPC\Base\Enqueue'));
    }

    /**
     * Test mock CPC API response structure
     */
    public function test_mock_cpc_response_structure() {
        $response = QQ_CPC_Test_Utilities::create_mock_cpc_response();
        
        $this->assertObjectHasAttribute('Codice', $response);
        $this->assertObjectHasAttribute('NumSped', $response);
        $this->assertObjectHasAttribute('StatoSpedizione', $response);
        $this->assertObjectHasAttribute('StatoOrdine', $response);
        $this->assertObjectHasAttribute('InfoUrl', $response);
        
        $this->assertEquals('CPC123456', $response->Codice);
        $this->assertEquals('TRACK123456789', $response->NumSped);
        $this->assertNotEmpty($response->InfoUrl);
    }

    /**
     * Test mock WooCommerce order creation
     */
    public function test_mock_wc_order_creation() {
        $order = QQ_CPC_Test_Utilities::create_mock_wc_order();
        
        $this->assertNotNull($order);
        $this->assertEquals(123, $order->get_id());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEquals('John Doe', $order->get_formatted_billing_full_name());
    }

    /**
     * Test that WordPress functions are properly mocked
     */
    public function test_wordpress_functions_mocked() {
        // Test that get_option returns our mocked value
        $token = get_option('qq_cpc_api_token');
        $this->assertEquals('test_token', $token);
        
        // Test that error_log is mocked and doesn't cause errors
        $result = error_log('Test message');
        $this->assertTrue($result);
    }

    /**
     * Test JSON encoding/decoding of API responses
     */
    public function test_json_handling() {
        $response = QQ_CPC_Test_Utilities::create_mock_cpc_response();
        $json = json_encode($response);
        $decoded = json_decode($json);
        
        $this->assertNotFalse($json);
        $this->assertEquals($response->Codice, $decoded->Codice);
        $this->assertEquals($response->InfoUrl, $decoded->InfoUrl);
    }
}