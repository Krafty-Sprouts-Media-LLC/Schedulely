<?php
/**
 * Settings and Admin Interface
 *
 * @package Schedulely
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Schedulely_Settings
 * 
 * Manages plugin settings and admin interface.
 */
class Schedulely_Settings {
    
    /**
     * Initialize settings page
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_schedulely_check_capacity', [$this, 'ajax_check_capacity']);
        add_action('admin_notices', [$this, 'show_welcome_notice']);
        add_action('wp_ajax_schedulely_dismiss_notice', [$this, 'ajax_dismiss_notice']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('Schedulely Settings', 'schedulely'),
            __('Schedulely', 'schedulely'),
            'manage_options',
            'schedulely',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin page
        if ('tools_page_schedulely' !== $hook) {
            return;
        }
        
        // SweetAlert2
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',
            [],
            '11.0.0',
            true
        );
        
        // Select2
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );
        
        // Flatpickr
        wp_enqueue_style(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
            [],
            '4.6.13'
        );
        wp_enqueue_script(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
            [],
            '4.6.13',
            true
        );
        
        // Plugin styles
        wp_enqueue_style(
            'schedulely-admin',
            SCHEDULELY_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SCHEDULELY_VERSION
        );
        
        // Plugin scripts
        wp_enqueue_script(
            'schedulely-admin',
            SCHEDULELY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'sweetalert2', 'select2', 'flatpickr'],
            SCHEDULELY_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('schedulely-admin', 'schedulely_admin', [
            'nonce' => wp_create_nonce('schedulely_admin'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'scheduled_posts_url' => admin_url('edit.php?post_status=future&post_type=post'),
            'strings' => [
                'confirm_schedule' => __('Schedule available posts now?', 'schedulely'),
                'scheduling' => __('Scheduling...', 'schedulely'),
                'schedule_now' => __('Schedule Now', 'schedulely'),
            ]
        ]);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('schedulely_settings', 'schedulely_post_status', [
            'sanitize_callback' => [$this, 'sanitize_post_status']
        ]);
        register_setting('schedulely_settings', 'schedulely_posts_per_day', [
            'sanitize_callback' => 'absint'
        ]);
        register_setting('schedulely_settings', 'schedulely_start_time', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('schedulely_settings', 'schedulely_end_time', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('schedulely_settings', 'schedulely_active_days', [
            'sanitize_callback' => [$this, 'sanitize_active_days']
        ]);
        register_setting('schedulely_settings', 'schedulely_min_interval', [
            'sanitize_callback' => 'absint'
        ]);
        register_setting('schedulely_settings', 'schedulely_randomize_authors', [
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('schedulely_settings', 'schedulely_excluded_authors', [
            'sanitize_callback' => [$this, 'sanitize_excluded_authors']
        ]);
        register_setting('schedulely_settings', 'schedulely_auto_schedule', [
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('schedulely_settings', 'schedulely_email_notifications', [
            'sanitize_callback' => [$this, 'sanitize_checkbox']
        ]);
        register_setting('schedulely_settings', 'schedulely_notification_users', [
            'sanitize_callback' => [$this, 'sanitize_notification_users']
        ]);
    }
    
    /**
     * Sanitize post status
     * 
     * @param string $value Input value
     * @return string Sanitized value
     */
    public function sanitize_post_status($value) {
        // Get all registered post statuses except publish, future, trash, auto-draft, and inherit
        $post_statuses = get_post_stati(['show_in_admin_status_list' => true], 'names');
        $excluded = ['publish', 'future', 'trash', 'auto-draft', 'inherit'];
        $allowed = array_diff($post_statuses, $excluded);
        
        return in_array($value, $allowed) ? $value : 'draft';
    }
    
    /**
     * Sanitize active days
     * 
     * @param array $value Input value
     * @return array Sanitized value
     */
    public function sanitize_active_days($value) {
        if (!is_array($value)) {
            return [1, 2, 3, 4, 5, 6, 0];
        }
        
        $sanitized = array_map('intval', $value);
        $valid = array_filter($sanitized, function($day) {
            return $day >= 0 && $day <= 6;
        });
        
        return !empty($valid) ? array_values($valid) : [1, 2, 3, 4, 5, 6, 0];
    }
    
