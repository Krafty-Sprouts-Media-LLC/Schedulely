<?php
/**
 * Template: settings-page.php
 * Admin settings page dashboard for Schedulely.
 * Required from Schedulely_Settings::render_settings_page().
 *
 * Available variables (set by the calling method):
 *   $stats             — array from get_statistics()
 *   $stored_ai_key_raw — raw stored API key string (may be empty)
 *   $stored_ai_key_len — int length of stored key (0 if none)
 *   $ai_reorder_log    — array of AI reorder log entries
 *   $this              — the Schedulely_Settings instance
 *
 * @package Schedulely
 * @since   1.6.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
    <?php settings_errors( 'schedulely_messages' ); ?>
    <div class="schedulely-wrap">

    <div class="dash-header">
        <div>
            <h1 class="dash-title"><?php esc_html_e( 'Schedulely', 'schedulely' ); ?></h1>
            <div class="dash-subtitle"><?php esc_html_e( 'Intelligent Post Scheduling for WordPress', 'schedulely' ); ?></div>
        </div>
        <div class="action-bar">
            <a href="https://wordpress.org/support/plugin/schedulely/" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                <span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Report Issue', 'schedulely' ); ?>
            </a>
            <button type="button" id="schedulely-schedule-now" class="btn btn-primary">
                <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Run Schedule Now', 'schedulely' ); ?>
            </button>
        </div>
    </div>

    <!-- Insight Panel -->
    <div class="insight-panel">
        <button class="close-insight">✕</button>
        <div class="insight-title">
            <span class="dashicons dashicons-info insight-icon"></span>
            <?php esc_html_e( 'How Random Scheduling Works', 'schedulely' ); ?>
        </div>
        <div class="insight-content">
            <?php echo wp_kses_post( __( 'Posts are scheduled at <strong>random times</strong> within your time window for a natural appearance.', 'schedulely' ) ); ?>
            <ul class="insight-list">
                <li><?php echo wp_kses_post( __( '✅ Posts are <strong>at least X minutes</strong> apart (never closer)', 'schedulely' ) ); ?></li>
                <li><?php esc_html_e( '✅ Gaps between posts vary randomly (some 30 min, some 60+ min)', 'schedulely' ); ?></li>
            </ul>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field( 'schedulely_settings_save' ); ?>
        <div class="dashboard-grid">

            <!-- Stat 1: Drafts Available -->
            <div class="stat-card">
                <div class="stat-icon"><span class="dashicons dashicons-format-aside"></span></div>
                <div class="stat-value"><?php echo esc_html( (string) $stats['available_posts'] ); ?></div>
                <div class="stat-label"><?php esc_html_e( 'Drafts Available', 'schedulely' ); ?></div>
                <div class="stat-trend">
                    <?php
                    /* translators: %s: number of posts currently in pool */
                    echo esc_html( sprintf( __( '%s currently in pool', 'schedulely' ), $stats['available_posts'] ) );
                    ?>
                </div>
            </div>

            <!-- Stat 2: Last Date Scheduled -->
            <?php
            // Re-use data from $stats to avoid duplicate SQL queries.
            // $stats['last_date_status'] already contains the count/quota info as a string.
            // For the card values we need the raw numbers — compute once here.
            $scheduler_stat  = new Schedulely_Scheduler();
            $last_date        = $scheduler_stat->get_last_scheduled_date();
            $quota            = (int) get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY );
            $posts_count      = $last_date ? $scheduler_stat->count_posts_on_date( $last_date ) : 0;
            $is_complete      = $posts_count >= $quota;
            unset( $scheduler_stat ); // Release reference; all further stats use $stats array.
            ?>
            <div class="stat-card">
                <div class="stat-icon"><span class="dashicons dashicons-calendar"></span></div>
                <div class="stat-value"><?php echo esc_html( (string) $posts_count ); ?></div>
                <div class="stat-label"><?php esc_html_e( 'Last Date Scheduled', 'schedulely' ); ?></div>
                <div class="stat-trend <?php echo $is_complete ? '' : 'down'; ?>">
                    <?php
                    /* translators: 1: posts scheduled 2: daily quota */
                    echo esc_html( sprintf( __( 'Target: %d/day', 'schedulely' ), $quota ) );
                    echo $is_complete ? '' : ' ' . esc_html__( '(Incomplete)', 'schedulely' );
                    ?>
                </div>
            </div>

            <!-- Stat 3: Furthest Scheduled Date -->
            <div class="stat-card">
                <div class="stat-icon schedulely-stat-icon<?php echo $is_complete ? '' : '--alert'; ?>">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="stat-value schedulely-stat-value<?php echo $is_complete ? '' : '--alert'; ?>">
                    <?php echo $last_date ? esc_html( wp_date( 'M j', strtotime( $last_date ) ) ) : esc_html__( 'None', 'schedulely' ); ?>
                </div>
                <div class="stat-label"><?php esc_html_e( 'Furthest Scheduled Date', 'schedulely' ); ?></div>
                <div class="stat-trend <?php echo $is_complete ? '' : 'down'; ?>">
                    <?php
                    if ( ! $is_complete && $last_date ) {
                        esc_html_e( 'Action Needed', 'schedulely' );
                    } elseif ( $last_date ) {
                        esc_html_e( 'Scheduled', 'schedulely' );
                    } else {
                        esc_html_e( 'No posts scheduled', 'schedulely' );
                    }
                    ?>
                </div>
            </div>

            <!-- Stat 4: System Health -->
            <?php
            $auto_schedule  = (bool) get_option( 'schedulely_auto_schedule', Schedulely_Defaults::AUTO_SCHEDULE );
            $last_run       = (int) get_option( 'schedulely_last_run', 0 );
            /* translators: %s: human-readable time difference, e.g. "5 minutes" */
            $last_run_text  = $last_run > 0
                ? sprintf( __( 'Run %s ago', 'schedulely' ), human_time_diff( $last_run, time() ) )
                : __( 'Never ran', 'schedulely' );
            ?>
            <div class="stat-card">
                <div class="stat-icon schedulely-stat-icon<?php echo $auto_schedule ? '--ok' : '--alert'; ?>">
                    <span class="dashicons dashicons-heart"></span>
                </div>
                <div class="stat-value schedulely-stat-value<?php echo $auto_schedule ? '--ok' : '--alert'; ?>">
                    <?php echo $auto_schedule ? esc_html__( 'Active', 'schedulely' ) : esc_html__( 'Paused', 'schedulely' ); ?>
                </div>
                <div class="stat-label"><?php esc_html_e( 'System Health', 'schedulely' ); ?></div>
                <div class="stat-trend">
                    <?php echo $auto_schedule ? esc_html( $last_run_text ) : esc_html__( 'Auto-Schedule Off', 'schedulely' ); ?>
                </div>
            </div>

            <!-- Main Config Card -->
            <div class="config-card">
                <div class="card-header">
                    <h3 class="card-title"><?php esc_html_e( 'Configuration & Constraints', 'schedulely' ); ?></h3>
                    <button type="submit" name="schedulely_save_settings" class="btn btn-primary schedulely-save-btn">
                        <?php esc_html_e( 'Save Changes', 'schedulely' ); ?>
                    </button>
                </div>
                <div class="timeline-view">

                    <div id="schedulely-capacity-notice">
                        <div class="schedulely-capacity-loading">
                            <span class="spinner is-active" style="float: none; margin: 0;"></span>
                            <?php esc_html_e( 'Checking capacity...', 'schedulely' ); ?>
                        </div>
                    </div>
                    <div id="schedulely-capacity-suggestions" class="suggestion-group" style="display: none;">
                        <div id="schedulely-suggestions-list"></div>
                    </div>

                    <hr class="schedulely-divider">
                    <div class="form-grid">

                        <!-- Content & Volume -->
                        <p class="description schedulely-section-label"><?php esc_html_e( 'Content & volume', 'schedulely' ); ?></p>
                        <div class="schedulely-form-row">
                            <div class="form-group schedulely-form-col">
                                <label class="form-label" for="schedulely_post_status"><?php esc_html_e( 'Post Status to Monitor', 'schedulely' ); ?></label>
                                <select name="schedulely_post_status" id="schedulely_post_status" style="width:100%;">
                                    <?php
                                    $post_statuses  = get_post_stati( [ 'show_in_admin_status_list' => true ], 'objects' );
                                    $status_exclude = [ 'publish', 'future', 'trash', 'auto-draft', 'inherit' ];
                                    $current_status = get_option( 'schedulely_post_status', Schedulely_Defaults::POST_STATUS );
                                    foreach ( $post_statuses as $s ) {
                                        if ( in_array( $s->name, $status_exclude, true ) ) continue;
                                        echo '<option value="' . esc_attr( $s->name ) . '" ' . selected( $current_status, $s->name, false ) . '>' . esc_html( $s->label ) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description schedulely-field-hint"><?php esc_html_e( 'Source status for scheduling.', 'schedulely' ); ?></p>
                            </div>
                            <div class="form-group schedulely-form-col">
                                <label class="form-label" for="schedulely_post_types"><?php esc_html_e( 'Post Types to Schedule', 'schedulely' ); ?></label>
                                <select name="schedulely_post_types[]" id="schedulely_post_types" class="schedulely-post-type-select" multiple="multiple" style="width:100%;">
                                    <?php
                                    $all_post_types   = get_post_types( [ 'public' => true ], 'objects' );
                                    $current_pt       = get_option( 'schedulely_post_types', Schedulely_Defaults::POST_TYPES );
                                    foreach ( $all_post_types as $pt ) {
                                        $sel = in_array( $pt->name, $current_pt, true ) ? 'selected' : '';
                                        echo '<option value="' . esc_attr( $pt->name ) . '" ' . $sel . '>' . esc_html( $pt->label ) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description schedulely-field-hint"><?php esc_html_e( 'Select which post types to include in scheduling.', 'schedulely' ); ?></p>
                            </div>
                            <div class="form-group schedulely-form-col">
                                <label class="form-label" for="schedulely_posts_per_day"><?php esc_html_e( 'Posts Per Day', 'schedulely' ); ?></label>
                                <input type="number" name="schedulely_posts_per_day" id="schedulely_posts_per_day"
                                       value="<?php echo esc_attr( get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY ) ); ?>"
                                       min="1" max="100" style="width:100%;">
                            </div>
                            <div class="form-group schedulely-form-col">
                                <label class="form-label" for="schedulely_min_interval"><?php esc_html_e( 'Min Interval (Minutes)', 'schedulely' ); ?></label>
                                <input type="number" name="schedulely_min_interval" id="schedulely_min_interval"
                                       value="<?php echo esc_attr( get_option( 'schedulely_min_interval', Schedulely_Defaults::MIN_INTERVAL ) ); ?>"
                                       min="1" max="1440" style="width:100%;">
                            </div>
                            <div class="form-group schedulely-form-col">
                                <label class="form-label" for="schedulely_pool_size"><?php esc_html_e( 'Pool Size (Max Posts per Run)', 'schedulely' ); ?></label>
                                <input type="number" name="schedulely_pool_size" id="schedulely_pool_size"
                                       value="<?php echo esc_attr( get_option( 'schedulely_pool_size', Schedulely_Defaults::MAX_POSTS_PER_RUN ) ); ?>"
                                       min="1" max="10000" style="width:100%;">
                                <p class="description schedulely-field-hint">
                                    <?php esc_html_e( 'How many eligible posts to load per run. A larger pool gives shuffle and AI ordering more variety. Lower this only if your host has tight execution time limits.', 'schedulely' ); ?>
                                </p>
                            </div>
                        </div>

                        <hr class="schedulely-divider">

                        <!-- When to Publish -->
                        <p class="description schedulely-section-label"><?php esc_html_e( 'When to publish', 'schedulely' ); ?></p>
                        <div class="schedulely-form-row">
                            <div class="form-group" style="flex:2; min-width:300px;">
                                <label class="form-label"><?php esc_html_e( 'Time Window', 'schedulely' ); ?></label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="text" name="schedulely_start_time" id="schedulely_start_time"
                                           value="<?php echo esc_attr( get_option( 'schedulely_start_time', Schedulely_Defaults::START_TIME ) ); ?>"
                                           class="regular-text schedulely-timepicker" style="width:120px;">
                                    <span style="color:#646970;">→</span>
                                    <input type="text" name="schedulely_end_time" id="schedulely_end_time"
                                           value="<?php echo esc_attr( get_option( 'schedulely_end_time', Schedulely_Defaults::END_TIME ) ); ?>"
                                           class="regular-text schedulely-timepicker" style="width:120px;">
                                </div>
                                <p class="description" style="margin-top:8px; max-width:640px;">
                                    <?php esc_html_e( 'Same calendar day: end time must be after start (e.g. 2:30 PM → 11:59 PM). Overnight: if end is at or before start on the clock, the window runs from start on the anchor day through end on the next calendar morning (e.g. 2:30 PM → 3:00 AM).', 'schedulely' ); ?>
                                </p>
                            </div>
                            <div class="form-group" style="flex:1; min-width:300px;">
                                <label class="form-label"><?php esc_html_e( 'Active Days', 'schedulely' ); ?></label>
                                <div style="display:flex; gap:15px; flex-wrap:wrap;">
                                    <?php
                                    $active_days = get_option( 'schedulely_active_days', Schedulely_Defaults::ACTIVE_DAYS );
                                    $day_labels  = [ 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun' ];
                                    foreach ( $day_labels as $day_num => $day_label ) {
                                        $chk = in_array( $day_num, $active_days, true ) ? 'checked' : '';
                                        echo '<label class="day-checkbox"><input type="checkbox" name="schedulely_active_days[]" value="' . esc_attr( (string) $day_num ) . '" ' . $chk . '> ' . esc_html( $day_label ) . '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <hr class="schedulely-divider">

                        <!-- Queue Order & Scheduling Mode -->
                        <p class="description schedulely-section-label"><?php esc_html_e( 'Queue order & scheduling mode', 'schedulely' ); ?></p>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label style="display:block;">
                                <input type="checkbox" name="schedulely_shuffle_queue" id="schedulely_shuffle_queue"
                                       value="1" <?php checked( get_option( 'schedulely_shuffle_queue', Schedulely_Defaults::SHUFFLE_QUEUE ) ); ?>>
                                <?php esc_html_e( 'Shuffle posts before assigning dates (breaks strict draft-date order)', 'schedulely' ); ?>
                            </label>
                            <p class="description schedulely-field-hint"><?php esc_html_e( 'When enabled, each run randomizes which eligible posts get the next slots instead of always using oldest post date first.', 'schedulely' ); ?></p>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label"><?php esc_html_e( 'Scheduling Mode', 'schedulely' ); ?></label>
                            <?php $current_mode = get_option( 'schedulely_scheduling_mode', Schedulely_Defaults::SCHEDULING_MODE ); ?>
                            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:6px;">
                                <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer; flex:1; min-width:200px; padding:12px; border:2px solid <?php echo $current_mode === 'random' ? '#2271b1' : '#dcdcde'; ?>; border-radius:4px; background:<?php echo $current_mode === 'random' ? '#f0f6fc' : '#fff'; ?>;">
                                    <input type="radio" name="schedulely_scheduling_mode" value="random" <?php checked( $current_mode, 'random' ); ?> style="margin-top:2px; flex-shrink:0;">
                                    <div>
                                        <strong><?php esc_html_e( 'Random', 'schedulely' ); ?></strong>
                                        <p class="description" style="margin:4px 0 0; font-size:12px;"><?php esc_html_e( 'Posts land at random times in the window. Looks most natural. ~70% efficiency — some days may get fewer posts than quota.', 'schedulely' ); ?></p>
                                    </div>
                                </label>
                                <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer; flex:1; min-width:200px; padding:12px; border:2px solid <?php echo $current_mode === 'sequential' ? '#2271b1' : '#dcdcde'; ?>; border-radius:4px; background:<?php echo $current_mode === 'sequential' ? '#f0f6fc' : '#fff'; ?>;">
                                    <input type="radio" name="schedulely_scheduling_mode" value="sequential" <?php checked( $current_mode, 'sequential' ); ?> style="margin-top:2px; flex-shrink:0;">
                                    <div>
                                        <strong><?php esc_html_e( 'Sequential', 'schedulely' ); ?></strong>
                                        <p class="description" style="margin:4px 0 0; font-size:12px;"><?php esc_html_e( 'Posts are spaced perfectly evenly (e.g. every 45 min). 100% efficiency — always hits quota. Predictable, but looks automated.', 'schedulely' ); ?></p>
                                    </div>
                                </label>
                                <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer; flex:1; min-width:200px; padding:12px; border:2px solid <?php echo $current_mode === 'hybrid' ? '#2271b1' : '#dcdcde'; ?>; border-radius:4px; background:<?php echo $current_mode === 'hybrid' ? '#f0f6fc' : '#fff'; ?>;">
                                    <input type="radio" name="schedulely_scheduling_mode" value="hybrid" <?php checked( $current_mode, 'hybrid' ); ?> style="margin-top:2px; flex-shrink:0;">
                                    <div>
                                        <strong><?php esc_html_e( 'Hybrid', 'schedulely' ); ?></strong>
                                        <p class="description" style="margin:4px 0 0; font-size:12px;"><?php esc_html_e( 'Window divided into equal slots, post placed randomly within each slot. Near-100% efficiency and looks natural. Best of both worlds.', 'schedulely' ); ?></p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <hr class="schedulely-divider">

                        <!-- Author Assignment -->
                        <div class="form-group">
                            <label class="form-label"><?php esc_html_e( 'Author Assignment', 'schedulely' ); ?></label>
                            <label style="display:block; margin-bottom:15px;">
                                <input type="checkbox" name="schedulely_randomize_authors" id="schedulely_randomize_authors"
                                       value="1" <?php checked( get_option( 'schedulely_randomize_authors', Schedulely_Defaults::RANDOMIZE_AUTHORS ) ); ?>>
                                <?php esc_html_e( 'Randomly assign authors to scheduled posts', 'schedulely' ); ?>
                            </label>
                            <div class="schedulely-form-row">
                                <div style="flex:1; min-width:300px;">
                                    <label class="form-label schedulely-field-hint"><?php esc_html_e( 'Excluded Authors', 'schedulely' ); ?></label>
                                    <select name="schedulely_excluded_authors[]" id="schedulely_excluded_authors" class="schedulely-author-select" multiple="multiple" style="width:100%;">
                                        <?php
                                        $all_authors   = get_users( [ 'capability' => 'edit_posts' ] );
                                        $excl_authors  = get_option( 'schedulely_excluded_authors', Schedulely_Defaults::EXCLUDED_AUTHORS );
                                        foreach ( $all_authors as $u ) {
                                            $sel = in_array( $u->ID, $excl_authors, true ) ? 'selected' : '';
                                            echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . $sel . '>' . esc_html( $u->display_name ) . ' (' . esc_html( $u->user_login ) . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div style="flex:1; min-width:300px;">
                                    <label class="form-label schedulely-field-hint"><?php esc_html_e( 'Preserved Authors', 'schedulely' ); ?></label>
                                    <select name="schedulely_preserved_authors[]" id="schedulely_preserved_authors" class="schedulely-author-select" multiple="multiple" style="width:100%;">
                                        <?php
                                        $pres_authors = get_option( 'schedulely_preserved_authors', Schedulely_Defaults::PRESERVED_AUTHORS );
                                        foreach ( $all_authors as $u ) {
                                            $sel = in_array( $u->ID, $pres_authors, true ) ? 'selected' : '';
                                            echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . $sel . '>' . esc_html( $u->display_name ) . ' (' . esc_html( $u->user_login ) . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="schedulely-divider">

                        <!-- AI Series Spacing -->
                        <p class="description schedulely-section-label"><?php esc_html_e( 'AI series spacing (optional)', 'schedulely' ); ?></p>
                        <div class="form-group schedulely-ai-panel">
                            <?php
                            $wp_ai_ready = function_exists( 'wp_ai_client_prompt' );
                            try {
                                $wp_ai_connected = $wp_ai_ready && (bool) wp_ai_client_prompt( '' )->is_supported_for_text_generation();
                            } catch ( \Throwable $e ) {
                                $wp_ai_connected = false;
                            }
                            ?>
                            <?php if ( $wp_ai_ready && $wp_ai_connected ) : ?>
                                <div class="notice notice-success inline schedulely-ai-notice">
                                    <p style="margin:0;">
                                        <strong><?php esc_html_e( 'AI provider connected via WordPress Connectors.', 'schedulely' ); ?></strong>
                                        <?php esc_html_e( 'No API key required — Schedulely uses the provider configured in', 'schedulely' ); ?>
                                        <a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Settings → Connectors', 'schedulely' ); ?></a>.
                                    </p>
                                </div>
                                <label style="display:block; margin-bottom:12px;">
                                    <input type="checkbox" name="schedulely_ai_order_enabled" id="schedulely_ai_order_enabled"
                                           value="1" <?php checked( get_option( 'schedulely_ai_order_enabled', Schedulely_Defaults::AI_ORDER_ENABLED ) ); ?>>
                                    <?php esc_html_e( 'Use AI to order the queue before scheduling (skips shuffle when AI succeeds)', 'schedulely' ); ?>
                                </label>
                                <p style="margin-bottom:8px;">
                                    <button type="button" class="button button-secondary" id="schedulely-test-ai-connection"><?php esc_html_e( 'Test connection', 'schedulely' ); ?></button>
                                </p>
                                <p class="description" id="schedulely-ai-test-result" style="display:none; margin-top:8px;" aria-live="polite"></p>

                            <?php elseif ( $wp_ai_ready && ! $wp_ai_connected ) : ?>
                                <div class="notice notice-warning inline schedulely-ai-notice">
                                    <p style="margin:0;">
                                        <strong><?php esc_html_e( 'No AI provider connected.', 'schedulely' ); ?></strong>
                                        <?php esc_html_e( 'Configure one in', 'schedulely' ); ?>
                                        <a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Settings → Connectors', 'schedulely' ); ?></a>.
                                        <?php esc_html_e( 'No API key is needed in Schedulely — the provider is managed centrally.', 'schedulely' ); ?>
                                    </p>
                                </div>
                                <label style="display:block; margin-bottom:12px; opacity:0.5;">
                                    <input type="checkbox" name="schedulely_ai_order_enabled" id="schedulely_ai_order_enabled" value="1" disabled>
                                    <?php esc_html_e( 'Use AI to order the queue before scheduling (requires a connected provider)', 'schedulely' ); ?>
                                </label>

                            <?php else : ?>
                                <!-- Legacy path — pre-WP-7.0 -->
                                <p class="description" style="margin-top:0;">
                                    <?php echo wp_kses(
                                        sprintf( __( 'Defaults target the DeepSeek OpenAI-compatible API (<a href="%s" target="_blank" rel="noopener noreferrer">API overview</a>). You can change base URL and model for any compatible provider.', 'schedulely' ), esc_url( 'https://apidog.com/blog/how-to-use-deepseek-v4-api/' ) ),
                                        [ 'a' => [ 'href' => true, 'target' => true, 'rel' => true ] ]
                                    ); ?>
                                </p>
                                <label style="display:block; margin-bottom:12px;">
                                    <input type="checkbox" name="schedulely_ai_order_enabled" id="schedulely_ai_order_enabled"
                                           value="1" <?php checked( get_option( 'schedulely_ai_order_enabled', Schedulely_Defaults::AI_ORDER_ENABLED ) ); ?>>
                                    <?php esc_html_e( 'Use AI to order the queue before scheduling (skips shuffle when AI succeeds)', 'schedulely' ); ?>
                                </label>
                                <div class="schedulely-form-row" style="margin-bottom:12px;">
                                    <div style="flex:1; min-width:260px;">
                                        <label class="form-label schedulely-field-hint" for="schedulely_ai_base_url"><?php esc_html_e( 'API base URL', 'schedulely' ); ?></label>
                                        <input type="url" name="schedulely_ai_base_url" id="schedulely_ai_base_url" class="regular-text" style="width:100%;"
                                               value="<?php echo esc_attr( get_option( 'schedulely_ai_base_url', Schedulely_Defaults::AI_BASE_URL ) ); ?>"
                                               placeholder="https://api.deepseek.com/v1" autocomplete="off">
                                    </div>
                                    <div style="flex:1; min-width:220px;">
                                        <label class="form-label schedulely-field-hint" for="schedulely_ai_model"><?php esc_html_e( 'Model', 'schedulely' ); ?></label>
                                        <input type="text" name="schedulely_ai_model" id="schedulely_ai_model" class="regular-text" style="width:100%;"
                                               value="<?php echo esc_attr( get_option( 'schedulely_ai_model', Schedulely_Defaults::AI_MODEL ) ); ?>"
                                               placeholder="deepseek-v4-flash" autocomplete="off">
                                    </div>
                                </div>
                                <div style="margin-bottom:10px;">
                                    <?php if ( $stored_ai_key_len > 0 ) :
                                        $masked_key = strlen( $stored_ai_key_raw ) > 8
                                            ? substr( $stored_ai_key_raw, 0, 4 ) . str_repeat( '•', max( 4, strlen( $stored_ai_key_raw ) - 8 ) ) . substr( $stored_ai_key_raw, -4 )
                                            : str_repeat( '•', strlen( $stored_ai_key_raw ) );
                                    ?>
                                        <label class="form-label schedulely-field-hint" for="schedulely_ai_api_key_current"><?php esc_html_e( 'Current API key', 'schedulely' ); ?></label>
                                        <input type="text" readonly id="schedulely_ai_api_key_current" class="regular-text"
                                               style="width:100%; max-width:480px; font-family:monospace; margin-bottom:12px;"
                                               value="<?php echo esc_attr( $masked_key ); ?>" autocomplete="off">
                                        <label class="form-label schedulely-field-hint" for="schedulely_ai_api_key"><?php esc_html_e( 'Replace API key (optional)', 'schedulely' ); ?></label>
                                    <?php else : ?>
                                        <label class="form-label schedulely-field-hint" for="schedulely_ai_api_key"><?php esc_html_e( 'API key', 'schedulely' ); ?></label>
                                    <?php endif; ?>
                                    <p style="margin-bottom:8px;">
                                        <button type="button" class="button button-secondary" id="schedulely-test-ai-connection"><?php esc_html_e( 'Test connection', 'schedulely' ); ?></button>
                                    </p>
                                    <p class="description" id="schedulely-ai-test-result" style="display:none; margin-top:8px;" aria-live="polite"></p>
                                    <input type="password" name="schedulely_ai_api_key" id="schedulely_ai_api_key" class="regular-text"
                                           style="width:100%; max-width:480px;" value="" autocomplete="new-password"
                                           placeholder="<?php echo $stored_ai_key_len > 0 ? esc_attr__( 'Leave blank to keep the saved key, or type a new key to replace it', 'schedulely' ) : esc_attr__( 'Enter your API key', 'schedulely' ); ?>">
                                </div>
                                <label style="display:block;">
                                    <input type="checkbox" name="schedulely_ai_clear_api_key" id="schedulely_ai_clear_api_key" value="1">
                                    <?php esc_html_e( 'Remove stored API key on save', 'schedulely' ); ?>
                                </label>
                            <?php endif; ?>

                            <!-- AI Reorder Log -->
                            <div class="schedulely-ai-log-section">
                                <p class="description schedulely-section-label"><?php esc_html_e( 'AI queue reorder log', 'schedulely' ); ?></p>
                                <p class="description" style="margin:0 0 10px 0;">
                                    <?php esc_html_e( 'Each scheduling run that calls the reorder API records outcome, HTTP status, token usage, error code, and response excerpts.', 'schedulely' ); ?>
                                </p>
                                <?php if ( empty( $ai_reorder_log ) ) : ?>
                                    <p class="description" style="margin:0;"><?php esc_html_e( 'No reorder API attempts logged yet.', 'schedulely' ); ?></p>
                                <?php else : ?>
                                    <div class="schedulely-log-viewer">
                                        <?php foreach ( $ai_reorder_log as $row ) :
                                            if ( ! is_array( $row ) ) continue;
                                            $outcome   = isset( $row['outcome'] ) ? (string) $row['outcome'] : '';
                                            $at        = isset( $row['at_site'] ) ? (string) $row['at_site'] : '';
                                            $badge_bg  = ( 'success' === $outcome ) ? '#d1fae5' : '#fee2e2';
                                            $badge_fg  = ( 'success' === $outcome ) ? '#065f46' : '#991b1b';
                                        ?>
                                            <div class="schedulely-log-row">
                                                <div style="margin-bottom:4px;">
                                                    <span style="display:inline-block; padding:2px 8px; border-radius:3px; background:<?php echo esc_attr( $badge_bg ); ?>; color:<?php echo esc_attr( $badge_fg ); ?>; font-weight:600;">
                                                        <?php echo esc_html( strtoupper( $outcome !== '' ? $outcome : '?' ) ); ?>
                                                    </span>
                                                    <span style="color:#50575e;"><?php echo esc_html( $at ); ?></span>
                                                </div>
                                                <?php
                                                $parts = [];
                                                if ( ! empty( $row['model'] ) )            $parts[] = 'model: ' . $row['model'];
                                                if ( isset( $row['post_count'] ) )         $parts[] = 'posts: ' . (int) $row['post_count'];
                                                if ( isset( $row['http_code'] ) && null !== $row['http_code'] ) $parts[] = 'http: ' . (int) $row['http_code'];
                                                if ( isset( $row['usage_total_tokens'] ) && null !== $row['usage_total_tokens'] ) $parts[] = 'tokens: ' . (int) $row['usage_total_tokens'];
                                                if ( ! empty( $row['error_code'] ) )       $parts[] = 'error_code: ' . $row['error_code'];
                                                if ( ! empty( $row['error_message'] ) )    $parts[] = 'error_message: ' . $row['error_message'];
                                                if ( ! empty( $row['note'] ) )             $parts[] = 'note: ' . $row['note'];
                                                echo '<div style="color:#1d2327; margin:4px 0;">' . esc_html( implode( ' | ', $parts ) ) . '</div>';
                                                if ( ! empty( $row['assistant_excerpt'] ) ) {
                                                    echo '<div style="margin-top:6px;"><strong>assistant_excerpt</strong><pre class="schedulely-log-pre">' . esc_html( $row['assistant_excerpt'] ) . '</pre></div>';
                                                }
                                                if ( ! empty( $row['raw_excerpt'] ) ) {
                                                    echo '<div style="margin-top:6px;"><strong>raw_excerpt</strong><pre class="schedulely-log-pre">' . esc_html( $row['raw_excerpt'] ) . '</pre></div>';
                                                }
                                                ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php submit_button( __( 'Clear AI reorder log', 'schedulely' ), 'secondary', 'schedulely_clear_ai_log', false, [ 'style' => 'margin-top:0;', 'form' => 'schedulely-clear-ai-log-form' ] ); ?>
                                <?php endif; ?>
                            </div><!-- .schedulely-ai-log-section -->
                        </div><!-- .schedulely-ai-panel -->

                        <hr class="schedulely-divider">

                        <!-- Notification Recipients -->
                        <div class="form-group">
                            <label class="form-label"><?php esc_html_e( 'Notification Recipients', 'schedulely' ); ?></label>
                            <select name="schedulely_notification_users[]" id="schedulely_notification_users" class="schedulely-notification-select" multiple="multiple" style="width:100%;">
                                <?php
                                $notify_users    = get_users( [ 'capability' => 'publish_posts' ] );
                                $selected_users  = get_option( 'schedulely_notification_users', Schedulely_Defaults::NOTIFICATION_USERS );
                                if ( empty( $selected_users ) ) $selected_users = [ get_current_user_id() ];
                                foreach ( $notify_users as $u ) {
                                    $sel = in_array( $u->ID, $selected_users, true ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . $sel . '>' . esc_html( $u->display_name ) . ' (' . esc_html( $u->user_email ) . ')</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <?php if ( function_exists( 'wp_ai_client_prompt' ) ) : ?>
                        <div class="form-group" style="margin-top:12px;">
                            <label style="display:block;">
                                <input type="checkbox" name="schedulely_ai_email_summary" id="schedulely_ai_email_summary"
                                       value="1" <?php checked( get_option( 'schedulely_ai_email_summary', false ) ); ?>>
                                <?php esc_html_e( 'Include AI-generated summary at the top of scheduling notification emails', 'schedulely' ); ?>
                            </label>
                            <p class="description schedulely-field-hint">
                                <?php esc_html_e( 'A 2–3 sentence plain-text summary of each scheduling run, generated by your connected AI provider. Uses a small number of tokens per email.', 'schedulely' ); ?>
                            </p>
                        </div>
                        <?php endif; ?>

                    </div><!-- .form-grid -->
                </div><!-- .timeline-view -->
            </div><!-- .config-card -->

            <!-- Activity Feed / Sidebar -->
            <div class="activity-card">
                <div class="card-header">
                    <h3 class="card-title"><?php esc_html_e( 'Upcoming Posts', 'schedulely' ); ?></h3>
                    <?php
                    $sidebar_post_types = get_option( 'schedulely_post_types', Schedulely_Defaults::POST_TYPES );
                    if ( count( $sidebar_post_types ) === 1 ) {
                        $view_url = $this->get_scheduled_posts_url();
                        echo '<a href="' . esc_url( $view_url ) . '" style="font-size:11px;">' . esc_html__( 'View All', 'schedulely' ) . '</a>';
                    } else {
                        echo '<div style="position:relative; display:inline-block;">';
                        echo '<select id="schedulely-view-posts-type" style="font-size:11px; padding:2px 20px 2px 5px; border:1px solid #ddd; border-radius:3px; background:white; cursor:pointer;">';
                        echo '<option value="">' . esc_html__( 'Select Type...', 'schedulely' ) . '</option>';
                        echo '<option value="' . esc_url( admin_url( 'edit.php?post_status=future' ) ) . '">' . esc_html__( 'All Types', 'schedulely' ) . '</option>';
                        foreach ( $sidebar_post_types as $pt ) {
                            $pt_obj   = get_post_type_object( $pt );
                            $pt_label = $pt_obj ? $pt_obj->labels->name : $pt;
                            $pt_url   = $this->get_scheduled_posts_url( $pt );
                            echo '<option value="' . esc_url( $pt_url ) . '">' . esc_html( $pt_label ) . '</option>';
                        }
                        echo '</select></div>';
                    }
                    ?>
                </div>
                <?php $this->render_upcoming_posts_list(); ?>

                <div class="quick-settings">
                    <h4 class="quick-settings-title"><?php esc_html_e( 'Quick Toggles', 'schedulely' ); ?></h4>
                    <div class="setting-toggle">
                        <span style="font-size:13px; font-weight:500;"><?php esc_html_e( 'Auto-Schedule', 'schedulely' ); ?></span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="schedulely_auto_schedule" id="schedulely_auto_schedule"
                                   value="1" <?php checked( get_option( 'schedulely_auto_schedule', Schedulely_Defaults::AUTO_SCHEDULE ) ); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-toggle" style="margin-bottom:0;">
                        <span style="font-size:13px; font-weight:500;"><?php esc_html_e( 'Email Alerts', 'schedulely' ); ?></span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="schedulely_email_notifications" id="schedulely_email_notifications"
                                   value="1" <?php checked( get_option( 'schedulely_email_notifications', Schedulely_Defaults::EMAIL_NOTIFICATIONS ) ); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <?php
                    $cron_hook = 'schedulely_auto_schedule';
                    $cron_next = wp_next_scheduled( $cron_hook );
                    ?>
                    <p class="description" style="font-size:11px; margin-top:12px; margin-bottom:0; color:#646970; line-height:1.5;">
                        <strong><?php esc_html_e( 'WP-Cron', 'schedulely' ); ?></strong>
                        <?php esc_html_e( '— Event hook:', 'schedulely' ); ?>
                        <code style="font-size:11px;"><?php echo esc_html( $cron_hook ); ?></code>.
                        <?php esc_html_e( 'Search cron manager plugins by this slug (lowercase). Recurrence: twicedaily (~every 12 hours).', 'schedulely' ); ?>
                        <?php if ( false !== $cron_next ) : ?>
                            <strong><?php esc_html_e( 'Next run (site time):', 'schedulely' ); ?></strong>
                            <?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $cron_next ) ); ?>
                        <?php else : ?>
                            <strong><?php esc_html_e( 'No event scheduled right now.', 'schedulely' ); ?></strong>
                            <?php esc_html_e( 'Turn Auto-Schedule on and save, or reactivate the plugin.', 'schedulely' ); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div><!-- .activity-card -->

        </div><!-- .dashboard-grid -->
    </form>

    <form id="schedulely-clear-ai-log-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;" aria-hidden="true">
        <?php wp_nonce_field( 'schedulely_clear_ai_reorder_log', 'schedulely_clear_ai_reorder_nonce' ); ?>
        <input type="hidden" name="action" value="schedulely_clear_ai_reorder_log">
    </form>

    <div class="schedulely-footer">
        <?php
        echo wp_kses_post( sprintf(
            /* translators: 1: heart emoji span 2: company link */
            __( 'Made with %1$s by %2$s', 'schedulely' ),
            '<span style="color:#e25555;">❤️</span>',
            '<a href="https://kraftysprouts.com" target="_blank" rel="noopener noreferrer">Krafty Sprouts Media</a>'
        ) );
        ?>
    </div>

    </div><!-- .schedulely-wrap -->
</div><!-- .wrap -->
