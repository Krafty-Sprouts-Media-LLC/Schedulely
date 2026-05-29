=== Schedulely ===
Contributors: kraftysprouts
Tags: schedule, posts, automation, publishing, cron
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.7.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Intelligently schedule posts with smart deficit tracking, random timing, and automatic author assignment.

== Description ==

Schedulely helps you automatically schedule hundreds of posts across multiple days with intelligent features:

* **Last Date Completion** - Automatically completes the last scheduled date if it didn't meet quota
* **Smart Continuation** - Resumes scheduling from where it left off
* **Random Time Distribution** - Creates natural posting patterns within your defined time windows
* **Author Randomization** - Assign random authors to posts with exclusion options
* **Flexible Scheduling** - Custom time windows, daily limits, and active days
* **Minimum Intervals** - Ensure posts don't publish too close together
* **WordPress Native** - Uses WordPress's built-in cron and timezone settings
* **Beautiful Dashboard** - Clean, modern admin interface with real-time statistics
* **Email Notifications** - Get notified when scheduling runs complete

= Key Features =

**Last Date Completion**
If the last scheduled date doesn't meet its post quota, Schedulely automatically completes it before scheduling to new dates. This ensures consistent publishing and no gaps in your schedule.

**Random Time Windows**
Set a time window (e.g., 5:00 PM - 11:00 PM) and Schedulely will randomly distribute posts within that range, respecting minimum intervals for natural posting patterns.

**Author Management**
Enable random author assignment and optionally exclude specific users. Perfect for multi-author sites wanting varied attribution.

**Flexible Control**
- Choose which post status to monitor (draft, pending, private)
- Set posts per day quota
- Define active days of the week
- Configure minimum intervals between posts
- Manual or automatic scheduling via WordPress cron

**Professional Dashboard**
View available posts, next scheduled publication, active deficits, and last run statistics at a glance.

= Perfect For =

