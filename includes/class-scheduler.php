<?php
/**
 * Filename: class-scheduler.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 06/10/2025
 * Version: 1.2.3
 * Last Modified: 13/10/2025
 * Description: Main Scheduling Engine - Handles all post scheduling logic with last date completion
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
class Schedulely_Scheduler {
    
    /**
     * Author manager instance
     *
     * @var Schedulely_Author_Manager
     */
    private $author_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->author_manager = new Schedulely_Author_Manager();
    }
    
    /**
     * Run the scheduling process
     * 
     * @return array Results of scheduling operation
     */
    public function run_schedule() {
        $results = [
            'success' => false,
            'scheduled_count' => 0,
            'completed_last_date' => false,
            'message' => '',
            'errors' => [],
            'scheduled_posts' => []
        ];
        
        $quota = get_option('schedulely_posts_per_day', 8);
        $available_posts = $this->get_available_posts();
        
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
            // Check if this date is today or future (can still add posts)
            $today = date('Y-m-d', current_time('timestamp'));
            $last_date_timestamp = strtotime($last_scheduled_date);
            $today_timestamp = strtotime($today);
            
            if ($last_date_timestamp >= $today_timestamp) {
                // Date is today or future - we can add more posts to it
                $posts_on_last_date = $this->count_posts_on_date($last_scheduled_date);
                
                if ($posts_on_last_date < $quota) {
                    // Last date is incomplete, complete it first
                    $complete_count = $quota - $posts_on_last_date;
                    $start_date = $last_scheduled_date;
                    $results['completed_last_date'] = true;
                } else {
                    // Last date is complete, start from next day
                    $start_date = date('Y-m-d', strtotime($last_scheduled_date . ' +1 day'));
                }
            } else {
                // Last scheduled date is in the past, start from today/tomorrow
                $start_date = $this->get_next_scheduling_date();
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
        
        // Clear cache
        schedulely_clear_cache();
        
        return $results;
    }
    
    /**
     * Get available posts based on monitored status
     * 
     * @return array Array of post IDs
     */
    private function get_available_posts() {
        $status = get_option('schedulely_post_status', 'draft');
        
        $args = [
            'post_type' => 'post',
            'post_status' => $status,
            'posts_per_page' => 500, // Limit to 500 posts per run
            'orderby' => 'date',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];
        
        return get_posts($args);
    }
    
    /**
     * Get the last/furthest scheduled date
     * 
     * @return string|null Date string (Y-m-d) or null
     */
    public function get_last_scheduled_date() {
        global $wpdb;
        
        $last_date = $wpdb->get_var(
            "SELECT DATE(post_date) as schedule_date 
             FROM {$wpdb->posts} 
             WHERE post_status = 'future' 
             AND post_type = 'post'
             ORDER BY post_date DESC 
             LIMIT 1"
        );
        
        return $last_date; // Returns "2025-10-09" or null
    }
    
    /**
     * Count posts scheduled for a specific date
     * 
     * @param string $date Date string (Y-m-d)
     * @return int Number of posts scheduled on this date
     */
    public function count_posts_on_date($date) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->posts} 
             WHERE post_status = 'future' 
             AND post_type = 'post'
             AND DATE(post_date) = %s",
            $date
        ));
        
        return (int) $count;
    }
    
    /**
     * Get next scheduling date when no scheduled posts exist
     * 
     * @return string Date string (Y-m-d)
     */
    private function get_next_scheduling_date() {
        $now = current_time('timestamp');
        $end_time = get_option('schedulely_end_time', '11:00 PM');
        $today_date = date('Y-m-d', $now);
        $end_timestamp = strtotime($today_date . ' ' . $end_time);
        
        if ($now > $end_timestamp) {
            // Past today's window, start tomorrow
            return $this->get_next_active_date($today_date);
        }
        
        // Check if today is an active day
        $active_days = get_option('schedulely_active_days', [1, 2, 3, 4, 5, 6, 0]);
        $today_day_of_week = date('w', $now);
        
        if (in_array($today_day_of_week, $active_days)) {
            return $today_date;
        }
        
        return $this->get_next_active_date($today_date);
    }
    
    /**
     * Get next active date after a given date
     * 
     * @param string $current_date Current date (Y-m-d)
     * @return string Next active date (Y-m-d)
     */
    private function get_next_active_date($current_date) {
        $active_days = get_option('schedulely_active_days', [1, 2, 3, 4, 5, 6, 0]);
        $next_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        $attempts = 0;
        
        // Find next active day (max 7 attempts)
        while ($attempts < 7) {
            $day_of_week = date('w', strtotime($next_date));
            if (in_array($day_of_week, $active_days)) {
                return $next_date;
            }
            $next_date = date('Y-m-d', strtotime($next_date . ' +1 day'));
            $attempts++;
        }
        
        return $next_date; // Fallback
    }
    
    /**
     * Schedule posts starting from a specific date
     * 
     * @param array $posts Array of post IDs
     * @param string $start_date Starting date (Y-m-d)
     * @param int $complete_first Number of posts to complete on start date (if completing last date)
     * @return array Scheduling results
     */
    private function schedule_posts_from_date($posts, $start_date, $complete_first = 0) {
        $quota = get_option('schedulely_posts_per_day', 8);
        $current_date = $start_date;
        $posts_scheduled_today = 0;
        $scheduled_count = 0;
        $already_scheduled_times = [];
        $scheduled_posts = [];
        $errors = [];
        
        // If completing last date, account for existing posts
        if ($complete_first > 0) {
            $posts_scheduled_today = $quota - $complete_first;
            // Get already scheduled times for this date
            $already_scheduled_times = $this->get_scheduled_times_for_date($current_date);
        }
        
        foreach ($posts as $post_id) {
            // Check if we need to move to next day
            if ($posts_scheduled_today >= $quota) {
                $current_date = $this->get_next_active_date($current_date);
                $posts_scheduled_today = 0;
                $already_scheduled_times = [];
            }
            
            // Generate random time for this date
            $random_time = $this->generate_random_time($current_date, $already_scheduled_times);
            
            if ($random_time === false) {
                // Can't fit more posts in this day's window, move to next day
                $current_date = $this->get_next_active_date($current_date);
                $posts_scheduled_today = 0;
                $already_scheduled_times = [];
                $random_time = $this->generate_random_time($current_date, []);
                
                if ($random_time === false) {
                    $errors[] = sprintf(__('Failed to generate time slot for post ID %d', 'schedulely'), $post_id);
                    continue;
                }
            }
            
            // Schedule the post
            $datetime = $current_date . ' ' . $random_time;
            
            // Get random author if enabled
            $author_id = null;
            if ($this->author_manager->is_enabled()) {
                $author_id = $this->author_manager->get_random_author();
            }
            
            $success = $this->schedule_post($post_id, $datetime, $author_id);
            
            if ($success) {
                $scheduled_count++;
                $posts_scheduled_today++;
                $already_scheduled_times[] = $random_time;
                
                $scheduled_posts[] = [
                    'post_id' => $post_id,
                    'datetime' => $datetime,
                    'title' => get_the_title($post_id),
                    'date' => $current_date
                ];
            } else {
                $errors[] = sprintf(__('Failed to schedule post ID %d', 'schedulely'), $post_id);
            }
        }
        
        return [
            'success' => $scheduled_count > 0,
            'scheduled_count' => $scheduled_count,
            'scheduled_posts' => $scheduled_posts,
            'errors' => $errors,
            'message' => sprintf(
                __('Successfully scheduled %d posts.', 'schedulely'),
                $scheduled_count
            )
        ];
    }
    
    /**
     * Get already scheduled times for a date
     * 
     * @param string $date Date string (Y-m-d)
     * @return array Array of time strings (H:i:s)
     */
    private function get_scheduled_times_for_date($date) {
        global $wpdb;
        
        $times = $wpdb->get_col($wpdb->prepare(
            "SELECT TIME(post_date) as post_time
             FROM {$wpdb->posts} 
             WHERE post_status = 'future' 
             AND post_type = 'post'
             AND DATE(post_date) = %s",
            $date
        ));
        
        return $times ? $times : [];
    }
    
    /**
     * Schedule a single post to a specific datetime
     * 
     * @param int $post_id Post ID to schedule
     * @param string $datetime Datetime string in WordPress format (Y-m-d H:i:s)
     * @param int|null $author_id Optional author ID to assign
     * @return bool Success status
     */
    private function schedule_post($post_id, $datetime, $author_id = null) {
        // CRITICAL SAFETY CHECK: Ensure datetime is in the future
        $scheduled_timestamp = strtotime($datetime);
        $now = current_time('timestamp');
        $safety_buffer = 30 * 60; // 30 minutes minimum buffer
        $minimum_future_time = $now + $safety_buffer;
        
        if ($scheduled_timestamp < $minimum_future_time) {
            schedulely_log_error('CRITICAL: Attempted to schedule post too close to present or in the past', [
                'post_id' => $post_id,
                'datetime' => $datetime,
                'scheduled_timestamp' => $scheduled_timestamp,
                'current_timestamp' => $now,
                'minimum_required' => $minimum_future_time,
                'difference_minutes' => ($scheduled_timestamp - $now) / 60,
                'buffer_minutes' => 30
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
    public function calculate_capacity($start_time, $end_time, $min_interval, $desired_quota) {
        // Use today's date for calculation
        $date = date('Y-m-d');
        
        // Convert times to timestamps
        $start_timestamp = strtotime($date . ' ' . $start_time);
        $end_timestamp = strtotime($date . ' ' . $end_time);
        
        // Validate time window
        if ($start_timestamp === false || $end_timestamp === false || $start_timestamp >= $end_timestamp) {
            return [
                'valid' => false,
                'capacity' => 0,
                'desired_quota' => $desired_quota,
                'meets_quota' => false,
                'error' => __('Invalid time window. End time must be after start time.', 'schedulely')
            ];
        }
        
        // Calculate total minutes in window
        $total_minutes = ($end_timestamp - $start_timestamp) / 60;
        
        // Calculate theoretical maximum capacity (number of intervals that fit)
        // Example: 360 minutes / 35 min interval = 10.28 → 10 posts can fit with perfect spacing
        $theoretical_capacity = floor($total_minutes / $min_interval);
        
        // CRITICAL: Account for random time generation inefficiency
        // Random placement cannot achieve perfect packing like sequential placement
        // Efficiency factor depends on interval size - smaller intervals = harder to pack randomly
        
        // Dynamic efficiency based on interval size:
        // - Large intervals (60+ min): 70% efficiency (easier to find gaps)
        // - Medium intervals (30-59 min): 65% efficiency
        // - Small intervals (20-29 min): 55% efficiency (high collision probability)
        // - Tiny intervals (<20 min): 50% efficiency (very difficult)
        if ($min_interval >= 60) {
            $efficiency = 0.70;
        } elseif ($min_interval >= 30) {
            $efficiency = 0.65;
        } elseif ($min_interval >= 20) {
            $efficiency = 0.55;
        } else {
            $efficiency = 0.50;
        }
        
        $capacity = max(1, floor($theoretical_capacity * $efficiency));
        
        // For very small capacities (1-3 posts), be more conservative
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
            
            // Suggestion 3: Expand time window
            // Account for randomness: need more minutes than theoretical minimum
            $target_theoretical = ceil($desired_quota / $efficiency); // Use current efficiency factor
            $needed_minutes = $target_theoretical * $min_interval;
            $needed_hours = ceil($needed_minutes / 60);
            
            // Calculate how much to add (needed - current)
            $minutes_to_add = $needed_minutes - $total_minutes;
            
            // Hard limit: end time cannot go past 11:59 PM
            $max_end_timestamp = strtotime($date . ' 11:59 PM');
            $minutes_available_at_end = ($max_end_timestamp - $end_timestamp) / 60;
            
            // Decide strategy based on available space at end
            $suggested_start_time = $start_time;
            $suggested_end_time = $end_time;
            $expand_message = '';
            
            if ($minutes_to_add <= $minutes_available_at_end) {
                // Can expand by just extending the end time
                $new_end_timestamp = $end_timestamp + ($minutes_to_add * 60);
                $suggested_end_time = date('g:i A', $new_end_timestamp);
                $expand_message = sprintf(
                    __('Extend end time from %s-%s to %s-%s (~%d hours needed)', 'schedulely'),
                    $start_time,
                    $end_time,
                    $start_time,
                    $suggested_end_time,
                    $needed_hours
                );
            } elseif ($minutes_available_at_end > 0 && $minutes_to_add > $minutes_available_at_end) {
                // Need to extend to 11:59 PM AND start earlier
                $suggested_end_time = '11:59 PM';
                $remaining_minutes_needed = $minutes_to_add - $minutes_available_at_end;
                $new_start_timestamp = $start_timestamp - ($remaining_minutes_needed * 60);
                $suggested_start_time = date('g:i A', $new_start_timestamp);
                $expand_message = sprintf(
                    __('Extend from %s-%s to %s-%s (start earlier + extend to 11:59 PM, ~%d hours needed)', 'schedulely'),
                    $start_time,
                    $end_time,
                    $suggested_start_time,
                    $suggested_end_time,
                    $needed_hours
                );
            } else {
                // End time already at or near limit, must start earlier
                $new_start_timestamp = $start_timestamp - ($minutes_to_add * 60);
                $suggested_start_time = date('g:i A', $new_start_timestamp);
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
                'message' => $expand_message
            ];
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
            'error' => null
        ];
    }
    
    /**
     * Generate random time within configured window for a given date
     * 
     * @param string $date Date string (Y-m-d)
     * @param array $used_times Already used time strings (H:i:s)
     * @return string|false Time string (H:i:s) or false if no slot available
     */
    private function generate_random_time($date, $used_times = []) {
        $start_time = get_option('schedulely_start_time', '5:00 PM');
        $end_time = get_option('schedulely_end_time', '11:00 PM');
        $min_interval = get_option('schedulely_min_interval', 40) * 60; // Convert to seconds
        
        // Create datetime strings in 12hr format - WordPress/PHP handles conversion
        $start_datetime = strtotime($date . ' ' . $start_time);
        $end_datetime = strtotime($date . ' ' . $end_time);
        
        if ($start_datetime >= $end_datetime) {
            return false; // Invalid time window
        }
        
        // CRITICAL FIX: Dynamic max_attempts based on scheduling density
        // As more posts are scheduled, collision probability increases exponentially
        // Base attempts: 200 (doubled from 100)
        // Additional attempts: 50 per already-scheduled post to account for increasing difficulty
        // Example: 0 posts = 200 attempts, 8 posts = 200 + (8 * 50) = 600 attempts, 15 posts = 950 attempts
        $base_attempts = 200;
        $additional_attempts_per_post = 50;
        $max_attempts = $base_attempts + (count($used_times) * $additional_attempts_per_post);
        
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            // Generate random timestamp between start and end
            $random_timestamp = rand($start_datetime, $end_datetime);
            $random_time = date('H:i:s', $random_timestamp);
            
            // Check if this time is already used
            if (in_array($random_time, $used_times)) {
                $attempt++;
                continue;
            }
            
            // Check minimum interval with all existing times
            $valid = true;
            foreach ($used_times as $used_time) {
                $used_timestamp = strtotime($date . ' ' . $used_time);
                $diff = abs($random_timestamp - $used_timestamp);
                
                if ($diff < $min_interval) {
                    $valid = false;
                    break;
                }
            }
            
            if ($valid) {
                return $random_time;
            }
            
            $attempt++;
        }
        
        return false; // No available slot found
    }
}
