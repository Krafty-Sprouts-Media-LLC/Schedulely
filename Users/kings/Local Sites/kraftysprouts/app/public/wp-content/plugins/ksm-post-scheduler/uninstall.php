<?php
/**
 * KSM Post Scheduler Uninstall
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It handles cleanup of all plugin data including options, scheduled events,
 * and any other data created by the plugin.
 * 
 * @package KSM_Post_Scheduler
 * @version 1.0.0
 * @author KraftySpouts
 * @copyright 2025 KraftySpouts
 * @license GPL-2.0-or-later
 * 
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - ensure this is a legitimate uninstall
if (!current_user_can('activate_plugins')) {
    return;
}

// Check if the file is being accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clean up all plugin data
 * 
 * @since 1.0.0
 */
function ksm_ps_uninstall_cleanup() {
    // Remove plugin options
    delete_option('ksm_ps_settings');
    
    // Remove any site options (for multisite)
    delete_site_option('ksm_ps_settings');
    
    // Clear any scheduled cron jobs
    $cron_hook = 'ksm_ps_daily_cron';
    $timestamp = wp_next_scheduled($cron_hook);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $cron_hook);
    }
    
    // Clear all cron jobs for this hook (in case there are multiple)
    wp_clear_scheduled_hook($cron_hook);
    
    // Remove any transients created by the plugin
    delete_transient('ksm_ps_status_cache');
    delete_transient('ksm_ps_upcoming_posts');
    
    // Clean up any user meta data (if any was created)
    // delete_metadata('user', 0, 'ksm_ps_user_preference', '', true);
    
    // Clean up any post meta data created by the plugin
    global $wpdb;
    
    // Remove any custom post meta created by the plugin
    $wpdb->delete(
        $wpdb->postmeta,
        array('meta_key' => 'ksm_ps_scheduled_by')
    );
    
    // Remove any custom post meta for original status tracking
    $wpdb->delete(
        $wpdb->postmeta,
        array('meta_key' => 'ksm_ps_original_status')
    );
    
    // If we had custom database tables, we would drop them here
    // Example:
    // $table_name = $wpdb->prefix . 'ksm_ps_schedule_log';
    // $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    
    // Clear any cached data
    wp_cache_flush();
    
    // Log the uninstall (optional - for debugging)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('KSM Post Scheduler: Plugin uninstalled and all data cleaned up.');
    }
}

/**
 * Handle multisite uninstall
 * 
 * @since 1.0.0
 */
function ksm_ps_uninstall_multisite() {
    if (is_multisite()) {
        global $wpdb;
        
        // Get all blog IDs
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
        
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            ksm_ps_uninstall_cleanup();
            restore_current_blog();
        }
        
        // Clean up network-wide options
        delete_site_option('ksm_ps_network_settings');
    } else {
        ksm_ps_uninstall_cleanup();
    }
}

// Execute the uninstall
ksm_ps_uninstall_multisite();

// Final cleanup - remove any remaining traces
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}