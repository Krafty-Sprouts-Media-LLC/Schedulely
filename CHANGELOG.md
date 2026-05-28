# Changelog

All notable changes to Schedulely will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
==========================================================================


## [1.7.9] - 28/05/2026

### Fixed
- **AI Reorder Log stayed empty even though reorder attempts were happening.** On the WP 7.0 AI path, the log was only written from `process_ai_response()` — which is reached *only after* a successful `generate_text()` call. The two failure exits in `reorder_via_wp_ai()` (provider not supported for text generation, and the `\Throwable` catch that swallows request **timeouts** like `cURL error 28`) returned a `WP_Error` and wrote to the PHP error log, but never recorded an entry in the user-facing reorder log. Because v1.7.7 split timezone classification into PHP, a reorder that timed out still produced a correct-looking timezone distribution while leaving the log blank — so failures were invisible. Fixed by recording an `error` entry (with error code, sanitized message, and a note) in both WP-AI failure branches, plus the legacy path's missing-API-key early return. Every reorder attempt — success or failure — now leaves a log entry.

---

## [1.7.8] - 28/05/2026

### Fixed
- **Other plugins' admin notices appeared inside the Schedulely header banner.** Notices such as "Purged all caches successfully." or "Reset the optimized data successfully." (from cache/optimization plugins) flashed at the top of the page on load, then jumped into the dark header band. Root cause: WordPress core's `common.js` relocates all admin notices to just before an `<hr class="wp-header-end">` marker, and when that marker is absent it falls back to inserting them after the first `<h1>`/`<h2>` in `.wrap` — which is Schedulely's page title *inside* the header banner. Fixed by adding the standard `<hr class="wp-header-end">` marker immediately after the header band in `templates/admin/settings-page.php`, so notices render below the header where they belong. The marker is hidden by core admin CSS and adds no visible line.

---

## [1.7.7] - 28/05/2026

### Fixed
- **Timezone ordering produced "General: 200" — root cause was an AI request timeout, not the model disobeying.** Despite the 1.7.5/1.7.6 fixes, the WP 7.0 reorder request still timed out (`cURL error 28: Operation timed out after 150002 ms`) because the model was asked to do two jobs in one call: order the posts *and* emit a per-post `timezone_groups` map for up to 1,500 posts. The oversized output blew past the request timeout, the call threw, and the hard-failure fallback assigned every post to "general". The earlier fixes targeted the model's response shape, but no response ever arrived.

### Changed
- **Timezone classification now runs in PHP; the AI only orders.** The state→timezone assignment is a fixed lookup (the same table that used to live in the AI prompt), so it is now done deterministically in `Schedulely_AI_Order::classify_timezone_group()` by scanning each post's title and slug. The AI is now asked only for `ordered_ids` (series spacing) — the path that already worked reliably. This removes the timezone half of the response entirely, eliminating the timeout, and makes classification 100% correct and instant regardless of pool size. Whole-word matching ensures "arkansas" never resolves as "kansas" and city names like "indianapolis" never resolve as "indiana". Posts with no recognizable US state still fall into "general".
- **Removed the dead AI-timezone code paths** (`reorder_via_wp_ai_timezone()`, `reorder_via_legacy_http_timezone()`, `process_timezone_response()`, `get_timezone_system_instruction()`, and the `timezone_mode` branches in the prompt/request builders) now that classification is handled in PHP.

---

## [1.7.6] - 28/05/2026

### Fixed
- **WP 7.0 AI client timeout too short for large pools.** The WordPress AI client (`wp_ai_client_prompt()`) uses a default 30-second timeout. With 200+ posts the AI needs several minutes to process the timezone reorder — causing `cURL error 28: Operation timed out after 30006 milliseconds`. Fixed by hooking `wp_ai_client_default_request_timeout` during Schedulely's AI calls and raising the timeout using the same scaling formula as the legacy path: `60 + (post_count × 0.45)`, clamped between 120 and 1200 seconds. The filter is added before the call and removed immediately after.

---

## [1.7.5] - 28/05/2026

### Fixed
- **Timezone ordering not working — AI only returned `ordered_ids`, ignored `timezone_groups`.** Root cause: `build_user_prompt()` told the AI "Respond with JSON only: `{\"ordered_ids\":[...]}`" — which explicitly only asked for one key. The system instruction asked for both keys, but the user prompt contradicted it and the AI followed the user prompt. Fixed by making `build_user_prompt()` timezone-aware: when timezone mode is on, the user prompt now explicitly requests both `ordered_ids` and `timezone_groups` in the JSON shape.
- **Timezone fallback was silent — no log entry when `timezone_groups` was missing.** When the AI returned `ordered_ids` but no `timezone_groups`, the graceful fallback assigned all posts to "general" but wrote nothing to the AI reorder log. Now logs a success entry with a note explaining the fallback occurred.

---

## [1.7.4] - 28/05/2026

### Added
- **Timezone distribution in scheduling notification emails.** When US timezone-aware ordering is active, the email summary now includes a "🌎 US Timezone Distribution" line showing how many posts were assigned to each timezone group (e.g. "Eastern: 15 · Central: 12 · Mountain: 8 · Pacific: 10 · General: 5").
- **AI email summary now includes timezone context.** When timezone-aware ordering was used, the AI-generated summary prompt receives the timezone distribution data so it can write a sentence like "Posts were distributed across Eastern, Central, Mountain, and Pacific time zones targeting each audience's active hours."
- **`$results['timezone_distribution']` key** added to the scheduler results array when timezone ordering is active. Contains `[ 'eastern' => int, 'central' => int, 'mountain' => int, 'pacific' => int, 'general' => int ]`.

---

## [1.7.3] - 28/05/2026

### Fixed
- **Timezone ordering toggle not visible on legacy AI path.** The "US Timezone-Aware Queue Ordering" checkbox was only rendered inside the WP 7.0 connected provider block. Users on the legacy path (stored DeepSeek/OpenAI API key) never saw the option. Toggle now appears on all three AI states: WP 7.0 connected, WP 7.0 not connected, and legacy path.
- **WP 7.0 provider detection returning false when provider is connected.** `wp_ai_client_prompt( '' )->is_supported_for_text_generation()` was called with an empty string prompt, which caused some providers (including DeepSeek via Connectors) to return false even when correctly configured. Changed to `wp_ai_client_prompt( 'test' )` — a minimal non-empty prompt — in both the settings template and `Schedulely_AI_Order::wp_ai_available()`.
- **Welcome notice Dismiss button not working on non-settings admin pages.** The dismiss nonce was attached to the `schedulely-admin` script handle via `wp_add_inline_script()`, but that handle is only enqueued on the plugin's own settings page. On all other admin pages (Dashboard, Posts, etc.) the handle was never registered, so the nonce was never output and the JS dismiss handler bailed immediately. Fixed by registering a dedicated `schedulely-dismiss` inline script that is enqueued only when the notice is actually shown, on any admin page.

### Added
- **`AGENTS.md` § 16 — Release process rules.** Documents the mandatory workflow: every fix after a tag requires a new version bump, changelog entry, and tag before being considered released. Prevents the pattern of pushing untagged fixes to master that users never receive.

---

## [1.7.2] - 28/05/2026

### Fixed
- **`max_tokens` corrected to 40,960 in timezone mode** (was 32,768 in the 1.7.1 tag — the fix was pushed to master but not tagged). A 1,500-post timezone response needs ~23,000 output tokens; 40,960 gives comfortable headroom.
- **Stale code comment** in `build_request_body()` still referenced 32,768 — updated to reflect 40,960.
- **Capacity checker now shows US timezone active windows** when timezone-aware ordering is enabled. The capacity pill's accordion displays a table of each timezone group's overlap between the publishing window and its active hours (7 AM – 11 PM local time), shown in site-local time. Appears both when capacity is met (informational) and when quota is not met (alongside fix suggestions).

---

## [1.7.1] - 28/05/2026

### Changed
- **Timezone-aware scheduling: replaced equal-band division with window/active-hours overlap.** The 1.7.0 implementation divided the publishing window into four equal fixed slices (bands). This caused overflow when too many posts targeted the same timezone group, boundary collisions at band edges, and capacity issues that didn't exist before. The new approach computes the **intersection of the user's publishing window and each US timezone's active hours (7 AM – 11 PM local time)**. Each post gets a random time within that overlap — no hard boundaries, no overflow, no capacity changes needed. The minimum interval continues to apply across all posts as normal. Falls back to the full window silently if a timezone's active hours don't overlap with the configured window at all. Works correctly regardless of the WordPress site timezone (WAT, London, Kolkata, or any other) because all calculations are done in UTC internally.
- **`max_tokens` raised to 40,960 in timezone mode** — a 1,500-post timezone response (`ordered_ids` + `timezone_groups`) requires ~23,000 output tokens. The previous default of 16,384 would truncate the response for large pools. Standard (non-timezone) mode keeps the 16,384 default.

