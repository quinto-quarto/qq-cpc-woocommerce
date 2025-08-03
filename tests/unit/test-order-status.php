<?php
/**
 * Unit tests for OrderStatus API functionality
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use QQCPC\Api\OrderStatus;

class OrderStatusTest extends WP_UnitTestCase {

    private $order_status;

    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        QQ_CPC_Test_Utilities::mock_wp_functions();
        
        $this->order_status = new OrderStatus();
    }

    public function tearDown(): void {
        QQ_CPC_Test_Utilities::reset_mocks();
        parent::tearDown();
    }

    /**
     * Test that OrderStatus registers WordPress hooks correctly
     */
    public function test_register_hooks() {
        // Mock WordPress hook functions
        Functions\expect('add_action')
            ->with('woocommerce_order_status_processing', [$this->order_status, 'store_cpc_order_code'])
            ->once();
        
        Functions\expect('add_action')
            ->with('init', [$this->order_status, 'schedule_status_check'])
            ->once();
            
        Functions\expect('add_action')
            ->with('qq_cpc_check_order_status', [$this->order_status, 'check_orders_status'])
            ->once();

        $this->order_status->register();
    }

    /**
     * Test WP-Cron scheduling functionality
     */
    public function test_schedule_status_check() {
        Functions\expect('wp_next_scheduled')
            ->with('qq_cpc_check_order_status')
            ->andReturn(false);
            
        Functions\expect('wp_schedule_event')
            ->with(\Mockery::type('int'), 'hourly', 'qq_cpc_check_order_status')
            ->once()
            ->andReturn(true);

        $this->order_status->schedule_status_check();
    }

    /**
     * Test CPC order code storage
     */
    public function test_store_cpc_order_code() {
        $order_id = 123;
        $mock_order = QQ_CPC_Test_Utilities::create_mock_wc_order($order_id);
        
        // Mock order meta retrieval (empty initially)
        $mock_order->shouldReceive('get_meta')
            ->with('_qq_cpc_order_code')
            ->andReturn('');
            
        // Expect meta data to be added
        $mock_order->shouldReceive('add_meta_data')
            ->with('_qq_cpc_order_code', 'wc_order_123', true)
            ->once();
            
        $mock_order->shouldReceive('save')->once();

        Functions\when('wc_get_order')->justReturn($mock_order);

        $this->order_status->store_cpc_order_code($order_id);
    }

    /**
     * Test single order checking with valid API response
     */
    public function test_check_single_order_success() {
        $order_code = 'CPC123456';
        
        // Mock successful API response
        $api_response = QQ_CPC_Test_Utilities::create_mock_cpc_response();
        
        Functions\expect('wp_remote_post')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode([$api_response])
            ]);

        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([$api_response]));

        $result = $this->order_status->check_single_order($order_code);

        $this->assertNotNull($result);
        $this->assertEquals('CPC123456', $result->Codice);
        $this->assertEquals('TRACK123456789', $result->NumSped);
        $this->assertNotEmpty($result->InfoUrl);
    }

    /**
     * Test single order checking with no API token
     */
    public function test_check_single_order_no_token() {
        Functions\when('get_option')
            ->justReturn('');

        $result = $this->order_status->check_single_order('CPC123456');

        $this->assertNull($result);
    }

    /**
     * Test bulk order status checking
     */
    public function test_check_orders_status() {
        $mock_orders = [QQ_CPC_Test_Utilities::create_mock_wc_order()];
        
        Functions\when('wc_get_orders')
            ->with([
                'status' => 'processing',
                'limit' => 50
            ])
            ->andReturn($mock_orders);

        // Mock API response
        $api_response = [QQ_CPC_Test_Utilities::create_mock_cpc_response()];
        
        Functions\when('wp_remote_post')->justReturn([
            'response' => ['code' => 200],
            'body' => json_encode($api_response)
        ]);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($api_response));

        $this->order_status->check_orders_status();

        // Verify that the method runs without errors
        $this->assertTrue(true);
    }

    /**
     * Test automatic order completion when setting is enabled
     */
    public function test_automatic_order_completion_enabled() {
        $mock_order = QQ_CPC_Test_Utilities::create_mock_wc_order();
        $api_response = QQ_CPC_Test_Utilities::create_mock_cpc_response(true);

        // Mock auto-update setting as enabled
        Functions\when('get_option')
            ->with('qq_cpc_auto_update', '0')
            ->andReturn('1');

        Functions\when('wc_get_orders')->justReturn([$mock_order]);

        // Expect order completion methods to be called
        $mock_order->shouldReceive('add_order_note')
            ->with(\Mockery::pattern('/La spedizione per l\'ordine è in corso/'), true)
            ->once();
            
        $mock_order->shouldReceive('update_status')
            ->with('completed')
            ->once();

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->order_status);
        $update_method = $reflection->getMethod('update_order_status');
        $update_method->setAccessible(true);
        
        $update_method->invoke($this->order_status, $api_response);
    }

    /**
     * Test that automatic completion is skipped when setting is disabled
     */
    public function test_automatic_order_completion_disabled() {
        $mock_order = QQ_CPC_Test_Utilities::create_mock_wc_order();
        $api_response = QQ_CPC_Test_Utilities::create_mock_cpc_response(true);

        // Mock auto-update setting as disabled
        Functions\when('get_option')
            ->with('qq_cpc_auto_update', '0')
            ->andReturn('0');

        Functions\when('wc_get_orders')->justReturn([$mock_order]);

        // Expect order completion methods NOT to be called
        $mock_order->shouldNotReceive('add_order_note');
        $mock_order->shouldNotReceive('update_status');

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->order_status);
        $update_method = $reflection->getMethod('update_order_status');
        $update_method->setAccessible(true);
        
        $update_method->invoke($this->order_status, $api_response);
    }
}