<?php
/**
 * Filename: class-notifications.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 06/10/2025
 * Last Modified: 12/10/2025
 * Description: Email Notification System - Sends email notifications for scheduling events
 *
 * @package Schedulely
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Schedulely_Notifications
 * 
 * Handles email notifications for scheduling events.
 */
class Schedulely_Notifications
{

    /**
     * Send scheduling completion notification
     * 
     * @param array $results Scheduling results
     */
    public function send_scheduling_notification($results)
    {
        if (!$this->is_enabled()) {
            return;
        }

        if (!$results['success'] || $results['scheduled_count'] === 0) {
            return; // Don't send notification if nothing was scheduled
        }

        $to = $this->get_notification_email();
        $subject = sprintf(
            __('Schedulely: %d Posts Scheduled Successfully', 'schedulely'),
            $results['scheduled_count']
        );

        $message = $this->build_notification_message($results);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send error notification
     * 
     * @param string $error_message Error message
     */
    public function send_error_notification($error_message)
    {
        if (!$this->is_enabled()) {
            return;
        }

        $to = $this->get_notification_email();
        $subject = __('Schedulely: Scheduling Error', 'schedulely');

        $message = $this->build_error_message($error_message);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Check if notifications are enabled
     * 
     * @return bool
     */
    private function is_enabled()
    {
        return (bool) get_option('schedulely_email_notifications', true);
    }

    /**
     * Get notification email addresses from selected users
     * 
     * @return array|string Email addresses (array for multiple, string for single)
     */
    private function get_notification_email()
    {
        $user_ids = get_option('schedulely_notification_users', []);

        // Fallback: If no users selected, use current admin or site admin
        if (empty($user_ids)) {
            return get_option('admin_email');
        }

        $emails = [];
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user && !empty($user->user_email)) {
                $emails[] = $user->user_email;
            }
        }

        // Return single email as string, multiple as array
        if (count($emails) === 1) {
            return $emails[0];
        }

        return !empty($emails) ? $emails : get_option('admin_email');
    }

