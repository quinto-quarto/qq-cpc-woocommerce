<?php
/**
 * PHPUnit bootstrap file for unit tests only (no WordPress test suite required)
 */

// Composer autoloader
require_once dirname(__DIR__) . '/autoload.php';

// Brain\Monkey setup for WordPress function mocking
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define plugin constants for tests
if (!defined('QQ_CPC_API_ENDPOINT')) {
    define('QQ_CPC_API_ENDPOINT', 'https://cpcapi.erpmanagement.it/API/2.0/Ordine/Stato');
}

// Load test utilities
require_once __DIR__ . '/utilities/class-test-utilities.php';

// Mock WordPress base class for tests
if (!class_exists('WP_UnitTestCase')) {
    class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
        public function setUp(): void {
            parent::setUp();
            \Brain\Monkey\setUp();
        }

        public function tearDown(): void {
            \Brain\Monkey\tearDown();
            parent::tearDown();
        }
    }
}