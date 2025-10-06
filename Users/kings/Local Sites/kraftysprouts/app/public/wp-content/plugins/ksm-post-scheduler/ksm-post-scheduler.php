<?php
/**
 * Plugin Name: KSM Post Scheduler
 * Plugin URI: https://kraftysprouts.com
 * Description: Automatically schedules posts from a specific status to publish at random times
 * Version: 2.0.0
 * Author: Krafty Sprouts Media, LLC
 * Author URI: https://kraftysprouts.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ksm-post-scheduler
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package KSM_Post_Scheduler
 * @version 2.0.0
 * @author KraftySpoutsMedia, LLC
 * @copyright 2025 KraftySpouts
 * @license GPL-2.0-or-later
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KSM_PS_VERSION', '2.0.0');
define('KSM_PS_PLUGIN_FILE', __FILE__);
define('KSM_PS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KSM_PS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KSM_PS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main KSM Post Scheduler Class
 * 
 * @class KSM_PS_Main
 * @version 1.0.0
 * @since 1.0.0
 */
class KSM_PS_Main {
    
    /**
     * Plugin instance
     * 
     * @var KSM_PS_Main
     * @since 1.0.0
     */
    private static $instance = null;
    
    /**
     * Cron hook name
     * 
     * @var string
     * @since 1.0.0
     */
    private $cron_hook = 'ksm_ps_daily_cron';
    
    /**
     * Settings option name
     * 
     * @var string
     * @since 1.0.0
     */
    private $option_name = 'ksm_ps_settings';
    
