<?php
/**
 * Plugin Name: Schedulely
 * Plugin URI: https://kraftysprouts.com
 * Description: Intelligently schedule posts from any status with smart deficit tracking, random author assignment, and customizable time windows.
 * Version: 1.8.1
 * Author: Krafty Sprouts Media, LLC
 * Author URI: https://kraftysprouts.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: schedulely
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 6.8
 * Requires PHP: 8.2
 *
 * @package Schedulely
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SCHEDULELY_VERSION', '1.8.1');
define('SCHEDULELY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCHEDULELY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCHEDULELY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load plugin text domain for translations
 */
function schedulely_load_textdomain()
{
    load_plugin_textdomain('schedulely', false, dirname(SCHEDULELY_PLUGIN_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'schedulely_load_textdomain');

/**
 * Load plugin classes via autoloader.
 *
 * The autoloader maps Schedulely_* class names to includes/class-{slug}.php.
 * Individual require_once calls are no longer needed.
 *
 * @since 1.6.0 Replaced manual require_once chain with spl_autoload_register.
 */
require_once SCHEDULELY_PLUGIN_DIR . 'includes/class-defaults.php';
require_once SCHEDULELY_PLUGIN_DIR . 'includes/autoloader.php';

/**
 * Initialize plugin update checker.
 *
 * Loads the GitHub-based update checker for development builds and
 * self-hosted distributions only. This function is a no-op when
 * SCHEDULELY_WPORG_BUILD is defined (the wp.org release zip defines
 * this constant), or when the library is simply not present.
 *
 * The wp.org release zip excludes vendor/plugin-update-checker/ via
 * .distignore, so updates for wp.org-hosted installs flow through the
 * WordPress.org update API instead.
 *
 * @since 1.3.0
 * @since 1.6.0 Gated behind SCHEDULELY_WPORG_BUILD constant check.
 */
function schedulely_init_update_checker()
{
    // Never run on wp.org builds — the constant is defined by the build script.
    if (defined('SCHEDULELY_WPORG_BUILD')) {
        return;
    }

    // Only load update checker if the library exists (excluded from wp.org zip).
    if (file_exists(SCHEDULELY_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php')) {
        require_once SCHEDULELY_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

        $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/Krafty-Sprouts-Media-LLC/Schedulely',
            __FILE__,
            'schedulely'
        );

        // Enable release assets (zip files) for automatic updates.
        $update_checker->getVcsApi()->enableReleaseAssets();
    }
}
add_action('plugins_loaded', 'schedulely_init_update_checker', 5); // Priority 5 to run early

/**
 * Initialize plugin settings
 */
function schedulely_init()
{
    $settings = new Schedulely_Settings();
    $settings->init();

    // Register WordPress 7.0 Abilities when the API is available.
    if ( function_exists( 'wp_register_ability' ) ) {
        ( new Schedulely_Abilities() )->register_hooks();
    }
}
add_action('plugins_loaded', 'schedulely_init');

/**
 * Add Settings link on plugin page
 * 
 * @param array $links Existing plugin action links
 * @return array Modified links
 */
function schedulely_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('tools.php?page=schedulely') . '">' . __('Settings', 'schedulely') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . SCHEDULELY_PLUGIN_BASENAME, 'schedulely_plugin_action_links');

/**
 * Plugin activation hook
 * 
 * NOTE: This only runs on FIRST activation, NOT on updates.
 * User settings are preserved during plugin updates.
 * We use add_option() instead of update_option() to avoid overwriting existing settings.
 */
function schedulely_activate()
{
    // Set default options (only if they don't exist - preserves user settings)
    add_option('schedulely_post_status', 'draft');
    add_option('schedulely_posts_per_day', 8);
    add_option('schedulely_start_time', '5:00 PM');
    add_option('schedulely_end_time', '11:00 PM');
    add_option('schedulely_active_days', [1, 2, 3, 4, 5, 6, 0]); // Mon-Sun
    add_option('schedulely_min_interval', 40);
    add_option('schedulely_shuffle_queue', true);
    add_option('schedulely_pool_size', Schedulely_Defaults::MAX_POSTS_PER_RUN); // Max posts fetched per run
    add_option('schedulely_scheduling_mode', Schedulely_Defaults::SCHEDULING_MODE); // random | sequential | hybrid
    add_option('schedulely_ai_order_enabled', false);
    add_option('schedulely_ordering_method', Schedulely_Defaults::ORDERING_METHOD); // ai | php
    add_option('schedulely_ai_us_timezone_ordering', Schedulely_Defaults::AI_US_TIMEZONE_ORDERING);
    add_option('schedulely_ai_api_key', '');
    add_option('schedulely_ai_base_url', 'https://api.deepseek.com/v1');
    add_option('schedulely_ai_model', 'deepseek-v4-flash');
    add_option('schedulely_randomize_authors', false);
    add_option('schedulely_excluded_authors', []);
    add_option('schedulely_preserved_authors', []); // Authors whose posts should not be randomized
    add_option('schedulely_post_types', ['post']); // Post types to schedule (default: post for backward compatibility)
    add_option('schedulely_auto_schedule', false); // CRITICAL: Changed to false - prevents auto-run on activation
    add_option('schedulely_email_notifications', true);
    add_option('schedulely_ai_email_summary', false); // Opt-in AI summary in notification emails
    add_option('schedulely_notification_users', []); // Empty array - will default to current user on settings page
    add_option('schedulely_version', SCHEDULELY_VERSION);

    // Schedule cron event (runs twice daily)
    if (!wp_next_scheduled('schedulely_auto_schedule')) {
        wp_schedule_event(time(), 'twicedaily', 'schedulely_auto_schedule');
    }
}
register_activation_hook(__FILE__, 'schedulely_activate');

/**
 * Plugin deactivation hook
 */
function schedulely_deactivate()
{
    // Clear cron event
    $timestamp = wp_next_scheduled('schedulely_auto_schedule');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'schedulely_auto_schedule');
    }
}
register_deactivation_hook(__FILE__, 'schedulely_deactivate');