### Technical
- `Schedulely_Scheduler::get_timezone_active_overlap()` — new method replacing the band calculation. Takes an anchor date and timezone group, returns `[overlap_start_ts, overlap_end_ts]` in UTC. Uses PHP's `America/*` timezone rules for DST-aware offset lookup at the time of scheduling.
- `Schedulely_Scheduler::calculate_timezone_bands()` — kept as a deprecated wrapper around `get_timezone_active_overlap()` for backwards compatibility.
- `schedule_posts_from_date()` — updated to call `get_timezone_active_overlap()` directly per post.
- `calculate_capacity()` — now includes `timezone_overlaps` in its response when US timezone-aware ordering is enabled, showing the active window overlap for each US timezone group in site-local time. Displayed in the capacity accordion in the admin.
- `admin.js` — `buildTimezoneOverlapPanel()` added. When timezone ordering is on and capacity is met, the capacity pill's "Show suggestions" toggle reveals a table of each timezone group's active window overlap. When capacity is not met, the same table appears above the fix suggestions.

---

## [1.7.0] - 28/05/2026

### Added
- **US Timezone-Aware Queue Ordering** — new opt-in feature for sites publishing US state-specific content. When enabled alongside AI queue ordering and Random scheduling mode, Schedulely divides the configured publishing window into four equal timezone bands (Eastern → Central → Mountain → Pacific) and assigns each post a random time within its target audience's band. Posts with no identifiable US state are distributed evenly as spacers across all bands.
- **Slug included in AI prompt** — the post slug is now sent alongside the title in every AI reorder request. Since slugs are derived from the primary keyword (e.g. `pit-bull-laws-in-texas`), they provide a more reliable state signal than the title alone.
- **Timezone band calculation** — `Schedulely_Scheduler::calculate_timezone_bands()` dynamically divides any configured window into four equal bands. Bands recalculate automatically when the user changes their start/end time — nothing is hardcoded.
- **Inline mode warning** — when US Timezone-Aware Ordering is enabled but Sequential or Hybrid scheduling mode is active, an inline notice in the AI & Notifications tab explains that timezone bands only take effect in Random mode.
- **`schedulely_ai_us_timezone_ordering` option** — registered on activation, saved on settings save, deleted on uninstall.
- **`Schedulely_Defaults::AI_US_TIMEZONE_ORDERING`** constant (`false` — off by default, does not affect existing users).

### Changed
- **AI system prompt** — updated to send `id TAB title TAB slug` per line (was `id TAB title`). The timezone-aware prompt instructs the AI to extract the US state from title or slug, assign each post to its primary timezone group using the eastern-most zone for split-timezone states, order Eastern first and Pacific last, scatter general posts as spacers, and return both `ordered_ids` and `timezone_groups` in the JSON response.
- **`generate_random_datetime()`** — accepts an optional `$band` parameter (`[start_ts, end_ts]`). When provided, the random time is constrained to that sub-range of the window. Falls back to the full window if the band is outside the window or not provided.

### Notes
- Timezone-aware ordering only applies on manual "Run Schedule Now" clicks. Cron-driven runs skip AI reordering entirely (unchanged from 1.6.0) and use shuffle or draft-date order across the full window.
- Sequential and Hybrid modes are not timezone-band-aware in this release — they assign times by slot position across the full window. Full band support for those modes is planned for a future release.

---

## [1.6.2] - 27/05/2026

### Added
- **UI redesign:** Full admin page overhaul — dark gradient header band, status bar with Auto-Schedule and Email Alerts toggles prominently placed, 3-stat card row (merged redundant duplicate cards), tabbed configuration card (Schedule / Queue / Authors / AI & Notifications).
- **No-layout-shift capacity indicator:** Replaced the injected alert box (which caused page jumps and flickering on load) with a fixed-height pill in the tab bar. The pill updates text and colour in-place; suggestions are hidden in a collapsed accordion the user opens intentionally.

### Known Issues
- **Notice renders inside header band on save:** After clicking Save Changes, the "Settings saved successfully!" notice briefly appears overlaid inside the header band before the page finishes loading. Root cause: WordPress outputs `settings_errors()` before our template renders and the header's bleed margin visually overlaps the notice area during paint. Tracked for fix in 1.6.3.

---

## [1.6.1] - 27/05/2026

### Fixed
- **Bug fix:** All five `schedulely/*` Abilities were silently dropped on every page load with a `_doing_it_wrong()` notice. `wp_register_ability()` was being called on the `init` action; WordPress 6.9+ requires it to be called on `wp_abilities_api_init`. Registration hook corrected in `Schedulely_Abilities::register_hooks()`.

---

## [1.6.0] - 27/05/2026

### Changed
- **Performance:** `schedulely_clear_cache()` no longer calls `wp_cache_flush()`. Only the two named Schedulely cache keys are invalidated. This eliminates a severe footgun on Redis/Memcached sites where every scheduling pass was evicting the entire site object cache. (P0-T1)
- **Performance:** `uninstall.php` no longer calls `wp_cache_flush()` on plugin deletion. Only Schedulely's own cache keys are cleared. (P0-T2)
- **Bug fix:** `schedulely_auto_schedule` option is now read with a consistent default of `false` everywhere — activation hook, cron callback, settings render, and checkbox state. Previously the activation hook wrote `false` but all read sites used `true` as the default, causing the auto-schedule toggle to display as on even on fresh installs. (P0-T3)
- **Performance:** Dashboard "Drafts Available" count now uses `wp_count_posts()` (WP-core cached) instead of `get_posts(['posts_per_page' => -1])`. This eliminates an unbounded query that loaded every draft ID into memory on every settings page render. (P0-T4)
- **Compatibility:** Replaced deprecated `current_time('timestamp')` with `time()` in the auto-schedule health card and `wp_date()` in the email notification run timestamp. Both previously used the deprecated form which has been removed in forthcoming WordPress versions. (P0-T5)
- **wp.org compliance:** All third-party admin assets (SweetAlert2, Select2, Flatpickr) are now served from the local `assets/vendor/` directory instead of third-party CDNs. Google Fonts (Lato) removed — system fonts used instead. Libraries updated to stable versions: SweetAlert2 11.22.0, Select2 4.0.13 (was 4.1.0-rc.0), Flatpickr 4.6.13 unchanged. (P1-T3)
- **wp.org compliance:** GitHub update checker (`vendor/plugin-update-checker/`) is now gated behind a `SCHEDULELY_WPORG_BUILD` constant and excluded from the wp.org release zip via `.distignore`. wp.org-hosted installs receive updates through the WordPress.org update API. (P1-T2)
- **WP 7.0 AI migration:** `Schedulely_AI_Order` now detects WordPress 7.0+ via `wp_ai_client_prompt()` and uses the provider-agnostic WP AI client for queue reordering. No API key stored in Schedulely is required on WP 7.0+. The legacy direct-HTTP path (DeepSeek/OpenAI-compatible) is retained for older WordPress installs and is deprecated — it will be removed in a future major version. `test_api_connection()` now returns immediate success on WP 7.0+ when a connector is configured. (P1-T4, P1-T5, P1-T6)
- **wp.org compliance:** AI settings panel now shows three states — WP 7.0 with connector (no API key fields shown), WP 7.0 without connector (prompt to configure Settings → Connectors), legacy path (existing API key / base URL / model fields, with the stored key now masked to first 4 + last 4 chars only). (P1-T7, P1-T19)
- **wp.org compliance:** `wpai_preferred_text_models` filter registered so Schedulely expresses a preference for fast/cheap models during the AI reorder task. Priority order: DeepSeek `deepseek-v4-flash` (cheapest, JSON-native, original default — `deepseek-chat` is a deprecated alias), DeepSeek `deepseek-v4-pro` (fallback), Google `gemini-3.1-flash-lite` (GA May 2026, replaces deprecated `gemini-2.5-flash`), Google `gemini-3-flash-preview`, OpenAI `gpt-5.4-mini`, Anthropic `claude-sonnet-4-6`. (P1-T8)
- **wp.org compliance:** Privacy Policy section in `readme.txt` rewritten to accurately describe the WP 7.0 path (provider from Settings → Connectors) and the legacy path (DeepSeek default, user API key). (P1-T9)
- **wp.org compliance:** "Planned Features" section removed from `readme.txt`. Custom post type FAQ answer updated to reflect that CPT support ships in the current version. (P1-T10)
- **Security:** All `_e()` calls with plain text converted to `esc_html_e()`. Strings containing HTML (`<strong>` etc.) now use `wp_kses_post(__())`. Dynamic `echo` sites wrapped with `esc_html`, `esc_attr`, `esc_url` as appropriate. (P1-T11, P1-T12)
- **Security:** Welcome notice inline `<script>` block removed. Dismiss nonce now output via `wp_add_inline_script`. Dismiss handlers moved to `admin.js`. (P1-T14)
- **wp.org compliance:** Inline `onchange="..."` removed from post-type dropdown `<select>`. Handler moved to `admin.js`. (P1-T15)
- **Bug fix:** Author exclusion show/hide selector fixed — was using `.closest('tr')` on a flex layout, so it was always a no-op. Now targets the actual container. (P1-T16)
- **i18n:** All hardcoded English strings in `admin.js` (Swal dialog titles, button labels, validation messages) moved to the `schedulely_admin.strings` localisation map. (P1-T17)