    /**
     * Sanitize checkbox
     * 
     * @param mixed $value Input value
     * @return bool Sanitized value
     */
    public function sanitize_checkbox($value) {
        return !empty($value);
    }
    
    /**
     * Sanitize excluded authors
     * 
     * @param array $value Input value
     * @return array Sanitized value
     */
    public function sanitize_excluded_authors($value) {
        if (!is_array($value)) {
            return [];
        }
        
        return array_map('absint', $value);
    }
    
    /**
     * Sanitize notification users (array of user IDs)
     * 
     * @param array $value Input value
     * @return array Sanitized user IDs
     */
    public function sanitize_notification_users($value) {
        if (!is_array($value)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($value as $user_id) {
            $user_id = absint($user_id);
            // Verify user exists and has publish_posts capability
            $user = get_user_by('id', $user_id);
            if ($user && user_can($user, 'publish_posts')) {
                $sanitized[] = $user_id;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'schedulely'));
        }
        
        // Handle form submission
        if (isset($_POST['schedulely_save_settings'])) {
            check_admin_referer('schedulely_settings_save');
            
            // Update settings
            update_option('schedulely_post_status', $this->sanitize_post_status($_POST['schedulely_post_status'] ?? 'draft'));
            update_option('schedulely_posts_per_day', absint($_POST['schedulely_posts_per_day'] ?? 8));
            update_option('schedulely_start_time', sanitize_text_field($_POST['schedulely_start_time'] ?? '5:00 PM'));
            update_option('schedulely_end_time', sanitize_text_field($_POST['schedulely_end_time'] ?? '11:00 PM'));
            update_option('schedulely_active_days', $this->sanitize_active_days($_POST['schedulely_active_days'] ?? []));
            update_option('schedulely_min_interval', absint($_POST['schedulely_min_interval'] ?? 40));
            update_option('schedulely_randomize_authors', $this->sanitize_checkbox($_POST['schedulely_randomize_authors'] ?? false));
            update_option('schedulely_excluded_authors', $this->sanitize_excluded_authors($_POST['schedulely_excluded_authors'] ?? []));
            update_option('schedulely_auto_schedule', $this->sanitize_checkbox($_POST['schedulely_auto_schedule'] ?? false));
            update_option('schedulely_email_notifications', $this->sanitize_checkbox($_POST['schedulely_email_notifications'] ?? false));
            update_option('schedulely_notification_users', $this->sanitize_notification_users($_POST['schedulely_notification_users'] ?? []));
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'schedulely') . '</p></div>';
        }
        
        $stats = $this->get_statistics();
        
        ?>
        <div class="wrap schedulely-wrap">
            <h1>
                <?php _e('Schedulely Settings', 'schedulely'); ?>
                <span class="schedulely-version">v<?php echo esc_html(SCHEDULELY_VERSION); ?></span>
            </h1>
            
            <?php $this->render_dashboard($stats); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('schedulely_settings_save'); ?>
                