/**
 * Register model preferences for the AI queue reorder task.
 *
 * When wp_ai_client_prompt() is used (WP 7.0+ path), this filter steers the
 * platform toward fast, cheap, JSON-capable models. The reorder prompt is
 * structured-output-only — there is no value in using a reasoning model here.
 *
 * Only applies during an active reorder call (guarded by the
 * schedulely_pre_ai_reorder action flag).
 *
 * @since 1.6.0
 */
add_filter( 'wpai_preferred_text_models', function ( array $models ): array {
    if ( ! did_action( 'schedulely_pre_ai_reorder' ) ) {
        return $models;
    }
    /**
     * Preferred models for AI queue reordering.
     *
     * DeepSeek is listed first — it is the cheapest option for structured-JSON
     * tasks, its API natively supports response_format json_object, and it is
     * the original default provider for this feature.
     *
     * Model IDs (verified May 2026 — https://api-docs.deepseek.com/quick_start/pricing):
     *   deepseek   : deepseek-v4-flash      → current main fast/cheap model.
     *   deepseek   : deepseek-v4-pro        → current higher-tier model.
     *   deepseek   : deepseek-chat          → legacy alias, "will be deprecated";
     *                                          reroutes to deepseek-v4-flash in
     *                                          NON-thinking mode. Listed as a fallback
     *                                          because the installed DeepSeek connector
     *                                          may only expose the legacy IDs, and this
     *                                          one is the fast (non-reasoning) path.
     *   google     : gemini-3.1-flash-lite  → GA 2026-05-07; replaces deprecated gemini-2.5-flash.
     *   google     : gemini-3-flash-preview → preview tier.
     *   openai     : gpt-5.4-mini
     *   anthropic  : claude-sonnet-4-6
     *
     * IMPORTANT: deepseek-reasoner is deliberately NOT listed. It reroutes to
     * deepseek-v4-flash in THINKING mode, which spends thousands of tokens on
     * internal reasoning — useless for a permutation task and the likely cause of
     * the multi-minute, ~29k-token runs observed on large pools. We only ever want
     * the non-thinking flash model here.
     *
     * @since 1.6.0
     * @since 1.7.14 Added deepseek-chat fallback; documented the thinking-mode pitfall.
     */
    $preferred = [
        [ 'deepseek',  'deepseek-v4-flash' ],
        [ 'deepseek',  'deepseek-v4-pro' ],
        [ 'deepseek',  'deepseek-chat' ],
        [ 'google',    'gemini-3.1-flash-lite' ],
        [ 'google',    'gemini-3-flash-preview' ],
        [ 'openai',    'gpt-5.4-mini' ],
        [ 'anthropic', 'claude-sonnet-4-6' ],
    ];

    // Honor the user's model selection (Tools → Schedulely): put it first so it
    // is tried before the curated fallbacks. Kept as a [provider, model] tuple
    // for format consistency; provider defaults to deepseek (this feature's
    // primary provider) and is filterable for other connectors.
    $selected = trim( (string) get_option( 'schedulely_ai_model', Schedulely_Defaults::AI_MODEL ) );
    if ( '' !== $selected ) {
        $provider = (string) apply_filters( 'schedulely_ai_model_provider', 'deepseek', $selected );
        array_unshift( $preferred, [ $provider, $selected ] );
    }

    return $preferred;
} );

