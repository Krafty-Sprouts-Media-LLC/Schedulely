<?php
/**
 * Uninstall Schedulely
 * 
 * Removes all plugin data from the database when the plugin is deleted.
 *
 * @package Schedulely
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('schedulely_post_status');
delete_option('schedulely_posts_per_day');
delete_option('schedulely_start_time');
delete_option('schedulely_end_time');
delete_option('schedulely_active_days');
delete_option('schedulely_min_interval');
delete_option('schedulely_randomize_authors');
delete_option('schedulely_excluded_authors');
delete_option('schedulely_auto_schedule');
delete_option('schedulely_email_notifications');
delete_option('schedulely_notification_users');
delete_option('schedulely_notification_email'); // Legacy - remove if exists
delete_option('schedulely_last_run');
delete_option('schedulely_version');
delete_option('schedulely_welcome_dismissed');

// Clear scheduled cron events
wp_clear_scheduled_hook('schedulely_auto_schedule');

// Delete transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_schedulely_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_schedulely_%'");

// Clear any cached data
wp_cache_flush();

