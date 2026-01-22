<?php
/**
 * Filename: class-settings.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 06/10/2025
 * Last Modified: 19/01/2026
 * Description: Settings and Admin Interface
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
class Schedulely_Settings
{

    /**
     * Initialize settings page
     */
    public function init()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_schedulely_check_capacity', [$this, 'ajax_check_capacity']);
        add_action('admin_notices', [$this, 'show_welcome_notice']);
        add_action('wp_ajax_schedulely_dismiss_notice', [$this, 'ajax_dismiss_notice']);
        add_action('wp_ajax_schedulely_toggle_auto_schedule', [$this, 'ajax_toggle_auto_schedule']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
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
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on plugin page
        if ('tools_page_schedulely' !== $hook) {
            return;
        }

        // Google Fonts (Lato)
        wp_enqueue_style(
            'google-fonts-lato',
            'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap',
            [],
            null
        );

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
            'scheduled_posts_url' => admin_url('edit.php?post_status=future'),
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
    public function register_settings()
    {
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
        register_setting('schedulely_settings', 'schedulely_preserved_authors', [
            'sanitize_callback' => [$this, 'sanitize_preserved_authors']
        ]);
        register_setting('schedulely_settings', 'schedulely_post_types', [
            'sanitize_callback' => [$this, 'sanitize_post_types']
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
    public function sanitize_post_status($value)
    {
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
    public function sanitize_active_days($value)
    {
        if (!is_array($value)) {
            return [1, 2, 3, 4, 5, 6, 0];
        }

        $sanitized = array_map('intval', $value);
        $valid = array_filter($sanitized, function ($day) {
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
    public function sanitize_checkbox($value)
    {
        return !empty($value);
    }

    /**
     * Sanitize excluded authors
     * 
     * @param array $value Input value
     * @return array Sanitized value
     */
    public function sanitize_excluded_authors($value)
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map('absint', $value);
    }

    /**
     * Sanitize preserved authors
     * 
     * @param array $value Input value
     * @return array Sanitized value
     * @since 1.2.7
     */
    public function sanitize_preserved_authors($value)
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map('absint', $value);
    }

    /**
     * Sanitize post types
     * Since 1.3.3
     * @param array $value Input value
     * @return array Sanitized value
     */
    public function sanitize_post_types($value)
    {
        if (!is_array($value)) {
            return ['post']; // Default to post for backward compatibility
        }

        // Get all registered post types
        $registered_post_types = get_post_types(['public' => true], 'names');
        
        // Filter to only include valid, registered post types
        $sanitized = array_filter($value, function ($post_type) use ($registered_post_types) {
            return post_type_exists($post_type) && in_array($post_type, $registered_post_types, true);
        });

        // If no valid post types, default to 'post'
        return !empty($sanitized) ? array_values($sanitized) : ['post'];
    }

    /**
     * Sanitize notification users (array of user IDs)
     * 
     * @param array $value Input value
     * @return array Sanitized user IDs
     */
    public function sanitize_notification_users($value)
    {
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
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'schedulely'));
        }

        // Handle form submission
        if (isset($_POST['schedulely_save_settings'])) {
            check_admin_referer('schedulely_settings_save');

            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'schedulely'));
            }

            // Update settings
            update_option('schedulely_post_status', $this->sanitize_post_status($_POST['schedulely_post_status'] ?? 'draft'));
            update_option('schedulely_posts_per_day', absint($_POST['schedulely_posts_per_day'] ?? 8));
            update_option('schedulely_start_time', sanitize_text_field($_POST['schedulely_start_time'] ?? '5:00 PM'));
            update_option('schedulely_end_time', sanitize_text_field($_POST['schedulely_end_time'] ?? '11:00 PM'));
            update_option('schedulely_active_days', $this->sanitize_active_days($_POST['schedulely_active_days'] ?? []));
            update_option('schedulely_min_interval', absint($_POST['schedulely_min_interval'] ?? 40));
            update_option('schedulely_randomize_authors', $this->sanitize_checkbox($_POST['schedulely_randomize_authors'] ?? false));
            update_option('schedulely_excluded_authors', $this->sanitize_excluded_authors($_POST['schedulely_excluded_authors'] ?? []));
            update_option('schedulely_preserved_authors', $this->sanitize_preserved_authors($_POST['schedulely_preserved_authors'] ?? []));
            update_option('schedulely_post_types', $this->sanitize_post_types($_POST['schedulely_post_types'] ?? ['post']));
            update_option('schedulely_auto_schedule', $this->sanitize_checkbox($_POST['schedulely_auto_schedule'] ?? false));
            update_option('schedulely_email_notifications', $this->sanitize_checkbox($_POST['schedulely_email_notifications'] ?? false));
            update_option('schedulely_notification_users', $this->sanitize_notification_users($_POST['schedulely_notification_users'] ?? []));

            add_settings_error(
                'schedulely_messages',
                'schedulely_message',
                __('Settings saved successfully!', 'schedulely'),
                'success'
            );
        }

        $stats = $this->get_statistics();

        ?>
                <div class="wrap">
                    <?php settings_errors('schedulely_messages'); ?>
                    <div class="schedulely-wrap">
                    <div class="dash-header">
                        <div>
                            <h1 class="dash-title">
                                <?php _e('Schedulely', 'schedulely'); ?>
                            </h1>
                            <div class="dash-subtitle"><?php _e('Intelligent Post Scheduling for WordPress', 'schedulely'); ?></div>
                        </div>
                        <div class="action-bar">
                            <a href="https://wordpress.org/support/plugin/schedulely/" target="_blank" class="btn btn-secondary">
                                <span class="dashicons dashicons-external"></span> <?php _e('Report Issue', 'schedulely'); ?>
                            </a>
                            <button type="button" id="schedulely-schedule-now" class="btn btn-primary">
                                <span class="dashicons dashicons-calendar-alt"></span> <?php _e('Run Schedule Now', 'schedulely'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Insight Panel: How Logic Works -->
                    <div class="insight-panel">
                        <button class="close-insight">✕</button>
                        <div class="insight-title">
                            <span class="dashicons dashicons-info insight-icon"></span>
                            <?php _e('How Random Scheduling Works', 'schedulely'); ?>
                        </div>
                        <div class="insight-content">
                            <?php _e('Posts are scheduled at <strong>random times</strong> within your time window for a natural appearance.', 'schedulely'); ?>
                            <ul class="insight-list">
                                <li><?php _e('✅ Posts are <strong>at least X minutes</strong> apart (never closer)', 'schedulely'); ?></li>
                                <li><?php _e('✅ Gaps between posts vary randomly (some 30 min, some 60+ min)', 'schedulely'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <form method="post" action="">
                        <?php wp_nonce_field('schedulely_settings_save'); ?>
                
                        <div class="dashboard-grid">
                    
                            <!-- Stat 1: Drafts Available -->
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <span class="dashicons dashicons-format-aside"></span>
                                </div>
                                <div class="stat-value"><?php echo esc_html($stats['available_posts']); ?></div>
                                <div class="stat-label"><?php _e('Drafts Available', 'schedulely'); ?></div>
                                <div class="stat-trend">
                                     <?php echo sprintf(__('%s currently in pool', 'schedulely'), $stats['available_posts']); ?>
                                </div>
                            </div>

                            <!-- Stat 2: Scheduled Status -->
                            <?php
                            $scheduler = new Schedulely_Scheduler();
                            $last_date = $scheduler->get_last_scheduled_date();
                            $quota = get_option('schedulely_posts_per_day', 8);
                            $posts_count = $last_date ? $scheduler->count_posts_on_date($last_date) : 0;
                            $is_complete = $posts_count >= $quota;
                            ?>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <span class="dashicons dashicons-calendar"></span>
                                </div>
                                <div class="stat-value"><?php echo $posts_count; ?></div>
                                <div class="stat-label"><?php _e('Last Date Scheduled', 'schedulely'); ?></div>
                                <div class="stat-trend <?php echo !$is_complete ? 'down' : ''; ?>">
                                    Target: <?php echo $quota; ?>/day <?php echo !$is_complete ? '(Incomplete)' : ''; ?>
                                </div>
                            </div>

                            <!-- Stat 3: Deficit / Status -->
                            <div class="stat-card">
                                <div class="stat-icon" style="<?php echo !$is_complete ? 'color: #d63638; background: #fcf0f1;' : ''; ?>">
                                     <span class="dashicons dashicons-chart-bar"></span>
                                </div>
                                <div class="stat-value" style="<?php echo !$is_complete ? 'color: #d63638;' : ''; ?>">
                                     <?php echo $last_date ? date('M j', strtotime($last_date)) : 'None'; ?>
                                </div>
                                <div class="stat-label"><?php _e('Last Scheduled Date', 'schedulely'); ?></div>
                                <div class="stat-trend <?php echo !$is_complete ? 'down' : ''; ?>">
                                     <?php echo !$is_complete && $last_date ? __('Action Needed', 'schedulely') : ($last_date ? __('Scheduled', 'schedulely') : __('No Data', 'schedulely')); ?>
                                </div>
                            </div>

                            <!-- Stat 4: System Health -->
                            <?php $auto_schedule = get_option('schedulely_auto_schedule', true); ?>
                            <div class="stat-card">
                                <div class="stat-icon" style="<?php echo $auto_schedule ? 'color: #00a32a; background: #edfaef;' : 'color:#d63638; background: #fcf0f1;'; ?>">
                                    <span class="dashicons dashicons-heart"></span>
                                </div>
                                <div class="stat-value" style="<?php echo $auto_schedule ? 'color: #00a32a;' : 'color:#d63638;'; ?>">
                                    <?php echo $auto_schedule ? 'Active' : 'Paused'; ?>
                                </div>
                                <div class="stat-label"><?php _e('System Health', 'schedulely'); ?></div>
                                <div class="stat-trend">
                                     <?php echo $auto_schedule ? __('Cron Running', 'schedulely') : __('Auto-Schedule Off', 'schedulely'); ?>
                                </div>
                            </div>

                            <!-- Main Config -->
                            <div class="config-card">
                                <div class="card-header">
                                    <h3 class="card-title"><?php _e('Configuration & Constraints', 'schedulely'); ?></h3>
                                    <button type="submit" name="schedulely_save_settings" class="btn btn-primary" style="padding: 4px 12px; font-size: 12px;">
                                        <?php _e('Save Changes', 'schedulely'); ?>
                                    </button>
                                </div>
                                <div class="timeline-view">
                            
                                    <!-- Capacity Check Alert (Populated by JS) -->
                                    <div id="schedulely-capacity-notice">
                                        <div class="schedulely-capacity-loading">
                                            <span class="spinner is-active" style="float: none; margin: 0;"></span>
                                            <?php _e('Checking capacity...', 'schedulely'); ?>
                                        </div>
                                    </div>
                            
                                    <!-- Suggestions (Populated by JS) -->
                                    <div id="schedulely-capacity-suggestions" class="suggestion-group" style="display: none;">
                                        <div id="schedulely-suggestions-list"></div>
                                    </div>

                                    <hr style="border: 0; border-top: 1px solid #f0f0f1; margin: 25px 0;">

                                    <!-- Form Grid -->
                                    <div class="form-grid">
                                
                                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                            <div class="form-group" style="flex: 1; min-width: 250px;">
                                                <label class="form-label"><?php _e('Post Status to Monitor', 'schedulely'); ?></label>
                                                <select name="schedulely_post_status" id="schedulely_post_status" style="width: 100%;">
                                                    <?php
                                                    $post_statuses = get_post_stati(['show_in_admin_status_list' => true], 'objects');
                                                    $excluded = ['publish', 'future', 'trash', 'auto-draft', 'inherit'];
                                                    $current_status = get_option('schedulely_post_status', 'draft');
                                                    foreach ($post_statuses as $status_obj) {
                                                        if (in_array($status_obj->name, $excluded))
                                                            continue;
                                                        echo '<option value="' . esc_attr($status_obj->name) . '" ' . selected($current_status, $status_obj->name, false) . '>' . esc_html($status_obj->label) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <p class="description" style="font-size: 12px;"><?php _e('Source status for scheduling.', 'schedulely'); ?></p>
                                            </div>

                                            <div class="form-group" style="flex: 1; min-width: 250px;">
                                                <label class="form-label"><?php _e('Post Types to Schedule', 'schedulely'); ?></label>
                                                <select name="schedulely_post_types[]" id="schedulely_post_types" class="schedulely-post-type-select" multiple="multiple" style="width: 100%;">
                                                    <?php
                                                    $registered_post_types = get_post_types(['public' => true], 'objects');
                                                    $current_post_types = get_option('schedulely_post_types', ['post']);
                                                    foreach ($registered_post_types as $post_type_obj) {
                                                        $selected = in_array($post_type_obj->name, $current_post_types, true) ? 'selected' : '';
                                                        echo '<option value="' . esc_attr($post_type_obj->name) . '" ' . $selected . '>' . esc_html($post_type_obj->label) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <p class="description" style="font-size: 12px;"><?php _e('Select which post types to include in scheduling.', 'schedulely'); ?></p>
                                            </div>

                                            <div class="form-group" style="flex: 1; min-width: 250px;">
                                                <label class="form-label"><?php _e('Posts Per Day', 'schedulely'); ?></label>
                                                <input type="number" name="schedulely_posts_per_day" id="schedulely_posts_per_day" 
                                                       value="<?php echo esc_attr(get_option('schedulely_posts_per_day', 8)); ?>" 
                                                       min="1" max="100" style="width: 100%;">
                                            </div>

                                            <div class="form-group" style="flex: 1; min-width: 250px;">
                                                <label class="form-label"><?php _e('Min Interval (Minutes)', 'schedulely'); ?></label>
                                                <input type="number" name="schedulely_min_interval" id="schedulely_min_interval" 
                                                       value="<?php echo esc_attr(get_option('schedulely_min_interval', 40)); ?>" 
                                                       min="1" max="1440" style="width: 100%;">
                                            </div>
                                        </div>
                                
                                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                             <div class="form-group" style="flex: 2; min-width: 300px;">
                                                <label class="form-label"><?php _e('Time Window', 'schedulely'); ?></label>
                                                <div style="display: flex; gap: 10px; align-items: center;">
                                                    <input type="text" name="schedulely_start_time" id="schedulely_start_time" 
                                                           value="<?php echo esc_attr(get_option('schedulely_start_time', '5:00 PM')); ?>" 
                                                           class="regular-text schedulely-timepicker" style="width: 120px;">
                                                    <span style="color: #646970;">→</span>
                                                    <input type="text" name="schedulely_end_time" id="schedulely_end_time" 
                                                           value="<?php echo esc_attr(get_option('schedulely_end_time', '11:00 PM')); ?>" 
                                                           class="regular-text schedulely-timepicker" style="width: 120px;">
                                                </div>
                                             </div>
                                     
                                             <div class="form-group" style="flex: 1; min-width: 300px;">
                                                <label class="form-label"><?php _e('Active Days', 'schedulely'); ?></label>
                                                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                                    <?php
                                                    $active_days = get_option('schedulely_active_days', [1, 2, 3, 4, 5, 6, 0]);
                                                    $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
                                                    foreach ($days as $day_num => $day_name) {
                                                        $checked = in_array($day_num, $active_days) ? 'checked' : '';
                                                        echo '<label class="day-checkbox"><input type="checkbox" name="schedulely_active_days[]" value="' . $day_num . '" ' . $checked . '> ' . $day_name . '</label>';
                                                    }
                                                    ?>
                                                </div>
                                             </div>
                                        </div>
                                
                                        <div class="form-group">
                                            <label class="form-label"><?php _e('Author Assignment', 'schedulely'); ?></label>
                                            <label style="display: block; margin-bottom: 15px;">
                                                <input type="checkbox" name="schedulely_randomize_authors" id="schedulely_randomize_authors" 
                                                       value="1" <?php checked(get_option('schedulely_randomize_authors', false)); ?>>
                                                <?php _e('Randomly assign authors to scheduled posts', 'schedulely'); ?>
                                            </label>
                                    
                                            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                                <div style="flex: 1; min-width: 300px;">
                                                    <label class="form-label" style="font-size: 12px;"><?php _e('Excluded Authors', 'schedulely'); ?></label>
                                                    <select name="schedulely_excluded_authors[]" id="schedulely_excluded_authors" class="schedulely-author-select" multiple="multiple" style="width: 100%;">
                                                        <?php
                                                        $users = get_users(['capability' => 'edit_posts']);
                                                        $excluded = get_option('schedulely_excluded_authors', []);
                                                        foreach ($users as $user) {
                                                            $selected = in_array($user->ID, $excluded) ? 'selected' : '';
                                                            echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>' . esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div style="flex: 1; min-width: 300px;">
                                                    <label class="form-label" style="font-size: 12px;"><?php _e('Preserved Authors', 'schedulely'); ?></label>
                                                    <select name="schedulely_preserved_authors[]" id="schedulely_preserved_authors" class="schedulely-author-select" multiple="multiple" style="width: 100%;">
                                                        <?php
                                                        $preserved = get_option('schedulely_preserved_authors', []);
                                                        foreach ($users as $user) {
                                                            $selected = in_array($user->ID, $preserved) ? 'selected' : '';
                                                            echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>' . esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label"><?php _e('Notification Recipients', 'schedulely'); ?></label>
                                            <select name="schedulely_notification_users[]" id="schedulely_notification_users" class="schedulely-notification-select" multiple="multiple" style="width: 100%;">
                                                <?php
                                                $notify_users = get_users(['capability' => 'publish_posts']);
                                                $selected_users = get_option('schedulely_notification_users', []);
                                                if (empty($selected_users))
                                                    $selected_users = [get_current_user_id()];
                                                foreach ($notify_users as $user) {
                                                    $selected = in_array($user->ID, $selected_users) ? 'selected' : '';
                                                    echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- Activity Feed / Side Panel -->
                            <div class="activity-card">
                                <div class="card-header">
                                    <h3 class="card-title"><?php _e('Upcoming Posts', 'schedulely'); ?></h3>
                                    <?php
                                    $post_types = get_option('schedulely_post_types', ['post']);
                                    $post_type_param = count($post_types) === 1 ? $post_types[0] : implode(',', $post_types);
                                    ?>
                                    <a href="<?php echo esc_url(admin_url('edit.php?post_status=future&post_type=' . $post_type_param)); ?>" style="font-size: 11px;">View All</a>
                                </div>
                                <?php $this->render_upcoming_posts_list(); ?>
                        
                                <div class="quick-settings">
                                    <h4 class="quick-settings-title">Quick Toggles</h4>
                                    <div class="setting-toggle">
                                        <span style="font-size: 13px; font-weight: 500;">Auto-Schedule</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="schedulely_auto_schedule" id="schedulely_auto_schedule" 
                                                   value="1" <?php checked(get_option('schedulely_auto_schedule', true)); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="setting-toggle" style="margin-bottom: 0;">
                                        <span style="font-size: 13px; font-weight: 500;">Email Alerts</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="schedulely_email_notifications" id="schedulely_email_notifications" 
                                                   value="1" <?php checked(get_option('schedulely_email_notifications', false)); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>
            
                    <?php $this->render_footer(); ?>
                    </div><!-- .schedulely-wrap -->
                </div><!-- .wrap -->
                <?php
    }

    /**
     * Render upcoming scheduled posts list item
     */
    private function render_upcoming_posts_list()
    {
        $post_types = get_option('schedulely_post_types', ['post']);
        $args = [
            'post_type' => $post_types,
            'post_status' => 'future',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'ASC'
        ];

        $scheduled_posts = get_posts($args);

        echo '<ul class="activity-list">';

        if (empty($scheduled_posts)) {
            echo '<li class="activity-item"><div class="activity-content" style="color: #646970;">' . __('No upcoming posts scheduled.', 'schedulely') . '</div></li>';
        } else {
            foreach ($scheduled_posts as $post) {
                // Alternate dot colors for visual variety or logic
                $dot_class = 'dot-green';

                echo '<li class="activity-item">';
                echo '<div class="activity-dot ' . $dot_class . '"></div>';
                echo '<div class="activity-content">';
                echo '<strong>' . esc_html($post->post_title) . '</strong>';
                echo '<span class="activity-time">' . esc_html(date('M j, g:i A', strtotime($post->post_date))) . '</span>';
                echo '</div>';
                echo '</li>';
            }
        }

        echo '</ul>';
    }



    /**
     * Render footer credits
     */
    private function render_footer()
    {
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
    public function show_welcome_notice()
    {
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
                            nonce: '<?php echo esc_js(wp_create_nonce('schedulely_dismiss_notice')); ?>'
                        });
                    });
            
                    // Handle WordPress's built-in dismiss
                    $('#schedulely-welcome-notice').on('click', '.notice-dismiss', function() {
                        $.post(ajaxurl, {
                            action: 'schedulely_dismiss_notice',
                            nonce: '<?php echo esc_js(wp_create_nonce('schedulely_dismiss_notice')); ?>'
                        });
                    });
                });
                </script>
                <?php
    }

    /**
     * AJAX handler for dismissing notice
     */
    public function ajax_dismiss_notice()
    {
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
    public function ajax_check_capacity()
    {
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
     * AJAX handler for toggling auto schedule
     */
    public function ajax_toggle_auto_schedule()
    {
        check_ajax_referer('schedulely_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'schedulely')]);
        }

        // Get the new value from POST
        $enabled = isset($_POST['enabled']) && '1' === $_POST['enabled'];

        // Update the option
        update_option('schedulely_auto_schedule', $enabled);

        // Manage cron job based on toggle state
        $timestamp = wp_next_scheduled('schedulely_auto_schedule');

        if ($enabled) {
            // Enable: Ensure cron is scheduled
            if (!$timestamp) {
                wp_schedule_event(time(), 'twicedaily', 'schedulely_auto_schedule');
            }
        } else {
            // Disable: Clear cron if it exists
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'schedulely_auto_schedule');
            }
        }

        wp_send_json_success([
            'message' => $enabled
                ? __('Auto-schedule enabled. Posts will be scheduled automatically twice daily.', 'schedulely')
                : __('Auto-schedule disabled. Use "Run Schedule Now" to schedule posts manually.', 'schedulely'),
            'enabled' => $enabled
        ]);
    }

    /**
     * Get current statistics for dashboard
     * 
     * @return array Statistics data
     */
    private function get_statistics()
    {
        $status = get_option('schedulely_post_status', 'draft');

        // Available posts
        $post_types = get_option('schedulely_post_types', ['post']);
        $available_posts = count(get_posts([
            'post_type' => $post_types,
            'post_status' => $status,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]));

        // Next scheduled
        $post_types = get_option('schedulely_post_types', ['post']);
        $next_post = get_posts([
            'post_type' => $post_types,
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

