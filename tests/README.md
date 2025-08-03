# QQ CPC WooCommerce Plugin Tests

This directory contains the test suite for the QQ CPC WooCommerce plugin.

## Test Structure

```
tests/
├── bootstrap.php              # Test bootstrap file
├── utilities/                 # Test utilities and helpers
│   └── class-test-utilities.php
├── unit/                      # Unit tests (isolated, mocked dependencies)
│   ├── test-order-status.php  # OrderStatus API tests
│   └── test-admin.php         # Admin page tests
└── integration/               # Integration tests (with WordPress/WooCommerce)
    └── test-woocommerce-integration.php
```

## Running Tests

### Prerequisites

1. Install dependencies:
   ```bash
   composer install
   ```

2. Install WordPress test suite:
   ```bash
   composer install-wp-tests
   ```
   
   Or manually:
   ```bash
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

### Running Tests

Run all tests:
```bash
composer test
```

Run only unit tests:
```bash
composer test:unit
```

Run only integration tests:
```bash
composer test:integration
```

Run with verbose output:
```bash
./vendor/bin/phpunit --verbose
```

## Test Types

### Unit Tests

- **Fast execution** - No database or external dependencies
- **Mocked dependencies** - WordPress functions, WooCommerce objects, HTTP requests
- **Isolated testing** - Each test focuses on a single method or functionality
- **Uses Brain\Monkey** - For mocking WordPress functions
- **Uses Mockery** - For object mocking

**What's tested:**
- OrderStatus API logic
- Admin form rendering and validation
- Settings registration
- Hook registration
- Input sanitization

### Integration Tests

- **Real WordPress environment** - Uses actual WordPress test database
- **WooCommerce integration** - Tests with real WooCommerce orders
- **End-to-end workflows** - Complete order processing flows
- **HTTP mocking** - External API calls are mocked

**What's tested:**
- Complete order workflow (creation → CPC tracking → completion)
- WP-Cron scheduling and execution
- Order meta data storage and retrieval
- Customer notifications
- Automatic vs manual mode behavior

## Test Coverage

### Core Functionality Covered

✅ **API Integration**
- CPC API request formatting
- Response parsing and error handling
- Multiple order code format attempts
- HTTP timeout and error scenarios

✅ **Order Processing**
- CPC code extraction from order notes
- Order meta data storage
- Automatic order completion logic
- Customer notification sending

✅ **Admin Interface**
- Settings page rendering
- Form validation and submission
- Security checks (nonce, capabilities)
- Input sanitization

✅ **WordPress Integration**
- Hook registration
- WP-Cron scheduling
- Options API usage
- Admin menu creation

✅ **WooCommerce Integration**
- Order status management
- Order notes handling
- Meta data storage
- Customer notifications

### Mocking Strategy

**External Dependencies Mocked:**
- CPC API HTTP requests
- WordPress database operations
- WooCommerce order objects (in unit tests)
- WordPress scheduling functions
- Email notifications

**Real Components in Integration Tests:**
- WordPress database
- WooCommerce order creation
- Option storage
- WP-Cron scheduling

## Adding New Tests

### Unit Test Example

```php
public function test_new_functionality() {
    // Arrange
    Functions\when('some_wp_function')->justReturn('expected_value');
    
    // Act
    $result = $this->object_under_test->new_method();
    
    // Assert
    $this->assertEquals('expected_result', $result);
}
```

### Integration Test Example

```php
public function test_new_integration() {
    // Create real WooCommerce order
    $order = wc_create_order();
    $order->set_status('processing');
    $order->save();
    
    // Test functionality
    $this->plugin->process_order($order->get_id());
    
    // Verify results
    $order = wc_get_order($order->get_id());
    $this->assertEquals('completed', $order->get_status());
}
```

## CI/CD

Tests run automatically on GitHub Actions for:
- PHP versions: 7.4, 8.0, 8.1, 8.2
- WordPress versions: latest, 6.0
- Multiple test scenarios and code quality checks

See `.github/workflows/test.yml` for the complete CI configuration.