<?php
namespace QQCPC\Pages;

class Admin {
    private $settings_page = 'qq-cpc-settings';
    private $option_group = 'qq_cpc_settings';
    private $settings_section = 'qq_cpc_settings_section';
    private $api;

    public function __construct() {
        $this->api = new \QQCPC\Api\OrderStatus();
    }

    public function register() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_qq_cpc_check_order', array($this, 'handle_order_check'));
        add_action('admin_post_qq_cpc_update_tracking', array($this, 'handle_tracking_update'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'QQ CPC',
            'QQ CPC',
            'manage_options',
            'qq-cpc-woocommerce',
            array($this, 'render_main_page'),
            'dashicons-search',
            100
        );

        add_submenu_page(
            'qq-cpc-woocommerce',
            'Settings',
            'Settings',
            'manage_options',
            $this->settings_page,
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting($this->option_group, 'qq_cpc_api_token');

        add_settings_section(
            $this->settings_section,
            'API Settings',
            array($this, 'settings_section_callback'),
            $this->settings_page
        );

        add_settings_field(
            'qq_cpc_api_token',
            'API Token',
            array($this, 'token_field_callback'),
            $this->settings_page,
            $this->settings_section
        );
    }

    public function settings_section_callback() {
        if (!is_admin()) return;
        echo '<p>Enter your CPC API settings below:</p>';
    }

    public function token_field_callback() {
        if (!is_admin()) return;
        $token = get_option('qq_cpc_api_token');
        echo '<input type="text" name="qq_cpc_api_token" value="' . esc_attr($token) . '" class="regular-text">';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->settings_page);
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    public function render_main_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $order_info = null;
        if (isset($_GET['order_checked']) && $_GET['order_checked'] === '1') {
            if (isset($_GET['order_id'])) {
                $order_info = $this->get_order_info($_GET['order_id']);
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!get_option('qq_cpc_api_token')): ?>
                <div class="notice notice-warning">
                    <p>Please configure your API token in the <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->settings_page)); ?>">settings page</a>.</p>
                </div>
            <?php endif; ?>

            <div class="qq-cpc-card">
                <h2>Check Order Status</h2>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="qq-cpc-form">
                    <input type="hidden" name="action" value="qq_cpc_check_order">
                    <?php wp_nonce_field('qq_cpc_check_order', 'qq_cpc_nonce'); ?>
                    
                    <label for="order_id">Order ID:</label>
                    <input type="text" id="order_id" name="order_id" required>
                    
                    <button type="submit" class="button button-primary">Check Status</button>
                </form>

                <?php if ($order_info): ?>
                    <div class="qq-cpc-results">
                        <h3>Order Information</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <tbody>
                                <tr>
                                    <th>WooCommerce Order</th>
                                    <td>#<?php echo esc_html($order_info->WooOrderNumber ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Customer Name</th>
                                    <td><?php echo esc_html($order_info->CustomerName ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>CPC Order Code</th>
                                    <td><?php echo esc_html($order_info->Codice ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Shipping Number</th>
                                    <td><?php echo esc_html($order_info->NumSped ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Shipping Status</th>
                                    <td><?php echo esc_html($order_info->StatoSpedizione ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Order Status</th>
                                    <td><?php echo esc_html($order_info->StatoOrdine ?? 'N/A'); ?></td>
                                </tr>
                                <?php if (isset($order_info->InfoUrl) && !empty($order_info->InfoUrl)): ?>
                                <tr>
                                    <th>Tracking URL</th>
                                    <td>
                                        <a href="<?php echo esc_url($order_info->InfoUrl); ?>" target="_blank">View Tracking</a>
                                        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="display: inline-block; margin-left: 10px;">
                                            <input type="hidden" name="action" value="qq_cpc_update_tracking">
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_info->WooOrderNumber); ?>">
                                            <input type="hidden" name="tracking_url" value="<?php echo esc_url($order_info->InfoUrl); ?>">
                                            <?php wp_nonce_field('qq_cpc_update_tracking', 'qq_cpc_tracking_nonce'); ?>
                                            <button type="submit" class="button button-secondary">Send Tracking to Customer</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function get_order_info($order_id) {
        error_log('QQ CPC: Getting order info for order ' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('QQ CPC: Order not found');
            return null;
        }

        // Get order notes
        $args = array(
            'post_id' => $order_id,
            'type' => 'order_note'
        );
        $notes = wc_get_order_notes($args);
        
        $cpc_code = null;
        foreach ($notes as $note) {
            if (preg_match('/Ordine inviato a CPC con codice ([A-Z0-9]+)/', $note->content, $matches)) {
                $cpc_code = $matches[1];
                error_log('QQ CPC: Found CPC code in notes: ' . $cpc_code);
                break;
            }
        }

        if (empty($cpc_code)) {
            error_log('QQ CPC: No CPC code found in order notes');
            return null;
        }

        $result = $this->api->check_single_order($cpc_code);
        if ($result) {
            // Add WooCommerce order info
            $result->WooOrderNumber = $order_id;
            $result->CustomerName = $order->get_formatted_billing_full_name();
        }
        error_log('QQ CPC: API result for order: ' . print_r($result, true));
        return $result;
    }

    private function update_order_tracking($order_id, $tracking_url) {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('QQ CPC: Order not found for tracking update');
            return false;
        }

        // Add tracking note to customer
        $note = sprintf(
            'La spedizione per l\'ordine è in corso. Trovate il tracking del corriere a questo link: %s',
            $tracking_url
        );
        $order->add_order_note($note, true); // true means send to customer

        // Update order status to completed
        $order->update_status('completed');
        
        error_log('QQ CPC: Updated order ' . $order_id . ' with tracking info');
        return true;
    }

    public function handle_order_check() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('qq_cpc_check_order', 'qq_cpc_nonce');

        $order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';
        wp_redirect(add_query_arg(
            array(
                'page' => 'qq-cpc-woocommerce',
                'order_checked' => '1',
                'order_id' => $order_id
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function handle_tracking_update() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('qq_cpc_update_tracking', 'qq_cpc_tracking_nonce');

        $order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';
        $tracking_url = isset($_POST['tracking_url']) ? esc_url_raw($_POST['tracking_url']) : '';

        if (empty($order_id) || empty($tracking_url)) {
            wp_redirect(add_query_arg(
                array(
                    'page' => 'qq-cpc-woocommerce',
                    'tracking_updated' => '0'
                ),
                admin_url('admin.php')
            ));
            exit;
        }

        $success = $this->update_order_tracking($order_id, $tracking_url);
        wp_redirect(add_query_arg(
            array(
                'page' => 'qq-cpc-woocommerce',
                'tracking_updated' => $success ? '1' : '0',
                'order_id' => $order_id
            ),
            admin_url('admin.php')
        ));
        exit;
    }
}
