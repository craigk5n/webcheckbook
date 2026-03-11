<?php
declare(strict_types=1);

/**
 * PHPUnit test bootstrap.
 *
 * Loads the minimum necessary code for testing without requiring
 * a database connection or web server context.
 */

// Provide a stub translate() function so functions.php can load without translate.php
if (!function_exists('translate')) {
    function translate(string $str): string {
        return $str;
    }
}

// Provide a stub fatalError() that throws instead of exiting
if (!function_exists('fatalError')) {
    function fatalError(string $msg): void {
        throw new RuntimeException($msg);
    }
}

// Set a default timezone
date_default_timezone_set('America/New_York');

// Load the functions we want to test
require_once __DIR__ . '/../includes/functions.php';
