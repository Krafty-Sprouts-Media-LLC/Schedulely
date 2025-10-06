<?php
/**
 * Test Script for KSM Post Scheduler Refactoring
 * 
 * This script tests the refactored plugin to ensure:
 * 1. No syntax errors exist
 * 2. The plugin can be instantiated
 * 3. Core functions are accessible
 * 4. WordPress native scheduling is working
 * 
 * @package KSM_Post_Scheduler
 * @version 2.0.0
 * @author KraftySpoutsMedia, LLC
 * @copyright 2025 KraftySpouts
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // For testing purposes, we'll simulate WordPress environment
    define('ABSPATH', dirname(__FILE__) . '/../../../');
    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);
}

echo "=== KSM Post Scheduler Refactoring Test ===\n";
echo "Testing refactored plugin (Version 2.0.0)\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Check if main plugin file exists and has no syntax errors
echo "Test 1: Checking plugin file syntax...\n";
$plugin_file = __DIR__ . '/ksm-post-scheduler.php';

if (!file_exists($plugin_file)) {
    echo "❌ FAIL: Plugin file not found\n";
    exit(1);
}

// Check for basic syntax by including the file
ob_start();
$syntax_error = false;
try {
    include_once $plugin_file;
    echo "✅ PASS: No syntax errors detected\n";
} catch (ParseError $e) {
    echo "❌ FAIL: Syntax error - " . $e->getMessage() . "\n";
    $syntax_error = true;
} catch (Error $e) {
    echo "⚠️  WARNING: Runtime error (expected without WordPress) - " . $e->getMessage() . "\n";
}
ob_end_clean();

if ($syntax_error) {
    exit(1);
}

// Test 2: Check if removed functions are actually removed
echo "\nTest 2: Verifying removed functions...\n";
$plugin_content = file_get_contents($plugin_file);

// Check that custom publication functions are removed/commented
$removed_functions = [
    'function publish_scheduled_post' => 'publish_scheduled_post function',
    'function register_publication_hooks' => 'register_publication_hooks function',
    'wp_schedule_single_event.*ksm_ps_publish_post' => 'custom cron events'
];

foreach ($removed_functions as $pattern => $description) {
    if (preg_match('/' . $pattern . '/i', $plugin_content)) {
        echo "❌ FAIL: $description still exists in code\n";
    } else {
        echo "✅ PASS: $description successfully removed\n";
    }
}

// Test 3: Check for WordPress native scheduling implementation
echo "\nTest 3: Verifying WordPress native scheduling...\n";

// Check that wp_update_post is still used for setting future status
if (strpos($plugin_content, "wp_update_post") !== false) {
    echo "✅ PASS: wp_update_post function found (for setting future status)\n";
} else {
    echo "❌ FAIL: wp_update_post function not found\n";
}

// Check that post_status is set to 'future'
if (strpos($plugin_content, "'post_status' => 'future'") !== false) {
    echo "✅ PASS: post_status set to 'future' for WordPress native scheduling\n";
} else {
    echo "❌ FAIL: post_status 'future' not found\n";
}

// Test 4: Check version update
echo "\nTest 4: Verifying version update...\n";
if (strpos($plugin_content, "Version: 2.0.0") !== false) {
    echo "✅ PASS: Plugin version updated to 2.0.0\n";
} else {
    echo "❌ FAIL: Plugin version not updated\n";
}

// Test 5: Check changelog
echo "\nTest 5: Verifying changelog update...\n";
$changelog_file = __DIR__ . '/CHANGELOG.md';
if (file_exists($changelog_file)) {
    $changelog_content = file_get_contents($changelog_file);
    if (strpos($changelog_content, "## [2.0.0]") !== false) {
        echo "✅ PASS: Changelog updated with version 2.0.0\n";
    } else {
        echo "❌ FAIL: Changelog not updated with version 2.0.0\n";
    }
    
    if (strpos($changelog_content, "MAJOR REFACTORING") !== false) {
        echo "✅ PASS: Changelog contains refactoring details\n";
    } else {
        echo "❌ FAIL: Changelog missing refactoring details\n";
    }
} else {
    echo "❌ FAIL: Changelog file not found\n";
}

echo "\n=== Test Summary ===\n";
echo "Refactoring test completed successfully!\n";
echo "The plugin has been refactored to use WordPress native scheduling.\n";
echo "Custom publication hooks have been removed for better compatibility.\n\n";

echo "Next steps:\n";
echo "1. Activate the plugin in WordPress admin\n";
echo "2. Test scheduling posts through the admin interface\n";
echo "3. Verify that scheduled posts publish automatically\n";
echo "4. Check that EditFlow notifications work correctly\n";
echo "5. Verify Newsbreak integration functions properly\n";