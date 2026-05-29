<?php
/**
 * Filename: class-notifications.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 06/10/2025
 * Last Modified: 03/05/2026
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
     * Send scheduling completion notification.
     *
     * If `schedulely_ai_email_summary` is enabled and WP 7.0 AI is available,
     * a 2–3 sentence AI-generated summary is prepended to the email.
     *
     * @since 1.0.0
     * @since 1.6.0 AI summary support added (P3-T9).
     *
     * @param array $results Scheduling results.
     */
    public function send_scheduling_notification($results)
    {
        if (!$this->is_enabled()) {
            return;
        }

        if (!$results['success'] || $results['scheduled_count'] === 0) {
            return;
        }

        $to      = $this->get_notification_email();
        $subject = sprintf(
            __('Schedulely: %d Posts Scheduled Successfully', 'schedulely'),
            $results['scheduled_count']
        );

        $message = $this->build_notification_message($results);

        // Prepend AI-generated summary when the feature is enabled (WP 7.0+ only).
        if ( apply_filters( 'schedulely_feature_ai_email_summary', true )
            && get_option( 'schedulely_ai_email_summary', false )
            && function_exists( 'wp_ai_client_prompt' ) ) {
            $summary = $this->generate_ai_email_summary( $results );
            if ( '' !== $summary ) {
                $summary_html = '<div style="background:#f0f7ff; border-left:4px solid #2271b1; padding:12px 16px; margin-bottom:20px; font-size:15px; line-height:1.6;">'
                    . '<strong>' . esc_html__( 'AI Summary', 'schedulely' ) . '</strong><br>'
                    . wp_kses_post( $summary )
                    . '</div>';
                $message = $summary_html . $message;
            }
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Generate a 2–3 sentence AI summary of the scheduling run.
     *
     * Only called when `schedulely_ai_email_summary` is true and WP 7.0 AI is available.
     * Falls back silently to an empty string on any error.
     *
     * @since 1.6.0
     * @param array $results Scheduling results.
     * @return string Paragraph of HTML-safe plain text, or empty string.
     */
    private function generate_ai_email_summary( array $results ): string {
        $count      = (int) $results['scheduled_count'];
        $dates      = array_unique( array_column( $results['scheduled_posts'] ?? [], 'date' ) );
        sort( $dates );
        $date_range = ! empty( $dates )
            ? ( wp_date( 'M j', strtotime( $dates[0] ) ) . ( count( $dates ) > 1 ? ' – ' . wp_date( 'M j', strtotime( end( $dates ) ) ) : '' ) )
            : '';
        $ai_applied = ! empty( $results['ai_queue_ordered'] );

        // Timezone distribution context (when timezone-aware ordering was used).
        $tz_context = '';
        if ( ! empty( $results['timezone_distribution'] ) && is_array( $results['timezone_distribution'] ) ) {
            $parts = [];
            foreach ( $results['timezone_distribution'] as $group => $group_count ) {
                if ( $group_count > 0 ) {
                    $parts[] = ucfirst( $group ) . ': ' . $group_count;
                }
            }
            if ( ! empty( $parts ) ) {
                $tz_context = ' US timezone distribution: ' . implode( ', ', $parts ) . '. Posts were scheduled within each timezone\'s active hours (7 AM – 11 PM local time).';
            }
        }

        // Describe the ordering method accurately — PHP runs must not be called "AI".
        $ordering_info = $ai_applied ? $this->get_last_ordering_info() : [ 'method' => '', 'note' => '' ];
        if ( ! $ai_applied ) {
            $ordering_desc = 'none (queue order left unchanged)';
        } elseif ( 'php' === $ordering_info['method'] ) {
            $ordering_desc = 'deterministic PHP ordering (no AI model used)';
        } else {
            $ordering_desc = 'AI reordering';
        }
        $ordering_detail = ( '' !== trim( (string) $ordering_info['note'] ) ) ? ' Ordering detail: ' . $ordering_info['note'] : '';

        $prompt = sprintf(
            'You are a publishing operations assistant. Write a 2–3 sentence plain-text summary of this automated post-scheduling run. Be specific, use numbers, no marketing tone. Scheduled: %d posts. Date range: %s. Queue ordering: %s.%s%s',
            $count,
            $date_range ?: __( 'same day', 'schedulely' ),
            $ordering_desc,
            $ordering_detail,
            $tz_context
        );

        try {
            $builder = wp_ai_client_prompt( $prompt )
                ->using_system_instruction( 'Respond in plain text only. 2–3 sentences maximum. No markdown.' )
                ->using_temperature( 0.3 );

            if ( ! $builder->is_supported_for_text_generation() ) {
                return '';
            }
            return esc_html( trim( (string) $builder->generate_text() ) );
        } catch ( \Throwable $e ) {
            schedulely_log_error( 'AI email summary failed: ' . $e->getMessage() );
            return '';
        }
    }

    /**
     * Read the method + note of the most recent queue-ordering attempt.
     *
     * Source of truth is the latest AI Reorder Log entry (newest is index 0),
     * which is the only place that distinguishes deliberate PHP ordering from an
     * AI run that fell back to PHP — and carries the human note (PHP grouping
     * summary or AI reconciliation note). The reorder runs earlier in the same
     * request as this notification, so index 0 is this run's entry.
     *
     * @since 1.8.2
     * @return array{method:string,note:string} method is 'php', 'ai', or ''.
     */
    private function get_last_ordering_info(): array
    {
        $log = get_option('schedulely_ai_reorder_log', []);
        if (!is_array($log) || empty($log) || !is_array($log[0])) {
            return ['method' => '', 'note' => ''];
        }
        $latest = $log[0];
        $model  = isset($latest['model']) ? (string) $latest['model'] : '';
        $note   = isset($latest['note']) ? (string) $latest['note'] : '';
        return [
            'method' => ('php' === $model) ? 'php' : 'ai',
            'note'   => $note,
        ];
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
        $start_date = ! empty( $dates ) ? ( wp_date( 'M j, Y', strtotime( $dates[0] ) ) ?? '' ) : '';
        $end_date   = ! empty( $dates ) ? ( wp_date( 'M j, Y', strtotime( end( $dates ) ) ) ?? '' ) : '';

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
            $date_display = wp_date( 'l, M j, Y', strtotime( $date ) ) ?? $date;
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

        // Get the current date/time when scheduler ran.
        // wp_date() respects the site timezone set in Settings → General.
        $run_datetime = wp_date('l, M j, Y \a\t g:i A');

        // Get author randomization status
        $author_randomized = get_option('schedulely_randomize_authors', false) ? __('Yes', 'schedulely') : __('No', 'schedulely');

        // Queue ordering (optional): did this run order the queue, and how (AI vs PHP)?
        $ai_enabled = (bool) get_option('schedulely_ai_order_enabled', false);
        $ai_used = !empty($results['ai_queue_ordered']);
        $ordering_info = $ai_used ? $this->get_last_ordering_info() : ['method' => '', 'note' => ''];
        if ($ai_used) {
            if ('php' === $ordering_info['method']) {
                $ai_queue_summary = __('Applied (PHP) — the queue was ordered deterministically in PHP. No AI provider was called and no tokens were used.', 'schedulely');
            } else {
                $ai_queue_summary = __('Applied (AI) — the queue was reordered by the AI before publish times were assigned.', 'schedulely');
            }
        } elseif ($ai_enabled) {
            $ai_queue_summary = __('Not applied — ordering did not run this time; the shuffle or draft order was used.', 'schedulely');
        } else {
            $ai_queue_summary = __('Not used — queue ordering is disabled in Schedulely settings.', 'schedulely');
        }
        $ai_queue_summary_esc = esc_html($ai_queue_summary);

        // Second line: the method's own note — PHP grouping summary or AI reconciliation note.
        $ordering_note_html = '';
        if ('' !== trim((string) $ordering_info['note'])) {
            $ordering_note_html = '<br><span style="font-size:0.9em; color:#374151;">' . esc_html($ordering_info['note']) . '</span>';
        }

        // Get completed last date status
        $completion_status = $completed_last_date ? __('Yes (filled previous incomplete date)', 'schedulely') : __('No (started fresh dates)', 'schedulely');

        // Build upcoming posts list (first 10)
        $upcoming_posts_html = '';
        $posts_to_show = array_slice($results['scheduled_posts'], 0, 10);

        foreach ($posts_to_show as $post_data) {
            $display_time = wp_date( 'M j, g:i A', strtotime( $post_data['datetime'] ) ) ?? $post_data['datetime'];
            $title = esc_html($post_data['title']);
            $upcoming_posts_html .= "• {$display_time} - \"{$title}\"<br>\n";
        }

        // Build URL with selected post types
        $post_types = get_option('schedulely_post_types', ['post']);
        // If single post type, use it; if multiple, use base URL (WordPress will show all future posts)
        if (count($post_types) === 1) {
            $scheduled_posts_url = admin_url('edit.php?post_status=future&post_type=' . esc_attr($post_types[0]));
        } else {
            // Multiple post types: link to base future posts page
            // Note: WordPress defaults to 'post' type, but users can filter by type in the admin
            $scheduled_posts_url = admin_url('edit.php?post_status=future');
        }
        $settings_url = admin_url('tools.php?page=schedulely');

        $ai_log_hint_html = '';
        if ($ai_enabled && !$ai_used) {
            $ai_log_hint_html = '<div style="font-size: 0.9em; margin-top: 8px; color: #374151;">'
                . esc_html__('Latest API attempt (excerpts + error code): Tools → Schedulely → “AI queue reorder log”.', 'schedulely')
                . ' <a href="' . esc_url($settings_url) . '" style="color: #2271b1;">'
                . esc_html__('Open Schedulely settings', 'schedulely')
                . '</a></div>';
        }

        // Timezone distribution line for the email body.
        $tz_distribution_html = '';
        if ( ! empty( $results['timezone_distribution'] ) && is_array( $results['timezone_distribution'] ) ) {
            $tz_parts = [];
            foreach ( $results['timezone_distribution'] as $tz_group => $tz_count ) {
                if ( $tz_count > 0 ) {
                    $tz_parts[] = ucfirst( $tz_group ) . ': ' . $tz_count;
                }
            }
            if ( ! empty( $tz_parts ) ) {
                $tz_distribution_html = '🌎 US Timezone Distribution: <strong>' . esc_html( implode( ' · ', $tz_parts ) ) . '</strong><br>';
            }
        }

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
        🔄 Authors Randomized: <strong>{$author_randomized}</strong><br>
        🧠 Queue ordering (this run): <strong>{$ai_queue_summary_esc}</strong>{$ordering_note_html}
        {$ai_log_hint_html}
        {$tz_distribution_html}
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