                <!-- Scheduling Settings -->
                <div class="schedulely-card">
                    <h2 class="schedulely-card-header"><?php _e('Scheduling Settings', 'schedulely'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="schedulely_post_status"><?php _e('Post Status to Monitor', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <select name="schedulely_post_status" id="schedulely_post_status">
                                    <?php
                                    // Get all post statuses except the ones we don't want to schedule
                                    $post_statuses = get_post_stati(['show_in_admin_status_list' => true], 'objects');
                                    $excluded = ['publish', 'future', 'trash', 'auto-draft', 'inherit'];
                                    $current_status = get_option('schedulely_post_status', 'draft');
                                    
                                    foreach ($post_statuses as $status_obj) {
                                        if (in_array($status_obj->name, $excluded)) {
                                            continue;
                                        }
                                        
                                        $selected = selected($current_status, $status_obj->name, false);
                                        $label = $status_obj->label;
                                        echo '<option value="' . esc_attr($status_obj->name) . '" ' . $selected . '>';
                                        echo esc_html($label);
                                        echo '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Posts with this status will be scheduled for publication. Supports all registered post statuses.', 'schedulely'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="schedulely_posts_per_day"><?php _e('Posts Per Day', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="schedulely_posts_per_day" id="schedulely_posts_per_day" 
                                       value="<?php echo esc_attr(get_option('schedulely_posts_per_day', 8)); ?>" 
                                       min="1" max="100" class="small-text">
                                <p class="description"><?php _e('Number of posts to schedule per day.', 'schedulely'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php _e('Publishing Time Window', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="schedulely_start_time" id="schedulely_start_time" 
                                       value="<?php echo esc_attr(get_option('schedulely_start_time', '5:00 PM')); ?>" 
                                       class="regular-text schedulely-timepicker">
                                <span><?php _e('to', 'schedulely'); ?></span>
                                <input type="text" name="schedulely_end_time" id="schedulely_end_time" 
                                       value="<?php echo esc_attr(get_option('schedulely_end_time', '11:00 PM')); ?>" 
                                       class="regular-text schedulely-timepicker">
                                <p class="description"><?php _e('Posts will be randomly scheduled within this time window each day.', 'schedulely'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="schedulely_min_interval"><?php _e('Minimum Interval Between Posts', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="schedulely_min_interval" id="schedulely_min_interval" 
                                       value="<?php echo esc_attr(get_option('schedulely_min_interval', 40)); ?>" 
                                       min="1" max="1440" class="small-text">
                                <span><?php _e('minutes', 'schedulely'); ?></span>
                                <p class="description"><?php _e('Minimum time between scheduled posts to avoid publishing too close together.', 'schedulely'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php _e('Active Days', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <?php
                                $active_days = get_option('schedulely_active_days', [1, 2, 3, 4, 5, 6, 0]);
                                $days = [
                                    1 => __('Mon', 'schedulely'),
                                    2 => __('Tue', 'schedulely'),
                                    3 => __('Wed', 'schedulely'),
                                    4 => __('Thu', 'schedulely'),
                                    5 => __('Fri', 'schedulely'),
                                    6 => __('Sat', 'schedulely'),
                                    0 => __('Sun', 'schedulely')
                                ];
                                
                                foreach ($days as $day_num => $day_name) {
                                    $checked = in_array($day_num, $active_days) ? 'checked' : '';
                                    echo '<label style="margin-right: 15px;">';
                                    echo '<input type="checkbox" name="schedulely_active_days[]" value="' . esc_attr($day_num) . '" ' . $checked . '> ';
                                    echo esc_html($day_name);
                                    echo '</label>';
                                }
                                ?>
                                <p class="description"><?php _e('Posts will only be scheduled on these days.', 'schedulely'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Capacity Calculator -->
                    <div class="schedulely-capacity-section">
                        <h3 class="schedulely-capacity-header"><?php _e('Capacity Check', 'schedulely'); ?></h3>
                        
                        <div id="schedulely-capacity-notice" class="schedulely-capacity-notice">
                            <div class="schedulely-capacity-loading">
                                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                                <?php _e('Checking capacity...', 'schedulely'); ?>
                            </div>
                        </div>
                        
                        <!-- Random Scheduling Explanation -->
                        <div class="notice notice-info" style="margin: 15px 0; padding: 12px;">
                            <p style="margin: 0 0 8px 0;"><strong>ℹ️ <?php _e('How Random Scheduling Works', 'schedulely'); ?></strong></p>
                            <p style="margin: 0; font-size: 13px;">
                                <?php _e('Posts are scheduled at <strong>random times</strong> within your time window for a natural appearance. The minimum interval (e.g., 30 minutes) is the <strong>shortest gap</strong> allowed between posts — actual gaps may be larger (45 min, 60 min, or more) due to random placement. This means:', 'schedulely'); ?>
                            </p>
                            <ul style="margin: 8px 0 0 20px; font-size: 13px;">
                                <li><?php _e('✅ Posts are <strong>at least</strong> X minutes apart (never closer)', 'schedulely'); ?></li>
                                <li><?php _e('✅ Gaps between posts <strong>vary randomly</strong> (some 30 min, some 60+ min)', 'schedulely'); ?></li>
                                <li><?php _e('✅ There may be <strong>unused time</strong> at the end of your window', 'schedulely'); ?></li>
                                <li><?php _e('✅ Random scheduling achieves ~70% efficiency vs perfect sequential spacing', 'schedulely'); ?></li>
                            </ul>
                            <p style="margin: 8px 0 0 0; font-size: 13px;">
                                <em><?php _e('Example: 5:14 PM → 5:47 PM (33 min) → 6:23 PM (36 min) → 7:15 PM (52 min) → 8:42 PM (87 min gap!)', 'schedulely'); ?></em>
                            </p>
                        </div>
                        
                        <div id="schedulely-capacity-suggestions" class="schedulely-capacity-suggestions" style="display: none;">
                            <h3><?php _e('💡 Suggestions to Fix', 'schedulely'); ?></h3>
                            <div id="schedulely-suggestions-list"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Author Assignment -->
                <div class="schedulely-card">
                    <h2 class="schedulely-card-header"><?php _e('Author Assignment', 'schedulely'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="schedulely_randomize_authors"><?php _e('Randomize Post Authors', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="schedulely_randomize_authors" id="schedulely_randomize_authors" 
                                           value="1" <?php checked(get_option('schedulely_randomize_authors', false)); ?>>
                                    <?php _e('Randomly assign authors to scheduled posts', 'schedulely'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="schedulely_excluded_authors"><?php _e('Excluded Authors', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <select name="schedulely_excluded_authors[]" id="schedulely_excluded_authors" 
                                        class="schedulely-author-select" multiple="multiple" style="width: 50%;">
                                    <?php
                                    $users = get_users(['capability' => 'edit_posts']);
                                    $excluded = get_option('schedulely_excluded_authors', []);
                                    
                                    foreach ($users as $user) {
                                        $selected = in_array($user->ID, $excluded) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>';
                                        echo esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')';
                                        echo '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('These authors will not be assigned to posts.', 'schedulely'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Automation Settings -->
                <div class="schedulely-card">
                    <h2 class="schedulely-card-header"><?php _e('Automation Settings', 'schedulely'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="schedulely_auto_schedule"><?php _e('Automatic Scheduling', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="schedulely_auto_schedule" id="schedulely_auto_schedule" 
                                           value="1" <?php checked(get_option('schedulely_auto_schedule', true)); ?>>
                                    <?php _e('Enable automatic scheduling via WordPress Cron (twice daily)', 'schedulely'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="schedulely_email_notifications"><?php _e('Email Notifications', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="schedulely_email_notifications" id="schedulely_email_notifications" 
                                           value="1" <?php checked(get_option('schedulely_email_notifications', true)); ?>>
                                    <?php _e('Send email notifications after scheduling runs', 'schedulely'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="schedulely_notification_users"><?php _e('Notification Recipients', 'schedulely'); ?></label>
                            </th>
                            <td>
                                <select name="schedulely_notification_users[]" id="schedulely_notification_users" 
                                        class="schedulely-notification-select" multiple="multiple" style="width: 50%;">
                                    <?php
                                    // Get users with publish_posts capability (excludes contributors)
                                    $users = get_users(['capability' => 'publish_posts']);
                                    $selected_users = get_option('schedulely_notification_users', []);
                                    
                                    // If no users selected, default to current admin user
                                    if (empty($selected_users)) {
                                        $selected_users = [get_current_user_id()];
                                    }
                                    
                                    foreach ($users as $user) {
                                        $selected = in_array($user->ID, $selected_users) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>';
                                        echo esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')';
                                        echo '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    <?php _e('Select users who will receive scheduling notifications. Only users with publish capability are shown (Authors, Editors, Administrators).', 'schedulely'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="schedulely_save_settings" class="button button-primary" 
                           value="<?php _e('Save Changes', 'schedulely'); ?>">
                </p>
            </form>
            
            <?php $this->render_upcoming_posts(); ?>
            <?php $this->render_last_date_status(); ?>
            <?php $this->render_footer(); ?>
        </div>
        <?php
    }
    
    /**
     * Render scheduling overview dashboard
     * 
     * @param array $stats Statistics data
     */
    private function render_dashboard($stats) {
        ?>
        <div class="schedulely-card schedulely-dashboard">
            <h2 class="schedulely-card-header"><?php _e('Scheduling Overview', 'schedulely'); ?></h2>
            
            <div class="schedulely-stats-grid">
                <div class="schedulely-stat-item">
                    <div class="schedulely-stat-label"><?php _e('Available Posts', 'schedulely'); ?></div>
                    <div class="schedulely-stat-value"><?php echo esc_html($stats['available_posts']); ?></div>
                </div>
                
                <div class="schedulely-stat-item">
                    <div class="schedulely-stat-label"><?php _e('Next Scheduled', 'schedulely'); ?></div>
                    <div class="schedulely-stat-value"><?php echo esc_html($stats['next_scheduled']); ?></div>
                </div>
                
                <div class="schedulely-stat-item">
                    <div class="schedulely-stat-label"><?php _e('Last Scheduled Date', 'schedulely'); ?></div>
                    <div class="schedulely-stat-value"><?php echo esc_html($stats['last_date_status']); ?></div>
                </div>
                
                <div class="schedulely-stat-item">
                    <div class="schedulely-stat-label"><?php _e('Last Run', 'schedulely'); ?></div>
                    <div class="schedulely-stat-value"><?php echo esc_html($stats['last_run']); ?></div>
                </div>
            </div>
            
            <div class="schedulely-actions">
                <button type="button" id="schedulely-schedule-now" class="button button-primary">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e('Schedule Now', 'schedulely'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('edit.php?post_status=future&post_type=post')); ?>" 
                   class="button">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('View All Scheduled Posts', 'schedulely'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render upcoming scheduled posts
     */
    private function render_upcoming_posts() {
        $args = [
            'post_type' => 'post',
            'post_status' => 'future',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'ASC'
        ];
        
        $scheduled_posts = get_posts($args);
        
        ?>
        <div class="schedulely-card">
            <h2 class="schedulely-card-header"><?php _e('Upcoming Scheduled Posts', 'schedulely'); ?> (<?php echo count($scheduled_posts); ?>)</h2>
            
            <?php if (empty($scheduled_posts)) : ?>
                <p><?php _e('No posts currently scheduled.', 'schedulely'); ?></p>
            <?php else : ?>
                <ul class="schedulely-post-list">
                    <?php foreach ($scheduled_posts as $post) : ?>
                        <li class="schedulely-post-item">
                            <span class="schedulely-post-time">
                                <?php echo esc_html(date('M j, g:i A', strtotime($post->post_date))); ?>
                            </span>
                            <span class="schedulely-post-title">
                                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render last scheduled date status
     */
    private function render_last_date_status() {
        $scheduler = new Schedulely_Scheduler();
        $last_date = $scheduler->get_last_scheduled_date();
        $quota = get_option('schedulely_posts_per_day', 8);
        
        ?>
        <div class="schedulely-card">
            <h2 class="schedulely-card-header"><?php _e('Last Scheduled Date', 'schedulely'); ?></h2>
            
            <?php if ($last_date) : ?>
                <?php
                    $posts_count = $scheduler->count_posts_on_date($last_date);
                    $is_complete = $posts_count >= $quota;
                    $today = date('Y-m-d', current_time('timestamp'));
                    $is_past = strtotime($last_date) < strtotime($today);
                ?>
                <div class="schedulely-last-date-info">
                    <p>
                        <strong><?php _e('Date:', 'schedulely'); ?></strong> 
                        <?php echo esc_html(date('l, M j, Y', strtotime($last_date))); ?>
                        <?php if ($is_past) : ?>
                            <span style="color: #999;">(<?php _e('Past', 'schedulely'); ?>)</span>
                        <?php endif; ?>
                    </p>
                    <p>
                        <strong><?php _e('Posts Scheduled:', 'schedulely'); ?></strong>
                        <?php echo esc_html($posts_count); ?>/<?php echo esc_html($quota); ?>
                        <?php if ($is_complete) : ?>
                            <span style="color: #00a32a;">✓ <?php _e('Complete', 'schedulely'); ?></span>
                        <?php else : ?>
                            <span style="color: #dba617;">⚠ <?php echo sprintf(__('Needs %d more', 'schedulely'), $quota - $posts_count); ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if (!$is_complete && !$is_past) : ?>
                        <p class="description">
                            <?php _e('The next scheduling run will complete this date before adding new dates.', 'schedulely'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <p><?php _e('No posts have been scheduled yet. Click "Schedule Now" to get started!', 'schedulely'); ?> 🚀</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render footer credits
     */
    private function render_footer() {
        ?>
        <div class="schedulely-footer">
            <?php
            printf(
                /* translators: %s: Link to Krafty Sprouts Media */
                __('Made with %s by %s', 'schedulely'),
                '<span style="color: #e25555;">❤️</span>',
                '<a href="https://kraftysprouts.com" target="_blank" rel="noopener noreferrer">Krafty Sprouts Media</a>'
            );
            ?>
        </div>
        <?php
    }
    
    /**
     * Show welcome notice on activation
     */
    public function show_welcome_notice() {
        // Only show if not dismissed and not on settings page
        if (get_option('schedulely_welcome_dismissed', false)) {
            return;
        }
        
        // Don't show on Schedulely settings page
        $screen = get_current_screen();
        if ($screen && $screen->id === 'tools_page_schedulely') {
            return;
        }
        
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible" id="schedulely-welcome-notice">
            <h3><?php _e('🚀 Schedulely Activated!', 'schedulely'); ?></h3>
            <p>
                <?php _e('Thank you for installing Schedulely! To get started, configure your scheduling settings.', 'schedulely'); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('tools.php?page=schedulely'); ?>" class="button button-primary">
                    <?php _e('Go to Settings', 'schedulely'); ?>
                </a>
                <button type="button" class="button schedulely-dismiss-notice" data-notice="welcome">
                    <?php _e('Dismiss', 'schedulely'); ?>
                </button>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Handle dismiss button
            $('.schedulely-dismiss-notice').on('click', function() {
                $('#schedulely-welcome-notice').fadeOut();
                $.post(ajaxurl, {
                    action: 'schedulely_dismiss_notice',
                    nonce: '<?php echo wp_create_nonce('schedulely_dismiss_notice'); ?>'
                });
            });
            
            // Handle WordPress's built-in dismiss
            $('#schedulely-welcome-notice').on('click', '.notice-dismiss', function() {
                $.post(ajaxurl, {
                    action: 'schedulely_dismiss_notice',
                    nonce: '<?php echo wp_create_nonce('schedulely_dismiss_notice'); ?>'
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for dismissing notice
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer('schedulely_dismiss_notice', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        update_option('schedulely_welcome_dismissed', true);
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for capacity check
     */
    public function ajax_check_capacity() {
        check_ajax_referer('schedulely_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'schedulely')]);
        }
        
        // Get parameters from request
        $start_time = sanitize_text_field($_POST['start_time'] ?? get_option('schedulely_start_time', '5:00 PM'));
        $end_time = sanitize_text_field($_POST['end_time'] ?? get_option('schedulely_end_time', '11:00 PM'));
        $min_interval = absint($_POST['min_interval'] ?? get_option('schedulely_min_interval', 40));
        $posts_per_day = absint($_POST['posts_per_day'] ?? get_option('schedulely_posts_per_day', 8));
        
        // Calculate capacity
        $scheduler = new Schedulely_Scheduler();
        $capacity_data = $scheduler->calculate_capacity($start_time, $end_time, $min_interval, $posts_per_day);
        
        wp_send_json_success($capacity_data);
    }
    
    /**
     * Get current statistics for dashboard
     * 
     * @return array Statistics data
     */
    private function get_statistics() {
        $status = get_option('schedulely_post_status', 'draft');
        
        // Available posts
        $available_posts = count(get_posts([
            'post_type' => 'post',
            'post_status' => $status,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]));
        
        // Next scheduled
        $next_post = get_posts([
            'post_type' => 'post',
            'post_status' => 'future',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'ASC'
        ]);
        
        $next_scheduled = !empty($next_post) 
            ? date('M j, Y - g:i A', strtotime($next_post[0]->post_date))
            : __('None', 'schedulely');
        
        // Last scheduled date status
        $scheduler = new Schedulely_Scheduler();
        $last_date = $scheduler->get_last_scheduled_date();
        $last_date_status = __('None', 'schedulely');
        
        if ($last_date) {
            $quota = get_option('schedulely_posts_per_day', 8);
            $posts_count = $scheduler->count_posts_on_date($last_date);
            $is_complete = $posts_count >= $quota;
            
            $last_date_status = date('M j, Y', strtotime($last_date)) . ' - ';
            $last_date_status .= $is_complete 
                ? __('Complete', 'schedulely') . ' ✓'
                : $posts_count . '/' . $quota;
        }
        
        // Last run
        $last_run = get_option('schedulely_last_run', 0);
        $last_run_text = $last_run > 0 
            ? date('M j, Y - g:i A', $last_run)
            : __('Never', 'schedulely');
        
        return [
            'available_posts' => $available_posts,
            'next_scheduled' => $next_scheduled,
            'last_date_status' => $last_date_status,
            'last_run' => $last_run_text
        ];
    }
}