    /**
     * Build notification email message
     * 
     * @param array $results Scheduling results
     * @return string HTML email message
     */
    private function build_notification_message($results)
    {
        $site_name = get_bloginfo('name');
        $scheduled_count = $results['scheduled_count'];
        $completed_last_date = isset($results['completed_last_date']) && $results['completed_last_date'];
        $quota = get_option('schedulely_posts_per_day', 8);

        // Get all unique dates
        $dates = array_unique(array_column($results['scheduled_posts'], 'date'));
        sort($dates);
        $start_date = !empty($dates) ? date('M j, Y', strtotime($dates[0])) : '';
        $end_date = !empty($dates) ? date('M j, Y', strtotime(end($dates))) : '';

        // CRITICAL FIX: Count TOTAL posts per date (not just from current run)
        // This fixes the bug where counts reset instead of accumulating
        $scheduler = new Schedulely_Scheduler();
        $posts_per_date = [];
        foreach ($dates as $date) {
            $posts_per_date[$date] = $scheduler->count_posts_on_date($date);
        }

        // Build FULL date status report (ALL dates with their completion status)
        $date_status_html = '';
        $incomplete_dates = 0;
        $complete_dates = 0;

        foreach ($posts_per_date as $date => $count) {
            $date_display = date('l, M j, Y', strtotime($date));
            if ($count >= $quota) {
                $date_status_html .= "✅ <strong>{$date_display}</strong>: {$count}/{$quota} posts (Complete)<br>\n";
                $complete_dates++;
            } else {
                $needed = $quota - $count;
                $date_status_html .= "⚠️ <strong>{$date_display}</strong>: {$count}/{$quota} posts <span style='color: #dc2626;'>(NEEDS {$needed} MORE)</span><br>\n";
                $incomplete_dates++;
            }
        }

        // Overall status
        $overall_status = $incomplete_dates === 0 ? '✅ All dates complete' : "⚠️ {$incomplete_dates} date(s) incomplete";
        $overall_status_color = $incomplete_dates === 0 ? '#059669' : '#dc2626';

        // Get scheduling time window
        $start_time = get_option('schedulely_start_time', '5:00 PM');
        $end_time = get_option('schedulely_end_time', '11:00 PM');

        // Get the current date/time when scheduler ran
        $run_datetime = date('l, M j, Y \a\t g:i A', current_time('timestamp'));

        // Get author randomization status
        $author_randomized = get_option('schedulely_randomize_authors', false) ? __('Yes', 'schedulely') : __('No', 'schedulely');

        // Get completed last date status
        $completion_status = $completed_last_date ? __('Yes (filled previous incomplete date)', 'schedulely') : __('No (started fresh dates)', 'schedulely');

        // Build upcoming posts list (first 10)
        $upcoming_posts_html = '';
        $posts_to_show = array_slice($results['scheduled_posts'], 0, 10);

        foreach ($posts_to_show as $post_data) {
            $display_time = date('M j, g:i A', strtotime($post_data['datetime']));
            $title = esc_html($post_data['title']);
            $upcoming_posts_html .= "• {$display_time} - \"{$title}\"<br>\n";
        }

        // Build URL with selected post types
        $post_types = get_option('schedulely_post_types', ['post']);
        $post_type_param = count($post_types) === 1 ? $post_types[0] : implode(',', $post_types);
        $scheduled_posts_url = admin_url('edit.php?post_status=future&post_type=' . $post_type_param);
        $settings_url = admin_url('tools.php?page=schedulely');

        $message = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">
        Schedulely Notification
    </h2>
    
    <p>Hello,</p>
    
    <p>Schedulely has completed a scheduling run on your site: <strong>{$site_name}</strong></p>
    
    <div style="background: #f9fafb; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
        <strong>SUMMARY</strong><br>
        🕐 Scheduler Ran: <strong>{$run_datetime}</strong><br>
        ✅ Total Posts Scheduled: <strong>{$scheduled_count}</strong><br>
        📅 Date Range: <strong>{$start_date} to {$end_date}</strong><br>
        ⏰ Time Window: <strong>{$start_time} - {$end_time}</strong><br>
        📊 Dates Complete: <strong>{$complete_dates}</strong> | Dates Incomplete: <strong>{$incomplete_dates}</strong><br>
        📋 Filled Previous Incomplete: <strong>{$completion_status}</strong><br>
        🔄 Authors Randomized: <strong>{$author_randomized}</strong>
    </div>
    
    <div style="background: #fef3c7; border-left: 4px solid {$overall_status_color}; padding: 15px; margin: 20px 0;">
        <strong>📅 FULL DATE STATUS REPORT</strong><br>
        <div style="color: {$overall_status_color}; font-size: 16px; font-weight: bold; margin-bottom: 10px;">
            {$overall_status}
        </div>
        {$date_status_html}
    </div>
    
    <div style="margin: 20px 0;">
        <strong>UPCOMING POSTS (Next 10)</strong><br>
        {$upcoming_posts_html}
    </div>
    
    <div style="margin: 20px 0;">
        <a href="{$scheduled_posts_url}" style="display: inline-block; background: #2271b1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View All Scheduled Posts</a>
    </div>
    
    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
    
    <p style="font-size: 0.9em; color: #6b7280;">
        This email was sent by Schedulely plugin.<br>
        To disable these notifications, visit: <a href="{$settings_url}">Plugin Settings</a>
    </p>
    
    <p style="font-size: 0.85em; color: #9ca3af; text-align: center;">
        Made with ❤️ by <a href="https://kraftysprouts.com" style="color: #2271b1;">Krafty Sprouts Media, LLC</a>
    </p>
</body>
</html>
HTML;

        return $message;
    }

    /**
     * Build error notification message
     * 
     * @param string $error_message Error message
     * @return string HTML email message
     */
    private function build_error_message($error_message)
    {
        $site_name = get_bloginfo('name');
        $settings_url = admin_url('tools.php?page=schedulely');

        $message = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #d63638; border-bottom: 2px solid #d63638; padding-bottom: 10px;">
        Schedulely Error Notification
    </h2>
    
    <p>Hello,</p>
    
    <p>Schedulely encountered an error while attempting to schedule posts on your site: <strong>{$site_name}</strong></p>
    
    <div style="background: #fef2f2; border-left: 4px solid #d63638; padding: 15px; margin: 20px 0;">
        <strong>ERROR MESSAGE:</strong><br>
        {$error_message}
    </div>
    
    <p>Please check your <a href="{$settings_url}">Schedulely settings</a> and try again.</p>
    
    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
    
    <p style="font-size: 0.85em; color: #9ca3af; text-align: center;">
        Made with ❤️ by <a href="https://kraftysprouts.com" style="color: #2271b1;">Krafty Sprouts Media</a>
    </p>
</body>
</html>
HTML;

        return $message;
    }
}

