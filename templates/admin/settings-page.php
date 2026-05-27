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
 * @since   1.6.1 Full UI redesign — status bar, 3-stat row, tabbed config card.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Pre-compute values used across multiple sections ──────────────────────────
$scheduler_stat = new Schedulely_Scheduler();
$last_date      = $scheduler_stat->get_last_scheduled_date();
$quota          = (int) get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY );
$posts_count    = $last_date ? $scheduler_stat->count_posts_on_date( $last_date ) : 0;
$is_complete    = $posts_count >= $quota;
unset( $scheduler_stat );

$auto_schedule  = (bool) get_option( 'schedulely_auto_schedule', Schedulely_Defaults::AUTO_SCHEDULE );
$last_run       = (int) get_option( 'schedulely_last_run', 0 );
/* translators: %s: human-readable time difference */
$last_run_text  = $last_run > 0
    ? sprintf( __( 'Run %s ago', 'schedulely' ), human_time_diff( $last_run, time() ) )
    : __( 'Never ran', 'schedulely' );

$cron_next      = wp_next_scheduled( 'schedulely_auto_schedule' );

$wp_ai_ready = function_exists( 'wp_ai_client_prompt' );
try {
    $wp_ai_connected = $wp_ai_ready && (bool) wp_ai_client_prompt( '' )->is_supported_for_text_generation();
} catch ( \Throwable $e ) {
    $wp_ai_connected = false;
}