    /**
     * Get plugin instance
     * 
     * @return KSM_PS_Main
     * @since 1.0.0
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    

    
    /**
     * Initialize WordPress hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation, deactivation, and uninstall hooks
        register_activation_hook(KSM_PS_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(KSM_PS_PLUGIN_FILE, array($this, 'deactivate'));
        register_uninstall_hook(KSM_PS_PLUGIN_FILE, 'KSM_PS_Main::uninstall');
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Cron hooks
        add_action($this->cron_hook, array($this, 'random_post_scheduler_daily_cron'));
        
        // REMOVED: Dynamic publication hooks - no longer needed with WordPress native scheduling
        

        
        // AJAX hooks
        add_action('wp_ajax_ksm_ps_run_now', array($this, 'ajax_run_now'));
        add_action('wp_ajax_ksm_ps_get_status', array($this, 'ajax_get_status'));
        add_action('wp_ajax_ksm_ps_dismiss_notice', array($this, 'ajax_dismiss_notice'));
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check WordPress version compatibility
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(KSM_PS_PLUGIN_BASENAME);
            wp_die(__('KSM Post Scheduler requires WordPress 5.0 or higher.', 'ksm-post-scheduler'));
        }
        
        // Check PHP version compatibility
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(KSM_PS_PLUGIN_BASENAME);
            wp_die(__('KSM Post Scheduler requires PHP 7.4 or higher.', 'ksm-post-scheduler'));
        }
        
        // Set default options
        $default_options = array(
            'enabled' => false,
            'post_status' => 'draft',
            'posts_per_day' => 5,
            'start_time' => '9:00 AM',
            'end_time' => '6:00 PM',
            'days_active' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
            'min_interval' => 30,
            'randomize_authors' => false,
            'allowed_author_roles' => array('contributor', 'author', 'editor', 'administrator'),
            'excluded_users' => array(),
            'assignment_strategy' => 'random',
            'version' => KSM_PS_VERSION,
            'installed_date' => current_time('mysql')
        );
        
        // Get existing options
        $existing_options = get_option($this->option_name);
        
        if (!$existing_options) {
            // First time installation
            add_option($this->option_name, $default_options);
        } else {
            // Plugin update - merge new options with existing ones
            $updated_options = array_merge($default_options, $existing_options);
            $updated_options['version'] = KSM_PS_VERSION;
            
            // Only set default times if they don't exist or are invalid
            if (empty($updated_options['start_time']) || !preg_match('/^(1[0-2]|[1-9]):[0-5][0-9]\s?(AM|PM|am|pm)$/i', $updated_options['start_time'])) {
                $updated_options['start_time'] = '9:00 AM';
            }
            if (empty($updated_options['end_time']) || !preg_match('/^(1[0-2]|[1-9]):[0-5][0-9]\s?(AM|PM|am|pm)$/i', $updated_options['end_time'])) {
                $updated_options['end_time'] = '6:00 PM';
            }
            
            update_option($this->option_name, $updated_options);
        }
        
        // Schedule cron job using WordPress timezone
        if (!wp_next_scheduled($this->cron_hook)) {
            // Use WordPress timezone-aware scheduling instead of server timezone
            $wp_timezone = wp_timezone();
            $tomorrow_1am = new DateTime('tomorrow 1:00 AM', $wp_timezone);
            wp_schedule_event($tomorrow_1am->getTimestamp(), 'daily', $this->cron_hook);
        }
        
        // Set activation flag for admin notice
        set_transient('ksm_ps_activation_notice', true, 30);
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clear scheduled cron job
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
        
        // Clear all scheduled hooks for this plugin
        wp_clear_scheduled_hook($this->cron_hook);
        
        // Clear all publication hooks for scheduled posts
        $future_posts = get_posts(array(
            'post_status' => 'future',
            'numberposts' => -1,
            'post_type' => 'post'
        ));
        
        foreach ($future_posts as $post) {
            $hook_name = 'ksm_ps_publish_post_' . $post->ID;
            wp_clear_scheduled_hook($hook_name);
        }
        

        
        // Clear any transients
        delete_transient('ksm_ps_status_cache');
        delete_transient('ksm_ps_upcoming_posts');
        delete_transient('ksm_ps_activation_notice');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall (static method)
     * 
     * @since 1.0.0
     */
    public static function uninstall() {
        // This method is called when the plugin is deleted
        // The actual cleanup is handled by uninstall.php
        // This method exists for compatibility and logging
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KSM Post Scheduler: Uninstall hook called.');
        }
    }
    

    

    

    

    

    

    

    

    

    
    /**
     * Display admin notices
     * 
     * @since 1.0.0
     */
    public function admin_notices() {
        // Show activation notice
        if (get_transient('ksm_ps_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('KSM Post Scheduler', 'ksm-post-scheduler'); ?></strong> 
                    <?php _e('has been activated successfully!', 'ksm-post-scheduler'); ?>
                    <a href="<?php echo admin_url('options-general.php?page=ksm-post-scheduler'); ?>">
                        <?php _e('Configure settings', 'ksm-post-scheduler'); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_transient('ksm_ps_activation_notice');
        }
        
        // Check for version updates
        $options = get_option($this->option_name);
        if ($options && isset($options['version']) && version_compare($options['version'], KSM_PS_VERSION, '<')) {
            // Check if this version update notice has been dismissed
            $dismissed_notices = get_user_meta(get_current_user_id(), 'ksm_ps_dismissed_notices', true);
            if (!is_array($dismissed_notices)) {
                $dismissed_notices = array();
            }
            
            $notice_key = 'version_update_' . KSM_PS_VERSION;
            if (!in_array($notice_key, $dismissed_notices)) {
                ?>
                <div class="notice notice-info is-dismissible" data-notice-key="<?php echo esc_attr($notice_key); ?>">
                    <p>
                        <strong><?php _e('KSM Post Scheduler', 'ksm-post-scheduler'); ?></strong> 
                        <?php printf(__('has been updated to version %s.', 'ksm-post-scheduler'), KSM_PS_VERSION); ?>
                        <a href="<?php echo admin_url('options-general.php?page=ksm-post-scheduler'); ?>">
                            <?php _e('View settings', 'ksm-post-scheduler'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
        
        // TEMPORARY DEBUG: Show current settings on plugin page
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_ksm-post-scheduler') {
            $current_options = get_option($this->option_name, array());

        }
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_options_page(
            __('KSM Post Scheduler', 'ksm-post-scheduler'),
            __('Post Scheduler', 'ksm-post-scheduler'),
            'manage_options',
            'ksm-post-scheduler',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     * 
     * @since 1.0.0
     */
    public function admin_init() {
        register_setting('ksm_ps_settings_group', $this->option_name, array($this, 'sanitize_settings'));
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_ksm-post-scheduler' !== $hook) {
            return;
        }
        
        // Enqueue SweetAlert2 from CDN
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11.23.0/dist/sweetalert2.all.min.js',
            array(),
            '11.23.0',
            true
        );
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'ksm-ps-admin',
            KSM_PS_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'sweetalert2'),
            KSM_PS_VERSION,
            true
        );
        
        wp_localize_script('ksm-ps-admin', 'ksm_ps_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ksm_ps_nonce'),
            'strings' => array(
                'running' => __('Running...', 'ksm-post-scheduler'),
                'success' => __('Scheduler executed successfully!', 'ksm-post-scheduler'),
                'error' => __('Error occurred while running scheduler.', 'ksm-post-scheduler'),
                'validation_error' => __('Validation Error', 'ksm-post-scheduler'),
                'time_format_error' => __('Please enter time in 12-hour format (e.g., 9:00 AM or 6:30 PM)', 'ksm-post-scheduler'),
                'posts_per_day_error' => __('Posts per day must be between 1 and 50', 'ksm-post-scheduler'),
                'min_interval_error' => __('Minimum interval must be between 5 and 1440 minutes', 'ksm-post-scheduler'),
                'time_window_error' => __('Not enough time between start and end time for the specified number of posts with minimum intervals', 'ksm-post-scheduler'),
                'settings_saved' => __('Settings saved successfully!', 'ksm-post-scheduler'),
                'settings_error' => __('Error saving settings. Please check your input and try again.', 'ksm-post-scheduler')
            )
        ));
        
        wp_enqueue_style(
            'ksm-ps-admin',
            KSM_PS_PLUGIN_URL . 'assets/admin.css',
            array(),
            KSM_PS_VERSION
        );
    }
    
    /**
     * Sanitize settings
     * 
     * @param array $input Raw input data
     * @return array Sanitized settings
     * @since 1.0.0
     */
    public function sanitize_settings($input) {
        // Get current options to compare for changes
        $current_options = get_option($this->option_name, array());
        $current_posts_per_day = $current_options['posts_per_day'] ?? 5;
        
        $sanitized = array();
        $validation_errors = array();
        
        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;
        $sanitized['post_status'] = sanitize_text_field($input['post_status'] ?? 'draft');
        
        // Validate and sanitize posts per day
        $posts_per_day = absint($input['posts_per_day'] ?? 5);
        if ($posts_per_day < 1 || $posts_per_day > 50) {
            $validation_errors[] = __('Posts per day must be between 1 and 50.', 'ksm-post-scheduler');
            $posts_per_day = max(1, min(50, $posts_per_day)); // Clamp to valid range
        }
        $sanitized['posts_per_day'] = $posts_per_day;
        
        // Handle time fields - use 12-hour format for storage and display
        $sanitized['start_time'] = sanitize_text_field($input['start_time'] ?? '9:00 AM');
        $sanitized['end_time'] = sanitize_text_field($input['end_time'] ?? '6:00 PM');
        
        // Validate 12-hour time format
        if (!empty($sanitized['start_time'])) {
            $time_test = DateTime::createFromFormat('g:i A', $sanitized['start_time']);
            if (!$time_test) {
                $time_test = DateTime::createFromFormat('h:i A', $sanitized['start_time']);
            }
            if (!$time_test) {
                $validation_errors[] = __('Invalid start time format. Please use 12-hour format (e.g., 9:00 AM).', 'ksm-post-scheduler');
                $sanitized['start_time'] = '9:00 AM';
            }
        }
        
        if (!empty($sanitized['end_time'])) {
            $time_test = DateTime::createFromFormat('g:i A', $sanitized['end_time']);
            if (!$time_test) {
                $time_test = DateTime::createFromFormat('h:i A', $sanitized['end_time']);
            }
            if (!$time_test) {
                $validation_errors[] = __('Invalid end time format. Please use 12-hour format (e.g., 6:00 PM).', 'ksm-post-scheduler');
                $sanitized['end_time'] = '6:00 PM';
            }
        }
        
        // Validate and sanitize minimum interval
        $min_interval = absint($input['min_interval'] ?? 30);
        if ($min_interval < 5 || $min_interval > 1440) {
            $validation_errors[] = __('Minimum interval must be between 5 and 1440 minutes.', 'ksm-post-scheduler');
            $min_interval = max(5, min(1440, $min_interval)); // Clamp to valid range
        }
        $sanitized['min_interval'] = $min_interval;
        
        // Validate time window
        if (!empty($sanitized['start_time']) && !empty($sanitized['end_time'])) {
            $start_minutes = $this->time_to_minutes($sanitized['start_time']);
            $end_minutes = $this->time_to_minutes($sanitized['end_time']);
            
            if ($end_minutes <= $start_minutes) {
                $validation_errors[] = __('End time must be after start time.', 'ksm-post-scheduler');
            } else {
                // Check if there's enough time for the specified posts with intervals
                $available_minutes = $end_minutes - $start_minutes;
                $required_minutes = ($posts_per_day - 1) * $min_interval;
                
                if ($required_minutes > $available_minutes) {
                    $available_hours = floor($available_minutes / 60);
                    $available_mins = $available_minutes % 60;
                    $required_hours = floor($required_minutes / 60);
                    $required_mins = $required_minutes % 60;
                    
                    $error_message = sprintf(
                        __('Not enough time! You need %d hours %d minutes (%d minutes total) but only have %d hours %d minutes (%d minutes total) available. Suggestions: Reduce posts to %d per day, OR extend end time by %d minutes, OR reduce interval to %d minutes.', 'ksm-post-scheduler'),
                        $required_hours,
                        $required_mins,
                        $required_minutes,
                        $available_hours,
                        $available_mins,
                        $available_minutes,
                        max(1, floor($available_minutes / $min_interval) + 1),
                        $required_minutes - $available_minutes,
                        max(5, floor($available_minutes / ($posts_per_day - 1)))
                    );
                    
                    $validation_errors[] = $error_message;
                }
            }
        }
        
        // Sanitize days active
        $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $sanitized['days_active'] = array();
        if (isset($input['days_active']) && is_array($input['days_active'])) {
            foreach ($input['days_active'] as $day) {
                if (in_array($day, $valid_days)) {
                    $sanitized['days_active'][] = $day;
                }
            }
        }
        
        // Sanitize author assignment settings
        $sanitized['randomize_authors'] = isset($input['randomize_authors']) ? (bool) $input['randomize_authors'] : false;
        
        // Sanitize assignment strategy
        $valid_strategies = array('random', 'round_robin');
        $sanitized['assignment_strategy'] = isset($input['assignment_strategy']) && in_array($input['assignment_strategy'], $valid_strategies) 
            ? sanitize_text_field($input['assignment_strategy']) 
            : 'random';
        
        // Sanitize allowed author roles
        $sanitized['allowed_author_roles'] = array();
        if (isset($input['allowed_author_roles']) && is_array($input['allowed_author_roles'])) {
            global $wp_roles;
            $valid_roles = array_keys($wp_roles->roles);
            
            foreach ($input['allowed_author_roles'] as $role) {
                $role = sanitize_text_field($role);
                if (in_array($role, $valid_roles)) {
                    // Check if role has edit_posts capability
                    $role_obj = get_role($role);
                    if ($role_obj && $role_obj->has_cap('edit_posts')) {
                        $sanitized['allowed_author_roles'][] = $role;
                    }
                }
            }
            
            // If no valid roles selected but randomize_authors is enabled, add warning
            if ($sanitized['randomize_authors'] && empty($sanitized['allowed_author_roles'])) {
                $validation_errors[] = __('Author randomization is enabled but no valid author roles are selected. Please select at least one role with edit_posts capability.', 'ksm-post-scheduler');
                $sanitized['randomize_authors'] = false; // Disable if no valid roles
            }
        } else if ($sanitized['randomize_authors']) {
            // If randomize_authors is enabled but no roles provided, use defaults
            $sanitized['allowed_author_roles'] = array('author', 'editor', 'administrator');
        }
        
        // Sanitize excluded users
        $sanitized['excluded_users'] = array();
        if (isset($input['excluded_users']) && is_array($input['excluded_users'])) {
            foreach ($input['excluded_users'] as $user_id) {
                $user_id = absint($user_id);
                if ($user_id > 0 && get_userdata($user_id)) {
                    $sanitized['excluded_users'][] = $user_id;
                }
            }
        }
        
        // If there are validation errors, add them as settings errors
        if (!empty($validation_errors)) {
            foreach ($validation_errors as $error) {
                add_settings_error(
                    $this->option_name,
                    'validation_error',
                    $error,
                    'error'
                );
            }
        }
        
        // Check if posts_per_day has changed and there are scheduled posts
        if ($current_posts_per_day !== $sanitized['posts_per_day']) {
            
            
            // Schedule the re-adjustment to run after the settings are saved
            add_action('updated_option_' . $this->option_name, array($this, 'handle_posts_per_day_change'), 10, 2);
        }
        
        return $sanitized;
    }
    
    /**
     * Convert 12-hour time string to minutes since midnight
     * 
     * @param string $time_string Time in 12-hour format (e.g., "9:00 AM")
     * @return int Minutes since midnight
     * @since 1.1.5
     */
    private function time_to_minutes($time_string) {
        $time = DateTime::createFromFormat('g:i A', $time_string);
        if (!$time) {
            $time = DateTime::createFromFormat('h:i A', $time_string);
        }
        if ($time) {
            return (int)$time->format('H') * 60 + (int)$time->format('i');
        }
        return 0; // Default fallback
    }
    
    /**
     * Handle posts per day setting change
     * 
     * @param array $old_value Previous option value
     * @param array $new_value New option value
     * @since 1.1.5
     */
    public function handle_posts_per_day_change($old_value, $new_value) {
        
        
        // Re-adjust existing scheduled posts with new posts per day setting
        $result = $this->readjust_scheduled_posts();
        
        if ($result['success']) {
            add_settings_error(
                $this->option_name,
                'posts_readjusted',
                __('Settings saved and existing scheduled posts have been re-adjusted based on the new posts per day setting.', 'ksm-post-scheduler'),
                'updated'
            );
        } else {
            add_settings_error(
                $this->option_name,
                'readjust_failed',
                __('Settings saved, but failed to re-adjust existing scheduled posts: ' . $result['message'], 'ksm-post-scheduler'),
                'error'
            );
        }
    }
    
    /**
     * Admin page content
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ksm-post-scheduler'));
        }
        
        $options = get_option($this->option_name, array());
        $post_statuses = get_post_stati(array('public' => false), 'objects');
        
        // Get current status
        $monitored_count = $this->get_monitored_posts_count();
        $scheduling_preview = $this->get_scheduling_preview();
        
        include KSM_PS_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Get count of posts in monitored status
     * 
     * @return int Number of posts
     * @since 1.0.0
     */
    private function get_monitored_posts_count() {
        $options = get_option($this->option_name, array());
        $post_status = $options['post_status'] ?? 'draft';
        
        $posts = get_posts(array(
            'post_status' => $post_status,
            'numberposts' => -1,
            'post_type' => 'post'
        ));
        
        return count($posts);
    }
    

    
    /**
     * Daily cron job function
     * 
     * @since 1.0.0
     */
    public function random_post_scheduler_daily_cron() {
        $options = get_option($this->option_name, array());
        
        // Check if scheduler is enabled
        if (empty($options['enabled'])) {
            return;
        }
        
        // DEFICIT DETECTION: Check if yesterday met its quota before scheduling today
        $this->detect_and_record_deficit();
        
        // Check if today is an active day (using WordPress timezone)
        $today = strtolower(current_time('l'));
        if (!in_array($today, $options['days_active'] ?? array())) {
            return;
        }
        
        // Update last cron run time
        $options['last_cron_run'] = current_time('mysql');
        update_option($this->option_name, $options);
        
        $this->schedule_posts(true); // Pass true to indicate this is a cron run
    }
    
    /**
     * Re-adjust existing scheduled posts when posts per day setting changes
     * 
     * @return array Result array with success status and message
     * @since 1.1.5
     */
    private function readjust_scheduled_posts() {
        $options = get_option($this->option_name, array());
        $posts_per_day = $options['posts_per_day'] ?? 5;
        
        // Get all currently scheduled posts (future status)
        $scheduled_posts = get_posts(array(
            'post_status' => 'future',
            'numberposts' => -1,
            'post_type' => 'post',
            'orderby' => 'post_date',
            'order' => 'ASC'
        ));
        
        if (empty($scheduled_posts)) {
            return array('success' => true, 'message' => 'No scheduled posts to re-adjust.');
        }
        

        
        // Reset all scheduled posts to draft status first
        foreach ($scheduled_posts as $post) {
            wp_update_post(array(
                'ID' => $post->ID,
                'post_status' => $options['post_status'] ?? 'draft'
            ));
        }
        
        // Now reschedule them with the new posts per day setting
        return $this->schedule_posts();
    }
    
    // REMOVED: register_publication_hooks function
    // This function created custom hooks on every page load and is no longer needed.
    // WordPress handles post publication natively without custom hooks.
    
    // REMOVED: publish_scheduled_post function
    // This function bypassed WordPress hooks and caused compatibility issues.
    // WordPress now handles post publication natively when the future date arrives.

    /**
     * Schedule posts function
     * 
     * @param bool $is_cron_run Whether this is being called from cron (true) or manual scheduling (false)
     * @return array Result array with success status and message
     * @since 1.0.0
     */
    private function schedule_posts($is_cron_run = false) {
        // Validate plugin configuration
        $options = get_option($this->option_name, array());
        
        // Validate required options
        if (empty($options)) {
            return array(
                'success' => false,
                'message' => __('Plugin configuration not found. Please configure the plugin settings first.', 'ksm-post-scheduler')
            );
        }
        
        $posts_per_day = $options['posts_per_day'] ?? 5;
        
        // Validate posts per day setting
        if ($posts_per_day <= 0 || $posts_per_day > 50) {
            return array(
                'success' => false,
                'message' => __('Invalid posts per day setting. Please set a value between 1 and 50.', 'ksm-post-scheduler')
            );
        }
        
        // Validate time settings
        $start_time = $options['start_time'] ?? '9:00 AM';
        $end_time = $options['end_time'] ?? '5:00 PM';
        
        if (empty($start_time) || empty($end_time)) {
            return array(
                'success' => false,
                'message' => __('Invalid time settings. Please configure start and end times.', 'ksm-post-scheduler')
            );
        }
        
        // Validate active days
        $days_active = $options['days_active'] ?? array();
        if (empty($days_active) || !is_array($days_active)) {
            return array(
                'success' => false,
                'message' => __('No active days configured. Please select at least one day for scheduling.', 'ksm-post-scheduler')
            );
        }
        
        // For both manual and cron scheduling, allow scheduling across multiple days
        // Manual scheduling can now distribute posts across future dates like cron runs
        // FIXED: Remove artificial limit to allow scheduling of all available posts
        
        // Initialize progress tracking for manual runs
        $progress_report = array();
        $scheduled_posts_details = array(); // For chronological sorting
        
        // Get posts to schedule
        $post_status = $options['post_status'] ?? 'draft';
        $posts = get_posts(array(
            'post_status' => $post_status,
            'numberposts' => -1, // Get all available posts instead of limiting
            'post_type' => 'post',
            'orderby' => 'date',
            'order' => 'ASC'
        ));
        
        // Validate posts query result
        if (is_wp_error($posts)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error retrieving posts: %s', 'ksm-post-scheduler'), $posts->get_error_message())
            );
        }
        
        if (empty($posts)) {
            return array(
                'success' => false, 
                'message' => sprintf(__('No %s posts found to schedule. Please create some posts first.', 'ksm-post-scheduler'), $post_status)
            );
        }
        
        // SMART BACKFILL: Apply auto-completion feature before regular scheduling
        // This ensures deficit days are filled first before scheduling future posts
        $backfill_results = $this->apply_smart_backfill($posts, $options);
        
        // If backfill was applied, update our posts array and add progress info
        if (!empty($backfill_results['backfill_scheduled'])) {
            $progress_report[] = "🔄 Auto-completion: Filled " . count($backfill_results['backfill_scheduled']) . " deficit posts for incomplete days";
            
            // Schedule the backfill posts
            $backfill_schedule_results = $this->schedule_backfill_posts($backfill_results['backfill_posts'], $options, !$is_cron_run);
            
            // Add backfill results to progress
            if (!empty($backfill_schedule_results)) {
                foreach ($backfill_schedule_results as $date => $date_results) {
                    $progress_report[] = "✅ Completed " . count($date_results) . " posts for " . wp_date('l, M j', strtotime($date));
                }
            }
        }
        
        // Continue with remaining posts for future scheduling
        $posts = $backfill_results['remaining_posts'] ?? $posts;
        
        // If no posts remain after backfill, we're done
        if (empty($posts)) {
            return array(
                'success' => true,
                'message' => __('All available posts were used for auto-completion of incomplete days.', 'ksm-post-scheduler'),
                'progress_report' => $progress_report ?? array(),
                'scheduled_posts' => $backfill_schedule_results ?? array()
            );
        }
        
        // Get time settings
        $start_time = $options['start_time'] ?? '9:00 AM';
        $end_time = $options['end_time'] ?? '6:00 PM';
        $min_interval = $options['min_interval'] ?? 30;
        $days_active = $options['days_active'] ?? array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
        

        
        // Update total posts count after backfill
        $total_posts_to_schedule = count($posts);
        
        // Convert start and end times to minutes for easier calculation
        $start_minutes = $this->time_to_minutes($start_time);
        $end_minutes = $this->time_to_minutes($end_time);
        
        // Get current WordPress time (respects site timezone)
        $current_wp_timestamp = current_time('timestamp');
        $current_wp_time = current_time('Y-m-d H:i:s');
        $current_date = current_time('Y-m-d');
        $current_time_minutes = (int)current_time('H') * 60 + (int)current_time('i');
        
        // DEBUG: Log current settings and time

        
        $scheduled_count = 0;
        $current_day_offset = 0;
        $posts_scheduled_for_current_day = 0;
        
        // Both manual and cron scheduling now work identically - distribute across multiple days

        
        // Determine if we can schedule posts today
        $can_schedule_today = false;
        $today_name = strtolower(current_time('l'));
        
        if (in_array($today_name, $days_active)) {
            // Check if we're still within scheduling hours
            $latest_scheduling_time = $end_minutes;
            
            // We can schedule today if:
            // 1. Current time is before the latest scheduling time (end time)
            // 2. We still have time for at least one post (considering start time and minimum interval)
            if ($current_time_minutes < $latest_scheduling_time) {
                // Calculate the effective start time for today
                $effective_start_time = max($current_time_minutes, $start_minutes);
                
                // Check if we have enough time between effective start and latest scheduling time
                $remaining_time_today = $latest_scheduling_time - $effective_start_time;
                $possible_posts_today = min($posts_per_day, floor($remaining_time_today / $min_interval) + 1);
                
                if ($possible_posts_today > 0 && $effective_start_time <= $latest_scheduling_time) {
                    $can_schedule_today = true;
                }
            }
        }
        
        // For manual scheduling, allow scheduling across future dates just like cron runs
        // Remove the restriction that limited manual scheduling to current day only
        if (!$is_cron_run && !$can_schedule_today) {
            // Don't return error - continue to schedule across future dates
            $current_day_offset = 1; // Start from tomorrow
            $posts_scheduled_for_current_day = 0;
        }
        
        // Pre-generate schedules for each day as needed
        $daily_schedules = array();
        
        foreach ($posts as $index => $post) {
            // CONSOLIDATED DAY-CHANGE LOGIC: Check if we need to move to the next day
            if ($posts_scheduled_for_current_day >= $posts_per_day) {
                
                // Add progress report for manual runs
                if (!$is_cron_run && isset($target_date)) {
                    $progress_report[] = "✓ Daily limit reached for " . wp_date('l, M j', strtotime($target_date)) . " - Moving to next day";
                }
                
                // CRITICAL FIX: Move to next ACTIVE day, not just increment offset
                // Find the next active day by checking each subsequent day
                $next_active_day_found = false;
                $test_offset = $current_day_offset + 1;
                
                while (!$next_active_day_found && $test_offset < $current_day_offset + 14) { // Prevent infinite loop
                    $test_timestamp = $this->get_next_valid_day($test_offset, $days_active, $can_schedule_today);
                    $test_date = wp_date('Y-m-d', $test_timestamp);
                    
                    // Check if this is a different date than current target_date
                    if (!isset($target_date) || $test_date !== $target_date) {
                        $current_day_offset = $test_offset;
                        $next_active_day_found = true;
                        break;
                    }
                    $test_offset++;
                }
                
                if (!$next_active_day_found) {
                    // Fallback to simple increment if no valid day found
                    $current_day_offset++;
                }
                
                $posts_scheduled_for_current_day = 0;
            }
            
            // Find the target scheduling day
            $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
            $target_date = wp_date('Y-m-d', $target_timestamp);
            
            // Generate times for this day if not already done
            if (!isset($daily_schedules[$target_date])) {
                $day_start_time = $start_time;
                
                // CRITICAL FIX: Calculate actual posts needed for this day
                $remaining_posts = $total_posts_to_schedule - $index;
                $posts_to_generate = min($posts_per_day, $remaining_posts);
                
                // Add progress report for manual runs - new day started
                if (!$is_cron_run) {
                    $progress_report[] = "📅 " . wp_date('l, M j', $target_timestamp) . " - Can schedule {$posts_to_generate} posts today";
                }
                
                // For today, adjust start time and calculate available slots
                if ($current_day_offset === 0 && $can_schedule_today) {
                    $effective_start_time = max($current_time_minutes, $start_minutes);
                    
                    // CRITICAL FIX: Ensure start time is always in the future for today
                    if ($current_time_minutes > $start_minutes) {
                        // Add buffer time to ensure we're definitely in the future
                        $buffer_minutes = 5; // 5-minute buffer
                        $safe_start_time = $current_time_minutes + $buffer_minutes;
                        
                        $adjusted_hour = floor($safe_start_time / 60);
                        $adjusted_minute = $safe_start_time % 60;
                        
                        // Handle hour overflow (past midnight)
                        if ($adjusted_hour >= 24) {
                            $can_schedule_today = false;
                            $current_day_offset = 1; // Move to tomorrow
                            $posts_scheduled_for_current_day = 0;
                            // Recalculate target for tomorrow
                            $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
                            $target_date = wp_date('Y-m-d', $target_timestamp);
                            $day_start_time = $start_time; // Reset to original start time for tomorrow
                            // Recalculate posts needed for the new day
                            $remaining_posts = $total_posts_to_schedule - $index;
                            $posts_to_generate = min($posts_per_day, $remaining_posts);
                        }
                        
                        $day_start_time = sprintf('%d:%02d %s', 
                            $adjusted_hour > 12 ? $adjusted_hour - 12 : ($adjusted_hour == 0 ? 12 : $adjusted_hour),
                            $adjusted_minute,
                            $adjusted_hour >= 12 ? 'PM' : 'AM'
                        );
                        
                        $effective_start_time = $safe_start_time;
                    }
                    
                    $remaining_time_today = $end_minutes - $effective_start_time;
                    
                    // Ensure we have enough time for at least one post
                    if ($remaining_time_today < $min_interval) {
                        $can_schedule_today = false;
                        $current_day_offset = 1; // Move to tomorrow
                        $posts_scheduled_for_current_day = 0;
                        // Recalculate target for tomorrow
                        $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
                        $target_date = wp_date('Y-m-d', $target_timestamp);
                        $day_start_time = $start_time; // Reset to original start time for tomorrow
                        // Recalculate posts needed for the new day
                        $remaining_posts = $total_posts_to_schedule - $index;
                        $posts_to_generate = min($posts_per_day, $remaining_posts);
                    }
                    
                    $posts_to_generate = min($posts_per_day, floor($remaining_time_today / $min_interval) + 1);
                }
                
                $daily_schedules[$target_date] = $this->generate_random_times(
                    $posts_to_generate,
                    $day_start_time,
                    $end_time,
                    $min_interval
                );
            }
            
            // Check if we have a time slot available for this post
            if (!isset($daily_schedules[$target_date][$posts_scheduled_for_current_day])) {
                // Skip this post if no time slot available
                continue;
            }
            
            $scheduled_time_str = $daily_schedules[$target_date][$posts_scheduled_for_current_day];
            
            // FIXED TIMESTAMP CALCULATION: Create proper datetime string and convert to timestamp
            $scheduled_datetime_str = $target_date . ' ' . $scheduled_time_str;
            
            // Use WordPress timezone-aware conversion
            $wp_timezone = wp_timezone();
            $scheduled_datetime = DateTime::createFromFormat('Y-m-d g:i A', $scheduled_datetime_str, $wp_timezone);
            
            if (!$scheduled_datetime) {
                // Skip this post if datetime parsing fails
                continue;
            }
            
            $scheduled_timestamp = $scheduled_datetime->getTimestamp();
            
            // CRITICAL: Validate that the timestamp is in the future to prevent immediate publication
            $current_timestamp = current_time('timestamp');
            if ($scheduled_timestamp <= $current_timestamp) {
                error_log("KSM Post Scheduler: Skipping post {$post->ID} - Scheduled timestamp {$scheduled_timestamp} is not in the future (current: {$current_timestamp})");
                continue;
            }
            
            // Convert to MySQL format for WordPress
            $scheduled_time_mysql = $scheduled_datetime->format('Y-m-d H:i:s');
            $scheduled_time_gmt = get_gmt_from_date($scheduled_time_mysql);
            

            
            // PROPER WORDPRESS SCHEDULING: Use WordPress functions with proper future timestamps
            
            // Determine author for this post (author assignment logic)
            $original_author_id = $post->post_author;
            $assigned_author_id = $original_author_id; // Default to original author
            $author_changed = false;
            
            // Check if author randomization is enabled
            if ($options['randomize_authors'] ?? false) {
                $assignment_strategy = $options['assignment_strategy'] ?? 'random';
                
                if ($assignment_strategy === 'random') {
                    // Use the helper method to get a random author
                    $assigned_author_id = $this->get_random_author($original_author_id);
                } else if ($assignment_strategy === 'round_robin') {
                    // Round-robin assignment using allowed roles
                    $allowed_roles = $options['allowed_author_roles'] ?? array('author', 'editor', 'administrator');
                    $excluded_users = $options['excluded_users'] ?? array();
                    
                    if (!empty($allowed_roles)) {
                        // Get users with allowed roles for round-robin
                        $rotation_users = get_users(array(
                            'role__in' => $allowed_roles,
                            'fields' => 'ID',
                            'number' => 100
                        ));
                        
                        // Filter out the current author and excluded users
                        $rotation_users = array_filter($rotation_users, function($user_id) use ($original_author_id, $excluded_users) {
                            return $user_id != $original_author_id && !in_array($user_id, $excluded_users);
                        });
                        
                        if (!empty($rotation_users)) {
                            // Get current rotation index
                            $current_user_index = get_option('ksm_ps_current_user_index', 0);
                            
                            // Ensure index is within bounds
                            if ($current_user_index >= count($rotation_users)) {
                                $current_user_index = 0;
                            }
                            
                            $assigned_author_id = $rotation_users[$current_user_index];
                            
                            // Advance to next user for next post
                            $current_user_index = ($current_user_index + 1) % count($rotation_users);
                            update_option('ksm_ps_current_user_index', $current_user_index);
                        }
                    }
                }
                
                $author_changed = ($assigned_author_id != $original_author_id);
                
                if ($author_changed) {
                    error_log("KSM Post Scheduler: Post {$post->ID}: Changed author from {$original_author_id} to {$assigned_author_id} using {$assignment_strategy} strategy");
                }
            }
            
            // Validate post data before updating
            if (!$post->ID || $post->ID <= 0) {
                error_log("KSM Post Scheduler: Invalid post ID for scheduling");
                continue;
            }
            
            if (!$assigned_author_id || !get_userdata($assigned_author_id)) {
                error_log("KSM Post Scheduler: Invalid author ID {$assigned_author_id} for post {$post->ID}");
                continue;
            }
            
            // Use wp_update_post with proper future timestamps (no hook workarounds needed)
            $post_data = array(
                'ID' => $post->ID,
                'post_status' => 'future',
                'post_date' => $scheduled_time_mysql,
                'post_date_gmt' => $scheduled_time_gmt,
                'post_author' => $assigned_author_id,
                'edit_date' => true // This tells WordPress we're explicitly setting the date
            );
            
            $update_result = wp_update_post($post_data, true);
            
            if (is_wp_error($update_result)) {
                // Log detailed error for admin review but continue processing
                $error_message = $update_result->get_error_message();
                $error_code = $update_result->get_error_code();
                error_log("KSM Post Scheduler: Failed to update post {$post->ID} - Error: {$error_code} - {$error_message}");
                continue;
            }
            
            if ($update_result === 0 || $update_result === false) {
                // Log error for admin review but continue processing
                error_log("KSM Post Scheduler: wp_update_post failed for post {$post->ID} - No specific error returned");
                continue;
            }
            
            // WordPress will handle publication automatically when the future post date arrives
            // No custom cron scheduling needed - this ensures compatibility with other plugins
            error_log("KSM Post Scheduler: Post {$post->ID} scheduled for {$scheduled_time_mysql} using WordPress native scheduling");
            
            // Verify the post was updated correctly
            $updated_post = get_post($post->ID);
            
            if ($updated_post && $updated_post->post_status === 'future') {
                $scheduled_count++;
                $posts_scheduled_for_current_day++;
                
                // Add progress tracking for manual runs
                if (!$is_cron_run) {
                    $post_title = strlen($post->post_title) > 30 ? substr($post->post_title, 0, 30) . '...' : $post->post_title;
                    
                    // Get author information for progress report
                    $author_info = '';
                    if ($randomize_authors && $author_changed) {
                        $original_author = get_userdata($original_author_id);
                        $assigned_author = get_userdata($assigned_author_id);
                        $author_info = sprintf(
                            ' [Author: %s → %s]',
                            $original_author ? $original_author->display_name : 'Unknown',
                            $assigned_author ? $assigned_author->display_name : 'Unknown'
                        );
                    } elseif ($randomize_authors) {
                        $assigned_author = get_userdata($assigned_author_id);
                        $author_info = sprintf(
                            ' [Author: %s]',
                            $assigned_author ? $assigned_author->display_name : 'Unknown'
                        );
                    }
                    
                    $scheduled_posts_details[] = array(
                        'post_number' => 0, // Will be assigned sequentially after sorting
                        'title' => $post_title,
                        'time_str' => $scheduled_time_str,
                        'timestamp' => $scheduled_timestamp,
                        'date' => $target_date,
                        'author_info' => $author_info,
                        'author_changed' => $author_changed,
                        'original_author_id' => $original_author_id,
                        'assigned_author_id' => $assigned_author_id
                    );
                }
                
            } else {
                // Log verification failure for admin review
                error_log("KSM Post Scheduler: Post {$post->ID} status verification failed. Expected 'future', got: " . 
                         ($updated_post ? $updated_post->post_status : 'unknown'));
            }
        }
        
        // Build comprehensive result message
        if ($scheduled_count === 0) {
            $message = __('No posts were scheduled. This could be due to configuration issues or all available posts already being scheduled.', 'ksm-post-scheduler');
            return array('success' => false, 'message' => $message);
        }
        
        $failed_count = $total_posts_to_schedule - $scheduled_count;
        $message = sprintf(__('Successfully scheduled %d out of %d posts.', 'ksm-post-scheduler'), $scheduled_count, $total_posts_to_schedule);
        
        if ($failed_count > 0) {
            $message .= ' ' . sprintf(__('%d posts failed to schedule (check error logs for details).', 'ksm-post-scheduler'), $failed_count);
        }
        
        if (!$is_cron_run) {
            $message .= ' ' . __('(Manual scheduling - distributed across future dates)', 'ksm-post-scheduler');
            
            // Add detailed progress report for manual runs
            if (!empty($scheduled_posts_details)) {
                // Sort posts by timestamp for chronological order
                usort($scheduled_posts_details, function($a, $b) {
                    return $a['timestamp'] - $b['timestamp'];
                });
                
                // Assign sequential post numbers after sorting
                for ($i = 0; $i < count($scheduled_posts_details); $i++) {
                    $scheduled_posts_details[$i]['post_number'] = $i + 1;
                }
                
                // Group posts by date and build progress report
                $grouped_posts = array();
                foreach ($scheduled_posts_details as $post_detail) {
                    $date_key = $post_detail['date'];
                    if (!isset($grouped_posts[$date_key])) {
                        $grouped_posts[$date_key] = array();
                    }
                    $grouped_posts[$date_key][] = $post_detail;
                }
                
                $message .= "\n\n📊 " . __('SCHEDULING PROGRESS REPORT:', 'ksm-post-scheduler') . "\n";
                $message .= sprintf(__('Total posts processed: %d', 'ksm-post-scheduler'), $total_posts_to_schedule) . "\n";
                $message .= sprintf(__('Posts successfully scheduled: %d', 'ksm-post-scheduler'), $scheduled_count) . "\n\n";
                $message .= __('Day-by-day breakdown:', 'ksm-post-scheduler') . "\n";
                
                foreach ($grouped_posts as $date => $posts) {
                    $day_name = wp_date('l, M j', strtotime($date));
                    $post_count = count($posts);
                    $message .= sprintf(__('📅 %s - Scheduled %d posts', 'ksm-post-scheduler'), $day_name, $post_count) . "\n";
                    
                    foreach ($posts as $post_detail) {
                        $message .= sprintf(__('  ✓ Post #%d: "%s" at %s%s', 'ksm-post-scheduler'), 
                            $post_detail['post_number'], 
                            $post_detail['title'], 
                            $post_detail['time_str'], 
                            $post_detail['author_info']
                        ) . "\n";
                    }
                    $message .= "\n";
                }
                
                $message .= "✅ " . __('All posts have been scheduled according to your daily limits and time windows.', 'ksm-post-scheduler');
            }
        } else {
            $message .= ' ' . __('(Automatic scheduling - daily limits applied)', 'ksm-post-scheduler');
        }

        
        return array('success' => true, 'message' => $message);
    }
    
    /**
     * Get the next valid scheduling day based on active days
     * 
     * @param int $day_offset Number of days from the starting point (0 = today if can_schedule_today, otherwise tomorrow)
     * @param array $days_active Array of active day names
     * @param bool $can_schedule_today Whether we can schedule posts today
     * @return int Timestamp for the target day
     * @since 1.1.8
     */
    private function get_next_valid_day($day_offset, $days_active, $can_schedule_today = false) {
        // Use WordPress current time consistently
        $current_wp_timestamp = current_time('timestamp');
        
        // Calculate the base timestamp
        if ($can_schedule_today && $day_offset === 0) {
            // If we can schedule today and offset is 0, use today
            $target_timestamp = $current_wp_timestamp;
        } else {
            // Calculate the correct offset:
            // - If can_schedule_today is true: day_offset represents days from today
            // - If can_schedule_today is false: day_offset represents days from tomorrow
            $base_offset = $can_schedule_today ? $day_offset : ($day_offset + 1);
            $target_timestamp = $current_wp_timestamp + ($base_offset * 24 * 60 * 60);
        }
        
        // If no active days specified, use the calculated day
        if (empty($days_active)) {
            return $target_timestamp;
        }
        
        // Find the next valid day that matches active days
        $attempts = 0;
        while ($attempts < 14) { // Prevent infinite loop, check up to 2 weeks
            // Use WordPress timezone for day calculation
            $day_name = strtolower(wp_date('l', $target_timestamp));
            
            if (in_array($day_name, $days_active)) {
                return $target_timestamp;
            }
            
            // Move to next day
            $target_timestamp += 24 * 60 * 60;
            $attempts++;
        }
        
        // Fallback: return the original calculated day
        return $current_wp_timestamp + ($day_offset * 24 * 60 * 60);
    }
    
    /**
     * Generate random times within range
     * 
     * @param int $count Number of times to generate
     * @param string $start_time Start time (HH:MM format)
     * @param string $end_time End time (HH:MM format)
     * @param int $min_interval Minimum interval in minutes
     * @return array Array of times in HH:MM format
     * @since 1.0.0
     */
    private function generate_random_times($count, $start_time, $end_time, $min_interval) {
        $start_minutes = $this->time_to_minutes($start_time);
        $end_minutes = $this->time_to_minutes($end_time);
        
        $available_minutes = $end_minutes - $start_minutes;
        $total_interval_time = ($count - 1) * $min_interval;
        
        if ($total_interval_time >= $available_minutes) {
            // Not enough time for all posts with minimum interval
            return array();
        }
        
        $times = array();
        $used_times = array();
        
        for ($i = 0; $i < $count; $i++) {
            $attempts = 0;
            do {
                $random_minutes = rand($start_minutes, $end_minutes - 1);
                $attempts++;
            } while ($this->is_time_too_close($random_minutes, $used_times, $min_interval) && $attempts < 100);
            
            if ($attempts < 100) {
                $used_times[] = $random_minutes;
                $times[] = $this->minutes_to_time($random_minutes);
            }
        }
        
        return $times;
    }
    
    /**
     * Convert minutes since midnight to 12-hour time string
     * 
     * @param int $minutes Minutes since midnight
     * @return string Time in 12-hour format (e.g., "9:00 AM")
     * @since 1.0.0
     */
    private function minutes_to_time($minutes) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        // Convert to 12-hour format
        $period = ($hours >= 12) ? 'PM' : 'AM';
        $display_hours = ($hours == 0) ? 12 : (($hours > 12) ? $hours - 12 : $hours);
        
        return sprintf('%d:%02d %s', $display_hours, $mins, $period);
    }
    
    /**
     * Check if time is too close to existing times
     * 
     * @param int $time_minutes Time in minutes
     * @param array $used_times Array of used times in minutes
     * @param int $min_interval Minimum interval in minutes
     * @return bool True if too close
     * @since 1.0.0
     */
    private function is_time_too_close($time_minutes, $used_times, $min_interval) {
        foreach ($used_times as $used_time) {
            if (abs($time_minutes - $used_time) < $min_interval) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get a random author from allowed roles, excluding specified author
     * 
     * @param int $exclude_author_id Author ID to exclude from selection
     * @return int Author ID (original if no valid alternatives found)
     * @since 1.6.9
     */
    private function get_random_author($exclude_author_id) {
        $options = get_option($this->option_name, array());
        $allowed_roles = $options['allowed_author_roles'] ?? array('author', 'editor', 'administrator');
        $excluded_users = $options['excluded_users'] ?? array();
        
        if (empty($allowed_roles)) {
            return $exclude_author_id; // Return original if no roles configured
        }
        
        // Get users with allowed roles
        $users = get_users(array(
            'role__in' => $allowed_roles,
            'fields' => 'ID',
            'number' => 100 // Reasonable limit to prevent memory issues
        ));
        
        if (empty($users)) {
            error_log('KSM Post Scheduler: No users found with allowed author roles: ' . implode(', ', $allowed_roles));
            return $exclude_author_id;
        }
        
        // Filter out the excluded author and excluded users
        $available_users = array_filter($users, function($user_id) use ($exclude_author_id, $excluded_users) {
            return $user_id != $exclude_author_id && !in_array($user_id, $excluded_users);
        });
        
        // If no alternative users available, return original
        if (empty($available_users)) {
            error_log('KSM Post Scheduler: No alternative authors available after excluding author ID: ' . $exclude_author_id);
            return $exclude_author_id;
        }
        
        // Randomly select an author
        $random_key = array_rand($available_users);
        $selected_author_id = $available_users[$random_key];
        
        error_log('KSM Post Scheduler: Selected random author ID ' . $selected_author_id . ' (excluded: ' . $exclude_author_id . ')');
        
        return $selected_author_id;
    }
    
    /**
     * Get scheduling preview data for admin display
     * 
     * @return array Scheduling preview data
     * @since 1.4.8
     */
    private function get_scheduling_preview() {
        $options = get_option($this->option_name, array());
        $posts_per_day = $options['posts_per_day'] ?? 5;
        $start_time = $options['start_time'] ?? '9:00 AM';
        $end_time = $options['end_time'] ?? '6:00 PM';
        $min_interval = $options['min_interval'] ?? 30;
        $days_active = $options['days_active'] ?? array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
        
        // Get posts waiting to be scheduled
        $monitored_count = $this->get_monitored_posts_count();
        
        $preview = array(
            'posts_waiting' => $monitored_count,
            'posts_per_day' => $posts_per_day,
            'estimated_days' => $monitored_count > 0 ? ceil($monitored_count / $posts_per_day) : 0,
            'time_window' => $start_time . ' - ' . $end_time,
            'min_interval' => $min_interval,
            'active_days' => ucwords(implode(', ', $days_active)),
            'daily_preview' => array(),
            'warnings' => array()
        );
        
        // Calculate time window validation
        $start_minutes = $this->time_to_minutes($start_time);
        $end_minutes = $this->time_to_minutes($end_time);
        $window_minutes = $end_minutes - $start_minutes;
        $required_minutes = ($posts_per_day - 1) * $min_interval;
        
        // Add warnings
        if ($window_minutes < $required_minutes) {
            $preview['warnings'][] = sprintf(
                __('⚠️ Warning: Your time window (%d minutes) may be too narrow for %d posts with %d minute intervals (requires %d minutes)', 'ksm-post-scheduler'),
                $window_minutes,
                $posts_per_day,
                $min_interval,
                $required_minutes
            );
        }
        
        if ($monitored_count === 0) {
            $preview['warnings'][] = __('✓ No posts currently need scheduling', 'ksm-post-scheduler');
        }
        
        if (!($options['enabled'] ?? false)) {
            $preview['warnings'][] = __('⚠️ Scheduler is disabled - posts will not be automatically scheduled', 'ksm-post-scheduler');
        }
        
        // Generate 5-day preview if there are posts to schedule
        if ($monitored_count > 0 && ($options['enabled'] ?? false)) {
            $current_day_offset = 0;
            $posts_remaining = $monitored_count;
            $can_schedule_today = true; // Assume we can schedule today for preview
            
            for ($day = 0; $day < 5 && $posts_remaining > 0; $day++) {
                $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
                $target_date = wp_date('l, M j', $target_timestamp);
                
                $posts_for_day = min($posts_per_day, $posts_remaining);
                
                $preview['daily_preview'][] = array(
                    'day' => $day === 0 ? __('Today', 'ksm-post-scheduler') : $target_date,
                    'posts_count' => $posts_for_day,
                    'time_window' => $start_time . ' - ' . $end_time
                );
                
                $posts_remaining -= $posts_for_day;
                $current_day_offset++;
                $can_schedule_today = false; // After first day, we're not scheduling for "today"
            }
            
            if ($posts_remaining > 0) {
                $preview['daily_preview'][] = array(
                    'day' => sprintf(__('... and %d more days', 'ksm-post-scheduler'), ceil($posts_remaining / $posts_per_day)),
                    'posts_count' => $posts_remaining,
                    'time_window' => ''
                );
            }
        }
        
        return $preview;
    }

    
    /**
     * AJAX handler for run now button
     * 
     * @since 1.0.0
     */
    public function ajax_run_now() {
        try {
            // Verify nonce
            if (!check_ajax_referer('ksm_ps_nonce', 'nonce', false)) {
                wp_send_json_error(array(
                    'message' => __('Security check failed. Please refresh the page and try again.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => __('You do not have sufficient permissions to perform this action.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            // Execute scheduling
            $result = $this->schedule_posts(false); // Pass false to indicate manual scheduling
            
            if (!is_array($result) || !isset($result['success'])) {
                wp_send_json_error(array(
                    'message' => __('Invalid response from scheduling function.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            wp_send_json($result);
            
        } catch (Exception $e) {
            error_log("KSM Post Scheduler AJAX Error (run_now): " . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred. Please check the error logs.', 'ksm-post-scheduler')
            ));
        }
    }
    
    /**
     * AJAX handler for getting current status
     * 
     * @since 1.0.0
     */
    public function ajax_get_status() {
        try {
            // Verify nonce
            if (!check_ajax_referer('ksm_ps_nonce', 'nonce', false)) {
                wp_send_json_error(array(
                    'message' => __('Security check failed. Please refresh the page and try again.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => __('You do not have sufficient permissions to view this information.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            // Get plugin options
            $options = get_option($this->option_name);
            if ($options === false) {
                wp_send_json_error(array(
                    'message' => __('Plugin configuration not found. Please configure the plugin first.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            // Get scheduling preview
            $scheduling_preview = $this->get_scheduling_preview();
            
            $data = array(
                'monitored_count' => $this->get_monitored_posts_count(),
                'options' => $options,
                'scheduling_preview' => $scheduling_preview,
                'last_cron_run' => $options['last_cron_run'] ?? null,
                'next_cron_run' => wp_next_scheduled('ksm_ps_daily_cron')
            );
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log("KSM Post Scheduler AJAX Error (get_status): " . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred while retrieving status.', 'ksm-post-scheduler')
            ));
        }
    }
    
    /**
     * Handle AJAX request to dismiss admin notices
     * 
     * @since 1.9.1
     */
    public function ajax_dismiss_notice() {
        try {
            // Verify nonce
            if (!check_ajax_referer('ksm_ps_nonce', 'nonce', false)) {
                wp_send_json_error(array(
                    'message' => __('Security check failed. Please refresh the page and try again.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => __('You do not have sufficient permissions to perform this action.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            // Get notice key from request
            $notice_key = sanitize_text_field($_POST['notice_key'] ?? '');
            if (empty($notice_key)) {
                wp_send_json_error(array(
                    'message' => __('Invalid notice key.', 'ksm-post-scheduler')
                ));
                return;
            }
            
            // Get current dismissed notices for user
            $user_id = get_current_user_id();
            $dismissed_notices = get_user_meta($user_id, 'ksm_ps_dismissed_notices', true);
            if (!is_array($dismissed_notices)) {
                $dismissed_notices = array();
            }
            
            // Add notice key to dismissed list if not already there
            if (!in_array($notice_key, $dismissed_notices)) {
                $dismissed_notices[] = $notice_key;
                update_user_meta($user_id, 'ksm_ps_dismissed_notices', $dismissed_notices);
            }
            
            // If this is a version update notice, also update the plugin version in options
            if (strpos($notice_key, 'version_update_') === 0) {
                $options = get_option($this->option_name);
                if ($options) {
                    $options['version'] = KSM_PS_VERSION;
                    update_option($this->option_name, $options);
                }
            }
            
            wp_send_json_success(array(
                'message' => __('Notice dismissed successfully.', 'ksm-post-scheduler')
            ));
            
        } catch (Exception $e) {
            error_log("KSM Post Scheduler AJAX Error (dismiss_notice): " . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred while dismissing the notice.', 'ksm-post-scheduler')
            ));
        }
    }

    /**
     * Get deficit tracking data
     * 
     * @return array Array of date => deficit_count pairs
     * @since 1.9.6
     */
    private function get_deficits() {
        return get_option('ksm_ps_deficits', array());
    }

    /**
     * Update deficit tracking data
     * 
     * @param array $deficits Array of date => deficit_count pairs
     * @since 1.9.6
     */
    private function update_deficits($deficits) {
        // Clean up old deficits (older than 30 days)
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        foreach ($deficits as $date => $count) {
            if ($date < $cutoff_date) {
                unset($deficits[$date]);
            }
        }
        
        update_option('ksm_ps_deficits', $deficits);
    }

    /**
     * Record a deficit for a specific date
     * 
     * @param string $date Date in Y-m-d format
     * @param int $deficit_count Number of posts short of quota
     * @since 1.9.6
     */
    private function record_deficit($date, $deficit_count) {
        if ($deficit_count <= 0) {
            return;
        }

        $deficits = $this->get_deficits();
        $deficits[$date] = $deficit_count;
        $this->update_deficits($deficits);

        error_log("KSM Post Scheduler: Recorded deficit of {$deficit_count} posts for {$date}");
    }

    /**
     * Clear deficit for a specific date
     * 
     * @param string $date Date in Y-m-d format
     * @since 1.9.6
     */
    private function clear_deficit($date) {
        $deficits = $this->get_deficits();
        if (isset($deficits[$date])) {
            unset($deficits[$date]);
            $this->update_deficits($deficits);
            error_log("KSM Post Scheduler: Cleared deficit for {$date}");
        }
    }

    /**
     * Get total deficit count across all incomplete days
     * 
     * @return int Total number of posts needed to complete all deficits
     * @since 1.9.6
     */
    private function get_total_deficit() {
        $deficits = $this->get_deficits();
        return array_sum($deficits);
    }

    /**
     * Get the oldest incomplete day that needs backfilling
     * 
     * @return string|null Date in Y-m-d format or null if no deficits
     * @since 1.9.6
     */
    private function get_oldest_deficit_date() {
        $deficits = $this->get_deficits();
        if (empty($deficits)) {
            return null;
        }

        ksort($deficits); // Sort by date (oldest first)
        return array_key_first($deficits);
    }

    /**
     * Check if a date has been completed (no deficit)
     * 
     * @param string $date Date in Y-m-d format
     * @return bool True if date is complete, false if it has a deficit
     * @since 1.9.6
     */
    private function is_date_complete($date) {
        $deficits = $this->get_deficits();
        return !isset($deficits[$date]);
    }

    /**
     * Detect and record deficits after scheduling posts
     * This should be called after the main scheduling logic
     * 
     * @param string $target_date Date that was scheduled for (Y-m-d format)
     * @param int $posts_scheduled Number of posts actually scheduled
     * @param int $posts_per_day Target posts per day from settings
     * @since 1.9.6
     */
    private function detect_and_record_deficit($target_date, $posts_scheduled, $posts_per_day) {
        // Only record deficits for past or current dates
        $today = date('Y-m-d');
        if ($target_date > $today) {
            return; // Don't record deficits for future dates
        }

        $deficit = $posts_per_day - $posts_scheduled;
        
        if ($deficit > 0) {
            $this->record_deficit($target_date, $deficit);
        } else {
            // If we met or exceeded the quota, clear any existing deficit
            $this->clear_deficit($target_date);
        }
    }

    /**
     * Smart backfill algorithm - completes deficit days before scheduling future posts
     * 
     * @param array $available_posts Array of post objects available for scheduling
     * @param array $options Plugin settings
     * @return array Modified array with posts allocated for backfill and future scheduling
     * @since 1.9.6
     */
    private function apply_smart_backfill(&$available_posts, $options) {
        $deficits = $this->get_deficits();
        if (empty($deficits) || empty($available_posts)) {
            return array(
                'backfill_posts' => array(),
                'future_posts' => $available_posts
            );
        }

        $posts_per_day = intval($options['posts_per_day'] ?? 1);
        $backfill_posts = array();
        $remaining_posts = $available_posts;

        // Sort deficits by date (oldest first)
        ksort($deficits);

        foreach ($deficits as $deficit_date => $deficit_count) {
            if (empty($remaining_posts)) {
                break; // No more posts available
            }

            // Take posts for this deficit date
            $posts_for_date = array_splice($remaining_posts, 0, min($deficit_count, count($remaining_posts)));
            
            if (!empty($posts_for_date)) {
                $backfill_posts[$deficit_date] = $posts_for_date;
                
                // If we filled the deficit completely, clear it
                if (count($posts_for_date) >= $deficit_count) {
                    $this->clear_deficit($deficit_date);
                } else {
                    // Update the deficit with remaining count
                    $remaining_deficit = $deficit_count - count($posts_for_date);
                    $this->record_deficit($deficit_date, $remaining_deficit);
                }
            }
        }

        return array(
            'backfill_posts' => $backfill_posts,
            'future_posts' => $remaining_posts
        );
    }

    /**
     * Schedule posts for backfill (completing deficit days)
     * 
     * @param array $backfill_posts Array of posts grouped by date
     * @param array $options Plugin settings
     * @param bool $is_manual Whether this is a manual run
     * @return array Results of backfill scheduling
     * @since 1.9.6
     */
    private function schedule_backfill_posts($backfill_posts, $options, $is_manual = false) {
        $results = array();
        $total_scheduled = 0;

        foreach ($backfill_posts as $date => $posts) {
            $date_results = $this->schedule_posts_for_specific_date($posts, $date, $options, $is_manual);
            $results[$date] = $date_results;
            $total_scheduled += count($date_results['scheduled']);
        }

        if ($total_scheduled > 0) {
            error_log("KSM Post Scheduler: Backfilled {$total_scheduled} posts across " . count($backfill_posts) . " deficit days");
        }

        return array(
            'total_scheduled' => $total_scheduled,
            'dates' => $results
        );
    }

    /**
     * Schedule posts for a specific date (used for backfilling)
     * 
     * @param array $posts Array of post objects
     * @param string $target_date Date in Y-m-d format
     * @param array $options Plugin settings
     * @param bool $is_manual Whether this is a manual run
     * @return array Results of scheduling
     * @since 1.9.6
     */
    private function schedule_posts_for_specific_date($posts, $target_date, $options, $is_manual = false) {
        $scheduled = array();
        $errors = array();

        if (empty($posts)) {
            return array('scheduled' => $scheduled, 'errors' => $errors);
        }

        // Get time settings
        $start_time = $options['start_time'] ?? '09:00';
        $end_time = $options['end_time'] ?? '17:00';
        $min_interval = intval($options['min_interval'] ?? 60);

        // Generate random times for this date
        $times = $this->generate_random_times(count($posts), $start_time, $end_time, $min_interval);

        foreach ($posts as $index => $post) {
            if (!isset($times[$index])) {
                continue;
            }

            $time = $times[$index];
            $datetime_str = $target_date . ' ' . $time;
            
            // Convert to WordPress timezone
            $wp_timezone = wp_timezone();
            $datetime = new DateTime($datetime_str, $wp_timezone);
            $timestamp = $datetime->getTimestamp();

            // For backfill, we schedule immediately (don't check if time is in future)
            $mysql_date = $datetime->format('Y-m-d H:i:s');

            try {
                // Update post
                $result = wp_update_post(array(
                    'ID' => $post->ID,
                    'post_status' => 'future',
                    'post_date' => $mysql_date,
                    'post_date_gmt' => get_gmt_from_date($mysql_date)
                ));

                if (is_wp_error($result)) {
                    $errors[] = "Failed to schedule post {$post->ID}: " . $result->get_error_message();
                    continue;
                }

                // WordPress will handle publication automatically when the future post date arrives
                // No custom cron scheduling needed - this ensures compatibility with other plugins

                $scheduled[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'scheduled_time' => $datetime_str,
                    'timestamp' => $timestamp
                );

                error_log("KSM Post Scheduler: Backfilled post {$post->ID} for {$target_date} at {$time}");

            } catch (Exception $e) {
                $errors[] = "Exception scheduling post {$post->ID}: " . $e->getMessage();
                error_log("KSM Post Scheduler Error (backfill): " . $e->getMessage());
            }
        }

        return array('scheduled' => $scheduled, 'errors' => $errors);
    }
    

}

// Initialize the plugin
KSM_PS_Main::get_instance();