# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

This is a WordPress plugin, so development is done within the WordPress ecosystem:

- **Installation**: Place the plugin in `/wp-content/plugins/` and activate through WordPress admin
- **Dependencies**: Run `composer install` to install PHP dependencies (currently minimal - just autoloading)
- **No build process**: This plugin uses vanilla PHP/CSS/JS - no compilation step required

## Architecture Overview

### Plugin Structure
This is a WordPress plugin that integrates CPC (shipping provider) API with WooCommerce orders. The architecture follows a service-oriented pattern:

- **Entry Point**: `qq-cpc-woocommerce.php` - Main plugin file with activation/deactivation hooks
- **Initialization**: `inc/Init.php` - Service registry that loads all plugin components
- **Namespace**: All classes use the `QQCPC\` namespace with PSR-4 autoloading

### Core Components

1. **API Layer** (`inc/Api/OrderStatus.php`):
   - Handles CPC API communication for order status checking
   - Implements automatic order status monitoring via WP-Cron (hourly checks)
   - Supports multiple order code formats and API request variations
   - Stores tracking data in WooCommerce order meta

2. **Admin Interface** (`inc/Pages/Admin.php`):
   - Provides admin pages for manual order checking and settings
   - Main page: Search for orders and display CPC tracking information
   - Settings page: Configure CPC API token
   - Handles customer notifications and order status updates

3. **Base Services** (`inc/Base/`):
   - `Enqueue.php`: Manages CSS/JS assets for admin pages
   - `Activate.php` & `Deactivate.php`: Plugin lifecycle hooks

### Key Integration Points

- **WooCommerce Integration**: Plugin hooks into WooCommerce order lifecycle
- **CPC Code Extraction**: Parses order notes to find CPC tracking codes using regex pattern: `Ordine inviato a CPC con codice ([A-Z0-9]+)`
- **API Endpoint**: Configured via `QQ_CPC_API_ENDPOINT` constant (https://cpcapi.erpmanagement.it/API/2.0/Ordine/Stato)
- **Order Meta Fields**: Stores CPC data in order meta: `_qq_cpc_order_code`, `_qq_cpc_shipping_number`, `_qq_cpc_shipping_status`, `_qq_cpc_order_status`, `_qq_cpc_tracking_url`

### Data Flow

1. When WooCommerce order reaches "processing" status, CPC order code is extracted from order notes
2. Background WP-Cron job checks CPC API hourly for order status updates
3. Admin can manually check individual orders via the plugin interface
4. When tracking is available, customers can be notified and orders marked complete

### Dependencies

- **WordPress**: 5.0+
- **WooCommerce**: 3.0+
- **PHP**: 7.4+
- No external PHP packages beyond WordPress/WooCommerce

### Error Handling

The plugin uses extensive `error_log()` calls for debugging API interactions and order processing. Look for log entries prefixed with "QQ CPC:" in WordPress debug logs.