/**
 * WordPress cron hook for automatic scheduling.
 *
 * Wrapped in try/catch so any uncaught exception does not silently kill
 * the cron process. Exceptions are logged and an error notification email
 * is sent if email notifications are enabled.
 *
 * @since 1.0.0
 * @since 1.6.0 Added try/catch and error notification on failure.
 */
function schedulely_run_auto_schedule()
{
    if ( ! get_option( 'schedulely_auto_schedule', Schedulely_Defaults::AUTO_SCHEDULE ) ) {
        return;
    }

    try {
        $scheduler = new Schedulely_Scheduler();
        $results   = $scheduler->run_schedule();

        if ( get_option( 'schedulely_email_notifications', Schedulely_Defaults::EMAIL_NOTIFICATIONS ) ) {
            $notifier = new Schedulely_Notifications();
            $notifier->send_scheduling_notification( $results );
        }

        update_option( 'schedulely_last_run', time() );

    } catch ( \Throwable $e ) {
        schedulely_log_error( 'Cron scheduling exception: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ] );

        if ( get_option( 'schedulely_email_notifications', Schedulely_Defaults::EMAIL_NOTIFICATIONS ) ) {
            $notifier = new Schedulely_Notifications();
            $notifier->send_error_notification( 'Cron run failed: ' . $e->getMessage() );
        }
    }
}
add_action('schedulely_auto_schedule', 'schedulely_run_auto_schedule');

/**
 * AJAX handler for manual scheduling
 */
function schedulely_ajax_manual_schedule()
{
    // Check nonce
    check_ajax_referer('schedulely_admin', 'nonce');

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error([
            'message' => __('Permission denied.', 'schedulely')
        ]);
    }

    // Run scheduler
    try {
        $scheduler = new Schedulely_Scheduler();
        // Pass true to allow AI queue reordering — the admin is waiting,
        // so synchronous HTTP timeouts are acceptable and visible to the user.
        $results = $scheduler->run_schedule( true );

        if ($results['success']) {
            // Update last run timestamp
            update_option('schedulely_last_run', time());

            // Send notification if enabled
            if (get_option('schedulely_email_notifications', true)) {
                $notifier = new Schedulely_Notifications();
                $notifier->send_scheduling_notification($results);
            }

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully scheduled %d posts!', 'schedulely'),
                    $results['scheduled_count']
                ),
                'data' => $results
            ]);
        } else {
            wp_send_json_error([
                'message' => $results['message']
            ]);
        }
    } catch (\Throwable $e) {
        schedulely_log_error('AJAX scheduling error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => __('An error occurred. Please check the debug log.', 'schedulely')
        ]);
    }
}
add_action('wp_ajax_schedulely_manual_schedule', 'schedulely_ajax_manual_schedule');