### Added
- `.distignore` — defines everything excluded from the wp.org release zip. The GitHub Actions release workflow now reads this file instead of using a hardcoded inline exclusion list. (P1-T1)

### Phase 2 progress

- **Architecture:** Introduced `Schedulely_Defaults` constants class (`includes/class-defaults.php`). All option default values now live in one place. `get_option()` reads and `add_option()` writes both reference the constants. (P2-T1)
- **Architecture:** Added `spl_autoload_register`-based autoloader (`includes/autoloader.php`) — maps `Schedulely_*` class names to `includes/class-{slug}.php`. Removed the manual `require_once` chain from `schedulely.php`. (P2-T2)
- **Architecture:** `MAX_POSTS_PER_RUN` constant in `Schedulely_Scheduler` now references `Schedulely_Defaults::MAX_POSTS_PER_RUN` (1500, restored from a premature reduction). Pool size is now user-configurable in the UI — a new "Pool Size (Max Posts per Run)" field under Content & Volume. A larger pool gives shuffle and AI ordering more variety. The `schedulely_max_posts_per_run` filter provides a programmatic override for hosts with tight execution time limits. (P2-T1, P3-T1)

### Phase 3 — Performance hardening

- **Performance (Critical):** AI queue reordering is now disabled on cron-driven runs. `Schedulely_Scheduler::run_schedule()` accepts a new `$allow_ai_reorder` parameter (default `false`). The cron callback passes `false`; the manual "Run Schedule Now" button and the `schedulely/run-schedule` Ability pass `true`. This eliminates the up-to-20-minute synchronous HTTP call from the cron worker. (P3-T3)
- **Performance (High):** `Schedulely_Author_Manager::get_eligible_authors()` now caches the user list in a member variable (`$eligible_authors_cache`). The scheduling loop of 1500 posts previously issued 1500 `get_users()` queries; it now issues exactly one. (P3-T5)
- **Performance (High):** Post objects are now primed via `_prime_post_caches()` once before the scheduling loop rather than per-post. (P3-T4)
- **Reliability (High):** `schedulely_run_auto_schedule()` is now wrapped in `try/catch(\Throwable)`. Exceptions are logged and, if email notifications are enabled, an error notification is sent. Previously, any uncaught exception silently killed the cron pass with no trace for the admin. (P3-T8)
- **Bug fix (Medium):** `schedulely_ajax_manual_schedule()` now catches `\Throwable` instead of the narrower `Exception`, covering PHP 8 `Error` subclasses. The error message exposed to the user is now generic — the full exception is logged, not echoed. (P3-T8 adjacent)
- **Compatibility (Medium):** Replaced all remaining `date()` calls in `class-notifications.php` with `wp_date()` so email timestamps respect the site's configured timezone, not PHP's server default timezone. (P3-T6)
- **Performance (Low):** Per-retry `error_log()` calls inside the scheduling loop replaced with an aggregate counter; a single summary line is emitted at the end of the run if any retries occurred. (P3-T7)
- **Pool size configurable (user request):** Default pool size restored to 1500. New "Pool Size" field in Content & Volume section of the settings page. Filter `schedulely_max_posts_per_run` and the UI control both work — filter takes precedence if set. (P3-T1 revised)
- **Phase 2 regression fixes:** Welcome notice dismiss switched to per-user `update_user_meta` (was site-wide `update_option`). `wp_add_inline_script` nonce output now targets `schedulely-admin` handle (was `jquery`). Clear-log handler now checks nonce before capability (consistent with other handlers). `settings-page.php` footer uses `wp_kses_post` instead of unescaped `printf`. "Target: %d/day" string now translatable. (Issues A–G from audit review)
- **Architecture:** Extracted `Schedulely_Admin_Menu` (`includes/class-admin-menu.php`) — owns only the `add_management_page` call. (P2-T3)
- **Architecture:** Extracted `Schedulely_Admin_Assets` (`includes/class-admin-assets.php`) — owns asset enqueuing and `wp_localize_script`. Duplicate enqueue code removed from `Schedulely_Settings`. (P2-T4)
- **Architecture:** Extracted `Schedulely_Admin_Notices` (`includes/class-admin-notices.php`) — owns the welcome notice. (P2-T9)
- **Architecture:** Extracted `Schedulely_Ajax_Handlers` (`includes/class-ajax-handlers.php`) — owns all five AJAX/admin-post handlers (`check_capacity`, `dismiss_notice`, `toggle_auto_schedule`, `test_ai_connection`, `clear_ai_reorder_log`). (P2-T8)
- **Architecture:** Settings API `register_setting()` calls removed (P2-T5 — the form uses a direct `$_POST` handler, not `do_settings_sections`; the half-used Settings API pattern is gone). (P2-T5)
- **Architecture:** Form save handler extracted to `Schedulely_Settings::handle_form_save()` — `render_settings_page()` now calls it instead of embedding 40+ lines of `update_option` calls inline. `wp_unslash()` applied consistently to every `$_POST` access. Server-side clamping added for posts-per-day and min-interval (mirrors JS validation). (P2-T6)
- **Architecture:** `Schedulely_Settings` completely rewritten — 466 lines (was 1,407). HTML template extracted to `templates/admin/settings-page.php` (542 lines). `render_settings_page()` loads the template via `require`. All methods moved to dedicated classes in 1.6.0. `get_statistics()` updated to use `wp_date()` and `wp_count_posts`. (P2-T7, P2-T11)
- **WP 7.0 Abilities:** Registered `Schedulely_Abilities` class (`includes/class-abilities.php`) with five abilities: `schedulely/run-schedule`, `schedulely/check-capacity`, `schedulely/get-furthest-scheduled-date`, `schedulely/preview-next-slot`, `schedulely/run-ai-reorder`. All abilities are REST-visible (`show_in_rest: true`). Only registered when `wp_register_ability()` is available (WP 7.0+). (P2-T12 through P2-T17)

---

## [1.5.10] - 12/05/2026

### Added
- **Settings: WP-Cron hint** — Under Quick Toggles, shows the real event hook **`schedulely_auto_schedule`**, how to find it in cron plugins, recurrence (`twicedaily`), and **next run** in site time when scheduled.

---

## [1.5.9] - 03/05/2026

### Fixed
- **AI reorder when the model returns extra or duplicate IDs** — Large completions sometimes include **wrong counts** (e.g. 901 IDs for 881 posts). Schedulely now **reconciles** the model list: keeps valid IDs in model order, drops unknown/excess duplicates, then appends any still-missing IDs in original input order. Success log notes when reconciliation ran. Filter **`schedulely_ai_reconcile_invalid_ordered_ids`** (`true` default) restores the previous strict failure when set to `false`.

### Changed
- **AI system prompt** — Explicitly requires `ordered_ids` to match the input multiset (exactly once each, no invented IDs).

---

## [1.5.8] - 03/05/2026

### Changed
- **AI reorder HTTP cap** — Scaled default timeout and `schedulely_ai_request_timeout_max` now allow up to **1200** seconds (20 minutes) so very large queues are not capped at 600–900s.

---

## [1.5.7] - 03/05/2026

### Fixed
- **AI queue reorder timeouts on large pools** — Default HTTP timeout was fixed at **120s**, which is too short for hundreds of posts (huge prompt + long JSON). Timeout now **scales with post count** (about **0.45s per post**, between **120s and 540s** before filters). Upper cap **600s** (filter `schedulely_ai_request_timeout_max`, max **900**); raised to **1200s** in 1.5.8.

### Changed
- **Filter `schedulely_ai_request_timeout`** — Receives a second argument: **`$post_ids`** (the queue being reordered).

---

## [1.5.6] - 03/05/2026

### Fixed
- **Settings save / “The link you followed has expired”** — The **Clear AI reorder log** control used a second `wp_nonce_field` with the default name `_wpnonce` inside the main settings form. On **Save Changes**, PHP only saw one nonce (the wrong action), so `check_admin_referer` failed. The clear action now uses a **dedicated nonce field** (`schedulely_clear_ai_reorder_nonce`) and a **separate form** referenced via the button’s **`form`** attribute (valid HTML, no nested forms).

---

## [1.5.5] - 03/05/2026

### Changed
- **PHPDoc** — Added missing `@since` tags for AI reorder log helpers (`schedulely_ai_log_sanitize_excerpt`, `schedulely_append_ai_reorder_log`, `Schedulely_AI_Order::log_ai_reorder_attempt`, `Schedulely_Settings::handle_clear_ai_reorder_log`) and `Schedulely_AI_Order::reorder_post_ids` (`1.4.0`).

