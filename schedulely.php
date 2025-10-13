<?php
/**
 * Plugin Name: Schedulely
 * Plugin URI: https://kraftysprouts.com
 * Description: Intelligently schedule posts from any status with smart deficit tracking, random author assignment, and customizable time windows.
 * Version: 1.2.4
 * Author: Krafty Sprouts Media, LLC
 * Author URI: https://kraftysprouts.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: schedulely
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 6.8
 * Requires PHP: 8.2
 *
 * @package Schedulely
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SCHEDULELY_VERSION', '1.2.4');
define('SCHEDULELY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCHEDULELY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCHEDULELY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load plugin text domain for translations
 */
function schedulely_load_textdomain() {
    load_plugin_textdomain('schedulely', false, dirname(SCHEDULELY_PLUGIN_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'schedulely_load_textdomain');

/**
 * Load plugin classes
 */
require_once SCHEDULELY_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once SCHEDULELY_PLUGIN_DIR . 'includes/class-author-manager.php';
require_once SCHEDULELY_PLUGIN_DIR . 'includes/class-settings.php';
require_once SCHEDULELY_PLUGIN_DIR . 'includes/class-notifications.php';

/**
 * Initialize plugin settings
 */
function schedulely_init() {
    $settings = new Schedulely_Settings();
    $settings->init();
}
add_action('plugins_loaded', 'schedulely_init');

/**
 * Add Settings link on plugin page
 * 
 * @param array $links Existing plugin action links
 * @return array Modified links
 */
function schedulely_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('tools.php?page=schedulely') . '">' . __('Settings', 'schedulely') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . SCHEDULELY_PLUGIN_BASENAME, 'schedulely_plugin_action_links');

/**
 * Plugin activation hook
 * 
 * NOTE: This only runs on FIRST activation, NOT on updates.
 * User settings are preserved during plugin updates.
 * We use add_option() instead of update_option() to avoid overwriting existing settings.
 */
function schedulely_activate() {
    // Set default options (only if they don't exist - preserves user settings)
    add_option('schedulely_post_status', 'draft');
    add_option('schedulely_posts_per_day', 8);
    add_option('schedulely_start_time', '5:00 PM');
    add_option('schedulely_end_time', '11:00 PM');
    add_option('schedulely_active_days', [1, 2, 3, 4, 5, 6, 0]); // Mon-Sun
    add_option('schedulely_min_interval', 40);
    add_option('schedulely_randomize_authors', false);
    add_option('schedulely_excluded_authors', []);
    add_option('schedulely_auto_schedule', false); // CRITICAL: Changed to false - prevents auto-run on activation
    add_option('schedulely_email_notifications', true);
    add_option('schedulely_notification_users', []); // Empty array - will default to current user on settings page
    add_option('schedulely_version', SCHEDULELY_VERSION);
    
    // Schedule cron event (runs twice daily)
    if (!wp_next_scheduled('schedulely_auto_schedule')) {
        wp_schedule_event(time(), 'twicedaily', 'schedulely_auto_schedule');
    }
}
register_activation_hook(__FILE__, 'schedulely_activate');

/**
 * Plugin deactivation hook
 */
function schedulely_deactivate() {
    // Clear cron event
    $timestamp = wp_next_scheduled('schedulely_auto_schedule');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'schedulely_auto_schedule');
    }
}
register_deactivation_hook(__FILE__, 'schedulely_deactivate');

/**
 * WordPress cron hook for automatic scheduling
 */
function schedulely_run_auto_schedule() {
    if (get_option('schedulely_auto_schedule', true)) {
        $scheduler = new Schedulely_Scheduler();
        $results = $scheduler->run_schedule();
        
        // Send notification if enabled
        if (get_option('schedulely_email_notifications', true)) {
            $notifier = new Schedulely_Notifications();
            $notifier->send_scheduling_notification($results);
        }
        
        // Update last run timestamp
        update_option('schedulely_last_run', time());
    }
}
add_action('schedulely_auto_schedule', 'schedulely_run_auto_schedule');

