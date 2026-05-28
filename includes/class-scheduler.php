<?php
/**
 * Filename: class-scheduler.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 06/10/2025
 * Last Modified: 03/05/2026
 * Description: Main Scheduling Engine - Handles all post scheduling logic with last date completion and optional overnight time windows.
 *
 * @package Schedulely
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Schedulely_Scheduler
 * 
 * Handles all post scheduling logic with last date completion.
 */
class Schedulely_Scheduler
{

    /**
     * Maximum posts fetched from the monitored status per scheduling run.
     *
     * Default 1500. The shuffle feature and AI reordering both benefit from a large
     * pool — a low cap reduces variety. Raise or lower via filter on hosts with tight
     * execution time limits:
     *
     *   add_filter( 'schedulely_max_posts_per_run', fn() => 500 );
     *
     * A settings field is also available in Tools → Schedulely under Queue Order.
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_POSTS_PER_RUN = Schedulely_Defaults::MAX_POSTS_PER_RUN;

    /**
     * Author manager instance
     *
     * @var Schedulely_Author_Manager
     */
    private $author_manager;

    /**
     * Timezone queue — populated by maybe_apply_ai_queue_order() when
     * US timezone-aware ordering is active. Each entry is:
     *   [ 'id' => int, 'timezone_group' => string ]
     *
     * Keyed by post ID for O(1) lookup during scheduling.
     *
     * @since 1.7.0
     * @var array<int,string>  post_id => timezone_group
     */
    private array $timezone_queue = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->author_manager = new Schedulely_Author_Manager();
    }

    /**
     * Run the scheduling process.
     *
     * @since 1.0.0
     * @since 1.6.0 Added $allow_ai_reorder parameter. AI reordering is only
     *              permitted on manual runs (admin is waiting and timeouts are
     *              visible). Cron-driven runs skip AI and fall back to shuffle
     *              or original order to avoid blocking the cron worker for up
     *              to 20 minutes on an external HTTP call.
     *
     * @param bool $allow_ai_reorder Whether to allow AI queue reordering.
     *                               True on manual runs, false on cron runs.
     * @return array Results of scheduling operation.
     */
    public function run_schedule( bool $allow_ai_reorder = false )
    {
        $results = [
            'success' => false,
            'scheduled_count' => 0,
            'completed_last_date' => false,
            'message' => '',
            'errors' => [],
            'scheduled_posts' => [],
            'ai_queue_ordered' => false,
        ];

        $quota = get_option('schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY);
        $available_posts = $this->get_available_posts();
        $ai_ordered = false;

        if (!empty($available_posts) && count($available_posts) > 1) {
            if ( $allow_ai_reorder ) {
                // AI reordering only on manual runs (never blocks cron worker).
                $ai_ordered = $this->maybe_apply_ai_queue_order($available_posts);
            }
            if (!$ai_ordered && get_option('schedulely_shuffle_queue', Schedulely_Defaults::SHUFFLE_QUEUE)) {
                shuffle($available_posts);
            }
        }

        if (empty($available_posts)) {
            $results['message'] = sprintf(
                __('No posts available in %s status to schedule.', 'schedulely'),
                get_option('schedulely_post_status', 'draft')
            );
            return $results;
        }

        // Find the last scheduled date
        $last_scheduled_date = $this->get_last_scheduled_date();

        // Determine starting date and completion count
        $start_date = null;
        $complete_count = 0;

        if ($last_scheduled_date) {
            $posts_on_last_date = $this->count_posts_on_date($last_scheduled_date);
            list($last_win_start, $last_win_end) = $this->logical_window_bounds_ts($last_scheduled_date);
            $now_ts = time();

            if ($posts_on_last_date < $quota && $last_win_end >= $now_ts) {
                $complete_count = $quota - $posts_on_last_date;
                $start_date = $last_scheduled_date;
                $results['completed_last_date'] = true;
            } elseif ($posts_on_last_date < $quota && $last_win_end < $now_ts) {
                $start_date = $this->get_next_scheduling_date();
            } elseif ($posts_on_last_date >= $quota) {
                $start_date = $this->get_next_active_date($last_scheduled_date);
            }
        } else {
            // No scheduled posts exist, start from today/tomorrow
            $start_date = $this->get_next_scheduling_date();
        }

        // Schedule the posts
        $scheduling_results = $this->schedule_posts_from_date($available_posts, $start_date, $complete_count);

        // Merge results
        $results['success'] = $scheduling_results['success'];
        $results['scheduled_count'] = $scheduling_results['scheduled_count'];
        $results['scheduled_posts'] = $scheduling_results['scheduled_posts'];
        $results['errors'] = $scheduling_results['errors'];
        $results['message'] = $scheduling_results['message'];
        $results['ai_queue_ordered'] = $ai_ordered;

        if ($ai_ordered && $results['success'] && $results['scheduled_count'] > 0) {
            $results['message'] .= ' ' . __('AI reordered the queue for better series spacing.', 'schedulely');
        }

        // Clear cache
        schedulely_clear_cache();

        return $results;
    }

    /**
     * Optionally reorder posts via AI. Mutates $posts on success.
     *
     * When US timezone-aware ordering is enabled, returns an array of
     * [ 'id' => int, 'timezone_group' => string ] maps instead of plain IDs,
     * stored in $this->timezone_queue for use by schedule_posts_from_date().
     *
     * @since 1.0.0
     * @since 1.7.0 Timezone-aware ordering support.
     *
     * @param array $posts Post IDs (by reference).
     * @return bool True when AI order was applied.
     */
    private function maybe_apply_ai_queue_order(array &$posts)
    {
        if (count($posts) < 2) {
            return false;
        }

        if ( ! apply_filters( 'schedulely_feature_ai_ordering', true ) ) {
            return false;
        }

        if (!get_option('schedulely_ai_order_enabled', false)) {
            return false;
        }

        $using_wp_ai = function_exists( 'wp_ai_client_prompt' );
        if ( ! $using_wp_ai ) {
            $key = apply_filters('schedulely_ai_api_key', get_option('schedulely_ai_api_key', ''));
            if ('' === trim((string) $key)) {
                return false;
            }
        }

        $ai = new Schedulely_AI_Order();

        // Timezone-aware path.
        if ( get_option( 'schedulely_ai_us_timezone_ordering', Schedulely_Defaults::AI_US_TIMEZONE_ORDERING ) ) {
            $reordered = $ai->reorder_post_ids_with_timezone( $posts );
            // reorder_post_ids_with_timezone never returns WP_Error — it falls back internally.
            // Build a keyed map for O(1) lookup during scheduling.
            $this->timezone_queue = [];
            foreach ( $reordered as $entry ) {
                $this->timezone_queue[ (int) $entry['id'] ] = $entry['timezone_group'];
            }
            $posts = array_column( $reordered, 'id' );
            return true;
        }

        // Standard path.
        $reordered = $ai->reorder_post_ids($posts);

        if (is_wp_error($reordered)) {
            schedulely_log_error(
                'Schedulely AI queue order failed: ' . $reordered->get_error_message(),
                [ 'code' => $reordered->get_error_code(), 'post_count' => count($posts) ]
            );
            return false;
        }

        $posts = $reordered;
        return true;
    }

    /**
     * Get available posts based on monitored status
     * 
     * @return array Array of post IDs
     */
    private function get_available_posts()
    {
        $status = get_option('schedulely_post_status', Schedulely_Defaults::POST_STATUS);
        $post_types = get_option('schedulely_post_types', Schedulely_Defaults::POST_TYPES);

        // Read the user-configured pool size (set in Tools → Schedulely).
        // The filter lets hosts with tight execution time lower it programmatically.
        $configured_max = (int) get_option( 'schedulely_pool_size', self::MAX_POSTS_PER_RUN );
        $max = (int) apply_filters( 'schedulely_max_posts_per_run', $configured_max );
        if ( $max < 1 )     $max = 1;
        if ( $max > 10000 ) $max = 10000;

        $args = [
            'post_type' => $post_types,
            'post_status' => $status,
            'posts_per_page' => $max,
            'orderby' => 'date',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        // Polylang integration: fetch posts across all languages, not only current language context.
        if (function_exists('pll_languages_list')) {
            $args['lang'] = '';
        }

        return get_posts($args);
    }

    /**
     * Whether the configured start/end times cross midnight (end is at or before start on the same calendar day).
     *
     * @param string|null $start_time Start time string or null to read option.
     * @param string|null $end_time   End time string or null to read option.
     * @return bool
     */
    private function is_overnight_window($start_time = null, $end_time = null)
    {
        if (null === $start_time) {
            $start_time = get_option('schedulely_start_time', '5:00 PM');
        }
        if (null === $end_time) {
            $end_time = get_option('schedulely_end_time', '11:00 PM');
        }

        $ref = '2000-01-01';
        $start_ts = $this->site_local_string_to_timestamp($ref, $start_time);
        $end_ts = $this->site_local_string_to_timestamp($ref, $end_time);

        if (false === $start_ts || false === $end_ts) {
            return false;
        }

        return $end_ts <= $start_ts;
    }

    /**
     * Interpret Y-m-d plus time string in the site timezone (not PHP default timezone).
     * @since 1.5.3
     * @param string $date_ymd Y-m-d.
     * @param string $time_str  Time from settings (e.g. "3:00 PM").
     * @return int|false Unix timestamp, or false on parse failure.
     */
    private function site_local_string_to_timestamp($date_ymd, $time_str)
    {
        $time_str = trim((string) $time_str);
        if ('' === $time_str || !is_string($date_ymd) || '' === $date_ymd) {
            return false;
        }

        try {
            $tz = wp_timezone();
            $dt = new DateTimeImmutable($date_ymd . ' ' . $time_str, $tz);

            return $dt->getTimestamp();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Parse post_date-style local datetime (blog-local storage).
     *
     * @param string $mysql Y-m-d H:i:s.
     * @return int|false
     */
    private function site_local_mysql_to_timestamp($mysql)
    {
        if (!is_string($mysql) || '' === $mysql) {
            return false;
        }

        try {
            $tz = wp_timezone();
            $dt = date_create_from_format('Y-m-d H:i:s', $mysql, $tz);
            if (false === $dt) {
                $dt = date_create_from_format('Y-m-d G:i:s', $mysql, $tz);
            }

            return $dt ? $dt->getTimestamp() : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add calendar days in site timezone (DST-safe).
     *
     * @param string $date_ymd Y-m-d.
     * @param int    $days     Delta (e.g. 1 or -1).
     * @return string|false
     */
    private function site_add_calendar_days($date_ymd, $days)
    {
        try {
            $tz = wp_timezone();
            $dt = new DateTimeImmutable($date_ymd . ' 12:00:00', $tz);

            return $dt->modify(((int) $days) . ' days')->format('Y-m-d');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Start/end timestamps (inclusive) for the logical publishing window anchored on $anchor_date (Y-m-d).
     *
     * @param string $anchor_date Anchor date Y-m-d.
     * @return array{0: int, 1: int} [start_ts, end_ts].
     */
    private function logical_window_bounds_ts($anchor_date)
    {
        $start_time = get_option('schedulely_start_time', '5:00 PM');
        $end_time = get_option('schedulely_end_time', '11:00 PM');

        $start_ts = $this->site_local_string_to_timestamp($anchor_date, $start_time);
        if (false === $start_ts) {
            return [0, 0];
        }

        if ($this->is_overnight_window($start_time, $end_time)) {
            $end_day = $this->site_add_calendar_days($anchor_date, 1);
            if (false === $end_day) {
                return [0, 0];
            }
            $end_ts = $this->site_local_string_to_timestamp($end_day, $end_time);
        } else {
            $end_ts = $this->site_local_string_to_timestamp($anchor_date, $end_time);
        }

        if (false === $end_ts) {
            return [0, 0];
        }

        return [$start_ts, $end_ts];
    }

    /**
     * MySQL local datetime strings for the logical window (for SQL BETWEEN).
     *
     * @param string $anchor_date Y-m-d.
     * @return array{0: string, 1: string}
     */
    private function logical_window_bounds_mysql($anchor_date)
    {
        list($start_ts, $end_ts) = $this->logical_window_bounds_ts($anchor_date);

        return [
            wp_date('Y-m-d H:i:s', $start_ts),
            wp_date('Y-m-d H:i:s', $end_ts),
        ];
    }

    /**
     * Logical anchor date (Y-m-d) for a scheduled post timestamp.
     *
     * @param int $ts Unix timestamp (site-local semantics via strtotime/wp_date).
     * @return string Y-m-d anchor.
     */
    private function logical_anchor_from_timestamp($ts)
    {
        if (!$this->is_overnight_window()) {
            return wp_date('Y-m-d', $ts);
        }

        $post_day = wp_date('Y-m-d', $ts);
        list($ws, $we) = $this->logical_window_bounds_ts($post_day);

        if ($ts >= $ws && $ts <= $we) {
            return $post_day;
        }

        $prev = $this->site_add_calendar_days($post_day, -1);
        if (false === $prev) {
            return $post_day;
        }
        list($ws2, $we2) = $this->logical_window_bounds_ts($prev);

        if ($ts >= $ws2 && $ts <= $we2) {
            return $prev;
        }

        return $post_day;
    }

    /**
     * Whether a timestamp falls inside the logical window for an anchor day.
     *
     * @param int    $ts          Unix timestamp.
     * @param string $anchor_date Y-m-d.
     * @return bool
     */
    private function is_timestamp_in_logical_window($ts, $anchor_date)
    {
        list($ws, $we) = $this->logical_window_bounds_ts($anchor_date);

        return ($ts >= $ws && $ts <= $we);
    }

    /**
     * Whether the anchor calendar day is an active weekday per settings.
     *
     * @param string $anchor_date Y-m-d.
     * @return bool
     */
    private function is_anchor_active_day($anchor_date)
    {
        $active_days = get_option('schedulely_active_days', [1, 2, 3, 4, 5, 6, 0]);
        $anchor_ts = $this->site_local_string_to_timestamp($anchor_date, '12:00:00');
        if (false === $anchor_ts) {
            return false;
        }
        $dow = (int) wp_date('w', $anchor_ts);

        return in_array($dow, $active_days, true);
    }

    /**
     * Get the last/furthest scheduled date
     * 
     * @return string|null Date string (Y-m-d) or null
     */
    public function get_last_scheduled_date()
    {
        global $wpdb;

        $post_types = get_option('schedulely_post_types', ['post']);
        $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $last_post_date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_date
                 FROM {$wpdb->posts}
                 WHERE post_status = %s
                 AND post_type IN ($post_types_placeholders)
                 ORDER BY post_date DESC
                 LIMIT 1",
                array_merge(['future'], $post_types)
            )
        );

        if (empty($last_post_date)) {
            return null;
        }

        $ts = $this->site_local_mysql_to_timestamp($last_post_date);
        if (false === $ts) {
            return null;
        }

        return $this->logical_anchor_from_timestamp($ts);
    }

    /**
     * Count future posts in the logical publishing window for an anchor date (includes next calendar morning when overnight).
     *
     * @param string $anchor_date Logical anchor Y-m-d.
     * @return int Post count in the window.
     */
    public function count_posts_on_date($anchor_date)
    {
        global $wpdb;

        $post_types = get_option('schedulely_post_types', ['post']);
        $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        list($start_mysql, $end_mysql) = $this->logical_window_bounds_mysql($anchor_date);

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->posts}
                 WHERE post_status = 'future'
                 AND post_type IN ($post_types_placeholders)
                 AND post_date >= %s
                 AND post_date <= %s",
                array_merge($post_types, [$start_mysql, $end_mysql])
            )
        );

        return (int) $count;
    }

    /**
     * Get next scheduling anchor date when no scheduled posts exist (logical day; supports overnight windows).
     *
     * @return string Date string (Y-m-d)
     */
    private function get_next_scheduling_date()
    {
        $now_ts = time();
        $today = wp_date('Y-m-d', $now_ts);
        $yesterday = $this->site_add_calendar_days($today, -1);
        $candidates = (false !== $yesterday) ? [$yesterday, $today] : [$today];

        foreach ($candidates as $anchor) {
            if (!$this->is_anchor_active_day($anchor)) {
                continue;
            }
            if ($this->is_timestamp_in_logical_window($now_ts, $anchor)) {
                return $anchor;
            }
        }

        $d = $today;
        for ($i = 0; $i < 21; $i++) {
            if (!$this->is_anchor_active_day($d)) {
                $d = $this->get_next_active_date($d);
                continue;
            }

            list($ws, $we) = $this->logical_window_bounds_ts($d);

            if ($we < $now_ts) {
                $d = $this->get_next_active_date($d);
                continue;
            }

            return $d;
        }

        return $today;
    }

    /**
     * Get next active date after a given date
     * 
     * @param string $current_date Current date (Y-m-d)
     * @return string Next active date (Y-m-d)
     */
    private function get_next_active_date($current_date)
    {
        $active_days = get_option('schedulely_active_days', [1, 2, 3, 4, 5, 6, 0]);
        $next_date = $this->site_add_calendar_days($current_date, 1);
        if (false === $next_date) {
            return $current_date;
        }
        $attempts = 0;

        // Find next active day (max 7 attempts)
        while ($attempts < 7) {
            $noon_ts = $this->site_local_string_to_timestamp($next_date, '12:00:00');
            $day_of_week = false !== $noon_ts ? (int) wp_date('w', $noon_ts) : 0;
            if (in_array($day_of_week, $active_days)) {
                return $next_date;
            }
            $next_date = $this->site_add_calendar_days($next_date, 1);
            if (false === $next_date) {
                break;
            }
            $attempts++;
        }

        return $next_date; // Fallback
    }

    /**
     * Schedule posts starting from a specific date.
     *
     * Dispatches to the appropriate time-slot strategy based on
     * schedulely_scheduling_mode:
     *   random     — existing random trial-and-error placement
     *   sequential — perfectly even intervals across the window
     *   hybrid     — even slots, random time within each slot
     *
     * @since 1.0.0
     * @since 1.6.0 Added sequential and hybrid modes.
     *
     * @param array  $posts         Array of post IDs.
     * @param string $start_date    Starting anchor date (Y-m-d).
     * @param int    $complete_first Posts already on the start date (deficit completion).
     * @return array Scheduling results.
     */
    private function schedule_posts_from_date($posts, $start_date, $complete_first = 0)
    {
        $quota        = (int) get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY );
        $mode         = (string) get_option( 'schedulely_scheduling_mode', Schedulely_Defaults::SCHEDULING_MODE );
        $current_date = $start_date;
        $posts_scheduled_today = 0;
        $scheduled_count  = 0;
        $scheduled_posts  = [];
        $errors           = [];
        $retry_total      = 0;

        // Pre-computed slot queue for sequential/hybrid modes (rebuilt per day).
        $day_slots     = [];
        $slot_index    = 0;

        // Prime the post object cache once before the loop.
        if ( function_exists( '_prime_post_caches' ) ) {
            _prime_post_caches( $posts, false, false );
        }

        if ( $complete_first > 0 ) {
            $posts_scheduled_today = $quota - $complete_first;
            $already_scheduled_times = $this->get_scheduled_timestamps_for_anchor( $current_date );
            // For slot modes, pre-fill already-used slots by skipping ahead.
            if ( in_array( $mode, [ 'sequential', 'hybrid' ], true ) ) {
                $day_slots  = $this->generate_day_slots( $current_date, $quota, $mode );
                $slot_index = $posts_scheduled_today; // Skip already-placed posts.
            }
        } else {
            $already_scheduled_times = [];
            if ( in_array( $mode, [ 'sequential', 'hybrid' ], true ) ) {
                $day_slots  = $this->generate_day_slots( $current_date, $quota, $mode );
                $slot_index = 0;
            }
        }

        foreach ( $posts as $post_id ) {
            if ( $posts_scheduled_today >= $quota ) {
                $current_date          = $this->get_next_active_date( $current_date );
                $posts_scheduled_today = 0;
                $already_scheduled_times = [];
                if ( in_array( $mode, [ 'sequential', 'hybrid' ], true ) ) {
                    $day_slots  = $this->generate_day_slots( $current_date, $quota, $mode );
                    $slot_index = 0;
                }
            }

            // -----------------------------------------------------------------
            // Choose the datetime based on mode.
            // -----------------------------------------------------------------
            if ( in_array( $mode, [ 'sequential', 'hybrid' ], true ) ) {
                // Slot-based modes: pull the next pre-computed slot.
                $datetime = isset( $day_slots[ $slot_index ] ) ? $day_slots[ $slot_index ] : false;
                $slot_index++;

                if ( false === $datetime ) {
                    $errors[] = sprintf(
                        __( 'No available slot for post ID %d (day full — consider raising Posts Per Day or widening the time window).', 'schedulely' ),
                        $post_id
                    );
                    $posts_scheduled_today++; // Count toward quota to advance the day.
                    continue;
                }
            } else {
                // Random mode — use timezone active-hours overlap if available, otherwise full window.
                $overlap = null;
                if ( ! empty( $this->timezone_queue ) ) {
                    $group   = $this->timezone_queue[ $post_id ] ?? 'general';
                    $overlap = $this->get_timezone_active_overlap( $current_date, $group );
                }

                $datetime = $this->generate_random_datetime( $current_date, $already_scheduled_times, $overlap );

                if ( false === $datetime ) {
                    $current_date          = $this->get_next_active_date( $current_date );
                    $posts_scheduled_today = 0;
                    $already_scheduled_times = [];
                    $overlap = null !== $overlap
                        ? $this->get_timezone_active_overlap( $current_date, $this->timezone_queue[ $post_id ] ?? 'general' )
                        : null;
                    $datetime = $this->generate_random_datetime( $current_date, [], $overlap );

                    if ( false === $datetime ) {
                        $errors[] = sprintf( __( 'Failed to generate time slot for post ID %d', 'schedulely' ), $post_id );
                        continue;
                    }
                }
            }

            // -----------------------------------------------------------------
            // Author assignment (unchanged).
            // -----------------------------------------------------------------
            $author_id = null;
            if ( $this->author_manager->is_enabled() ) {
                $post = get_post( $post_id );
                if ( $post && $post->post_author ) {
                    $current_author_id = (int) $post->post_author;
                    $author_id = $this->author_manager->is_author_preserved( $current_author_id )
                        ? $current_author_id
                        : $this->author_manager->get_random_author();
                } else {
                    $author_id = $this->author_manager->get_random_author();
                }
            }

            $success = $this->schedule_post( $post_id, $datetime, $author_id );

            if ( $success ) {
                $scheduled_count++;
                $posts_scheduled_today++;
                $ts_slot = $this->site_local_mysql_to_timestamp( $datetime );
                if ( false !== $ts_slot ) {
                    $already_scheduled_times[] = $ts_slot;
                }
                $scheduled_posts[] = [
                    'post_id'  => $post_id,
                    'datetime' => $datetime,
                    'title'    => get_the_title( $post_id ),
                    'date'     => $current_date,
                ];
            } else {
                // For slot modes a failed write is just counted toward quota — no retry.
                if ( in_array( $mode, [ 'sequential', 'hybrid' ], true ) ) {
                    $posts_scheduled_today++;
                    $errors[] = sprintf( __( 'Failed to schedule post ID %d', 'schedulely' ), $post_id );
                } else {
                    // Random mode: retry up to 2 times with different times.
                    $max_retries   = 2;
                    $retry_count   = 0;
                    $retry_success = false;

                    while ( $retry_count < $max_retries && ! $retry_success ) {
                        $retry_count++;
                        $retry_datetime = $this->generate_random_datetime( $current_date, $already_scheduled_times );

                        if ( false !== $retry_datetime ) {
                            $retry_success = $this->schedule_post( $post_id, $retry_datetime, $author_id );
                            if ( $retry_success ) {
                                $retry_total++;
                                $scheduled_count++;
                                $posts_scheduled_today++;
                                $retry_ts = $this->site_local_mysql_to_timestamp( $retry_datetime );
                                if ( false !== $retry_ts ) {
                                    $already_scheduled_times[] = $retry_ts;
                                }
                                $scheduled_posts[] = [
                                    'post_id'  => $post_id,
                                    'datetime' => $retry_datetime,
                                    'title'    => get_the_title( $post_id ),
                                    'date'     => $current_date,
                                ];
                            }
                        } else {
                            break;
                        }
                    }

                    if ( ! $retry_success ) {
                        $posts_scheduled_today++;
                        $errors[] = sprintf(
                            __( 'Failed to schedule post ID %d after %d attempts', 'schedulely' ),
                            $post_id,
                            $retry_count + 1
                        );
                    }
                }
            }
        }

        if ( $retry_total > 0 ) {
            schedulely_log_error( sprintf(
                'Scheduling run used %d retries across %d posts scheduled.',
                $retry_total,
                $scheduled_count
            ) );
        }

        return [
            'success'         => $scheduled_count > 0,
            'scheduled_count' => $scheduled_count,
            'scheduled_posts' => $scheduled_posts,
            'errors'          => $errors,
            'message'         => sprintf( __( 'Successfully scheduled %d posts.', 'schedulely' ), $scheduled_count ),
        ];
    }

    /**
     * Unix timestamps of future posts in the logical window for an anchor date (for min-interval collision checks).
     *
     * @param string $anchor_date Y-m-d anchor.
     * @return array<int> Timestamps.
     */
    private function get_scheduled_timestamps_for_anchor($anchor_date)
    {
        global $wpdb;

        $post_types = get_option('schedulely_post_types', ['post']);
        $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        list($start_mysql, $end_mysql) = $this->logical_window_bounds_mysql($anchor_date);

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_date
                 FROM {$wpdb->posts}
                 WHERE post_status = 'future'
                 AND post_type IN ($post_types_placeholders)
                 AND post_date >= %s
                 AND post_date <= %s",
                array_merge($post_types, [$start_mysql, $end_mysql])
            )
        );

        if (empty($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $mysql) {
            $t = $this->site_local_mysql_to_timestamp($mysql);
            if (false !== $t) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * Schedule a single post to a specific datetime
     * 
     * @param int $post_id Post ID to schedule
     * @param string $datetime Datetime string in WordPress format (Y-m-d H:i:s)
     * @param int|null $author_id Optional author ID to assign
     * @return bool Success status
     */
    private function schedule_post($post_id, $datetime, $author_id = null)
    {
        // CRITICAL SAFETY CHECK: Ensure datetime is in the future (site-local string vs real Unix time).
        $scheduled_timestamp = $this->site_local_mysql_to_timestamp($datetime);
        if (false === $scheduled_timestamp) {
            schedulely_log_error('CRITICAL: Unparseable schedule datetime', [
                'post_id' => $post_id,
                'datetime' => $datetime,
            ]);
            return false;
        }

        $now = time();
        $safety_buffer = (int) apply_filters('schedulely_schedule_safety_buffer_seconds', 30 * 60, $post_id, $datetime);
        if ($safety_buffer < 0) {
            $safety_buffer = 0;
        }
        $minimum_future_time = $now + $safety_buffer;

        if ($scheduled_timestamp < $minimum_future_time) {
            schedulely_log_error('CRITICAL: Attempted to schedule post too close to present or in the past', [
                'post_id' => $post_id,
                'datetime' => $datetime,
                'scheduled_timestamp' => $scheduled_timestamp,
                'current_timestamp' => $now,
                'minimum_required' => $minimum_future_time,
                'difference_minutes' => ($scheduled_timestamp - $now) / 60,
                'buffer_seconds' => $safety_buffer,
            ]);
            return false; // Refuse to schedule posts less than 30 minutes in the future
        }

        $post_data = [
            'ID' => $post_id,
            'post_status' => 'future',
            'post_date' => $datetime,
            'post_date_gmt' => get_gmt_from_date($datetime)
        ];

        // Add author if provided
        if ($author_id) {
            $post_data['post_author'] = $author_id;
        }

        $result = wp_update_post(wp_slash($post_data), true);

        if (is_wp_error($result)) {
            schedulely_log_error('Failed to schedule post', [
                'post_id' => $post_id,
                'datetime' => $datetime,
                'error' => $result->get_error_message()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Calculate capacity - how many posts can fit in the time window
     * 
     * @param string $start_time Start time (e.g., "5:00 PM")
     * @param string $end_time End time (e.g., "11:00 PM")
     * @param int $min_interval Minimum interval in minutes
     * @param int $desired_quota Desired posts per day
     * @return array Capacity information
     */
    public function calculate_capacity($start_time, $end_time, $min_interval, $desired_quota)
    {
        $date = wp_date('Y-m-d', time());
        $overnight = $this->is_overnight_window($start_time, $end_time);

        $start_timestamp = $this->site_local_string_to_timestamp($date, $start_time);

        if ($overnight) {
            $end_day = $this->site_add_calendar_days($date, 1);
            $end_timestamp = false !== $end_day ? $this->site_local_string_to_timestamp($end_day, $end_time) : false;
        } else {
            $end_timestamp = $this->site_local_string_to_timestamp($date, $end_time);
        }

        if (false === $start_timestamp || false === $end_timestamp || $start_timestamp >= $end_timestamp) {
            return [
                'valid' => false,
                'capacity' => 0,
                'desired_quota' => $desired_quota,
                'meets_quota' => false,
                'error' => $overnight
                    ? __('Invalid overnight window. End time on the next morning must be after the start time on the first day.', 'schedulely')
                    : __('Invalid time window. End time must be after start time on the same day.', 'schedulely'),
            ];
        }

        $total_minutes = ($end_timestamp - $start_timestamp) / 60;

        // Theoretical maximum: how many min_interval-sized slots fit in the window.
        $theoretical_capacity = floor($total_minutes / $min_interval);

        // Efficiency factor — how close to theoretical we get in practice.
        // Sequential and Hybrid modes achieve near-perfect efficiency because slots
        // are pre-computed rather than found by random trial. Random mode has lower
        // efficiency due to collision probability.
        $mode = (string) get_option( 'schedulely_scheduling_mode', Schedulely_Defaults::SCHEDULING_MODE );

        if ( 'sequential' === $mode ) {
            // Sequential: perfectly even spacing → 100% efficiency.
            $efficiency = 1.0;
        } elseif ( 'hybrid' === $mode ) {
            // Hybrid: even slots + random within slot → ~95% efficiency
            // (tiny rounding loss at slot boundaries, effectively negligible).
            $efficiency = 0.95;
        } else {
            // Random mode: depends on interval size and window length.
            if ($min_interval >= 60) {
                $efficiency = 0.70;
            } elseif ($min_interval >= 30) {
                $efficiency = 0.65;
            } elseif ($min_interval >= 20) {
                $efficiency = 0.55;
            } else {
                $efficiency = 0.50;
            }
            // Long windows give random placement more room.
            if ($total_minutes >= 12 * 60) {
                $efficiency = min(0.70, $efficiency + 0.10);
            }
        }

        $efficiency = (float) apply_filters('schedulely_capacity_efficiency', $efficiency, $total_minutes, $min_interval, $desired_quota, $overnight);

        $capacity = max(1, floor($theoretical_capacity * $efficiency));

        // For very small capacities (1-3 posts), be slightly more conservative.
        if ($theoretical_capacity <= 3) {
            $capacity = max(1, $theoretical_capacity - 1);
        }

        $meets_quota = $capacity >= $desired_quota;

        // Calculate suggestions if doesn't meet quota
        $suggestions = [];
        if (!$meets_quota) {
            // Suggestion 1: Reduce interval
            // Account for randomness: need to target higher theoretical capacity to achieve desired actual capacity
            $target_theoretical = ceil($desired_quota / $efficiency); // Use current efficiency factor
            $needed_interval = floor($total_minutes / $target_theoretical);
            if ($needed_interval > 0 && $needed_interval < $min_interval) {
                $theoretical = floor($total_minutes / $needed_interval);
                // Calculate efficiency for the suggested interval
                $suggested_efficiency = $needed_interval >= 60 ? 0.70 : ($needed_interval >= 30 ? 0.65 : ($needed_interval >= 20 ? 0.55 : 0.50));
                $realistic_capacity = max(1, floor($theoretical * $suggested_efficiency));
                $suggestions[] = [
                    'type' => 'reduce_interval',
                    'label' => __('Reduce Minimum Interval', 'schedulely'),
                    'current' => $min_interval,
                    'suggested' => $needed_interval,
                    'new_capacity' => $realistic_capacity,
                    'message' => sprintf(
                        __('Change interval from %d to %d minutes → fits ~%d posts', 'schedulely'),
                        $min_interval,
                        $needed_interval,
                        $realistic_capacity
                    )
                ];
            }

            // Suggestion 2: Reduce quota
            $suggestions[] = [
                'type' => 'reduce_quota',
                'label' => __('Reduce Posts Per Day', 'schedulely'),
                'current' => $desired_quota,
                'suggested' => $capacity,
                'message' => sprintf(
                    __('Lower quota from %d to %d posts per day', 'schedulely'),
                    $desired_quota,
                    $capacity
                )
            ];

            // Suggestion 3: Expand time window (same-day only; overnight uses a generic hint).
            $target_theoretical = ceil($desired_quota / $efficiency);
            $needed_minutes = $target_theoretical * $min_interval;
            $needed_hours = ceil($needed_minutes / 60);
            $minutes_to_add = $needed_minutes - $total_minutes;

            if ($overnight) {
                $suggestions[] = [
                    'type' => 'expand_window',
                    'label' => __('Expand Time Window', 'schedulely'),
                    'current_start' => $start_time,
                    'current_end' => $end_time,
                    'suggested_start' => $start_time,
                    'suggested_end' => $end_time,
                    'needed_hours' => $needed_hours,
                    'message' => sprintf(
                        /* translators: %d: approximate hours of window needed */
                        __('Overnight window: widen by starting earlier on the first day or ending later the next morning (~%d hours of span helps).', 'schedulely'),
                        $needed_hours
                    ),
                ];
            } else {
                $max_end_timestamp = $this->site_local_string_to_timestamp($date, '11:59 PM');
                if (false === $max_end_timestamp) {
                    $max_end_timestamp = $end_timestamp;
                }
                $minutes_available_at_end = ($max_end_timestamp - $end_timestamp) / 60;
                $suggested_start_time = $start_time;
                $suggested_end_time = $end_time;
                $expand_message = '';

                if ($minutes_to_add <= $minutes_available_at_end) {
                    $new_end_timestamp = $end_timestamp + ($minutes_to_add * 60);
                    $suggested_end_time = wp_date('g:i A', $new_end_timestamp);
                    $expand_message = sprintf(
                        __('Extend end time from %s-%s to %s-%s (~%d hours needed)', 'schedulely'),
                        $start_time,
                        $end_time,
                        $start_time,
                        $suggested_end_time,
                        $needed_hours
                    );
                } elseif ($minutes_available_at_end > 0 && $minutes_to_add > $minutes_available_at_end) {
                    $suggested_end_time = '11:59 PM';
                    $remaining_minutes_needed = $minutes_to_add - $minutes_available_at_end;
                    $new_start_timestamp = $start_timestamp - ($remaining_minutes_needed * 60);
                    $suggested_start_time = wp_date('g:i A', $new_start_timestamp);
                    $expand_message = sprintf(
                        __('Extend from %s-%s to %s-%s (start earlier + extend to 11:59 PM, ~%d hours needed)', 'schedulely'),
                        $start_time,
                        $end_time,
                        $suggested_start_time,
                        $suggested_end_time,
                        $needed_hours
                    );
                } else {
                    $new_start_timestamp = $start_timestamp - ($minutes_to_add * 60);
                    $suggested_start_time = wp_date('g:i A', $new_start_timestamp);
                    $suggested_end_time = $end_time;
                    $expand_message = sprintf(
                        __('Start earlier from %s-%s to %s-%s (end time cannot extend past 11:59 PM, ~%d hours needed)', 'schedulely'),
                        $start_time,
                        $end_time,
                        $suggested_start_time,
                        $suggested_end_time,
                        $needed_hours
                    );
                }

                $suggestions[] = [
                    'type' => 'expand_window',
                    'label' => __('Expand Time Window', 'schedulely'),
                    'current_start' => $start_time,
                    'current_end' => $end_time,
                    'suggested_start' => $suggested_start_time,
                    'suggested_end' => $suggested_end_time,
                    'needed_hours' => $needed_hours,
                    'message' => $expand_message,
                ];
            }
        }

        // When US timezone-aware ordering is enabled, append overlap info for
        // each timezone group so the capacity pill can show it to the user.
        $timezone_overlaps = null;
        if ( get_option( 'schedulely_ai_us_timezone_ordering', Schedulely_Defaults::AI_US_TIMEZONE_ORDERING ) ) {
            $today  = wp_date( 'Y-m-d', time() );
            $groups = [ 'eastern', 'central', 'mountain', 'pacific' ];
            $timezone_overlaps = [];
            foreach ( $groups as $group ) {
                list( $ov_start, $ov_end ) = $this->get_timezone_active_overlap( $today, $group );
                $timezone_overlaps[ $group ] = [
                    'start' => wp_date( 'g:i A', $ov_start ),
                    'end'   => wp_date( 'g:i A', $ov_end ),
                    'minutes' => (int) round( ( $ov_end - $ov_start ) / 60 ),
                ];
            }
        }

        return [
            'valid' => true,
            'capacity' => $capacity,
            'desired_quota' => $desired_quota,
            'meets_quota' => $meets_quota,
            'total_minutes' => $total_minutes,
            'min_interval' => $min_interval,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'suggestions' => $suggestions,
            'timezone_overlaps' => $timezone_overlaps,
            'error' => null
        ];
    }

    /**
     * Calculate the overlap between the configured publishing window and a
     * timezone group's active hours for a given anchor date.
     *
     * Instead of dividing the window into fixed equal bands, this method
     * computes the intersection of:
     *   - The user's configured publishing window (start_ts → end_ts)
     *   - The target timezone's "active hours" (7 AM – 11 PM local time)
     *
     * The result is the range within which a random publish time will feel
     * natural for that timezone's audience. If there is no overlap (the
     * window doesn't cover that timezone's active hours at all), the full
     * window is returned as a fallback so scheduling never fails.
     *
     * Active hours use standard US UTC offsets adjusted for DST at the time
     * of scheduling. The offsets are:
     *   Eastern  UTC-5 (EST) / UTC-4 (EDT)
     *   Central  UTC-6 (CST) / UTC-5 (CDT)
     *   Mountain UTC-7 (MST) / UTC-6 (MDT)
     *   Pacific  UTC-8 (PST) / UTC-7 (PDT)
     *
     * DST is determined by PHP's America/* timezone rules at the anchor date.
     *
     * @since 1.7.0
     * @since 1.7.1 Replaced equal-band division with window/active-hours overlap.
     *
     * @param string $anchor_date  Y-m-d anchor date.
     * @param string $group        Timezone group: 'eastern'|'central'|'mountain'|'pacific'|'general'.
     * @return array{0:int,1:int}  [overlap_start_ts, overlap_end_ts] in UTC.
     */
    public function get_timezone_active_overlap( string $anchor_date, string $group ): array {
        list( $win_start, $win_end ) = $this->logical_window_bounds_ts( $anchor_date );

        if ( 'general' === $group || $win_start >= $win_end ) {
            return [ $win_start, $win_end ];
        }

        // Map group to a representative PHP timezone for DST-aware offset lookup.
        $tz_map = [
            'eastern'  => 'America/New_York',
            'central'  => 'America/Chicago',
            'mountain' => 'America/Denver',
            'pacific'  => 'America/Los_Angeles',
        ];

        $tz_name = $tz_map[ $group ] ?? null;
        if ( null === $tz_name ) {
            return [ $win_start, $win_end ];
        }

        try {
            $tz  = new DateTimeZone( $tz_name );
            // Use the midpoint of the window to determine DST offset for that day.
            $mid = (int) round( ( $win_start + $win_end ) / 2 );
            $dt  = new DateTimeImmutable( '@' . $mid );
            $offset_seconds = $tz->getOffset( $dt ); // e.g. -18000 for EST (UTC-5)

            // Active hours: 7:00 AM – 11:00 PM in the target timezone.
            // Convert to UTC by subtracting the offset.
            $active_start_utc = mktime( 7,  0, 0,
                (int) wp_date( 'n', $win_start ),
                (int) wp_date( 'j', $win_start ),
                (int) wp_date( 'Y', $win_start )
            ) - $offset_seconds;

            $active_end_utc = mktime( 23, 0, 0,
                (int) wp_date( 'n', $win_start ),
                (int) wp_date( 'j', $win_start ),
                (int) wp_date( 'Y', $win_start )
            ) - $offset_seconds;

            // For overnight windows the window may span two calendar days.
            // If the active window ends before the publishing window starts,
            // try the next calendar day's active hours.
            if ( $active_end_utc < $win_start ) {
                $active_start_utc += 86400;
                $active_end_utc   += 86400;
            }

        } catch ( \Exception $e ) {
            return [ $win_start, $win_end ];
        }

        // Compute intersection.
        $overlap_start = max( $win_start, $active_start_utc );
        $overlap_end   = min( $win_end,   $active_end_utc );

        // No overlap — fall back to full window so scheduling never fails.
        if ( $overlap_start >= $overlap_end ) {
            return [ $win_start, $win_end ];
        }

        return [ $overlap_start, $overlap_end ];
    }

    /**
     * @deprecated 1.7.1 Use get_timezone_active_overlap() instead.
     *             Kept for backwards compatibility with any external callers.
     *
     * @since 1.7.0
     * @param string $anchor_date
     * @return array<string,array{0:int,1:int}>
     */
    public function calculate_timezone_bands( string $anchor_date ): array {
        return [
            'eastern'  => $this->get_timezone_active_overlap( $anchor_date, 'eastern' ),
            'central'  => $this->get_timezone_active_overlap( $anchor_date, 'central' ),
            'mountain' => $this->get_timezone_active_overlap( $anchor_date, 'mountain' ),
            'pacific'  => $this->get_timezone_active_overlap( $anchor_date, 'pacific' ),
        ];
    }

    /**
     * Pre-compute all time slots for a given anchor date and scheduling mode.
     *
     * Sequential: evenly divides the window into $quota equal intervals.
     *   Slot N starts at: window_start + N × (window_span / quota)
     *
     * Hybrid: same even division, but each slot's assigned time is a random
     *   point within the slot boundaries — giving even distribution with
     *   natural-looking randomness.
     *
     * Returns an array of MySQL datetime strings (Y-m-d H:i:s), one per slot,
     * in order. Returns fewer slots if the window is too short for the safety buffer.
     *
     * @since 1.6.0
     *
     * @param string $anchor_date Y-m-d.
     * @param int    $quota       Posts per day.
     * @param string $mode        'sequential' or 'hybrid'.
     * @return array<string> MySQL datetime strings.
     */
    private function generate_day_slots( string $anchor_date, int $quota, string $mode ): array {
        list( $start_ts, $end_ts ) = $this->logical_window_bounds_ts( $anchor_date );

        if ( $start_ts >= $end_ts || $quota < 1 ) {
            return [];
        }

        $now_ts        = time();
        $safety_buffer = (int) apply_filters( 'schedulely_schedule_safety_buffer_seconds', 30 * 60, 0, $anchor_date );
        if ( $safety_buffer < 0 ) $safety_buffer = 0;
        $floor_ts = max( $start_ts, $now_ts + $safety_buffer );

        if ( $floor_ts > $end_ts ) {
            return [];
        }

        $usable_span = $end_ts - $floor_ts;

        // Slot width in seconds. Minimum 1 second to avoid divide-by-zero.
        $slot_width = max( 1, (int) floor( $usable_span / $quota ) );

        $slots = [];
        for ( $i = 0; $i < $quota; $i++ ) {
            $slot_start = $floor_ts + ( $i * $slot_width );
            $slot_end   = min( $end_ts, $slot_start + $slot_width - 1 );

            if ( $slot_start > $end_ts ) {
                break; // Window exhausted.
            }

            if ( 'sequential' === $mode ) {
                // Exactly the start of each slot — perfectly even spacing.
                $point = $slot_start;
            } else {
                // Hybrid: random within the slot.
                $point = ( $slot_start < $slot_end ) ? rand( (int) $slot_start, (int) $slot_end ) : $slot_start;
            }

            $dt = wp_date( 'Y-m-d H:i:s', (int) $point );
            if ( $dt ) {
                $slots[] = $dt;
            }
        }

        return $slots;
    }

    /**
     * Random local datetime within the logical window for anchor date (supports overnight). Respects min interval vs used timestamps.
     *
     * When $band is provided ([ start_ts, end_ts ]), the random time is constrained
     * to that sub-range of the window — used for timezone-aware scheduling.
     *
     * @since 1.0.0
     * @since 1.7.0 Added $band parameter for timezone-aware scheduling.
     *
     * @param string       $anchor_date Logical anchor Y-m-d.
     * @param array<int>   $used_ts     Unix timestamps already taken this run.
     * @param array{0:int,1:int}|null $band Optional [start_ts, end_ts] sub-range.
     * @return string|false MySQL datetime Y-m-d H:i:s or false.
     */
    private function generate_random_datetime($anchor_date, array $used_ts = [], ?array $band = null)
    {
        list($start_ts, $end_ts) = $this->logical_window_bounds_ts($anchor_date);

        if ($start_ts >= $end_ts) {
            return false;
        }

        // Constrain to timezone band if provided.
        if ( null !== $band && isset( $band[0], $band[1] ) ) {
            $start_ts = max( $start_ts, (int) $band[0] );
            $end_ts   = min( $end_ts,   (int) $band[1] );
            if ( $start_ts >= $end_ts ) {
                // Band is outside the window — fall back to full window.
                list( $start_ts, $end_ts ) = $this->logical_window_bounds_ts( $anchor_date );
            }
        }

        $min_interval = (int) get_option('schedulely_min_interval', 40) * 60;
        $now_ts = time();
        $safety_buffer = (int) apply_filters('schedulely_schedule_safety_buffer_seconds', 30 * 60, 0, $anchor_date);
        if ($safety_buffer < 0) {
            $safety_buffer = 0;
        }
        $floor_ts = max($start_ts, $now_ts + $safety_buffer);

        if ($floor_ts > $end_ts) {
            return false;
        }

        $base_attempts = 200;
        $additional_attempts_per_post = 50;
        $max_attempts = $base_attempts + (count($used_ts) * $additional_attempts_per_post);
        $attempt = 0;

        while ($attempt < $max_attempts) {
            $random_ts = rand((int) $floor_ts, (int) $end_ts);

            if (in_array($random_ts, $used_ts, true)) {
                $attempt++;
                continue;
            }

            $valid = true;
            foreach ($used_ts as $used_stamp) {
                if (abs($random_ts - (int) $used_stamp) < $min_interval) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                $random_ts = min((int) $end_ts, max((int) $start_ts, (int) $random_ts));

                return wp_date('Y-m-d H:i:s', $random_ts);
            }

            $attempt++;
        }

        return false;
    }
}