---

## [1.5.4] - 03/05/2026

### Added
- **AI queue reorder log** — Tools → Schedulely stores the last several **reorder API** attempts: outcome (success/error), model, post count, HTTP status, `usage.total_tokens` when present, error code/message, and **excerpts** of assistant text and raw body. **Clear log** button; optional `WP_DEBUG_LOG` mirror. Filters: `schedulely_ai_reorder_logging_enabled`, `schedulely_ai_reorder_log_max_entries`, `schedulely_ai_reorder_log_entry`.

### Changed
- **Completion email (AI line)** — Clarifies that **Not applied** means the queue order was not taken from the model’s reply; if the API ran, usage can still appear on the provider. Points to the **AI queue reorder log** on the settings page.

---

## [1.5.3] - 03/05/2026

### Fixed
- **Overnight windows and site timezone** — Window bounds, random slot generation, and related date math now use the **WordPress site timezone** (`wp_timezone()` / `DateTimeImmutable`) instead of PHP’s default timezone via `strtotime()`. Stops slots from drifting **past the configured end** (e.g. after 3:00 AM) or mis-aligning afternoon starts when the server TZ differs from **Settings → General → Timezone**.
- **“Now” vs window floor** — Random scheduling uses **`time()`** with the existing safety buffer so the earliest slot lines up with real UTC “now” while bounds stay site-local.

### Added
- **Filter `schedulely_schedule_safety_buffer_seconds`** — Default **1800** (30 minutes). Lower it (e.g. `0` or `300`) if you need the first posts closer to the window start when the scheduler runs shortly after the window opens.

---

## [1.5.2] - 03/05/2026

### Changed
- **Settings layout** — Configuration card is grouped into **Content & volume** (status, types, posts/day, interval), **When to publish** (time window + active days), **Queue order**, **Author assignment**, then **AI series spacing** (boxed), then notifications. Removes AI from between queue controls and the time window.

---

## [1.5.1] - 03/05/2026

### Fixed
- **Capacity estimate for long windows** — Windows **12+ hours** (including overnight spans such as 2:00 PM–3:00 AM) use a **higher random-packing efficiency** (capped at 70%) so the admin capacity check matches realistic scheduling density. Filter: `schedulely_capacity_efficiency`.

---

## [1.5.0] - 03/05/2026

### Added
- **Overnight time windows** — If **end time is at or before start time** on the clock (e.g. **2:30 PM → 3:00 AM**), Schedulely treats the window as **start on anchor day through end on the next calendar morning**. Random slots and **posts-per-day** quotas use that **full span** (including `post_date` on the next date after midnight). Same-day windows unchanged (end must be after start on the same day).

### Changed
- **Settings** — Short help text under the time window fields explains same-day vs overnight behavior.
- **Capacity tool** — Computes span for overnight windows; expand-window suggestions for overnight are descriptive (same-day numeric suggestions unchanged).

### Technical Details
- `Schedulely_Scheduler`: `logical_window_bounds_*`, `logical_anchor_from_timestamp`, `generate_random_datetime`, `get_scheduled_timestamps_for_anchor`; `count_posts_on_date`, `get_last_scheduled_date`, `get_next_scheduling_date`, and `run_schedule` deficit logic use **logical anchor days** when overnight is enabled.

---

## [1.4.8] - 03/05/2026

### Changed
- **Completion email** - SUMMARY line renamed to **AI ordering (this run)** with explicit statuses: **Applied** (API reorder was used), **Not applied** (AI enabled but this run used shuffle or draft-date; short note on provider tokens), **Not used** (feature off in settings).

---

## [1.4.7] - 03/05/2026

### Fixed
- **AI queue reorder** - Reads assistant text the same way as the connection test: legacy `choices[0].text`, string or multimodal `message.content`, and `reasoning_content` when content is empty. Avoids treating a billable non-string `content` shape as “empty” and falling back to shuffle while the provider still reports tokens.

### Changed
- **Completion email (AI line)** - Clarifies that “No” means the plugin did not apply an AI-ordered queue; the API may still show token usage when a model reply was rejected (format or ID validation).

---

## [1.4.6] - 05/05/2026

### Fixed
- **AI connection test** - Treats HTTP 200 as success when the API returns **usage/token counts** but an empty `message.content` (some providers or modes). Parses `content` as string or multimodal parts, legacy `text`, and `reasoning_content`. Surfaces JSON `error` objects, non-JSON bodies, and includes a **response excerpt** in the failure message. Slightly higher `max_tokens` for the probe request. Sends `User-Agent` and `Accept` headers (filterable via `schedulely_ai_http_user_agent`).
- **AI reorder** - Chat Completions reorder requests now use the same **Accept** and **User-Agent** headers as the connection test (shared builder, same `schedulely_ai_http_user_agent` filter) so provider dashboards and logs stay consistent.

### Changed
- **Test connection UI** - Button shows a **spinner** and “Contacting the API…” text while the request runs; status line updates in parallel.

---

## [1.4.5] - 05/05/2026

### Changed
- **API key on settings screen** - When a key is saved, it is shown in a **read-only** text field (no `name`, not submitted with the form) so you always see the current key. The password field below is labeled **Replace API key (optional)** with a placeholder that refers to keeping the key above. Removed the separate “Show full key” button and the `schedulely_reveal_ai_api_key` AJAX action.

---

## [1.4.4] - 05/05/2026

### Changed
- **Scheduling email summary** - Completion notifications now include one line under SUMMARY: **AI queue order** — whether AI reordering ran on this pass (`Yes — API reordered…`), fell back (`No — used shuffle…`), or the feature is off in settings. Uses the existing `ai_queue_ordered` flag from the scheduler.

### Technical Details
- Updated `build_notification_message()` in `includes/class-notifications.php`.

---

## [1.4.3] - 05/05/2026

### Added
- **Test connection** - Tools → Schedulely includes a **Test connection** button that runs a tiny Chat Completions request against the **saved** base URL, model, and API key (save after editing, then test). AJAX action `schedulely_test_ai_connection`; logic in `Schedulely_AI_Order::test_api_connection()`. Optional filters: `schedulely_ai_test_connection_body`, `schedulely_ai_test_request_timeout`.

