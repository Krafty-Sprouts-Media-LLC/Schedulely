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
delete_option('schedulely_shuffle_queue');
delete_option('schedulely_pool_size');
delete_option('schedulely_scheduling_mode');
delete_option('schedulely_ai_order_enabled');
delete_option('schedulely_ai_api_key');
delete_option('schedulely_ai_base_url');
delete_option('schedulely_ai_model');
delete_option('schedulely_ai_reorder_log');
delete_option('schedulely_randomize_authors');
delete_option('schedulely_excluded_authors');
delete_option('schedulely_auto_schedule');
delete_option('schedulely_email_notifications');
delete_option('schedulely_ai_email_summary');
delete_option('schedulely_notification_users');
delete_option('schedulely_notification_email'); // Legacy - remove if exists
delete_option('schedulely_last_run');
delete_option('schedulely_version');
// Per-user welcome dismiss (switched to user_meta in 1.6.0; also clear legacy option)
delete_option('schedulely_welcome_dismissed');
delete_metadata( 'user', 0, 'schedulely_welcome_dismissed', '', true );

// Clear scheduled cron events
wp_clear_scheduled_hook('schedulely_auto_schedule');

// Delete transients
global $wpdb;
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like('_transient_schedulely_') . '%'
));
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like('_transient_timeout_schedulely_') . '%'
));

// Clear only the named cache keys Schedulely writes.
// wp_cache_flush() is intentionally NOT called — it would evict the entire
// site object cache, which is unnecessary since the plugin data has been deleted.
wp_cache_delete('schedulely_available_posts', 'schedulely');
wp_cache_delete('schedulely_scheduled_posts', 'schedulely');