/**
 * Check and handle plugin updates
 * 
 * This runs on every page load to detect version changes and perform
 * any necessary data migrations while preserving user settings.
 */
function schedulely_check_version()
{
    $current_version = get_option('schedulely_version', '0');

    if (version_compare($current_version, SCHEDULELY_VERSION, '<')) {
        schedulely_upgrade($current_version);
        update_option('schedulely_version', SCHEDULELY_VERSION);

        // Log the upgrade for debugging
        schedulely_log_error(sprintf(
            'Schedulely upgraded from version %s to %s. User settings preserved.',
            $current_version,
            SCHEDULELY_VERSION
        ));
    }
}
add_action('plugins_loaded', 'schedulely_check_version');

/**
 * Handle upgrades between versions
 * 
 * This function performs version-specific data migrations while preserving
 * all user settings. Settings are never reset during updates.
 * 
 * @param string $from_version Previous version number
 */
function schedulely_upgrade($from_version)
{
    // Version-specific migrations go here
    // User settings are automatically preserved - we only add/modify as needed

    if (version_compare($from_version, '1.6.0', '<')) {
        add_option('schedulely_pool_size', Schedulely_Defaults::MAX_POSTS_PER_RUN);
        add_option('schedulely_ai_email_summary', false);
        add_option('schedulely_scheduling_mode', Schedulely_Defaults::SCHEDULING_MODE);

        // Migrate legacy single-email option to the multi-user array.
        // schedulely_notification_email was removed in 1.0.2 but may exist on very old installs.
        $legacy_email = get_option( 'schedulely_notification_email', '' );
        if ( '' !== $legacy_email && empty( get_option( 'schedulely_notification_users', [] ) ) ) {
            $user = get_user_by( 'email', $legacy_email );
            if ( $user ) {
                add_option( 'schedulely_notification_users', [ $user->ID ] );
            }
        }
    }

    if (version_compare($from_version, '1.3.7', '<')) {
        add_option('schedulely_shuffle_queue', true);
    }

    if (version_compare($from_version, '1.4.0', '<')) {
        add_option('schedulely_ai_order_enabled', false);
        add_option('schedulely_ai_api_key', '');
        add_option('schedulely_ai_base_url', 'https://api.deepseek.com/v1');
        add_option('schedulely_ai_model', 'deepseek-v4-flash');
    }

    if (version_compare($from_version, '1.4.3', '<')) {
        $ai_base = get_option('schedulely_ai_base_url');
        if (!is_string($ai_base) || '' === trim($ai_base)) {
            update_option('schedulely_ai_base_url', 'https://api.deepseek.com/v1');
        }
        $ai_model = get_option('schedulely_ai_model');
        if (!is_string($ai_model) || '' === trim($ai_model)) {
            update_option('schedulely_ai_model', 'deepseek-v4-flash');
        }
    }

    if (version_compare($from_version, '1.8.0', '<')) {
        // Default existing installs to the AI ordering method to preserve
        // current behavior; the deterministic PHP method is opt-in.
        add_option('schedulely_ordering_method', Schedulely_Defaults::ORDERING_METHOD);
    }

    // CRITICAL FIX: Clear old hourly cron and reschedule with twicedaily (v1.0.8+)
    if (version_compare($from_version, '1.0.8', '<')) {
        // Clear ANY existing cron schedule (hourly or otherwise)
        $timestamp = wp_next_scheduled('schedulely_auto_schedule');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'schedulely_auto_schedule');
        }
        // Reschedule with correct frequency
        wp_schedule_event(time(), 'twicedaily', 'schedulely_auto_schedule');
        schedulely_log_error('Cron schedule updated from hourly to twicedaily during upgrade', [
            'from_version' => $from_version,
            'to_version' => SCHEDULELY_VERSION
        ]);
    }

    // Ensure cron is scheduled (in case it was lost)
    if (!wp_next_scheduled('schedulely_auto_schedule')) {
        wp_schedule_event(time(), 'twicedaily', 'schedulely_auto_schedule');
    }
}

