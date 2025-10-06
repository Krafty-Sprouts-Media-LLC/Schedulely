<?php
/**
 * Admin Page Template
 * 
 * @package KSM_Post_Scheduler
 * @version 1.0.0
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php 
    // WordPress automatically handles success messages for settings pages
    // Only display settings_errors() if there are actual validation errors
    if (get_settings_errors()) {
        $errors = get_settings_errors();
        foreach ($errors as $error) {
            if ($error['type'] === 'error') {
                settings_errors();
                break;
            }
        }
    }
    ?>
    
    <!-- Post Status Information -->
    <div class="notice notice-info">
        <h3><?php _e('Understanding Post Statuses', 'ksm-post-scheduler'); ?></h3>
        <p>
            <strong><?php _e('Scheduled (WordPress):', 'ksm-post-scheduler'); ?></strong> 
            <?php _e('Posts that are already scheduled for future publication by WordPress itself.', 'ksm-post-scheduler'); ?>
        </p>
        <p>
            <strong><?php _e('Draft:', 'ksm-post-scheduler'); ?></strong> 
            <?php _e('Unpublished posts that can be monitored for automatic scheduling.', 'ksm-post-scheduler'); ?>
        </p>
    </div>

    <div class="ksm-ps-admin-container">
        <div class="ksm-ps-main-content">
            <form method="post" action="options.php">
                <?php
                settings_fields('ksm_ps_settings_group');
                do_settings_sections('ksm_ps_settings_group');
                ?>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- Enable/Disable Toggle -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_enabled"><?php _e('Enable Scheduler', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <label class="ksm-ps-toggle">
                                    <input type="checkbox" 
                                           id="ksm_ps_enabled" 
                                           name="<?php echo esc_attr($this->option_name); ?>[enabled]" 
                                           value="1" 
                                           <?php checked(isset($options['enabled']) ? $options['enabled'] : false, true); ?>>
                                    <span class="ksm-ps-toggle-slider"></span>
                                </label>
                                <p class="description"><?php _e('Enable or disable the automatic post scheduler.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Post Status to Monitor -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_post_status"><?php _e('Post Status to Monitor', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <select id="ksm_ps_post_status" name="<?php echo esc_attr($this->option_name); ?>[post_status]">
                                    <?php foreach ($post_statuses as $status_key => $status_obj): ?>
                                        <option value="<?php echo esc_attr($status_key); ?>" 
                                                <?php selected($options['post_status'] ?? 'draft', $status_key); ?>>
                                            <?php echo esc_html($status_obj->label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="draft" <?php selected($options['post_status'] ?? 'draft', 'draft'); ?>>
                                        <?php _e('Draft', 'ksm-post-scheduler'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('Select which post status to monitor for automatic scheduling.', 'ksm-post-scheduler'); ?><br>
                                    <strong><?php _e('Note:', 'ksm-post-scheduler'); ?></strong> 
                                    <?php _e('Posts with the selected status will be automatically scheduled by this plugin. "Scheduled" posts are already scheduled by WordPress.', 'ksm-post-scheduler'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Posts Per Day -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_posts_per_day"><?php _e('Posts Per Day', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="ksm_ps_posts_per_day" 
                                       name="<?php echo esc_attr($this->option_name); ?>[posts_per_day]" 
                                       value="<?php echo esc_attr($options['posts_per_day'] ?? 5); ?>" 
                                       min="1" 
                                       max="50" 
                                       class="small-text">
                                <p class="description"><?php _e('Maximum number of posts to schedule per day.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Start Time -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_start_time"><?php _e('Start Time', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <?php
                                $start_time_12 = $options['start_time'];
                                ?>
                                <input type="text" 
                                       id="ksm_ps_start_time" 
                                       name="<?php echo esc_attr($this->option_name); ?>[start_time]" 
                                       value="<?php echo esc_attr($start_time_12); ?>"
                                       placeholder="9:00 AM"
                                       pattern="^(1[0-2]|[1-9]):[0-5][0-9]\s?(AM|PM|am|pm)$"
                                       title="Enter time in 12-hour format (e.g., 9:00 AM)">
                                <p class="description"><?php _e('Earliest time posts can be published each day. This defines when your posts will start going live. Use format like 9:00 AM or 10:30 AM.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- End Time -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_end_time"><?php _e('End Time', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <?php
                                $end_time_12 = $options['end_time'];
                                ?>
                                <input type="text" 
                                       id="ksm_ps_end_time" 
                                       name="<?php echo esc_attr($this->option_name); ?>[end_time]" 
                                       value="<?php echo esc_attr($end_time_12); ?>"
                                       placeholder="6:00 PM"
                                       pattern="^(1[0-2]|[1-9]):[0-5][0-9]\s?(AM|PM|am|pm)$"
                                       title="Enter time in 12-hour format (e.g., 6:00 PM)">
                                <p class="description"><?php _e('Latest time posts can be published each day. This defines when your posts will stop going live. Use format like 6:00 PM or 11:30 PM.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Days Active -->
                        <tr>
                            <th scope="row"><?php _e('Days Active', 'ksm-post-scheduler'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('Days Active', 'ksm-post-scheduler'); ?></legend>
                                    <?php
                                    $days = array(
                                        'monday' => __('Monday', 'ksm-post-scheduler'),
                                        'tuesday' => __('Tuesday', 'ksm-post-scheduler'),
                                        'wednesday' => __('Wednesday', 'ksm-post-scheduler'),
                                        'thursday' => __('Thursday', 'ksm-post-scheduler'),
                                        'friday' => __('Friday', 'ksm-post-scheduler'),
                                        'saturday' => __('Saturday', 'ksm-post-scheduler'),
                                        'sunday' => __('Sunday', 'ksm-post-scheduler')
                                    );
                                    
                                    $active_days = $options['days_active'] ?? array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
                                    
                                    foreach ($days as $day_key => $day_label):
                                    ?>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->option_name); ?>[days_active][]" 
                                                   value="<?php echo esc_attr($day_key); ?>" 
                                                   <?php checked(in_array($day_key, $active_days), true); ?>>
                                            <?php echo esc_html($day_label); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                    <p class="description"><?php _e('Select which days the scheduler should be active.', 'ksm-post-scheduler'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <!-- Minimum Interval -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_min_interval"><?php _e('Minimum Interval Between Posts', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="ksm_ps_min_interval" 
                                       name="<?php echo esc_attr($this->option_name); ?>[min_interval]" 
                                       value="<?php echo esc_attr($options['min_interval'] ?? 30); ?>" 
                                       min="5" 
                                       max="1440" 
                                       class="small-text">
                                <span><?php _e('minutes', 'ksm-post-scheduler'); ?></span>
                                <p class="description"><?php _e('Minimum time between scheduled posts in minutes.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Author Assignment Settings -->
                <h2><?php _e('Author Assignment Settings', 'ksm-post-scheduler'); ?></h2>
                <p><?php _e('Automatically assign scheduled posts to different authors. This helps distribute content across multiple users and prevents all posts from having the same author.', 'ksm-post-scheduler'); ?></p>
                
                <table class="form-table">
                    <tbody>
                        <!-- Randomize Authors -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_randomize_authors"><?php _e('Randomize Post Authors', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <label class="ksm-ps-switch">
                                    <input type="checkbox" 
                                           id="ksm_ps_randomize_authors" 
                                           name="<?php echo esc_attr($this->option_name); ?>[randomize_authors]" 
                                           value="1" 
                                           <?php checked($options['randomize_authors'] ?? false, true); ?>>
                                    <span class="ksm-ps-slider"></span>
                                </label>
                                <p class="description"><?php _e('Randomly assign a different author to posts when scheduling (excludes current author).', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Assignment Strategy -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_assignment_strategy"><?php _e('Assignment Strategy', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <select id="ksm_ps_assignment_strategy" 
                                        name="<?php echo esc_attr($this->option_name); ?>[assignment_strategy]">
                                    <option value="random" <?php selected($options['assignment_strategy'] ?? 'random', 'random'); ?>>
                                        <?php _e('Random Assignment (Different author each time)', 'ksm-post-scheduler'); ?>
                                    </option>
                                    <option value="round_robin" <?php selected($options['assignment_strategy'] ?? 'random', 'round_robin'); ?>>
                                        <?php _e('Round Robin (User A → User B → User C → User A...)', 'ksm-post-scheduler'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php _e('Choose how posts are distributed among eligible authors.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Author Roles to Use -->
                        <tr>
                            <th scope="row">
                                <label><?php _e('Author Roles to Use', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('Select which user roles can be randomly assigned as authors', 'ksm-post-scheduler'); ?></legend>
                                    <?php
                                    // Get all WordPress roles
                                    global $wp_roles;
                                    $all_roles = $wp_roles->roles;
                                    $allowed_roles = $options['allowed_author_roles'] ?? array('author', 'editor', 'administrator');
                                    
                                    // Filter roles that can edit posts
                                    $eligible_roles = array();
                                    foreach ($all_roles as $role_slug => $role_info) {
                                        if (isset($role_info['capabilities']['edit_posts']) && $role_info['capabilities']['edit_posts']) {
                                            $eligible_roles[$role_slug] = $role_info['name'];
                                        }
                                    }
                                    
                                    foreach ($eligible_roles as $role_slug => $role_name):
                                    ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->option_name); ?>[allowed_author_roles][]" 
                                                   value="<?php echo esc_attr($role_slug); ?>" 
                                                   <?php checked(in_array($role_slug, $allowed_roles), true); ?>>
                                            <?php echo esc_html($role_name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                    <p class="description"><?php _e('Select which user roles can be randomly assigned as authors. Only roles with "edit_posts" capability are shown.', 'ksm-post-scheduler'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_excluded_users"><?php _e('Exclude Individual Users', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('Select individual users to exclude from author assignment', 'ksm-post-scheduler'); ?></legend>
                                    <?php
                                    // Get all users with allowed roles
                                    $allowed_roles = $options['allowed_author_roles'] ?? array('contributor', 'author', 'editor', 'administrator');
                                    $excluded_users = $options['excluded_users'] ?? array();
                                    
                                    if (!empty($allowed_roles)) {
                                        $users = get_users(array(
                                            'role__in' => $allowed_roles,
                                            'orderby' => 'display_name',
                                            'order' => 'ASC',
                                            'number' => 500 // Increased limit but still reasonable
                                        ));
                                        
                                        if (!empty($users)) {
                                            $user_count = count($users);
                                            ?>
                                            <div class="ksm-user-exclusion-container">
                                                <?php if ($user_count > 10): ?>
                                                <div class="ksm-user-search" style="margin-bottom: 15px;">
                                                    <input type="text" 
                                                           id="ksm-user-search" 
                                                           placeholder="<?php _e('Search users...', 'ksm-post-scheduler'); ?>" 
                                                           style="width: 300px; padding: 5px;">
                                                    <button type="button" 
                                                            id="ksm-select-all" 
                                                            class="button button-secondary" 
                                                            style="margin-left: 10px;">
                                                        <?php _e('Select All Visible', 'ksm-post-scheduler'); ?>
                                                    </button>
                                                    <button type="button" 
                                                            id="ksm-deselect-all" 
                                                            class="button button-secondary">
                                                        <?php _e('Deselect All', 'ksm-post-scheduler'); ?>
                                                    </button>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="ksm-users-grid" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                                    <?php foreach ($users as $user): ?>
                                                    <label class="ksm-user-item" style="display: block; margin-bottom: 8px; padding: 5px; background: white; border-radius: 3px;">
                                                        <input type="checkbox" 
                                                               name="<?php echo esc_attr($this->option_name); ?>[excluded_users][]" 
                                                               value="<?php echo esc_attr($user->ID); ?>" 
                                                               <?php checked(in_array($user->ID, $excluded_users), true); ?>
                                                               class="ksm-user-checkbox">
                                                        <strong><?php echo esc_html($user->display_name); ?></strong>
                                                        <span style="color: #666; font-size: 0.9em;">
                                                            (<?php echo esc_html($user->user_login); ?>) - 
                                                            <?php 
                                                            $user_roles = array_intersect($user->roles, $allowed_roles);
                                                            echo esc_html(implode(', ', $user_roles)); 
                                                            ?>
                                                        </span>
                                                    </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <p style="margin-top: 10px; font-style: italic; color: #666;">
                                                    <?php printf(__('Showing %d users with allowed roles.', 'ksm-post-scheduler'), $user_count); ?>
                                                </p>
                                            </div>
                                            
                                            <script type="text/javascript">
                                            jQuery(document).ready(function($) {
                                                // Search functionality
                                                $('#ksm-user-search').on('keyup', function() {
                                                    var searchTerm = $(this).val().toLowerCase();
                                                    $('.ksm-user-item').each(function() {
                                                        var userText = $(this).text().toLowerCase();
                                                        if (userText.indexOf(searchTerm) > -1) {
                                                            $(this).show();
                                                        } else {
                                                            $(this).hide();
                                                        }
                                                    });
                                                });
                                                
                                                // Select all visible users
                                                $('#ksm-select-all').on('click', function() {
                                                    $('.ksm-user-item:visible .ksm-user-checkbox').prop('checked', true);
                                                });
                                                
                                                // Deselect all users
                                                $('#ksm-deselect-all').on('click', function() {
                                                    $('.ksm-user-checkbox').prop('checked', false);
                                                });
                                            });
                                            </script>
                                            <?php
                                        } else {
                                            echo '<p>' . __('No users found with the selected roles.', 'ksm-post-scheduler') . '</p>';
                                        }
                                    } else {
                                        echo '<p>' . __('Please select allowed author roles first.', 'ksm-post-scheduler') . '</p>';
                                    }
                                    ?>
                                    <p class="description"><?php _e('Select individual users to exclude from random author assignment. These users will never be assigned as authors even if they have the allowed roles. Use the search box above to quickly find specific users.', 'ksm-post-scheduler'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                    </tbody>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <!-- Manual Scheduling Section -->
            <div class="ksm-ps-manual-run">
                <h2><?php _e('Manual Scheduling', 'ksm-post-scheduler'); ?></h2>
                <p><?php _e('Click this button to schedule your draft posts right now. It works exactly like the automatic scheduler - it will spread your posts across future dates, respecting your daily limits and active days.', 'ksm-post-scheduler'); ?></p>
                <button type="button" id="ksm-ps-run-now" class="button button-primary">
                    <?php _e('Schedule Posts Now', 'ksm-post-scheduler'); ?>
                </button>
                <div id="ksm-ps-run-result" class="ksm-ps-result"></div>
            </div>
        </div>
        
        <!-- Status Sidebar -->
        <div class="ksm-ps-sidebar">
            <!-- Unified Scheduling Overview -->
            <div class="ksm-ps-overview-box">
                <h3><?php _e('Scheduling Overview', 'ksm-post-scheduler'); ?></h3>
                
                <?php if (!empty($scheduling_preview['warnings'])): ?>
                    <div class="ksm-ps-warnings">
                        <?php foreach ($scheduling_preview['warnings'] as $warning): ?>
                            <div class="ksm-ps-warning"><?php echo esc_html($warning); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Status & Timing Section -->
                <div class="ksm-ps-overview-section">
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Scheduler Status:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-status-indicator <?php echo ($options['enabled'] ?? false) ? 'enabled' : 'disabled'; ?>">
                            <?php echo ($options['enabled'] ?? false) ? __('Enabled', 'ksm-post-scheduler') : __('Disabled', 'ksm-post-scheduler'); ?>
                        </span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Last Cron Run:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-time">
                            <?php
                            $last_run = $options['last_cron_run'] ?? null;
                            if ($last_run) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_run)));
                            } else {
                                echo '<em>' . __('Never', 'ksm-post-scheduler') . '</em>';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Next Cron Run:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-time">
                            <?php
                            $next_cron = wp_next_scheduled('ksm_ps_daily_cron');
                            if ($next_cron) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_cron));
                            } else {
                                echo '<em>' . __('Not scheduled', 'ksm-post-scheduler') . '</em>';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- Visual Separator -->
                <hr class="ksm-ps-section-divider">
                
                <!-- Queue & Configuration Section -->
                <div class="ksm-ps-overview-section">
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Posts waiting to be scheduled:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-count"><?php echo esc_html($scheduling_preview['posts_waiting']); ?> <?php _e('posts', 'ksm-post-scheduler'); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Posts per day limit:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['posts_per_day']); ?> <?php _e('posts', 'ksm-post-scheduler'); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Estimated days needed:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['estimated_days']); ?> <?php _e('days', 'ksm-post-scheduler'); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Time window:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['time_window']); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Minimum spacing:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['min_interval']); ?> <?php _e('minutes', 'ksm-post-scheduler'); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Active days:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['active_days']); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Author Assignment:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-status-indicator <?php echo ($options['randomize_authors'] ?? false) ? 'enabled' : 'disabled'; ?>">
                            <?php echo ($options['randomize_authors'] ?? false) ? __('Enabled', 'ksm-post-scheduler') : __('Disabled', 'ksm-post-scheduler'); ?>
                        </span>
                    </div>
                    
                    <?php if ($options['randomize_authors'] ?? false): ?>
                        <div class="ksm-ps-overview-item">
                            <strong><?php _e('Assignment Strategy:', 'ksm-post-scheduler'); ?></strong>
                            <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $options['assignment_strategy'] ?? 'random'))); ?></span>
                        </div>
                        
                        <div class="ksm-ps-overview-item">
                            <strong><?php _e('Eligible Authors:', 'ksm-post-scheduler'); ?></strong>
                            <span>
                                <?php
                                $allowed_roles = $options['allowed_author_roles'] ?? array();
                                $excluded_users = $options['excluded_users'] ?? array();
                                $eligible_count = 0;
                                if (!empty($allowed_roles)) {
                                    $users = get_users(array(
                                        'role__in' => $allowed_roles,
                                        'fields' => 'ID'
                                    ));
                                    // Filter out excluded users
                                    $eligible_users = array_filter($users, function($user_id) use ($excluded_users) {
                                        return !in_array($user_id, $excluded_users);
                                    });
                                    $eligible_count = count($eligible_users);
                                }
                                echo esc_html($eligible_count) . ' ' . __('users', 'ksm-post-scheduler');
                                ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($excluded_users)): ?>
                        <div class="ksm-ps-overview-item">
                            <strong><?php _e('Excluded Authors:', 'ksm-post-scheduler'); ?></strong>
                            <span>
                                <?php
                                echo esc_html(count($excluded_users)) . ' ' . __('users', 'ksm-post-scheduler');
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Visual Separator -->
                <hr class="ksm-ps-section-divider">
                
                <!-- Schedule Preview Section -->
                <?php if (!empty($scheduling_preview['daily_preview'])): ?>
                    <div class="ksm-ps-overview-section">
                        <h4 class="ksm-ps-section-title"><?php _e('5-Day Scheduling Preview:', 'ksm-post-scheduler'); ?></h4>
                        <div class="ksm-ps-daily-preview">
                            <?php foreach ($scheduling_preview['daily_preview'] as $day_info): ?>
                                <div class="ksm-ps-day-preview">
                                    <strong><?php echo esc_html($day_info['day']); ?>:</strong>
                                    <span>
                                        <?php echo esc_html($day_info['posts_count']); ?> <?php _e('posts', 'ksm-post-scheduler'); ?>
                                        <?php if (!empty($day_info['time_window'])): ?>
                                            (<?php echo esc_html($day_info['time_window']); ?>)
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Visual Separator -->
                <hr class="ksm-ps-section-divider">
                
                <!-- Auto-Completion Status Section -->
                <div class="ksm-ps-overview-section">
                    <h4 class="ksm-ps-section-title"><?php _e('Daily Quota Completion Status:', 'ksm-post-scheduler'); ?></h4>
                    <?php
                    // Get deficit information
                    $deficits = $this->get_deficits();
                    $total_deficit = $this->get_total_deficit();
                    $oldest_deficit_date = $this->get_oldest_deficit_date();
                    ?>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Total Deficit:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-count ksm-ps-total-deficit <?php echo $total_deficit > 0 ? 'ksm-ps-deficit' : 'ksm-ps-complete'; ?>">
                            <?php echo esc_html($total_deficit); ?> <?php _e('posts', 'ksm-post-scheduler'); ?>
                        </span>
                    </div>
                    
                    <?php if ($total_deficit > 0): ?>
                        <div class="ksm-ps-overview-item">
                            <strong><?php _e('Incomplete Days:', 'ksm-post-scheduler'); ?></strong>
                            <span class="ksm-ps-count"><?php echo esc_html(count($deficits)); ?> <?php _e('days', 'ksm-post-scheduler'); ?></span>
                        </div>
                        
                        <?php if ($oldest_deficit_date): ?>
                            <div class="ksm-ps-overview-item">
                                <strong><?php _e('Oldest Deficit:', 'ksm-post-scheduler'); ?></strong>
                                <span class="ksm-ps-time">
                                    <?php echo esc_html(wp_date(get_option('date_format'), strtotime($oldest_deficit_date))); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Recent Deficits List -->
                        <?php if (!empty($deficits)): ?>
                            <div class="ksm-ps-deficit-list">
                                <strong><?php _e('Recent Incomplete Days:', 'ksm-post-scheduler'); ?></strong>
                                <div class="ksm-ps-deficit-items">
                                    <?php
                                    // Show only the 5 most recent deficits
                                    $recent_deficits = array_slice($deficits, -5, 5, true);
                                    krsort($recent_deficits); // Show newest first
                                    foreach ($recent_deficits as $date => $deficit_count):
                                    ?>
                                        <div class="ksm-ps-deficit-item">
                                            <span class="ksm-ps-deficit-date">
                                                <?php echo esc_html(wp_date('M j', strtotime($date))); ?>
                                            </span>
                                            <span class="ksm-ps-deficit-count">
                                                -<?php echo esc_html($deficit_count); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="ksm-ps-overview-item">
                            <span class="ksm-ps-status-complete">
                                <?php _e('All daily quotas are up to date!', 'ksm-post-scheduler'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            

            
            <!-- Refresh Status Button -->
            <button type="button" id="ksm-ps-refresh-status" class="button button-secondary">
                <?php _e('Refresh Status', 'ksm-post-scheduler'); ?>
            </button>
        </div>
    </div>
</div>