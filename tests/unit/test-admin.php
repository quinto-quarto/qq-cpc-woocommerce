<?php
/**
 * Unit tests for Admin page functionality
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use QQCPC\Pages\Admin;

class AdminTest extends WP_UnitTestCase {

    private $admin;

    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        QQ_CPC_Test_Utilities::mock_wp_functions();
        
        $this->admin = new Admin();
    }

    public function tearDown(): void {
        QQ_CPC_Test_Utilities::reset_mocks();
        parent::tearDown();
    }

    /**
     * Test that Admin registers WordPress hooks correctly
     */
    public function test_register_hooks() {
        Functions\expect('add_action')
            ->with('admin_menu', [$this->admin, 'add_admin_menu'])
            ->once();
        
        Functions\expect('add_action')
            ->with('admin_init', [$this->admin, 'register_settings'])
            ->once();
            
        Functions\expect('add_action')
            ->with('admin_post_qq_cpc_check_order', [$this->admin, 'handle_order_check'])
            ->once();
            
        Functions\expect('add_action')
            ->with('admin_post_qq_cpc_update_tracking', [$this->admin, 'handle_tracking_update'])
            ->once();

        $this->admin->register();
    }

    /**
     * Test admin menu registration
     */
    public function test_add_admin_menu() {
        Functions\expect('add_menu_page')
            ->with(
                'QQ CPC',
                'QQ CPC', 
                'manage_options',
                'qq-cpc-woocommerce',
                [$this->admin, 'render_main_page'],
                'dashicons-search',
                100
            )
            ->once();

        Functions\expect('add_submenu_page')
            ->with(
                'qq-cpc-woocommerce',
                'Settings',
                'Settings',
                'manage_options',
                'qq-cpc-settings',
                [$this->admin, 'render_settings_page']
            )
            ->once();

        $this->admin->add_admin_menu();
    }

    /**
     * Test settings registration
     */
    public function test_register_settings() {
        Functions\expect('register_setting')
            ->with('qq_cpc_settings', 'qq_cpc_api_token')
            ->once();
            
        Functions\expect('register_setting')
            ->with('qq_cpc_settings', 'qq_cpc_auto_update')
            ->once();

        Functions\expect('add_settings_section')
            ->with(
                'qq_cpc_settings_section',
                'API Settings',
                [$this->admin, 'settings_section_callback'],
                'qq-cpc-settings'
            )
            ->once();

        Functions\expect('add_settings_field')
            ->with(
                'qq_cpc_api_token',
                'API Token',
                [$this->admin, 'token_field_callback'],
                'qq-cpc-settings',
                'qq_cpc_settings_section'
            )
            ->once();
            
        Functions\expect('add_settings_field')
            ->with(
                'qq_cpc_auto_update',
                'Automatic Updates',
                [$this->admin, 'auto_update_field_callback'],
                'qq-cpc-settings',
                'qq_cpc_settings_section'
            )
            ->once();

        $this->admin->register_settings();
    }

    /**
     * Test settings section callback renders correctly
     */
    public function test_settings_section_callback() {
        Functions\when('is_admin')->justReturn(true);
        
        ob_start();
        $this->admin->settings_section_callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('Enter your CPC API settings below:', $output);
    }

    /**
     * Test token field callback renders input correctly
     */
    public function test_token_field_callback() {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('get_option')
            ->with('qq_cpc_api_token')
            ->andReturn('test_token_123');
        Functions\when('esc_attr')->returnArg();
        
        ob_start();
        $this->admin->token_field_callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="text"', $output);
        $this->assertStringContainsString('name="qq_cpc_api_token"', $output);
        $this->assertStringContainsString('value="test_token_123"', $output);
        $this->assertStringContainsString('class="regular-text"', $output);
    }

    /**
     * Test auto update field callback renders checkbox correctly
     */
    public function test_auto_update_field_callback() {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('get_option')
            ->with('qq_cpc_auto_update', '0')
            ->andReturn('1');
        Functions\when('checked')
            ->with('1', '1', false)
            ->andReturn('checked="checked"');
        
        ob_start();
        $this->admin->auto_update_field_callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('name="qq_cpc_auto_update"', $output);
        $this->assertStringContainsString('value="1"', $output);
        $this->assertStringContainsString('checked="checked"', $output);
        $this->assertStringContainsString('Automatically update order status', $output);
    }

    /**
     * Test settings page renders with proper capability check
     */
    public function test_render_settings_page_with_capability() {
        Functions\when('current_user_can')
            ->with('manage_options')
            ->andReturn(true);
        Functions\when('get_admin_page_title')->andReturn('QQ CPC Settings');
        Functions\when('esc_html')->returnArg();
        Functions\when('settings_fields')->justReturn(null);
        Functions\when('do_settings_sections')->justReturn(null);
        Functions\when('submit_button')->justReturn(null);
        
        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div class="wrap">', $output);
        $this->assertStringContainsString('<h1>QQ CPC Settings</h1>', $output);
        $this->assertStringContainsString('<form action="options.php"', $output);
    }

    /**
     * Test settings page blocked without proper capability
     */
    public function test_render_settings_page_without_capability() {
        Functions\when('current_user_can')
            ->with('manage_options')
            ->andReturn(false);
        
        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /**
     * Test main page renders with API token warning
     */
    public function test_render_main_page_with_token_warning() {
        Functions\when('current_user_can')
            ->with('manage_options')
            ->andReturn(true);
        Functions\when('get_admin_page_title')->andReturn('QQ CPC');
        Functions\when('get_option')
            ->with('qq_cpc_api_token')
            ->andReturn('');
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('admin_url')->returnArg();
        Functions\when('wp_nonce_field')->justReturn(null);
        
        ob_start();
        $this->admin->render_main_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice notice-warning', $output);
        $this->assertStringContainsString('configure your API token', $output);
        $this->assertStringContainsString('Check Order Status', $output);
    }

    /**
     * Test order check form submission handling
     */
    public function test_handle_order_check() {
        Functions\when('current_user_can')
            ->with('manage_options')
            ->andReturn(true);
        Functions\expect('check_admin_referer')
            ->with('qq_cpc_check_order', 'qq_cpc_nonce')
            ->once();
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('add_query_arg')->returnArg();
        Functions\when('admin_url')->returnArg();
        Functions\expect('wp_redirect')->once();
        Functions\expect('exit')->once();

        $_POST['order_id'] = '123';

        $this->admin->handle_order_check();
    }

    /**
     * Test tracking update form submission handling
     */
    public function test_handle_tracking_update() {
        Functions\when('current_user_can')
            ->with('manage_options')
            ->andReturn(true);
        Functions\expect('check_admin_referer')
            ->with('qq_cpc_update_tracking', 'qq_cpc_tracking_nonce')
            ->once();
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('add_query_arg')->returnArg();
        Functions\when('admin_url')->returnArg();
        Functions\expect('wp_redirect')->once();
        Functions\expect('exit')->once();

        $_POST['order_id'] = '123';
        $_POST['tracking_url'] = 'https://tracking.example.com/123';

        $this->admin->handle_tracking_update();
    }

    /**
     * Test unauthorized access is blocked
     */
    public function test_handle_order_check_unauthorized() {
        Functions\when('current_user_can')
            ->with('manage_options')
            ->andReturn(false);
        Functions\expect('wp_die')
            ->with('Unauthorized')
            ->once();

        $this->admin->handle_order_check();
    }
}