* Content marketers managing large content calendars
* Multi-author blogs wanting automated scheduling
* Sites with consistent publishing schedules
* Anyone tired of manually scheduling posts

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/schedulely` or install through WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Tools → Schedulely** to configure settings
4. Click "Schedule Now" to run manual scheduling or enable automatic scheduling via WordPress cron

= Updating the Plugin =

**Your settings are always preserved during updates!** The plugin uses WordPress best practices to ensure your configuration is never lost when you update to a new version. You can update with confidence.

== Frequently Asked Questions ==

= How does last date completion work? =

Schedulely checks the last/furthest scheduled date. If that date has fewer posts than your daily quota (e.g., 5 posts when quota is 8), it automatically completes that date first (adds 3 more posts) before scheduling to new future dates. This ensures consistent publishing without gaps.

= Can I exclude certain authors from randomization? =

Yes! In the Author Assignment section, you can select specific users to exclude from random assignment. These users will never be assigned to scheduled posts.

= Does this work with custom post types? =

Yes! Go to Tools → Schedulely and use the "Post Types to Schedule" selector to choose any public post type registered on your site. Multiple post types can be scheduled simultaneously.

= What happens if I don't have enough posts? =

Schedulely schedules as many posts as possible from your available pool and tracks any remaining deficits for the next run.

= How do time windows work? =

You set a start and end time (e.g., 5:00 PM - 11:00 PM). Schedulely generates random times within this window for each post, ensuring minimum intervals are respected.

= Can I schedule posts immediately? =

Yes! Use the "Schedule Now" button on the settings page to trigger immediate scheduling. Alternatively, enable automatic scheduling to let WordPress cron handle it hourly.

= Will this work with my timezone? =

Yes! Schedulely uses WordPress's timezone settings from Settings → General, so all times are in your site's configured timezone.

= How do I disable automatic scheduling? =

Uncheck "Enable Automatic Scheduling" in the Automation Settings section. You can still use the manual "Schedule Now" button.

== Screenshots ==

1. Main settings dashboard with statistics and overview
2. Scheduling settings configuration
3. Author assignment options
4. Upcoming scheduled posts view
5. Deficit status tracking

== Changelog ==

= 1.6.0 - 27/05/2026 =
* Changed: Pool size default 1500 (configurable from settings — "Pool Size" field under Content & Volume). Larger pool = better shuffle variety and AI series spacing
* Added: Sequential scheduling mode — perfectly even spacing, 100% efficiency, posts at exact intervals
* Added: Hybrid scheduling mode — even slots with random placement within each slot; near-100% efficiency and natural-looking. Recommended upgrade from Random
* Added: Scheduling Mode selector in settings (Random / Sequential / Hybrid) with visual description of each
* Added: WordPress 7.0 Abilities — run-schedule, check-capacity, get-furthest-scheduled-date, preview-next-slot, run-ai-reorder (command palette, REST, AI agents)
* Added: AI email summary option — optional 2–3 sentence AI summary prepended to scheduling notification emails (WP 7.0+ only, opt-in)
* Added: AI capacity hint — when settings don't fit the quota, an AI-generated plain-English explanation appears alongside the programmatic suggestions (WP 7.0+ only)
* Changed: WP 7.0 AI client migration — AI queue ordering now uses wp_ai_client_prompt() on WordPress 7.0+; no API key stored in Schedulely required. Legacy DeepSeek/OpenAI path retained for older WordPress installs
* Changed: AI queue ordering disabled on cron-driven runs; only active on manual "Run Schedule Now" and Ability calls (eliminates 20-minute synchronous HTTP calls in cron worker)
* Changed: All admin assets now served from local vendor directory — no CDN requests. SweetAlert2 11.22.0, Select2 4.0.13 (stable), Flatpickr 4.6.13
* Changed: Author list cached per scheduling run — eliminates up to 1500 redundant get_users() calls per cron pass
* Changed: Post cache primed once before the scheduling loop (was per-post)
* Changed: wp_cache_flush() removed from schedulely_clear_cache() and uninstall.php — no longer nukes the site-wide object cache
* Changed: auto_schedule default consistent everywhere (false)
* Changed: Dashboard stats use wp_count_posts() — no more unbounded get_posts() call on every settings page load
* Changed: date() → wp_date() in all notification email output (respects site timezone)
* Fixed: Welcome notice dismiss now per-user (was site-wide); new admins see the notice independently
* Fixed: Cron callback now wrapped in try/catch — uncaught exceptions are logged and trigger error notification email
* Fixed: GitHub update checker gated behind SCHEDULELY_WPORG_BUILD constant; excluded from wp.org zip via .distignore
* Added: .distignore — canonical exclusion list for the wp.org release zip

= 1.5.10 - 03/05/2026 =
* Added: Tools → Schedulely shows WP-Cron hook name and next run (so cron plugins can be searched by slug)

= 1.5.9 - 03/05/2026 =
* Fixed: AI queue reorder reconciles model ordered_ids when the API returns extras/duplicates or wrong length (optional strict mode via schedulely_ai_reconcile_invalid_ordered_ids)

= 1.5.8 - 03/05/2026 =
* Changed: AI reorder HTTP timeout scaled default and max cap raised to 1200 seconds (20 minutes)

= 1.5.7 - 03/05/2026 =
* Fixed: AI reorder HTTP timeout scales with queue size (large draft pools no longer stuck at 120s); cap 600s, filter schedulely_ai_request_timeout_max up to 900s

= 1.5.6 - 03/05/2026 =
* Fixed: Save Changes on Tools → Schedulely no longer fails with “The link you followed has expired” (duplicate _wpnonce from nested clear-log form)

= 1.5.5 - 03/05/2026 =
* Changed: PHPDoc @since tags completed for AI reorder log and reorder API method

= 1.5.4 - 03/05/2026 =
* Added: AI queue reorder log on Tools → Schedulely (response excerpts, error codes, token usage when reported) plus optional WP_DEBUG_LOG mirror
* Changed: Email “AI ordering” line and link to settings when reorder was not applied

= 1.5.3 - 03/05/2026 =
* Fixed: Overnight and same-day windows use the WordPress site timezone for bounds and random times (avoids slots after the configured end or skewed afternoon starts when PHP’s default timezone differs)
* Added: Filter schedulely_schedule_safety_buffer_seconds (default 30 minutes) to allow earlier first slots after the window opens

= 1.5.2 - 03/05/2026 =
* Changed: Tools → Schedulely settings — clearer sections; AI series spacing moved below authors so time window and active days stay together

= 1.5.1 - 03/05/2026 =
* Fixed: Capacity meter for long (12+ hour) windows uses a less pessimistic packing factor so targets like 25 posts with a 20-minute minimum in an overnight window can show as feasible

= 1.5.0 - 03/05/2026 =
* Added: Overnight publishing window when end time is at or before start on the clock (e.g. 2:30 PM–3:00 AM); quota and spacing use the full span into the next calendar morning
* Changed: Time window help text on settings screen; capacity handling for overnight spans

= 1.4.8 - 03/05/2026 =
* Changed: Email SUMMARY uses “AI ordering (this run)” with clear Applied / Not applied / Not used wording

= 1.4.7 - 03/05/2026 =
* Fixed: AI reorder reads assistant text like the connection test (multimodal content, reasoning_content, legacy text)
* Changed: Email “AI queue order: No” line explains provider token usage can still appear when a reply was rejected

= 1.4.6 - 05/05/2026 =
* Fixed: AI test connection accepts usage-only replies and more response shapes; clearer errors with response excerpt
* Improved: Test button shows spinner and contacting message while waiting

= 1.4.5 - 05/05/2026 =
* Changed: Saved API key always visible in read-only field; replace field + placeholder clarified; removed reveal AJAX

= 1.4.4 - 05/05/2026 =
* Changed: Email notification summary includes AI queue order status for the run

= 1.4.3 - 05/05/2026 =
* Added: Test connection button for AI settings (verifies saved URL, model, and API key)
* Changed: Restored DeepSeek-focused defaults and admin copy; upgrade fills empty URL/model from 1.4.2

= 1.4.2 - 05/05/2026 =
* Added: Show stored API key length + "Show full key" (AJAX) on settings screen
* Changed: Neutral AI settings copy; empty URL/model use built-in defaults (filters); new installs store empty URL/model

= 1.4.1 - 05/05/2026 =
* Improved: Stronger AI system prompt for series spacing (variety, minimum gaps when possible, even spread when not)

= 1.4.0 - 04/05/2026 =
* Added: Optional AI queue ordering via OpenAI-compatible Chat Completions (DeepSeek-oriented defaults) using post titles; plugin still assigns all publish times and rules
* Settings: API base URL, model, API key (shown read-only when saved), enable toggle, optional connection test

= 1.3.8 - 04/05/2026 =
* Changed: Scheduling run can load up to 1500 eligible posts per query (was 500) for larger draft pools

= 1.3.7 - 03/05/2026 =
* Added: Shuffle queue before scheduling (on by default) so posts are not always scheduled in strict post date order; optional toggle under Queue order

= 1.3.6 - 27/04/2026 =
* Fixed: Polylang integration now fetches posts from all languages during scheduling runs (not only the current/default language context)

= 1.3.5 - 10/02/2026 =
* Improved: Renamed "Last Scheduled Date" to "Furthest Scheduled Date" for clarity
* Improved: Added "Last Run" timestamp to System Health card


= 1.0.4 - 07/10/2025 =
* Added: SweetAlert2 for beautiful modal dialogs (replaces browser confirm)
* Added: Loading states with animated spinners
* Added: Better success/error notifications
* Changed: Complete UI refresh with clean WordPress native styling
* Changed: Simplified CSS with WordPress admin colors
* Improved: Better responsive design and mobile experience
* Improved: Form validation with SweetAlert2 alerts

= 1.0.3 - 06/10/2025 =
* Fixed: Contributors not appearing in author randomization list
* Changed: Capability check from 'publish_posts' to 'edit_posts'
* Now includes Contributors, Authors, Editors, and Administrators in author selection

= 1.0.2 - 06/10/2025 =
* Fixed: CRITICAL - Removed flawed deficit logic that tried to schedule to past dates (WordPress rejects this)
* Changed: Complete refactor to "last date completion" logic - simpler and more reliable
* Changed: Checks only the LAST scheduled date and completes it if needed (and not in past)
* Removed: Deficit tracker class and database storage (no longer needed)
* Updated: Admin UI now shows "Last Scheduled Date" status instead of deficits
* Updated: Email notifications show last date completion status

= 1.0.1 - 06/10/2025 =
* Fixed: Admin menu not appearing in WordPress dashboard
* Fixed: Changed initialization hook from admin_menu to plugins_loaded
* Plugin settings now correctly accessible at Tools → Schedulely

= 1.0.0 - 06/10/2025 =
* Initial release
* Smart deficit tracking and auto-completion
* Random author assignment with exclusions
* Customizable time windows and intervals
* WordPress cron integration
* Email notifications
* Beautiful admin dashboard
* Manual and automatic scheduling

== Upgrade Notice ==

= 1.0.2 =
CRITICAL UPDATE: Fixes fundamental flaw in deficit logic. Old logic tried to schedule to past dates (WordPress rejects this). New "last date completion" logic is simpler and works correctly. ALL USERS MUST UPDATE.

= 1.0.1 =
Critical fix: Resolves admin menu not appearing. All users should update immediately.

= 1.0.0 =
Initial release of Schedulely. Install and configure to start intelligent post scheduling!

== Privacy Policy ==

Schedulely does not collect or transmit personal data by default.

= Optional AI Queue Ordering =

If you enable the "AI series spacing" feature in Tools → Schedulely, the plugin
sends the following data to an AI provider when a scheduling run is triggered:

* Post IDs and post titles of the posts queued for scheduling
* Your site's home URL (in the HTTP User-Agent header)

**On WordPress 7.0 or later:** the provider is whichever one you configure in
Settings → Connectors. Schedulely does not store an API key — key management
is handled centrally by WordPress. Refer to your chosen provider's privacy policy.

**On older WordPress installs (legacy mode):** the plugin sends requests to the
API endpoint you configure in the settings (default: DeepSeek at
https://api.deepseek.com/v1). Your API key is stored in wp_options. DeepSeek's
privacy policy: https://www.deepseek.com/privacy. You can change the endpoint
to any HTTPS OpenAI-compatible API; review the privacy policy of the provider
you choose.

The AI feature is **disabled by default**. To use it you must explicitly enable
it and, on older WordPress installs, supply your own API key.

= Local Data =

All scheduling configuration and the AI reorder log are stored in your site's
wp_options table. The plugin's uninstall routine removes all of this data.
The "Remove stored API key on save" checkbox on the settings page lets you
clear a stored legacy API key at any time.

== Support ==

For support, feature requests, or bug reports, please visit:
* Website: https://kraftysprouts.com/contact
* Email: support@kraftysprouts.com

== Credits ==

Developed by Krafty Sprouts Media, LLC
https://kraftysprouts.com

Third-party libraries bundled in this plugin (all GPL-compatible):

* SweetAlert2 11.22.0 — MIT License — https://sweetalert2.github.io
* Select2 4.0.13 — MIT License — https://select2.org
* Flatpickr 4.6.13 — MIT License — https://flatpickr.js.org