/**
 * Log errors to WordPress debug log.
 *
 * @since 1.0.0
 * @since 1.6.0 Uses wp_json_encode instead of print_r for compact, greppable output.
 *
 * @param string $message Error message to log.
 * @param array  $data    Additional context data.
 */
function schedulely_log_error($message, $data = [])
{
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log(sprintf(
            '[Schedulely] %s%s',
            $message,
            ! empty( $data ) ? ' | ' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : ''
        ));
    }
}

/**
 * Shorten text for AI reorder log entries (no HTML).
 *
 * @since 1.5.4
 * @param string $text    Raw text.
 * @param int    $max_len Max characters before truncation.
 * @return string
 */
function schedulely_ai_log_sanitize_excerpt($text, $max_len = 1200)
{
    if (!is_string($text)) {
        return '';
    }

    $text = wp_strip_all_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    if (strlen($text) > $max_len) {
        return substr($text, 0, $max_len) . '…';
    }

    return $text;
}

/**
 * Append one AI queue-reorder attempt to the rolling log (Tools → Schedulely).
 *
 * Disable with: add_filter( 'schedulely_ai_reorder_logging_enabled', '__return_false' );
 * Cap entries: add_filter( 'schedulely_ai_reorder_log_max_entries', fn() => 40 );
 *
 * @since 1.5.4
 * @param array<string, mixed> $entry Keys: outcome, model, post_count, http_code, usage_total_tokens,
 *                                    error_code, error_message, assistant_excerpt, raw_excerpt, note.
 */
function schedulely_append_ai_reorder_log(array $entry)
{
    if (!apply_filters('schedulely_ai_reorder_logging_enabled', true)) {
        return;
    }

    $entry['at_site'] = wp_date('Y-m-d H:i:s');
    $entry['at_utc'] = gmdate('Y-m-d H:i:s');

    $entry = apply_filters('schedulely_ai_reorder_log_entry', $entry);
    if (empty($entry) || !is_array($entry)) {
        return;
    }

    $max = (int) apply_filters('schedulely_ai_reorder_log_max_entries', 25);
    if ($max < 1) {
        $max = 25;
    }
    if ($max > 100) {
        $max = 100;
    }

    $list = get_option('schedulely_ai_reorder_log', array());
    if (!is_array($list)) {
        $list = array();
    }

    array_unshift($list, $entry);
    $list = array_slice($list, 0, $max);
    update_option('schedulely_ai_reorder_log', $list, false);

    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[Schedulely AI reorder] ' . wp_json_encode($entry, JSON_UNESCAPED_UNICODE));
    }
}

/**
 * Clear plugin caches.
 *
 * Invalidates only the named keys Schedulely writes. We deliberately do NOT call
 * wp_cache_flush() — that would evict the entire site object cache, which is a
 * severe performance footgun on object-cached sites (Redis, Memcached). The
 * wp_update_post() calls inside the scheduler already invalidate per-post cache
 * entries automatically.
 *
 * Third-party page-cache plugins can listen to the schedulely_clear_cache action
 * to do their own selective purging.
 *
 * @since 1.0.0
 * @since 1.6.0 Removed wp_cache_flush() — no longer evicts the site-wide object cache.
 */
function schedulely_clear_cache()
{
    // Invalidate the two named cache groups Schedulely writes to.
    wp_cache_delete('schedulely_available_posts', 'schedulely');
    wp_cache_delete('schedulely_scheduled_posts', 'schedulely');

    // Allow third-party cache plugins to selectively purge their own caches.
    do_action('schedulely_clear_cache');
}