$current_mode    = get_option( 'schedulely_scheduling_mode', Schedulely_Defaults::SCHEDULING_MODE );
$active_days     = get_option( 'schedulely_active_days', Schedulely_Defaults::ACTIVE_DAYS );
$day_labels      = [ 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun' ];
$all_authors     = get_users( [ 'capability' => 'edit_posts' ] );
$excl_authors    = get_option( 'schedulely_excluded_authors',  Schedulely_Defaults::EXCLUDED_AUTHORS );
$pres_authors    = get_option( 'schedulely_preserved_authors', Schedulely_Defaults::PRESERVED_AUTHORS );
?>
<div class="wrap">
    <?php settings_errors( 'schedulely_messages' ); ?>
    <div class="schedulely-wrap">

    <!-- ═══════════════════════════════════════════════════════════════
         HEADER BAND
    ═══════════════════════════════════════════════════════════════ -->
    <div class="schedulely-page-header">
        <div class="schedulely-page-header-inner">
            <div class="schedulely-page-header-brand">
                <div class="schedulely-page-header-icon" aria-hidden="true">📅</div>
                <div>
                    <h1 class="schedulely-page-title">
                        <?php esc_html_e( 'Schedulely', 'schedulely' ); ?>
                        <span class="schedulely-version-badge"><?php echo esc_html( SCHEDULELY_VERSION ); ?></span>
                    </h1>
                    <p class="schedulely-page-subtitle"><?php esc_html_e( 'Intelligent Post Scheduling for WordPress', 'schedulely' ); ?></p>
                </div>
            </div>
            <div class="schedulely-page-header-actions">
                <a href="https://wordpress.org/support/plugin/schedulely/" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                    <span class="dashicons dashicons-sos" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>
                    <?php esc_html_e( 'Support', 'schedulely' ); ?>
                </a>
                <button type="button" id="schedulely-schedule-now" class="btn btn-primary">
                    <span class="dashicons dashicons-controls-play" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>
                    <?php esc_html_e( 'Run Schedule Now', 'schedulely' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         STATUS BAR  — always visible, key toggles live here
    ═══════════════════════════════════════════════════════════════ -->
    <form method="post" action="" id="schedulely-main-form">
        <?php wp_nonce_field( 'schedulely_settings_save' ); ?>

    <div class="schedulely-status-bar">
        <span class="schedulely-status-bar-label"><?php esc_html_e( 'Status', 'schedulely' ); ?></span>

        <?php if ( $auto_schedule ) : ?>
            <span class="schedulely-status-pill schedulely-pill-green">
                <span class="schedulely-pill-dot"></span>
                <?php esc_html_e( 'Auto-Schedule On', 'schedulely' ); ?>
            </span>
            <span class="schedulely-status-pill schedulely-pill-grey">
                <?php echo esc_html( $last_run_text ); ?>
            </span>
        <?php else : ?>
            <span class="schedulely-status-pill schedulely-pill-red">
                <span class="schedulely-pill-dot"></span>
                <?php esc_html_e( 'Auto-Schedule Off', 'schedulely' ); ?>
            </span>
        <?php endif; ?>

        <?php if ( $cron_next ) : ?>
            <span class="schedulely-status-pill schedulely-pill-grey">
                <?php
                /* translators: %s: date/time of next scheduled cron run */
                echo esc_html( sprintf( __( 'Next cron: %s', 'schedulely' ), wp_date( 'M j, H:i', $cron_next ) ) );
                ?>
            </span>
        <?php endif; ?>

        <div class="schedulely-status-bar-toggles">
            <div class="schedulely-toggle-group">
                <span><?php esc_html_e( 'Auto-Schedule', 'schedulely' ); ?></span>
                <label class="toggle-switch">
                    <input type="checkbox" name="schedulely_auto_schedule" id="schedulely_auto_schedule"
                           value="1" <?php checked( $auto_schedule ); ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="schedulely-toggle-divider"></div>
            <div class="schedulely-toggle-group">
                <span><?php esc_html_e( 'Email Alerts', 'schedulely' ); ?></span>
                <label class="toggle-switch">
                    <input type="checkbox" name="schedulely_email_notifications" id="schedulely_email_notifications"
                           value="1" <?php checked( get_option( 'schedulely_email_notifications', Schedulely_Defaults::EMAIL_NOTIFICATIONS ) ); ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         STAT CARDS  — 3 cards (merged duplicates)
    ═══════════════════════════════════════════════════════════════ -->
    <div class="schedulely-stats-row">

        <!-- Drafts Available -->
        <div class="schedulely-stat-card">
            <div class="schedulely-stat-icon schedulely-icon-blue">
                <span class="dashicons dashicons-format-aside"></span>
            </div>
            <div class="schedulely-stat-body">
                <div class="schedulely-stat-value"><?php echo esc_html( (string) $stats['available_posts'] ); ?></div>
                <div class="schedulely-stat-label"><?php esc_html_e( 'Drafts Available', 'schedulely' ); ?></div>
                <div class="schedulely-stat-sub">
                    <?php
                    /* translators: %s: post count */
                    echo esc_html( sprintf( __( '%s currently in pool', 'schedulely' ), $stats['available_posts'] ) );
                    ?>
                </div>
            </div>
        </div>

        <!-- Furthest Scheduled Date (merged card) -->
        <div class="schedulely-stat-card">
            <div class="schedulely-stat-icon <?php echo $is_complete ? 'schedulely-icon-green' : 'schedulely-icon-orange'; ?>">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="schedulely-stat-body">
                <div class="schedulely-stat-value">
                    <?php echo $last_date ? esc_html( wp_date( 'M j', strtotime( $last_date ) ) ) : esc_html__( 'None', 'schedulely' ); ?>
                </div>
                <div class="schedulely-stat-label"><?php esc_html_e( 'Furthest Scheduled Date', 'schedulely' ); ?></div>
                <div class="schedulely-stat-sub <?php echo $is_complete ? 'schedulely-sub-good' : 'schedulely-sub-warn'; ?>">
                    <?php
                    if ( $last_date ) {
                        /* translators: 1: posts on date 2: daily quota */
                        echo esc_html( sprintf( __( '%1$d/%2$d posts — ', 'schedulely' ), $posts_count, $quota ) );
                        echo $is_complete ? esc_html__( 'quota met ✓', 'schedulely' ) : esc_html__( 'incomplete', 'schedulely' );
                    } else {
                        esc_html_e( 'No posts scheduled yet', 'schedulely' );
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="schedulely-stat-card">
            <div class="schedulely-stat-icon <?php echo $auto_schedule ? 'schedulely-icon-green' : 'schedulely-icon-red'; ?>">
                <span class="dashicons dashicons-heart"></span>
            </div>
            <div class="schedulely-stat-body">
                <div class="schedulely-stat-value">
                    <?php echo $auto_schedule ? esc_html__( 'Active', 'schedulely' ) : esc_html__( 'Paused', 'schedulely' ); ?>
                </div>
                <div class="schedulely-stat-label"><?php esc_html_e( 'System Health', 'schedulely' ); ?></div>
                <div class="schedulely-stat-sub">
                    <?php echo $auto_schedule ? esc_html( $last_run_text ) : esc_html__( 'Enable Auto-Schedule to run automatically', 'schedulely' ); ?>
                </div>
            </div>
        </div>

    </div><!-- /.schedulely-stats-row -->

    <!-- ═══════════════════════════════════════════════════════════════
         MAIN GRID  — tabbed config card + sidebar
    ═══════════════════════════════════════════════════════════════ -->
    <div class="schedulely-main-grid">

        <!-- ── CONFIG CARD ──────────────────────────────────────── -->
        <div class="schedulely-config-card">
            <div class="schedulely-card-header">
                <h2 class="schedulely-card-title"><?php esc_html_e( 'Configuration', 'schedulely' ); ?></h2>
                <button type="submit" name="schedulely_save_settings" class="btn btn-primary schedulely-save-btn">
                    <?php esc_html_e( 'Save Changes', 'schedulely' ); ?>
                </button>
            </div>

            <!-- Capacity pill — sits in tab nav row, never shifts layout -->
            <div class="schedulely-tab-bar">
                <div class="schedulely-tab-nav" role="tablist">
                    <button type="button" class="schedulely-tab-btn active" role="tab" aria-selected="true"  aria-controls="sly-tab-schedule" data-tab="schedule"><?php esc_html_e( 'Schedule', 'schedulely' ); ?></button>
                    <button type="button" class="schedulely-tab-btn"        role="tab" aria-selected="false" aria-controls="sly-tab-queue"    data-tab="queue"   ><?php esc_html_e( 'Queue', 'schedulely' ); ?></button>
                    <button type="button" class="schedulely-tab-btn"        role="tab" aria-selected="false" aria-controls="sly-tab-authors"  data-tab="authors" ><?php esc_html_e( 'Authors', 'schedulely' ); ?></button>
                    <button type="button" class="schedulely-tab-btn"        role="tab" aria-selected="false" aria-controls="sly-tab-ai"       data-tab="ai"      ><?php esc_html_e( 'AI & Notifications', 'schedulely' ); ?></button>
                </div>
                <div class="schedulely-capacity-bar">
                    <span class="schedulely-capacity-pill is-checking" id="schedulely-capacity-pill" aria-live="polite" aria-atomic="true">
                        <span class="schedulely-capacity-pill-dot"></span>
                        <span class="schedulely-capacity-pill-text"><?php esc_html_e( 'Checking…', 'schedulely' ); ?></span>
                    </span>
                    <button type="button" class="schedulely-capacity-details-toggle" id="schedulely-capacity-details-toggle"
                            hidden aria-expanded="false" aria-controls="schedulely-capacity-details">
                        <?php esc_html_e( 'Show suggestions', 'schedulely' ); ?>
                    </button>
                </div>
            </div>

            <div class="schedulely-capacity-details" id="schedulely-capacity-details" hidden>
                <div id="schedulely-suggestions-list"></div>
            </div>

            <!-- ══ TAB: SCHEDULE ══════════════════════════════════ -->
            <div class="schedulely-tab-panel active" id="sly-tab-schedule" role="tabpanel">

                <p class="schedulely-section-label"><?php esc_html_e( 'Content & Volume', 'schedulely' ); ?></p>
                <div class="schedulely-form-row">
                    <div class="schedulely-form-col">
                        <label class="schedulely-field-label" for="schedulely_post_status"><?php esc_html_e( 'Post Status to Monitor', 'schedulely' ); ?></label>
                        <select name="schedulely_post_status" id="schedulely_post_status">
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
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Source status Schedulely watches for posts to schedule.', 'schedulely' ); ?></p>
                    </div>
                    <div class="schedulely-form-col">
                        <label class="schedulely-field-label" for="schedulely_posts_per_day"><?php esc_html_e( 'Posts Per Day', 'schedulely' ); ?></label>
                        <input type="number" name="schedulely_posts_per_day" id="schedulely_posts_per_day"
                               value="<?php echo esc_attr( get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY ) ); ?>"
                               min="1" max="100">
                    </div>
                    <div class="schedulely-form-col">
                        <label class="schedulely-field-label" for="schedulely_pool_size"><?php esc_html_e( 'Pool Size (Max per Run)', 'schedulely' ); ?></label>
                        <input type="number" name="schedulely_pool_size" id="schedulely_pool_size"
                               value="<?php echo esc_attr( get_option( 'schedulely_pool_size', Schedulely_Defaults::MAX_POSTS_PER_RUN ) ); ?>"
                               min="1" max="10000">
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Larger pool = more variety for shuffle & AI.', 'schedulely' ); ?></p>
                    </div>
                </div>

                <div class="schedulely-form-row" style="margin-top:4px;">
                    <div class="schedulely-form-col schedulely-form-col--full">
                        <label class="schedulely-field-label" for="schedulely_post_types"><?php esc_html_e( 'Post Types to Schedule', 'schedulely' ); ?></label>
                        <select name="schedulely_post_types[]" id="schedulely_post_types" class="schedulely-post-type-select" multiple="multiple">
                            <?php
                            $all_post_types = get_post_types( [ 'public' => true ], 'objects' );
                            $current_pt     = get_option( 'schedulely_post_types', Schedulely_Defaults::POST_TYPES );
                            foreach ( $all_post_types as $pt ) {
                                $sel = in_array( $pt->name, $current_pt, true ) ? 'selected' : '';
                                echo '<option value="' . esc_attr( $pt->name ) . '" ' . $sel . '>' . esc_html( $pt->label ) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Select which post types to include. Hold Ctrl/Cmd to select multiple.', 'schedulely' ); ?></p>
                    </div>
                </div>

                <hr class="schedulely-divider">
                <p class="schedulely-section-label"><?php esc_html_e( 'Publishing Window', 'schedulely' ); ?></p>

                <div class="schedulely-form-row">
                    <div class="schedulely-form-col schedulely-form-col--wide">
                        <label class="schedulely-field-label"><?php esc_html_e( 'Time Window', 'schedulely' ); ?></label>
                        <div class="schedulely-time-row">
                            <input type="text" name="schedulely_start_time" id="schedulely_start_time"
                                   value="<?php echo esc_attr( get_option( 'schedulely_start_time', Schedulely_Defaults::START_TIME ) ); ?>"
                                   class="schedulely-timepicker schedulely-time-input">
                            <span class="schedulely-time-arrow" aria-hidden="true">→</span>
                            <input type="text" name="schedulely_end_time" id="schedulely_end_time"
                                   value="<?php echo esc_attr( get_option( 'schedulely_end_time', Schedulely_Defaults::END_TIME ) ); ?>"
                                   class="schedulely-timepicker schedulely-time-input">
                        </div>
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Same day: end after start. Overnight: set end before start on the clock (e.g. 10 PM → 2 AM).', 'schedulely' ); ?></p>
                    </div>
                    <div class="schedulely-form-col">
                        <label class="schedulely-field-label" for="schedulely_min_interval"><?php esc_html_e( 'Min Interval (minutes)', 'schedulely' ); ?></label>
                        <input type="number" name="schedulely_min_interval" id="schedulely_min_interval"
                               value="<?php echo esc_attr( get_option( 'schedulely_min_interval', Schedulely_Defaults::MIN_INTERVAL ) ); ?>"
                               min="1" max="1440">
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Minimum gap between two consecutive posts.', 'schedulely' ); ?></p>
                    </div>
                </div>

                <div style="margin-top:4px;">
                    <label class="schedulely-field-label"><?php esc_html_e( 'Active Days', 'schedulely' ); ?></label>
                    <div class="schedulely-day-pills">
                        <?php foreach ( $day_labels as $day_num => $day_label ) :
                            $chk = in_array( $day_num, $active_days, true ) ? 'checked' : '';
                        ?>
                        <label class="schedulely-day-pill">
                            <input type="checkbox" name="schedulely_active_days[]"
                                   value="<?php echo esc_attr( (string) $day_num ); ?>" <?php echo $chk; ?>>
                            <?php echo esc_html( $day_label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /#sly-tab-schedule -->

            <!-- ══ TAB: QUEUE ═════════════════════════════════════ -->
            <div class="schedulely-tab-panel" id="sly-tab-queue" role="tabpanel" hidden>

                <p class="schedulely-section-label"><?php esc_html_e( 'Scheduling Mode', 'schedulely' ); ?></p>
                <div class="schedulely-mode-cards">
                    <label class="schedulely-mode-card <?php echo $current_mode === 'random' ? 'is-selected' : ''; ?>">
                        <input type="radio" name="schedulely_scheduling_mode" value="random" <?php checked( $current_mode, 'random' ); ?>>
                        <span class="schedulely-mode-badge schedulely-badge-blue"><?php esc_html_e( '~70% efficiency', 'schedulely' ); ?></span>
                        <div class="schedulely-mode-title"><?php esc_html_e( 'Random', 'schedulely' ); ?></div>
                        <p class="schedulely-mode-desc"><?php esc_html_e( 'Posts land at random times in the window. Looks most natural. Some days may fall slightly short of quota.', 'schedulely' ); ?></p>
                    </label>
                    <label class="schedulely-mode-card <?php echo $current_mode === 'sequential' ? 'is-selected' : ''; ?>">
                        <input type="radio" name="schedulely_scheduling_mode" value="sequential" <?php checked( $current_mode, 'sequential' ); ?>>
                        <span class="schedulely-mode-badge schedulely-badge-green"><?php esc_html_e( '100% efficiency', 'schedulely' ); ?></span>
                        <div class="schedulely-mode-title"><?php esc_html_e( 'Sequential', 'schedulely' ); ?></div>
                        <p class="schedulely-mode-desc"><?php esc_html_e( 'Posts spaced perfectly evenly. Always hits quota. Predictable — can look automated.', 'schedulely' ); ?></p>
                    </label>
                    <label class="schedulely-mode-card <?php echo $current_mode === 'hybrid' ? 'is-selected' : ''; ?>">
                        <input type="radio" name="schedulely_scheduling_mode" value="hybrid" <?php checked( $current_mode, 'hybrid' ); ?>>
                        <span class="schedulely-mode-badge schedulely-badge-purple"><?php esc_html_e( '~99% efficiency', 'schedulely' ); ?></span>
                        <div class="schedulely-mode-title"><?php esc_html_e( 'Hybrid', 'schedulely' ); ?></div>
                        <p class="schedulely-mode-desc"><?php esc_html_e( 'Window divided into equal slots; post placed randomly inside each. Hits quota and looks natural. Recommended.', 'schedulely' ); ?></p>
                    </label>
                </div>

                <hr class="schedulely-divider">
                <p class="schedulely-section-label"><?php esc_html_e( 'Queue Order', 'schedulely' ); ?></p>
                <label class="schedulely-checkbox-row">
                    <input type="checkbox" name="schedulely_shuffle_queue" id="schedulely_shuffle_queue"
                           value="1" <?php checked( get_option( 'schedulely_shuffle_queue', Schedulely_Defaults::SHUFFLE_QUEUE ) ); ?>>
                    <div>
                        <span class="schedulely-chk-label"><?php esc_html_e( 'Shuffle posts before assigning dates', 'schedulely' ); ?></span>
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Randomises which eligible posts get the next slots instead of always using oldest-first draft date.', 'schedulely' ); ?></p>
                    </div>
                </label>

            </div><!-- /#sly-tab-queue -->

            <!-- ══ TAB: AUTHORS ═══════════════════════════════════ -->
            <div class="schedulely-tab-panel" id="sly-tab-authors" role="tabpanel" hidden>

                <p class="schedulely-section-label"><?php esc_html_e( 'Author Assignment', 'schedulely' ); ?></p>
                <label class="schedulely-checkbox-row" style="margin-bottom:20px;">
                    <input type="checkbox" name="schedulely_randomize_authors" id="schedulely_randomize_authors"
                           value="1" <?php checked( get_option( 'schedulely_randomize_authors', Schedulely_Defaults::RANDOMIZE_AUTHORS ) ); ?>>
                    <div>
                        <span class="schedulely-chk-label"><?php esc_html_e( 'Randomly assign authors to scheduled posts', 'schedulely' ); ?></span>
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Each post gets a random eligible author on scheduling. Preserved authors are never reassigned.', 'schedulely' ); ?></p>
                    </div>
                </label>

                <div class="schedulely-form-row">
                    <div class="schedulely-form-col">
                        <label class="schedulely-field-label" for="schedulely_excluded_authors"><?php esc_html_e( 'Excluded Authors', 'schedulely' ); ?></label>
                        <select name="schedulely_excluded_authors[]" id="schedulely_excluded_authors" class="schedulely-author-select" multiple="multiple">
                            <?php foreach ( $all_authors as $u ) :
                                $sel = in_array( $u->ID, $excl_authors, true ) ? 'selected' : '';
                                echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . $sel . '>' . esc_html( $u->display_name ) . ' (' . esc_html( $u->user_login ) . ')</option>';
                            endforeach; ?>
                        </select>
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Never assigned as author during scheduling.', 'schedulely' ); ?></p>
                    </div>
                    <div class="schedulely-form-col">
                        <label class="schedulely-field-label" for="schedulely_preserved_authors"><?php esc_html_e( 'Preserved Authors', 'schedulely' ); ?></label>
                        <select name="schedulely_preserved_authors[]" id="schedulely_preserved_authors" class="schedulely-author-select" multiple="multiple">
                            <?php foreach ( $all_authors as $u ) :
                                $sel = in_array( $u->ID, $pres_authors, true ) ? 'selected' : '';
                                echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . $sel . '>' . esc_html( $u->display_name ) . ' (' . esc_html( $u->user_login ) . ')</option>';
                            endforeach; ?>
                        </select>
                        <p class="schedulely-field-hint"><?php esc_html_e( 'Author kept as-is — Schedulely won\'t replace them.', 'schedulely' ); ?></p>
                    </div>
                </div>

            </div><!-- /#sly-tab-authors -->

            <!-- ══ TAB: AI & NOTIFICATIONS ════════════════════════ -->
            <div class="schedulely-tab-panel" id="sly-tab-ai" role="tabpanel" hidden>

                <p class="schedulely-section-label"><?php esc_html_e( 'AI Series Spacing', 'schedulely' ); ?></p>

                <?php if ( $wp_ai_ready && $wp_ai_connected ) : ?>
                    <div class="schedulely-ai-notice schedulely-ai-notice--connected">
                        <span class="schedulely-ai-notice-icon">✅</span>
                        <div class="schedulely-ai-notice-text">
                            <strong><?php esc_html_e( 'AI provider connected via WordPress Connectors.', 'schedulely' ); ?></strong>
                            <?php esc_html_e( 'No API key needed in Schedulely — managed in', 'schedulely' ); ?>
                            <a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Settings → Connectors', 'schedulely' ); ?></a>.
                        </div>
                    </div>
                    <label class="schedulely-checkbox-row" style="margin-bottom:12px;">
                        <input type="checkbox" name="schedulely_ai_order_enabled" id="schedulely_ai_order_enabled"
                               value="1" <?php checked( get_option( 'schedulely_ai_order_enabled', Schedulely_Defaults::AI_ORDER_ENABLED ) ); ?>>
                        <div>
                            <span class="schedulely-chk-label"><?php esc_html_e( 'Use AI to order the queue before scheduling', 'schedulely' ); ?></span>
                            <p class="schedulely-field-hint"><?php esc_html_e( 'Skips shuffle when AI succeeds. Only runs on manual "Run Schedule Now" — never on cron.', 'schedulely' ); ?></p>
                        </div>
                    </label>
                    <p>
                        <button type="button" class="btn btn-secondary" id="schedulely-test-ai-connection"><?php esc_html_e( 'Test connection', 'schedulely' ); ?></button>
                    </p>
                    <p class="schedulely-field-hint" id="schedulely-ai-test-result" style="display:none; margin-top:8px;" aria-live="polite"></p>

                <?php elseif ( $wp_ai_ready && ! $wp_ai_connected ) : ?>
                    <div class="schedulely-ai-notice schedulely-ai-notice--warn">
                        <span class="schedulely-ai-notice-icon">⚠️</span>
                        <div class="schedulely-ai-notice-text">
                            <strong><?php esc_html_e( 'No AI provider connected.', 'schedulely' ); ?></strong>
                            <?php esc_html_e( 'Configure one in', 'schedulely' ); ?>
                            <a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Settings → Connectors', 'schedulely' ); ?></a>.
                            <?php esc_html_e( 'No API key needed in Schedulely — managed centrally.', 'schedulely' ); ?>
                        </div>
                    </div>
                    <label class="schedulely-checkbox-row" style="opacity:.5; margin-bottom:12px;">
                        <input type="checkbox" name="schedulely_ai_order_enabled" id="schedulely_ai_order_enabled" value="1" disabled>
                        <div><span class="schedulely-chk-label"><?php esc_html_e( 'Use AI to order the queue (requires a connected provider)', 'schedulely' ); ?></span></div>
                    </label>

                <?php else : ?>
                    <!-- Legacy path — pre-WP-7.0 -->
                    <p class="schedulely-field-hint" style="margin-bottom:12px;">
                        <?php echo wp_kses(
                            sprintf( __( 'Defaults target the DeepSeek OpenAI-compatible API (<a href="%s" target="_blank" rel="noopener noreferrer">API overview</a>). You can change the base URL and model for any compatible provider.', 'schedulely' ), esc_url( 'https://apidog.com/blog/how-to-use-deepseek-v4-api/' ) ),
                            [ 'a' => [ 'href' => true, 'target' => true, 'rel' => true ] ]
                        ); ?>
                    </p>
                    <label class="schedulely-checkbox-row" style="margin-bottom:16px;">
                        <input type="checkbox" name="schedulely_ai_order_enabled" id="schedulely_ai_order_enabled"
                               value="1" <?php checked( get_option( 'schedulely_ai_order_enabled', Schedulely_Defaults::AI_ORDER_ENABLED ) ); ?>>
                        <div><span class="schedulely-chk-label"><?php esc_html_e( 'Use AI to order the queue before scheduling', 'schedulely' ); ?></span></div>
                    </label>
                    <div class="schedulely-form-row" style="margin-bottom:12px;">
                        <div class="schedulely-form-col">
                            <label class="schedulely-field-label" for="schedulely_ai_base_url"><?php esc_html_e( 'API Base URL', 'schedulely' ); ?></label>
                            <input type="url" name="schedulely_ai_base_url" id="schedulely_ai_base_url"
                                   value="<?php echo esc_attr( get_option( 'schedulely_ai_base_url', Schedulely_Defaults::AI_BASE_URL ) ); ?>"
                                   placeholder="https://api.deepseek.com/v1" autocomplete="off">
                        </div>
                        <div class="schedulely-form-col">
                            <label class="schedulely-field-label" for="schedulely_ai_model"><?php esc_html_e( 'Model', 'schedulely' ); ?></label>
                            <input type="text" name="schedulely_ai_model" id="schedulely_ai_model"
                                   value="<?php echo esc_attr( get_option( 'schedulely_ai_model', Schedulely_Defaults::AI_MODEL ) ); ?>"
                                   placeholder="deepseek-v4-flash" autocomplete="off">
                        </div>
                    </div>
                    <div style="margin-bottom:14px;">
                        <?php if ( $stored_ai_key_len > 0 ) :
                            $masked_key = strlen( $stored_ai_key_raw ) > 8
                                ? substr( $stored_ai_key_raw, 0, 4 ) . str_repeat( '•', max( 4, strlen( $stored_ai_key_raw ) - 8 ) ) . substr( $stored_ai_key_raw, -4 )
                                : str_repeat( '•', strlen( $stored_ai_key_raw ) );
                        ?>
                            <label class="schedulely-field-label"><?php esc_html_e( 'Current API Key', 'schedulely' ); ?></label>
                            <input type="text" readonly value="<?php echo esc_attr( $masked_key ); ?>"
                                   style="font-family:monospace; max-width:420px; margin-bottom:10px;" autocomplete="off">
                            <label class="schedulely-field-label" for="schedulely_ai_api_key"><?php esc_html_e( 'Replace API Key (optional)', 'schedulely' ); ?></label>
                        <?php else : ?>
                            <label class="schedulely-field-label" for="schedulely_ai_api_key"><?php esc_html_e( 'API Key', 'schedulely' ); ?></label>
                        <?php endif; ?>
                        <p style="margin-bottom:8px;">
                            <button type="button" class="btn btn-secondary" id="schedulely-test-ai-connection"><?php esc_html_e( 'Test connection', 'schedulely' ); ?></button>
                        </p>
                        <p class="schedulely-field-hint" id="schedulely-ai-test-result" style="display:none; margin-top:8px;" aria-live="polite"></p>
                        <input type="password" name="schedulely_ai_api_key" id="schedulely_ai_api_key"
                               style="max-width:420px;" value="" autocomplete="new-password"
                               placeholder="<?php echo $stored_ai_key_len > 0 ? esc_attr__( 'Leave blank to keep saved key', 'schedulely' ) : esc_attr__( 'Enter your API key', 'schedulely' ); ?>">
                    </div>
                    <label class="schedulely-checkbox-row">
                        <input type="checkbox" name="schedulely_ai_clear_api_key" id="schedulely_ai_clear_api_key" value="1">
                        <div><span class="schedulely-chk-label"><?php esc_html_e( 'Remove stored API key on save', 'schedulely' ); ?></span></div>
                    </label>
                <?php endif; ?>

                <!-- AI Reorder Log -->
                <hr class="schedulely-divider">
                <p class="schedulely-section-label"><?php esc_html_e( 'AI Reorder Log', 'schedulely' ); ?></p>
                <p class="schedulely-field-hint" style="margin-bottom:10px;">
                    <?php esc_html_e( 'Each scheduling run that calls the reorder API records outcome, HTTP status, token usage, error code, and response excerpts.', 'schedulely' ); ?>
                </p>
                <?php if ( empty( $ai_reorder_log ) ) : ?>
                    <p class="schedulely-field-hint"><?php esc_html_e( 'No reorder API attempts logged yet.', 'schedulely' ); ?></p>
                <?php else : ?>
                    <div class="schedulely-log-viewer">
                        <?php foreach ( $ai_reorder_log as $row ) :
                            if ( ! is_array( $row ) ) continue;
                            $outcome  = isset( $row['outcome'] ) ? (string) $row['outcome'] : '';
                            $at       = isset( $row['at_site'] ) ? (string) $row['at_site'] : '';
                            $badge_cls = ( 'success' === $outcome ) ? 'schedulely-log-success' : 'schedulely-log-error';
                        ?>
                            <div class="schedulely-log-row">
                                <div style="margin-bottom:4px;">
                                    <span class="schedulely-log-badge <?php echo esc_attr( $badge_cls ); ?>">
                                        <?php echo esc_html( strtoupper( $outcome !== '' ? $outcome : '?' ) ); ?>
                                    </span>
                                    <span style="color:#50575e; font-size:12px;"><?php echo esc_html( $at ); ?></span>
                                </div>
                                <?php
                                $parts = [];
                                if ( ! empty( $row['model'] ) )            $parts[] = 'model: ' . $row['model'];
                                if ( isset( $row['post_count'] ) )         $parts[] = 'posts: ' . (int) $row['post_count'];
                                if ( isset( $row['http_code'] ) && null !== $row['http_code'] ) $parts[] = 'http: ' . (int) $row['http_code'];
                                if ( isset( $row['usage_total_tokens'] ) && null !== $row['usage_total_tokens'] ) $parts[] = 'tokens: ' . (int) $row['usage_total_tokens'];
                                if ( ! empty( $row['error_code'] ) )       $parts[] = 'error_code: ' . $row['error_code'];
                                if ( ! empty( $row['error_message'] ) )    $parts[] = 'error: ' . $row['error_message'];
                                if ( ! empty( $row['note'] ) )             $parts[] = 'note: ' . $row['note'];
                                echo '<div style="color:#1d2327; font-size:12px;">' . esc_html( implode( ' · ', $parts ) ) . '</div>';
                                if ( ! empty( $row['assistant_excerpt'] ) ) {
                                    echo '<div style="margin-top:5px;"><strong>assistant_excerpt</strong><pre class="schedulely-log-pre">' . esc_html( $row['assistant_excerpt'] ) . '</pre></div>';
                                }
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php submit_button( __( 'Clear AI reorder log', 'schedulely' ), 'secondary', 'schedulely_clear_ai_log', false, [ 'style' => 'margin-top:0;', 'form' => 'schedulely-clear-ai-log-form' ] ); ?>
                <?php endif; ?>

                <!-- Notifications -->
                <hr class="schedulely-divider">
                <p class="schedulely-section-label"><?php esc_html_e( 'Notifications', 'schedulely' ); ?></p>
                <div style="margin-bottom:16px;">
                    <label class="schedulely-field-label" for="schedulely_notification_users"><?php esc_html_e( 'Notification Recipients', 'schedulely' ); ?></label>
                    <select name="schedulely_notification_users[]" id="schedulely_notification_users" class="schedulely-notification-select" multiple="multiple">
                        <?php
                        $notify_users   = get_users( [ 'capability' => 'publish_posts' ] );
                        $selected_users = get_option( 'schedulely_notification_users', Schedulely_Defaults::NOTIFICATION_USERS );
                        if ( empty( $selected_users ) ) $selected_users = [ get_current_user_id() ];
                        foreach ( $notify_users as $u ) {
                            $sel = in_array( $u->ID, $selected_users, true ) ? 'selected' : '';
                            echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . $sel . '>' . esc_html( $u->display_name ) . ' (' . esc_html( $u->user_email ) . ')</option>';
                        }
                        ?>
                    </select>
                </div>
                <?php if ( $wp_ai_ready ) : ?>
                <label class="schedulely-checkbox-row">
                    <input type="checkbox" name="schedulely_ai_email_summary" id="schedulely_ai_email_summary"
                           value="1" <?php checked( get_option( 'schedulely_ai_email_summary', false ) ); ?>>
                    <div>
                        <span class="schedulely-chk-label"><?php esc_html_e( 'Include AI-generated summary in notification emails', 'schedulely' ); ?></span>
                        <p class="schedulely-field-hint"><?php esc_html_e( 'A 2–3 sentence plain-text summary of each scheduling run. Uses a small number of tokens per email.', 'schedulely' ); ?></p>
                    </div>
                </label>
                <?php endif; ?>

            </div><!-- /#sly-tab-ai -->

        </div><!-- /.schedulely-config-card -->

        <!-- ── SIDEBAR ───────────────────────────────────────────── -->
        <div class="schedulely-sidebar">

            <div class="schedulely-side-card">
                <div class="schedulely-side-card-header">
                    <h3 class="schedulely-side-card-title"><?php esc_html_e( 'Upcoming Posts', 'schedulely' ); ?></h3>
                    <?php
                    $sidebar_post_types = get_option( 'schedulely_post_types', Schedulely_Defaults::POST_TYPES );
                    if ( count( $sidebar_post_types ) === 1 ) {
                        echo '<a href="' . esc_url( $this->get_scheduled_posts_url() ) . '" style="font-size:11px;">' . esc_html__( 'View All', 'schedulely' ) . '</a>';
                    } else {
                        echo '<select id="schedulely-view-posts-type" style="font-size:11px; padding:2px 20px 2px 6px; border:1px solid #ddd; border-radius:3px; background:#fff; cursor:pointer;">';
                        echo '<option value="">' . esc_html__( 'View…', 'schedulely' ) . '</option>';
                        echo '<option value="' . esc_url( admin_url( 'edit.php?post_status=future' ) ) . '">' . esc_html__( 'All Types', 'schedulely' ) . '</option>';
                        foreach ( $sidebar_post_types as $pt ) {
                            $pt_obj   = get_post_type_object( $pt );
                            $pt_label = $pt_obj ? $pt_obj->labels->name : $pt;
                            echo '<option value="' . esc_url( $this->get_scheduled_posts_url( $pt ) ) . '">' . esc_html( $pt_label ) . '</option>';
                        }
                        echo '</select>';
                    }
                    ?>
                </div>
                <?php $this->render_upcoming_posts_list(); ?>

                <div class="schedulely-cron-info">
                    <strong><?php esc_html_e( 'WP-Cron', 'schedulely' ); ?></strong>
                    <?php esc_html_e( '— Hook:', 'schedulely' ); ?>
                    <code>schedulely_auto_schedule</code>.
                    <?php esc_html_e( 'Recurrence: twicedaily (~12 hrs).', 'schedulely' ); ?>
                    <?php if ( false !== $cron_next ) : ?>
                        <strong><?php esc_html_e( 'Next run:', 'schedulely' ); ?></strong>
                        <?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $cron_next ) ); ?>
                    <?php else : ?>
                        <strong><?php esc_html_e( 'No event scheduled.', 'schedulely' ); ?></strong>
                        <?php esc_html_e( 'Enable Auto-Schedule and save.', 'schedulely' ); ?>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /.schedulely-sidebar -->

    </div><!-- /.schedulely-main-grid -->
    </form>

    <form id="schedulely-clear-ai-log-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;" aria-hidden="true">
        <?php wp_nonce_field( 'schedulely_clear_ai_reorder_log', 'schedulely_clear_ai_reorder_nonce' ); ?>
        <input type="hidden" name="action" value="schedulely_clear_ai_reorder_log">
    </form>

    <div class="schedulely-footer">
        <?php
        echo wp_kses_post( sprintf(
            /* translators: 1: heart emoji 2: company link */
            __( 'Made with %1$s by %2$s', 'schedulely' ),
            '<span aria-hidden="true">❤️</span>',
            '<a href="https://kraftysprouts.com" target="_blank" rel="noopener noreferrer">Krafty Sprouts Media</a>'
        ) );
        ?>
    </div>

    </div><!-- /.schedulely-wrap -->
</div><!-- /.wrap -->
