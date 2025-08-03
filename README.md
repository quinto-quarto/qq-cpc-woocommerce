# QQ CPC WooCommerce Integration

A WordPress plugin that integrates CPC shipping tracking with WooCommerce orders.

## Features

- Automatic CPC tracking code retrieval from order notes
- Real-time order status checking via CPC API
- Automatic customer notifications with tracking URLs
- Order status updates to "Completed" when tracking is available
- Display of WooCommerce order information alongside tracking details


## Installation

1. Upload the `qq-cpc-woocommerce` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings in QQ - CPCW > Settings

## Configuration

1. Navigate to QQ - CPCW > Settings
2. Enter your CPC API credentials
3. Save changes

## Usage

### Checking Order Status
1. Go to QQ - CPC > Orders
2. Enter the WooCommerce order number in the search field
3. Click on "Check CPC Status"
4. View the tracking information in the results table

### Updating Customer Orders
When viewing order status results, you can:
1. See the tracking URL if available
2. Use the "Send Tracking to Customer" button to:
   - Add the tracking URL to the order notes
   - Send a notification to the customer
   - Update the order status to "Completed"

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher

## Support

For support, please contact the plugin developer or raise an issue in the plugin's repository.

## Changelog

### 0.2.0
- Added WP-Cron for automatic periodic CPC order status checking (hourly)
- Added setting to enable/disable automatic order status updates
- Automatic order completion and customer notifications when tracking is available
- Enhanced admin interface with automatic update controls

### 0.1.0
- Initial release
- Basic CPC tracking integration
- Customer notifications
- Order status updates