/**
 * AJAX handler for manual scheduling
 */
function schedulely_ajax_manual_schedule() {
    // Check nonce
    check_ajax_referer('schedulely_admin', 'nonce');
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error([
            'message' => __('Permission denied.', 'schedulely')
        ]);
    }
    
    // Run scheduler
    try {
        $scheduler = new Schedulely_Scheduler();
        $results = $scheduler->run_schedule();
        
        if ($results['success']) {
            // Update last run timestamp
            update_option('schedulely_last_run', time());
            
            // Send notification if enabled
            if (get_option('schedulely_email_notifications', true)) {
                $notifier = new Schedulely_Notifications();
                $notifier->send_scheduling_notification($results);
            }
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully scheduled %d posts!', 'schedulely'),
                    $results['scheduled_count']
                ),
                'data' => $results
            ]);
        } else {
            wp_send_json_error([
                'message' => $results['message']
            ]);
        }
    } catch (Exception $e) {
        schedulely_log_error('AJAX scheduling error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => __('An error occurred: ', 'schedulely') . $e->getMessage()
        ]);
    }
}
add_action('wp_ajax_schedulely_manual_schedule', 'schedulely_ajax_manual_schedule');

/**
 * Check and handle plugin updates
 * 
 * This runs on every page load to detect version changes and perform
 * any necessary data migrations while preserving user settings.
 */
function schedulely_check_version() {
    $current_version = get_option('schedulely_version', '0');
    
    if (version_compare($current_version, SCHEDULELY_VERSION, '<')) {
        schedulely_upgrade($current_version);
        update_option('schedulely_version', SCHEDULELY_VERSION);
        
        // Log the upgrade for debugging
        schedulely_log_error(sprintf(
            'Schedulely upgraded from version %s to %s. User settings preserved.',
            $current_version,
            SCHEDULELY_VERSION
        ));
    }
}
add_action('plugins_loaded', 'schedulely_check_version');

/**
 * Handle upgrades between versions
 * 
 * This function performs version-specific data migrations while preserving
 * all user settings. Settings are never reset during updates.
 * 
 * @param string $from_version Previous version number
 */
function schedulely_upgrade($from_version) {
    // Version-specific migrations go here
    // User settings are automatically preserved - we only add/modify as needed
    
    // CRITICAL FIX: Clear old hourly cron and reschedule with twicedaily (v1.0.8+)
    if (version_compare($from_version, '1.0.8', '<')) {
        // Clear ANY existing cron schedule (hourly or otherwise)
        $timestamp = wp_next_scheduled('schedulely_auto_schedule');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'schedulely_auto_schedule');
        }
        // Reschedule with correct frequency
        wp_schedule_event(time(), 'twicedaily', 'schedulely_auto_schedule');
        schedulely_log_error('Cron schedule updated from hourly to twicedaily during upgrade', [
            'from_version' => $from_version,
            'to_version' => SCHEDULELY_VERSION
        ]);
    }
    
    // Ensure cron is scheduled (in case it was lost)
    if (!wp_next_scheduled('schedulely_auto_schedule')) {
        wp_schedule_event(time(), 'twicedaily', 'schedulely_auto_schedule');
    }
}

/**
 * Log errors to WordPress debug log
 * 
 * @param string $message Error message to log
 * @param array $data Additional data to log
 */
function schedulely_log_error($message, $data = []) {
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log(sprintf(
            '[Schedulely] %s | Data: %s',
            $message,
            print_r($data, true)
        ));
    }
}

/**
 * Clear plugin caches
 */
function schedulely_clear_cache() {
    // Clear post cache
    wp_cache_delete('schedulely_available_posts', 'schedulely');
    wp_cache_delete('schedulely_scheduled_posts', 'schedulely');
    
    // Clear object cache
    wp_cache_flush();
    
    // Clear query cache if present
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('posts');
    }
    
    // Trigger action for third-party cache plugins
    do_action('schedulely_clear_cache');
}