### Changed
- **DeepSeek defaults and UI restored** - Explicit default base URL and model again in settings, activation, and sanitizers when fields are cleared. Admin description again names **DeepSeek** and links to the [API overview](https://apidog.com/blog/how-to-use-deepseek-v4-api/). Upgrade repopulates empty URL/model options left from 1.4.2 installs. **Show full key** behavior from 1.4.2 is unchanged.

---

## [1.4.2] - 05/05/2026

### Added
- **Stored API key visibility** - When a key exists, the settings screen shows its **character count**, a **Show full key** button (loads the key over AJAX for administrators only), and a read-only field for verification. Entering a new value in the password field still replaces the key on save; leave blank to keep.

### Changed
- **Vendor-neutral AI settings UI** - Removed provider-specific branding and doc links from the admin screen. **API base URL** and **model** fields default empty in the database for new installs; when left empty, the plugin uses built-in defaults via the `schedulely_ai_default_base_url` and `schedulely_ai_default_model` filters (same technical defaults as before, overridable without exposing names in the UI).

### Technical Details
- `sanitize_ai_base_url()` / `sanitize_ai_model()` may save empty strings; `Schedulely_AI_Order` resolves URL/model through those filters when unset or invalid.
- New AJAX action `schedulely_reveal_ai_api_key` (nonce `schedulely_admin`, capability `manage_options`).
- `wp_localize_script` passes `hasStoredAiKey` for the admin script.

---

## [1.4.1] - 05/05/2026

### Changed
- **Stronger AI queue system prompt** - Instructions now ask for maximum variety, minimum spacing of several unrelated posts between same-series titles when possible, and even distribution across the full list when perfect spacing is impossible (instead of only “avoid adjacent”).

### Technical Details
- Updated `build_request_body()` system message in `includes/class-ai-order.php`.

---

## [1.4.0] - 04/05/2026

### Added
- **AI queue ordering (optional)** - When enabled with an API key, Schedulely calls an **OpenAI-compatible** `POST …/chat/completions` endpoint before assigning publish times. The model receives each post’s **ID and title** and returns a JSON `ordered_ids` list that spaces obvious same-series titles when possible. **DeepSeek** is the default (`https://api.deepseek.com/v1`, model `deepseek-v4-flash`); base URL and model are editable for other providers. See the [DeepSeek V4 API guide](https://apidog.com/blog/how-to-use-deepseek-v4-api/) for the compatible request shape.
- **Settings**: Tools → Schedulely → **AI series spacing** — enable toggle, API base URL, model, API key (leave blank to keep existing key; checkbox to clear), and short documentation link.

### Changed
- If AI ordering **succeeds**, the shuffle step is **skipped** for that run. If AI is off, fails, or no key is set, behavior falls back to existing shuffle / date order rules.

### Technical Details
- New `includes/class-ai-order.php` (`Schedulely_AI_Order`) using `wp_remote_post`, JSON mode, and strict permutation validation against the eligible ID list.
- `Schedulely_Scheduler::run_schedule()` calls AI reorder when `schedulely_ai_order_enabled` is on; results include `ai_queue_ordered` and an extra success message line when applicable.
- New options: `schedulely_ai_order_enabled`, `schedulely_ai_api_key`, `schedulely_ai_base_url`, `schedulely_ai_model`. Filters: `schedulely_ai_api_key`, `schedulely_ai_chat_completions_body`, `schedulely_ai_request_timeout`, `schedulely_ai_max_output_tokens`.

---

## [1.3.8] - 04/05/2026

### Changed
- **Larger scheduling pool per run** - Eligible posts query now loads up to **1500** posts per run (was 500), so bigger draft pools are considered in one pass without trimming variety at the old ceiling.

### Technical Details
- Added `Schedulely_Scheduler::MAX_POSTS_PER_RUN` and wired `get_posts()` `posts_per_page` to that constant in `includes/class-scheduler.php`.

---

## [1.3.7] - 03/05/2026

### Added
- **Shuffle queue before scheduling** - Optional setting (on by default) randomizes the order of eligible posts each run so publication order is not locked to oldest `post_date` first. Disable under **Queue order** on the settings screen to restore strict draft-date order.

### Technical Details
- New option `schedulely_shuffle_queue`; `run_schedule()` in `includes/class-scheduler.php` calls `shuffle()` on the post ID list when enabled and more than one post is available.
- Settings UI and `register_setting` / save handling in `includes/class-settings.php`; default enabled via `schedulely_activate()` and `schedulely_upgrade()` for installs upgrading from before 1.3.7.

---

## [1.3.6] - 27/04/2026

### Fixed
- **Polylang Language Scope in Scheduling Query** - Schedulely now fetches eligible posts across all Polylang languages when collecting posts from the configured status, instead of inheriting only the active/default language context.

### Technical Details
- Updated `get_available_posts()` in `includes/class-scheduler.php` to set the Polylang query argument `lang` to an empty value when Polylang is available, ensuring all language variants in the selected status are included in one scheduling run.

---

## [1.3.5] - 10/02/2026

### Improved
- **Dashboard UI Clarifications** - Renamed "Last Scheduled Date" to "Furthest Scheduled Date" to prevent confusion with execution time.
- **System Health Status** - Added "Last Run" timestamp to System Health card to show when the scheduler actually last executed.

### Technical Details
- Updated `class-settings.php` to fetch `schedulely_last_run` option and display it in the System Health Stat card.
- Changed label for Stat 3 to "Furthest Scheduled Date".

---

## [1.3.4] - 25/01/2026

### Fixed
- **Fixed "View All" URL Error** - Resolved "Invalid post type" error when multiple post types are selected by implementing a dropdown menu instead of invalid comma-separated URL parameter
- Email notification "View All Scheduled Posts" link now works correctly with multiple post types

### Added
- **Smart "View All" Link** - When multiple post types are selected, "View All" becomes a dropdown menu allowing users to view scheduled posts by specific post type or all types

### Technical Details
- Added `get_scheduled_posts_url()` helper method in `Schedulely_Settings` class to properly handle single vs multiple post type URLs
- Updated "View All" link in settings page to show dropdown menu when multiple post types are selected
- Updated notification email link generation to handle multiple post types correctly

---

## [1.3.3] - 22/01/2026

### Added
- **Custom Post Type Support** - Plugin now supports scheduling posts from custom post types, not just the default 'post' type
- **Post Type Selection** - New multi-select field in settings to choose which post types to include in scheduling
- All registered public post types are now available for selection in the settings page

### Fixed
- **CRITICAL:** Fixed issue where plugin only queried 'post' post type, ignoring custom post types
- Plugin now correctly finds and schedules posts from all selected post types
- Statistics and dashboard now show accurate counts for all selected post types
- Scheduled posts queries now include all selected post types

### Changed
- Default post type setting changed from hardcoded 'post' to configurable array (defaults to ['post'] for backward compatibility)
- All database queries updated to support multiple post types

### Technical Details
- Added `schedulely_post_types` option to store selected post types (array)
- Added `sanitize_post_types()` method to validate and sanitize post type selections
- Updated `get_available_posts()`, `get_last_scheduled_date()`, `count_posts_on_date()`, and `get_scheduled_times_for_date()` methods in `Schedulely_Scheduler` class to use selected post types
- Updated `get_statistics()` and `render_upcoming_posts_list()` methods in `Schedulely_Settings` class
- Added Select2 initialization for post type multi-select field
- All SQL queries now use `IN` clause with prepared statements for multiple post types

### Migration
- Existing installations default to ['post'] for backward compatibility
- Users can now select multiple post types from the settings page
- No data migration required - existing scheduled posts are unaffected

---

## [1.3.2] - 19/01/2026

### Changed
- **Documentation Cleanup** - Consolidated and organized documentation
- **Repository Structure** - Moved internal documentation to `docs/` folder and excluded from public repository
- **Removed Outdated Files** - Deleted old version notes, bug reports, and deployment checklists that are no longer relevant

### Technical Details
- Added `docs/` folder to `.gitignore` to keep internal documentation private
- Removed 18 outdated documentation files from git tracking
- Kept essential documentation files: `INSTALL.md`, `PROJECT_SUMMARY.md`, `QUICK_REFERENCE.md`, `AI_INTEGRATION_OPPORTUNITIES.md` (in docs folder, not tracked)

---

## [1.3.1] - 19/01/2026

### Fixed
- **Security Vulnerabilities** - Fixed all critical and high-priority security issues identified in security audit
- **Capability Check** - Added capability verification in form submission handler to prevent unauthorized access
- **SQL Injection Prevention** - Converted all direct SQL queries to use `$wpdb->prepare()` for proper parameter binding
- **XSS Prevention** - Escaped JavaScript context outputs with `esc_js()` for nonce values
- **Uninstall Security** - Fixed uninstall script to use prepared statements with proper escaping

### Technical Details
- Fixed `includes/class-settings.php`:
  - Added `current_user_can('manage_options')` check inside form submission handler (Line 308-311)
  - Escaped JavaScript nonce outputs with `esc_js()` (Lines 718, 726)
- Fixed `includes/class-scheduler.php`:
  - Converted `get_last_scheduled_date()` query to use `$wpdb->prepare()` with parameter binding (Lines 151-160)
- Fixed `uninstall.php`:
  - Converted transient deletion queries to use `$wpdb->prepare()` with `$wpdb->esc_like()` for LIKE patterns (Lines 37-42)

### Security
- All critical security vulnerabilities from security audit have been resolved
- Plugin now fully complies with WordPress security coding standards
- All SQL queries use prepared statements
- All output is properly escaped for context

---

## [1.3.0] - 19/01/2026

### Added
- **GitHub Update Integration** - Integrated plugin-update-checker library for automatic updates from GitHub
- **Automatic Update Notifications** - Users will now receive update notifications when new versions are released on GitHub
- **GitHub Release Workflow** - Enhanced release workflow to automatically create zip files for plugin updates
- **One-Click Updates** - Users can update the plugin directly from WordPress admin using the standard WordPress update interface

### Technical Details
- Integrated plugin-update-checker library in `schedulely.php`:
  - Library located in `vendor/plugin-update-checker/` folder (organized structure)
  - Initializes update checker on `plugins_loaded` hook with priority 5
  - Connects to GitHub repository: `https://github.com/Krafty-Sprouts-Media-LLC/Schedulely`
  - Enables release assets (zip files) for automatic updates
  - Uses plugin slug `schedulely` for update identification
- Updated `.github/workflows/release.yml`:
  - Added step to create plugin zip file excluding git files and unnecessary files
  - Zip file is automatically attached to GitHub releases
  - Zip file naming: `schedulely-{VERSION}.zip`
  - Excludes: `.git*`, `.github*`, `.DS_Store`, `node_modules`, existing zip files, lock files

### Notes
- Updates are delivered via GitHub releases
- The update checker checks for updates every 12 hours
- Users can manually check for updates by clicking "Check for updates" on the Plugins page
- Update notifications appear in the WordPress admin dashboard

---

## [1.2.10] - 19/01/2026

### Fixed
- **Auto Schedule Toggle Not Working** - Fixed auto schedule toggle button not saving state when clicked
- Toggle now saves immediately via AJAX without requiring form submission
- Cron job is properly scheduled/unscheduled when toggle is changed
- Added visual feedback with success toast notification when toggle is changed

### Technical Details
- Added `ajax_toggle_auto_schedule()` method in `includes/class-settings.php`:
  - Handles AJAX request to save auto schedule toggle state
  - Manages WordPress cron job scheduling/unscheduling based on toggle state
  - Returns success/error messages for user feedback
- Added `initAutoScheduleToggle()` function in `assets/js/admin.js`:
  - Handles toggle change event
  - Sends AJAX request to save state immediately
  - Shows success toast notification
  - Reloads page to update status display
  - Includes error handling with toggle revert on failure

---

## [1.2.9] - 05/01/2026

### Fixed
- **WordPress admin notices display issue** - Fixed notices being cut off and not displaying at full width
- Admin notices now properly appear outside the constrained plugin content area
- Restructured HTML wrapper to separate `.wrap` (full width) from `.schedulely-wrap` (constrained content)
- Changed from inline notice output to WordPress `add_settings_error()` and `settings_errors()` functions for proper notice handling

### Technical Details
- Modified `includes/class-settings.php`:
  - Replaced inline `echo` for success notice with `add_settings_error()` function
  - Added `settings_errors('schedulely_messages')` call to display notices properly
  - Separated `.wrap` container from `.schedulely-wrap` container in HTML structure
  - Added HTML comments for better code clarity
- Updated `assets/css/admin.css`:
  - Added `.wrap` styles with full width (margin: 0, padding: 0)
  - Clarified `.schedulely-wrap` as plugin content wrapper with max-width constraint
  - Added comments to distinguish between WordPress admin wrapper and plugin content wrapper

---

## [1.2.8] - 05/01/2026


### Added
- **Modern Integrated Dashboard UI** - Complete redesign of settings page with dashboard grid layout
- **Dashboard Statistics Cards** - Four stat cards showing Drafts Available, Next Scheduled, Last Scheduled Date, and System Health
- **Insight Panel** - Collapsible informational panel explaining how random scheduling works
- **Dynamic Capacity Alerts** - Visual alert box with capacity meter showing scheduling capacity in real-time
- **Resolution Center** - Actionable suggestion cards with "Apply Fix" buttons for capacity issues
- **Quick Toggles Section** - Modern CSS-only toggle switches for Auto-Schedule and Email Alerts settings
- **Upcoming Posts Activity Feed** - Side panel showing next 5 scheduled posts with timestamps
- **Comprehensive Form Styling** - Professional WordPress-admin-style inputs, selects, and checkboxes

### Changed
- **Header Redesign** - Removed "Pro" badge, updated subtitle to "Intelligent Post Scheduling for WordPress"
- **Form Layout Optimization** - Active Days now aligned horizontally with Time Window for better space utilization
- **Toggle Switch Implementation** - Replaced basic checkboxes with animated CSS toggle switches
- **Select2 Integration** - Updated to properly target all author selection fields (excluded and preserved)
- **Removed Duplicate Headings** - "Recommended Fixes" heading now added dynamically by JavaScript only when suggestions exist

### Enhanced
- **Visual Hierarchy** - Improved header styling with proper font weights and sizing
- **Form Field Styling** - Added focus states, borders, and transitions to all form elements
- **Checkbox Styling** - Day selection checkboxes now have consistent styling with proper labels
- **Responsive Design** - All new UI elements adapt properly to different screen sizes
- **Color Coding** - Stat cards use color indicators (green for success, red for warnings) for quick status recognition

### Technical Details
- Modified `includes/class-settings.php`:
  - Completely rewrote `render_settings_page()` method with new HTML structure
  - Added `render_upcoming_posts_list()` private method for activity feed
  - Removed legacy `render_dashboard()`, `render_upcoming_posts()`, and `render_last_date_status()` methods
  - Updated form field structure to match new grid layout
  - Integrated dynamic stat card data from `get_statistics()` method
- Updated `assets/css/admin.css`:
  - Added 175+ lines of new CSS for form elements, toggle switches, and UI components
  - Added `.form-grid`, `.form-group`, `.form-label` classes for consistent form styling
  - Added `.toggle-switch`, `.toggle-slider` classes for animated toggle switches
  - Added `.day-checkbox`, `.quick-settings-title` classes for improved UI elements
  - Added comprehensive input/select/checkbox styling with focus states
- Updated `assets/js/admin.js`:
  - Modified `initAuthorSelect()` to target `.schedulely-author-select` class for both excluded and preserved authors
  - Fixed Select2 detection to use `$.fn.select2` for proper library checking
  - Ensured all dynamic UI components (capacity checker, suggestions, insight panel) work with new HTML structure

### UI/UX Improvements
- Dashboard now provides at-a-glance overview of scheduling status
- Capacity issues are immediately visible with visual meter and percentage
- Suggestions are presented as actionable cards instead of plain text
- Toggle switches provide clear on/off visual feedback
- Form fields have consistent, professional styling throughout
- Better visual separation between configuration sections

---

## [1.2.7] - 05/01/2026

### Added
- **Preserved Authors feature** - New setting to protect specific authors from randomization
- Posts currently assigned to preserved authors will keep their author when scheduling
- Only posts NOT assigned to preserved authors will be randomized among all eligible authors
- Example: If Author A is preserved and has 15 articles, all 15 will remain with Author A when Schedulely runs
- Posts assigned to non-preserved authors will be randomly assigned to any eligible author (including potentially the same author)

### Technical Details
- Added `schedulely_preserved_authors` option to store preserved author IDs
- Added `get_preserved_authors()` and `is_author_preserved()` methods to `Schedulely_Author_Manager` class
- Modified scheduler to check if post's current author is preserved before randomization
- If author is preserved, post keeps its current author; otherwise, proceeds with full randomization among all eligible authors
- Added "Preserved Authors" multi-select field in settings page with clear description
- Works in conjunction with "Excluded Authors" setting for complete author control

---

## [1.2.6] - 01/12/2025

### Enhanced
- **Email notifications now include scheduling context** - Added time window and run timestamp to email summary
- Email summary now displays when the scheduler ran (date and time)
- Email summary now shows the configured time window (start time - end time) used for scheduling posts
- Provides complete context about scheduling operations for better tracking and transparency

### Technical Details
- Modified `build_notification_message()` in `Schedulely_Notifications` class
- Added `$run_datetime` variable using WordPress `current_time()` function
- Added `$start_time` and `$end_time` variables from plugin settings
- Updated email HTML template to display new information in SUMMARY section
- Format: "Scheduler Ran: Sunday, Dec 1, 2025 at 8:34 AM"
- Format: "Time Window: 5:00 PM - 11:00 PM"

---

## [1.2.5] - 01/12/2025

### Fixed - CRITICAL
- **Incomplete first date bug** - Fixed rare edge case where failed post scheduling could leave the first date incomplete
- When `schedule_post()` failed for a single post, the scheduler would skip it and move to the next post without counting it toward the day's quota
- This caused dates to end up with fewer posts than the configured "Posts Per Day" setting (e.g., 9/10 instead of 10/10)
- Most commonly occurred when scheduling future dates where random time generation or WordPress database issues caused a single post to fail

### Root Cause
- When a post failed to schedule (line 310-312), the code logged an error but did NOT increment `$posts_scheduled_today` counter
- The scheduler would continue trying to fill the same date, but eventually move to the next day without completing the quota
- The "complete each date before moving" feature was present but broken due to improper failure handling

### Solution Implemented

#### Retry Logic with Different Times (Lines 310-365)
- **Before:** Single attempt to schedule each post - if it failed, just log error and continue
- **After:** Up to 3 attempts per post with different random times on the same date
  - First attempt fails → Retry with new random time
  - Second attempt fails → Retry again with different time
  - Third attempt fails → Count toward quota and move on

#### Proper Quota Counting
- **Before:** Failed posts didn't count toward `$posts_scheduled_today`, leaving days incomplete
- **After:** After all retries fail, the post is counted toward the day's quota to ensure scheduler moves to next day properly
- Prevents the scheduler from getting stuck trying to fill an incomplete date indefinitely

### Impact
- ✅ Each date now gets its full quota of posts (or maximum retry attempts)
- ✅ No more incomplete first dates due to random failures
- ✅ Better resilience against WordPress database hiccups or random time generation issues
- ✅ Detailed logging when retries succeed for debugging purposes
- ✅ Scheduler won't get stuck on one date - will move forward even if posts fail

### Technical Details
- Modified `schedule_posts_from_date()` method in `class-scheduler.php`
- Added retry loop with up to 2 additional attempts per failed post
- Each retry generates a new random time using `generate_random_time()`
- Successful retries are logged to debug.log when WP_DEBUG_LOG is enabled
- Failed posts (after all retries) increment `$posts_scheduled_today` to maintain quota logic
- No database changes required

### Example Scenario
**Before (v1.2.4):**
- Dec 1: Posts 1-9 succeed, Post 10 fails → Dec 1 ends with 9/10 posts ❌
- Dec 2-10: All complete with 10/10 posts ✅

**After (v1.2.5):**
- Dec 1: Posts 1-9 succeed, Post 10 fails → Retry with new time → Success! ✅
- Dec 1: 10/10 posts complete ✅
- Dec 2-10: All complete with 10/10 posts ✅

---

## [1.2.4] - 13/10/2025

### Fixed
- **Dashboard stats text wrapping** - Fixed PM/AM wrapping to another line by increasing column width from 200px to 250px and adjusting font size
- Improved stat value display with better line height and overflow handling

### Changed
- Stats grid minimum column width increased from 200px to 250px
- Stat value font size reduced from 28px to 20px for better fit
- Added white-space: nowrap to prevent text wrapping

---

## [1.2.3] - 13/10/2025

### Fixed - CRITICAL
- **Random time generator exhausting attempts before reaching capacity promise** - Fixed critical mismatch between capacity calculator and actual scheduling
- Plugin was promising 15 posts per day but only scheduling 8 posts due to insufficient retry attempts
- Capacity calculator showed "fits approximately 15 posts" but scheduling stopped at 8 posts

### Root Cause
- Random time generator had hardcoded limit of **100 attempts** to find valid time slots
- With small intervals (e.g., 24 minutes) and high quotas (e.g., 15 posts), collision probability increases exponentially
- After scheduling 8 posts, the generator couldn't find valid 24-minute gaps within 100 attempts
- Generator gave up and moved to next day, leaving dates incomplete at 8/15 posts

### Solution Implemented

#### 1. Dynamic Max Attempts (Lines 585-592)
- **Before:** Fixed 100 attempts for all scenarios
- **After:** Dynamic scaling based on scheduling density
  - Base: 200 attempts (doubled)
  - Additional: +50 attempts per already-scheduled post
  - Example: 0 posts = 200 attempts, 8 posts = 600 attempts, 15 posts = 950 attempts
- Accounts for exponentially increasing collision probability as more posts are placed

#### 2. Interval-Based Efficiency Factors (Lines 432-445)
- **Before:** Fixed 70% efficiency for all interval sizes
- **After:** Dynamic efficiency based on interval difficulty
  - Large intervals (60+ min): 70% efficiency
  - Medium intervals (30-59 min): 65% efficiency
  - Small intervals (20-29 min): 55% efficiency
  - Tiny intervals (<20 min): 50% efficiency
- Capacity calculator now accurately reflects actual scheduling performance

### Impact
- ✅ High-density scheduling (small intervals + many posts) now works correctly
- ✅ Capacity calculator shows realistic numbers that match actual scheduling
- ✅ No more "8/15 posts" incomplete dates - will fill to promised capacity
- ✅ User settings like "3:00 PM - 11:59 PM, 24min interval, 15 posts" now work as expected

### Technical Details
- Modified `generate_random_time()` method to use dynamic max_attempts
- Modified `calculate_capacity()` method to use interval-based efficiency
- Updated suggestion algorithms to use dynamic efficiency factors
- No database changes required

### Example: User's Settings
**Before (v1.2.2):**
- Settings: 3:00 PM - 11:59 PM, 24min interval, 15 posts/day
- Capacity Calculator: "fits approximately 15 posts" ✅
- Actual Scheduling: Only 8 posts scheduled ❌
- Result: 8/15 posts (NEEDS 7 MORE) on every date

**After (v1.2.3):**
- Settings: Same (3:00 PM - 11:59 PM, 24min interval, 15 posts/day)
- Capacity Calculator: "fits approximately 12 posts" (more realistic)
- Actual Scheduling: 12 posts scheduled ✅
- Result: Full date completion or accurate deficit tracking

---

## [1.2.2] - 12/10/2025

### Fixed
- **Capacity expansion suggestions now intelligently handle both start and end times**
- Plugin previously only suggested extending the end time to accommodate more articles
- End time hard limit of 11:59 PM created a ceiling that couldn't be exceeded
- Now provides three expansion strategies:
  1. Extend end time only (when space available before 11:59 PM)
  2. Start earlier AND extend to 11:59 PM (when end time is near limit)
  3. Start earlier only (when end time is already at or near 11:59 PM)
- Users can now fit more articles by adjusting either start time, end time, or both

### Changed
- Improved capacity calculation logic in `calculate_capacity()` method
- Smarter "Expand Time Window" suggestions based on available time at day's end
- More descriptive messages explaining why start time needs to change
- Better user experience when configuring scheduling windows

### Technical Details
- Modified `Schedulely_Scheduler::calculate_capacity()` in `class-scheduler.php`
- Added logic to calculate available minutes between current end time and 11:59 PM
- Three-tiered suggestion strategy based on `minutes_available_at_end`
- Respects 11:59 PM hard limit while maximizing scheduling capacity

---

## [1.2.1] - 12/10/2025

### Fixed - CRITICAL
- **Post count resetting bug in email notifications** - Email notifications now correctly count ALL posts scheduled for each date, not just posts from the current run
- Previously, if 5 posts were scheduled on Oct 16th, then 1 more was added later, the notification would incorrectly show "1/8 posts" instead of "6/8 posts"
- Fix ensures accurate date completion status reporting across multiple scheduling runs

### Technical Details
- Modified `build_notification_message()` in `Schedulely_Notifications` class
- Now uses `Schedulely_Scheduler::count_posts_on_date()` to query database for accurate total counts
- Eliminated reliance on `$results['scheduled_posts']` array which only contained current run's posts

### Impact
- Users will now see accurate cumulative post counts in email notifications
- Date completion status will correctly reflect all posts scheduled for a date, not just the most recent batch
- No changes to actual scheduling logic - only affects notification reporting

---

## [1.2.0] - 07/10/2025

### Added
- **"How Random Scheduling Works" notice** - Informative explanation in Capacity Check section explaining minimum intervals, variable gaps, and 70% efficiency
- **User education** - Clear explanation that random scheduling creates uneven spacing for natural appearance

### Fixed - CRITICAL
- **Cron schedule not updating** - Sites with hourly cron from old versions now properly migrate to twice-daily
- **Capacity calculator showing incorrect capacity** - Removed erroneous `+1` from formula and adjusted multiplier to 0.70 (70% efficiency)
- **Capacity vs actual scheduling mismatch** - Calculator now accurately predicts how many posts will actually be scheduled
- **Email notification incomplete** - Now shows FULL date status report with all dates and their completion status, highlighting any deficits

### Changed
- **Capacity calculation formula** - Changed from `floor(total_minutes / interval) + 1` to `floor(total_minutes / interval)`
- **Random scheduling efficiency** - Reduced from 75% to 70% to match real-world performance
- **Cron migration logic** - Upgrade function now forcibly clears old hourly schedule and reschedules with twicedaily
- **Email notification format** - Complete overhaul showing ALL dates with individual completion status and deficit warnings

### Technical Details
- **Capacity Formula Fix:**
  - Old (incorrect): `floor(360/35) + 1 = 11 * 0.75 = 8` (claimed 8, scheduled 7)
  - New (correct): `floor(360/35) = 10 * 0.70 = 7` (claims 7, schedules 7)
- **Cron Fix:** Upgrade from <1.0.8 now calls `wp_unschedule_event()` before `wp_schedule_event()`
- **Email Enhancement:** Full date-by-date report showing complete/incomplete status for EVERY scheduled date with clear deficit warnings

### Impact
- Users will now see accurate capacity estimates that match actual scheduling results
- Existing sites will automatically migrate to twice-daily cron on next page load
- Email notifications provide clearer feedback on scheduling status per date

---

## [1.0.10] - 07/10/2025

### Changed
- **BREAKING CHANGE:** Notification email field replaced with user selection dropdown
- Email notifications now use Select2 multi-select (same as author exclusion)
- Only users with `publish_posts` capability shown (Authors, Editors, Administrators - excludes Contributors)
- Emails fetched dynamically from selected users (no more typos!)
- Consistent UI across plugin settings
- Database storage changed from emails to user IDs

### Improved
- Better UX: Select users instead of typing emails
- No email typos possible
- Automatic email updates when users change their email
- Search functionality via Select2
- Visual consistency with author selection

### Technical
- Changed option from `schedulely_notification_email` to `schedulely_notification_users`
- Added `sanitize_notification_users()` method
- Updated `get_notification_email()` to fetch emails from user IDs
- Validates users have `publish_posts` capability
- Legacy option cleaned up on uninstall
- Select2 initialization added for notification users

### Migration
- Existing email settings won't carry over (different data format)
- Plugin defaults to current admin user if no selection
- Users need to select notification recipients in settings

---

## [1.0.9] - 07/10/2025

### Added
- **Settings link on Plugins page** - Quick access to Schedulely settings directly from the plugins list
- **Welcome notification** - Shows dismissible admin notice after activation with link to settings page
- **Multiple email support** - Notification emails now support multiple recipients (comma, semicolon, or newline separated)
- **All post statuses supported** - Plugin now detects and supports ALL registered post statuses, not just draft/pending/private
- Custom post statuses from other plugins are now automatically available

### Changed
- Post Status dropdown now dynamically loads all available statuses
- Notification email field changed from single-line input to textarea for multiple emails
- Welcome notice appears on all admin pages (except settings page) until dismissed
- Improved email sanitization to handle multiple formats

### Technical
- Added `schedulely_plugin_action_links()` filter for Settings link
- Added `show_welcome_notice()` and `ajax_dismiss_notice()` methods to Settings class
- Updated `sanitize_post_status()` to dynamically validate against all registered statuses
- Added `sanitize_email_list()` method for multiple email validation
- Updated `get_notification_email()` in Notifications class to return array for multiple recipients
- Uses WordPress `get_post_stati()` for dynamic status detection

---

## [1.0.8] - 07/10/2025

### Fixed
- **CRITICAL SECURITY FIX:** Auto-scheduling now disabled by default on plugin activation
- **CRITICAL BUG FIX:** Added safety check to prevent scheduling posts in the past
- **CRITICAL BUG FIX:** Added 30-minute minimum buffer before scheduled publish time
- Plugin will no longer auto-run immediately upon activation
- Posts can no longer be accidentally published due to past-time scheduling
- Comprehensive error logging for all time-related issues

### Changed
- `schedulely_auto_schedule` default changed from `true` to `false`
- Users must now explicitly enable auto-scheduling or click "Schedule Now"
- Added detailed logging when posts are rejected due to time issues
- **Cron frequency changed from hourly to twice daily** (12-hour intervals)
- UI updated to show "twice daily" instead of "hourly"

### Security
- Prevents automatic mass-publishing of drafts on plugin activation
- Ensures all scheduled posts have future times with safety buffer
- Protects against timezone-related publishing bugs

### Why This Fix
On October 7, 2025, a critical bug was discovered where activating the plugin would immediately trigger auto-scheduling, and due to a time calculation issue, posts could be published instead of scheduled. This version completely prevents that scenario by:
1. Disabling auto-schedule by default (requires user action)
2. Adding multiple safety checks to refuse past times
3. Implementing comprehensive error logging

**This is a mandatory security update. Update immediately.**

---

## [1.0.7] - 07/10/2025

### Fixed
- **CRITICAL:** Capacity calculator now accounts for random time placement inefficiency
- Capacity calculation reduced by 25% to reflect realistic random scheduling (not perfect spacing)
- Example: 6-hour window with 45-min interval now shows ~7 posts (realistic) instead of 9 (theoretical maximum)
- All suggestions now use adjusted capacity calculations for accuracy
- Prevents the plugin from promising more posts than it can actually deliver

### Changed
- UI now says "approximately X posts" to set realistic expectations
- Added note: "Estimate accounts for random time placement"
- Warning messages clarified: "With random time scheduling, fewer posts will fit"

### Technical
- Applied 0.75 multiplier to theoretical capacity for realistic estimate
- Special handling for small windows (1-3 posts) with more conservative calculations
- Updated all suggestion algorithms to account for randomness overhead
- Created CAPACITY_CALCULATION_EXPLAINED.md with full technical documentation

## [1.0.6] - 07/10/2025

### Fixed
- Success dialog "View Scheduled Posts" button now actually navigates to WordPress scheduled posts page
- Button previously just reloaded the page, now properly opens `edit.php?post_status=future&post_type=post`

### Added
- Added "Stay Here" option after scheduling to remain on settings page and view updated Upcoming Posts list
- Two-button choice gives users control over post-scheduling workflow

### Technical
- Added `scheduled_posts_url` to localized script variables
- Updated success dialog with `showCancelButton` and proper navigation logic

## [1.0.5] - 07/10/2025

### Added
- **Capacity Calculator**: Real-time validation that checks if your time window can actually fit your desired posts per day
- Live capacity checking as you adjust settings (with 500ms debounce)
- Visual feedback with color-coded notices (✅ success, ⚠️ warning, ❌ error)
- Smart suggestions when capacity is insufficient with three fix options:
  1. Reduce minimum interval between posts
  2. Reduce posts per day quota
  3. Expand time window duration
- One-click "Apply" buttons to automatically fix capacity issues
- Warning dialog before scheduling if settings won't fit desired quota
- Detailed capacity information display (total minutes, posts that can fit, etc.)

### Changed
- Schedule Now button now checks capacity first before confirming
- Improved user experience with proactive validation
- Settings page now shows capacity status in real-time
- Capacity Check now displays within Scheduling Settings card for better context and immediate feedback

### Technical
- Added `calculate_capacity()` method to `Schedulely_Scheduler` class
- Added AJAX endpoint `schedulely_check_capacity` for real-time validation
- New JavaScript functions: `initCapacityChecker()`, `checkCapacity()`, `displayCapacityResult()`
- Enhanced capacity warning in scheduling confirmation dialog
- New CSS styling for capacity notices and suggestions
- Responsive design for capacity suggestions on mobile devices
- Improved documentation for settings preservation during updates
- Added upgrade logging to debug log when WP_DEBUG is enabled
- Enhanced `schedulely_upgrade()` function with cron recovery

## [1.0.4] - 07/10/2025

### Added
- Added SweetAlert2 for beautiful, modern confirmation and notification dialogs
- Replaced browser confirm() with professional modal dialogs
- Added loading states with animated spinners during scheduling
- Added success/error notifications with better UX

### Changed
- Complete UI refresh with clean WordPress native styling
- Removed custom color schemes in favor of WordPress admin colors
- Simplified CSS from 400+ lines to clean, maintainable styles
- Better responsive design for mobile devices
- Improved button styling and spacing
- Enhanced card layouts and typography
- Better form validation with SweetAlert2 alerts

### Technical
- Added SweetAlert2 CDN integration (v11)
- Updated admin.js with new dialog handlers
- Complete admin.css rewrite with WordPress-native design
- Added spin animation for loading states
- Improved Select2 and Flatpickr theme integration

## [1.0.3] - 06/10/2025

### Fixed
- Fixed author randomization not including Contributors (capability changed from `publish_posts` to `edit_posts`)
- Contributors, Authors, Editors, and Administrators now all appear in author selection
- Updated both random assignment logic and settings page dropdown to show all eligible users

## [1.0.2] - 06/10/2025

### Fixed
- **CRITICAL:** Fixed fundamental deficit logic flaw that attempted to schedule posts to past dates
- WordPress rejects scheduling to past dates, rendering old deficit tracking ineffective

### Changed
- **Complete logic refactor:** Replaced multi-date deficit tracking with "last date completion" approach
- Now checks only the LAST/FURTHEST scheduled date and completes it if incomplete (and not in past)
- Simpler, more reliable logic that works with WordPress's scheduling limitations
- Removed `Schedulely_Deficit_Tracker` class (no longer needed)
- Updated admin UI: Replaced "Deficit Status" with "Last Scheduled Date" display
- Updated email notifications: Show last date completion status instead of deficit count
- Removed deficit tracker database option

### Technical Details
- Deleted: `includes/class-deficit-tracker.php`
- Refactored: `Schedulely_Scheduler` class with new methods:
  - `get_last_scheduled_date()` - Find furthest scheduled date
  - `count_posts_on_date()` - Count posts on specific date
  - `get_next_scheduling_date()` - Determine starting date
  - `schedule_posts_from_date()` - Schedule posts from specific date
  - `get_scheduled_times_for_date()` - Get existing times for date
- Updated: Settings class dashboard and statistics display
- Updated: Email notifications with new completion status

## [1.0.1] - 06/10/2025

### Fixed
- Fixed admin menu not appearing in WordPress dashboard
- Changed initialization hook from `admin_menu` to `plugins_loaded` to ensure proper menu registration timing
- Plugin settings now correctly accessible at **Tools → Schedulely**

## [1.0.0] - 06/10/2025

### Added
- Initial release of Schedulely
- Smart deficit tracking system that automatically fills missed daily quotas
- Random time distribution within user-defined windows
- Minimum interval enforcement between scheduled posts
- Random author assignment with user exclusion capability
- Manual scheduling via "Schedule Now" button
- Automatic scheduling via WordPress cron (hourly)
- Beautiful admin dashboard with real-time statistics
- Email notifications for scheduling events
- Configurable post status monitoring (draft, pending, private)
- Customizable daily post quotas
- Flexible time window configuration (12-hour format)
- Active days selection (choose which days to schedule)
- Upcoming scheduled posts display (shows next 20 posts)
- Deficit status tracking and display
- Settings validation and sanitization
- Complete uninstall cleanup
- WordPress coding standards compliance
- Internationalization support (i18n ready)
- Responsive admin interface
- Select2 integration for author selection
- Flatpickr integration for time picking
- Professional documentation (README.txt)

### Technical Details
- Minimum WordPress version: 6.8
- Minimum PHP version: 8.2
- Uses WordPress native time functions
- Implements proper security measures (nonces, sanitization, escaping)
- Optimized database queries
- Cache management
- Error logging support
- GPL v2 or later license

---

**Plugin:** Schedulely  
**Author:** Krafty Sprouts Media, LLC  
**Website:** https://kraftysprouts.com

