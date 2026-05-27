# agents.md — Operating Rules for AI Agents on the Schedulely Codebase

This file is for AI coding agents (Cursor, Claude Code, Copilot, Kiro, Cline, etc.) working on this plugin. It encodes lessons from the May 2026 audit (`audit/00-summary-and-verdict.md`) as rules. Read this before any non-trivial change.

The companion human-facing document is `development.md`. The audit's full findings are in `audit/`. The WP 7.0 AI integration audit is in `audit-schedulely-wp70-ai.md`. **The execution sequence and task-level plan live in `IMPLEMENTATION_PLAN.md` at the plugin root — read it before starting non-trivial work.**

---

## 1. Plugin overview

**What.** Schedulely takes posts in a configurable status (`draft`, `pending`, etc.) and assigns them future publish times in a configurable window with quotas, intervals, active days, and optional AI ordering of the queue.

**Operational modes.**
- Manual: admin clicks `Tools → Schedulely → Run Schedule Now`
- Automatic: WP-Cron event `schedulely_auto_schedule` runs `twicedaily`

**Architecture today.**
- Procedural bootstrap in `schedulely.php`
- Five class files in `includes/`
- One vendored library: `vendor/plugin-update-checker/` (must be removed for the wp.org build)
- All UI in `Schedulely_Settings` (a 1326-line God class — refactor target)

**Tech stack.** PHP 8.2+, WP 6.8+, jQuery + vanilla JS in admin, no build step, no Composer.

---

## 2. Versioning & `@since` tags

### Current version

The active development version is **1.6.0**. All new code you write targets this version.

Do not write `1.5.10` in new code. Do not invent a future version number (e.g. `1.7.0`). If the version has been bumped since this document was last updated, read `schedulely.php` header (`Version:`) and use that number.

### `@since` rules

`@since` means "this was **introduced** at this version." It is never retroactively rewritten.

| Situation | What to write |
|---|---|
| New function, class, method, or constant you are writing now | `@since 1.6.0` |
| New parameter added to an existing method | `@since 1.6.0` on that `@param` line only |
| Existing function whose **behaviour you are changing** | Keep the original `@since`. Add a second `@since 1.6.0 Description of what changed.` line below it |
| Existing function you are only reading or calling | Touch nothing |
| Deprecated function | Add `@deprecated 1.6.0 Use replacement_function() instead.` |

**Examples:**

```php
// New function — this version only
/**
 * Check whether WP 7.0 AI is available on this install.
 *
 * @since 1.6.0
 * @return bool
 */
function schedulely_wp_ai_available(): bool { ... }


// Modified existing function — keep original @since, add change note
/**
 * Clear plugin caches.
 *
 * @since 1.0.0
 * @since 1.6.0 Removed wp_cache_flush() — no longer evicts the site-wide object cache.
 */
function schedulely_clear_cache() { ... }


// New optional parameter on an existing method
/**
 * Run the scheduling process.
 *
 * @since 1.0.0
 *
 * @param bool $dry_run Optional. When true, returns what would be scheduled without
 *                      committing changes. Default false.
 *                      @since 1.6.0
 * @return array Results of scheduling operation.
 */
public function run_schedule( bool $dry_run = false ): array { ... }
```

### Version bump rules

- All work across Phase 0 through Phase 4 accumulates into **one release: 1.6.0**
- Do not bump the version mid-phase
- The version is bumped at Phase 4 exit gate (P4-T8) — once, by the release manager
- The bump touches exactly three places: `Version:` header in `schedulely.php`, `SCHEDULELY_VERSION` constant in `schedulely.php`, `Stable tag:` in `readme.txt`
- All three must match exactly

---

## 3. WordPress standards to enforce

### Naming
- Every class: `Schedulely_*`
- Every function: `schedulely_*`
- Every option: `schedulely_*`
- Every action/filter the plugin defines: `schedulely_*`
- Every CSS class: `schedulely-*` or `dash-*` (legacy) — prefer `schedulely-` for new code

### Hooks
- Default priority is 10 unless there's a documented reason otherwise
- The single existing exception is `plugins_loaded` priority 5 for the update checker bootstrap (which is being removed for wp.org)
- Document any priority deviation with an inline `// reason:` comment

### APIs to prefer
| Need | Use | Don't |
|---|---|---|
| Counts | `wp_count_posts` | `count(get_posts(['posts_per_page'=>-1]))` |
| Time formatting | `wp_date()` | `date()` |
| Current Unix time | `time()` | `current_time('timestamp')` (deprecated) |
| Site timezone | `wp_timezone()` | PHP `date_default_timezone_get()` |
| Database writes | `update_option`, `wp_update_post`, `$wpdb->prepare` | Direct SQL, raw concatenation |
| Cache ops | `wp_cache_delete` for named keys | `wp_cache_flush()` (NEVER, see § 5) |
| Translations | `__()`, `esc_html__()`, `_e()` family | Hardcoded English |
| Output | `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` | Raw `echo $variable` |

### Settings handling
The plugin uses a manual form-handler pattern, NOT the Settings API's `do_settings_sections` flow, because the dashboard is custom-built. When adding a new option:
1. Add an `add_option('schedulely_x', $default)` line in `schedulely_activate()`
2. Add a sanitizer method on `Schedulely_Settings`
3. Add a corresponding `update_option` line in the `if (isset($_POST['schedulely_save_settings']))` block
4. Add a `delete_option` line in `uninstall.php`
5. Add the form field to the render output, escaped
6. Add the default to a future `Schedulely_Defaults` constant once it exists

**Defaults must match between activation and reads.** This caused the `auto_schedule` bug. Always pass the same default to every `get_option` call site.

---

## 4. Security rules — mandatory patterns

### Input
Every `$_POST` / `$_GET` / `$_REQUEST` access:
```php
$value = isset( $_POST['x'] ) ? wp_unslash( $_POST['x'] ) : '';  // Unslash first
$value = sanitize_text_field( $value );                            // Then sanitize
```
Never combine the two; `sanitize_*` does not unslash.

### Output
**Never** `_e()` for strings containing HTML. Use:
```php
<?php esc_html_e('Plain text only', 'schedulely'); ?>
<?php echo wp_kses_post( __('Text with <strong>HTML</strong>.', 'schedulely') ); ?>
```

Every dynamic value:
```php
echo esc_html( $value );                  // Body text
echo esc_attr( $value );                  // Attribute value
echo esc_url( $url );                     // href, src, action
echo wp_kses_post( $rich_html );          // Allowed-tag HTML
```

### Nonces
- AJAX handlers: `check_ajax_referer( 'schedulely_admin', 'nonce' )` first, then capability check
- Form submits: `wp_nonce_field('schedulely_settings_save')` in the form, `check_admin_referer('schedulely_settings_save')` in the handler
- `admin-post.php` actions: their own nonce field with a unique action name (the 1.5.6 fix shows why — a duplicate `_wpnonce` field broke the main form save)

### Capabilities
Every privileged operation:
```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error([ 'message' => __( 'Insufficient permissions', 'schedulely' ) ]);
}
```
Use `manage_options` consistently for this plugin's admin features.

### Credentials
- API keys are stored in `wp_options`. Acceptable for this plugin's use case.
- **Do not render API keys in plaintext into HTML** (the current pattern is wrong; fixing in Phase 1)
- When adding "reveal" features, require an explicit user click + AJAX call with a fresh nonce

### Database
- Always use `$wpdb->prepare` even when the only dynamic part is a known-safe value
- For dynamic IN clauses, build placeholders with `array_fill`:
  ```php
  $placeholders = implode(',', array_fill(0, count($values), '%s'));
  ```
- All current queries hit `wp_posts` indexes (filter by `post_status` + `post_type` + `post_date`) — preserve this when adding new queries

### Errors
- Never echo raw `$exception->getMessage()` to users; log it, send a generic message
- `schedulely_log_error()` is gated by `WP_DEBUG_LOG`; respect that gate

---

## 5. Performance rules — critical

These rules are derived directly from past production issues.

### NEVER
- **Never call `wp_cache_flush()`.** It nukes the entire site object cache. The current codebase has one in `schedulely_clear_cache()` and one in `uninstall.php` — both are being removed. Do not reintroduce.
- **Never use `posts_per_page => -1`** on a query that doesn't constrain by a small known set
- **Never run synchronous external HTTP calls inside the cron tick** without a hard timeout under 30 seconds
- **Never run `version_compare`-style migrations on `init` or front-end requests** when an `admin_init` or activation gate would do
- **Never query the database in a render loop** without first batching or pre-priming the cache

### MUST
- Cap any post query that loops to 200 by default. Use Action Scheduler (when added) for larger sets.
- Cache user/author lookups for the duration of a single scheduling pass (member variable)
- For dashboard counts, use `wp_count_posts` (heavily cached)
- For statistics-style queries called in a render path, memoize within the request

### MUST CACHE
| Operation | Cache method | TTL |
|---|---|---|
| Eligible authors list (per scheduling pass) | Member variable | request |
| Eligible authors list (dashboard render) | `wp_cache_set` with a transient fallback | 5 min |
| Last scheduled date (dashboard render) | Memoize per request | request |
| AI ordering result (when input identical) | Transient keyed by hash of post IDs | 24 h |

### MUST DEFER
- AI HTTP calls — only on manual `Schedule Now` (admin-blocking is acceptable when the admin clicked) or via Action Scheduler. **Never** on cron-driven runs without async support.

---

## 6. Database rules

### Schema
**No custom tables.** All persistence in `wp_options`. Adding a custom table requires:
1. Discussion in the issue tracker first
2. `dbDelta()` for creation in activation hook
3. Drop in `uninstall.php`
4. Indexes for every column used in WHERE/ORDER BY
5. Documentation in this file's "Tables" subsection

If you must add a table, prefer Action Scheduler's existing tables for queue-shaped data.

### Options inventory
Maintained in `uninstall.php` (the deletion list is the source of truth). Current options:
```
schedulely_post_status               schedulely_excluded_authors
schedulely_posts_per_day             schedulely_preserved_authors
schedulely_start_time                schedulely_post_types
schedulely_end_time                  schedulely_auto_schedule
schedulely_active_days               schedulely_email_notifications
schedulely_min_interval              schedulely_notification_users
schedulely_shuffle_queue             schedulely_last_run
schedulely_ai_order_enabled          schedulely_version
schedulely_ai_api_key                schedulely_welcome_dismissed
schedulely_ai_base_url
schedulely_ai_model
schedulely_ai_reorder_log            (NOT autoloaded)
schedulely_randomize_authors
```
Adding an option means updating this list AND `uninstall.php` AND `schedulely_activate()`.

### Query rules
- Every query goes through `$wpdb->prepare`
- Use `WP_Query` or `get_posts` with `'fields' => 'ids'` and `'no_found_rows' => true` when full post objects aren't needed
- Filter to only `public` post types when surfacing CPT options to the user (already done)
- For Polylang sites, pass `'lang' => ''` to fetch across all languages (already done in `get_available_posts`)

---

## 7. Freemium boundaries

**As of audit date:** the plugin has no premium gating. Treat all current features as free.

### The convention — build together, gate later

Every significant new feature is built fully functional in the free plugin. Premium is activated later by hooking the `schedulely_feature_*` filters, not by restructuring code. This means you can build free and premium features simultaneously in the same codebase and defer the split to whenever it makes business sense.

**The free plugin default is always `true`** (feature enabled). The pro plugin hooks the filter and can return `false` to gate, or hook additional actions to provide a licensed replacement.

### Filter naming convention

```php
// Every premium-candidate feature has this gate on its entry point
if ( ! apply_filters( 'schedulely_feature_{slug}', true ) ) {
    return; // or fall back to free behaviour
}
```

If the feature has both a free and premium version (e.g., basic author randomization free, smart topic-matching premium), use the filter to choose which implementation runs:

```php
if ( apply_filters( 'schedulely_feature_smart_author_matching', false ) ) {
    $author_id = $this->author_manager->get_smart_author( $post_id );
} else {
    $author_id = $this->author_manager->get_random_author();
}
```

### Premium-candidate feature gates

Wire each gate in the phase where the feature is built. Document the gate in `agents.md` § 7. Do not build the pro plugin or Freemius integration yet — just the hooks.

| Feature | Filter | Introduced in |
|---|---|---|
| AI queue ordering | `schedulely_feature_ai_ordering` | Exists (v1.4.0) — gate to be added in Phase 1 |
| Sequential/Hybrid scheduling modes | `schedulely_feature_scheduling_modes` | Phase 5B |
| Redistribute existing schedule | `schedulely_feature_redistribute` | Phase 5B |
| Holiday/skip dates | `schedulely_feature_skip_dates` | Phase 5B |
| Per-post-type quotas | `schedulely_feature_per_type_quotas` | Phase 5B |
| Action Scheduler async AI | `schedulely_feature_async_processing` | Phase 3 |
| Publish-readiness AI gate | `schedulely_feature_publish_readiness` | Phase 5B |
| AI email summary | `schedulely_feature_ai_email_summary` | Phase 3 |

### Activating the split

When ready to charge (see `audit/03-freemium.md` and `IMPLEMENTATION_PLAN.md` Phase 5A):

1. Create `schedulely-pro` plugin in a separate repository
2. The pro plugin hooks each `schedulely_feature_*` filter
3. Announce `apply_filters('schedulely_pro_is_active', false)` for the UI layer to check
4. The `.org` free plugin is built from the same source — the gates are already in place
5. No code restructuring required; just a new plugin wiring the filters

**If premium is added later** (see also `audit/03-freemium.md`):
- Premium code lives in a separate plugin, never in the free plugin's files
- Premium features must be additive — disabling the premium plugin must leave the free plugin fully functional with no fatal errors, no broken UI, no missing data
- Free features must never depend on premium hooks
- The wp.org plugin must build with no premium code present

---

## 8. wp.org compliance — what to never do

These will trigger automatic rejection. Do not commit any of them.

1. **Do not load assets from third-party CDNs.** All JS/CSS must be in `assets/vendor/` or `assets/`. Bundling Select2/Flatpickr/SweetAlert from cdn.jsdelivr.net is being removed; do not reintroduce.
2. **Do not bundle the GitHub-based update checker.** The wp.org build must not include `vendor/plugin-update-checker/`. The release zip must exclude it via `.distignore`.
3. **Do not ship `.wp-ai/`, `.skills/`, `.agent-skills/`, `.vscode/`, `assets/demos/`, `docs/`, `audit/`, or `.gitignore` in the wp.org zip.**
4. **Do not call external services without disclosure.** If a feature contacts an external endpoint, update `readme.txt`'s Privacy Policy section in the same PR.
5. **Do not output unescaped data.** Even strings you control today.
6. **Do not use `eval()`, `extract()`, `base64_decode()` (for executable code), `goto`, or PHP short tags.**
7. **Do not use inline `<script>` or `on*=""` event handlers.** Use `wp_add_inline_script` or external JS.
8. **Do not store unencrypted credentials in plaintext output.** Mask API keys in HTML.
9. **Do not modify files outside the plugin directory** — no writes to `wp-content/uploads`, no log files outside `wp-content/debug.log` (which WP itself manages).
10. **Do not silently install or activate other plugins.**

---

## 9. Known pitfalls — explicit prohibitions

These are mistakes found during the May 2026 audit. Do not repeat them.

1. **Default mismatch:** `add_option('x', false)` on activation, `get_option('x', true)` on read. Use the same default everywhere. Add `Schedulely_Defaults::AUTO_SCHEDULE = false` and reference it.
2. **Half-Settings-API:** registering `register_setting` then bypassing `do_settings_sections` and reading `$_POST` directly. Pick one approach. Document it.
3. **Heredoc HTML emails with raw `{$variable}` interpolation.** Move templates to files. Escape at the boundary.
4. **`Schedulely_Scheduler` instantiated 6+ times in one render path.** Memoize.
5. **Inline event handler `onchange="..."` in PHP-rendered markup.** Use `admin.js` and event delegation.
6. **jQuery selector `.closest('tr')` after switching to flex layout.** When you change markup, re-check JS selectors.
7. **`_e()` for strings containing HTML.** Use `wp_kses_post(__())` instead.
8. **Loading 1500 post IDs and looping with per-post `get_post()`.** Prime the cache once.
9. **Two duplicate nonce fields in nested forms.** Each form gets one nonce with a unique action name.
10. **Dead code with confusing names.** `schedulely_clear_cache()` deletes cache keys that are never set. If you find dead code, delete it in the same PR.

---

## 10. File structure map

```
schedulely/
├── schedulely.php                     # Bootstrap; constants; cron; AJAX entry; activation; uninstall trigger
├── uninstall.php                      # Authoritative cleanup list. Update when adding options.
├── readme.txt                         # wp.org listing. Privacy Policy must reflect every external call.
├── README.md                          # Repo readme. NOT shipped to wp.org. Keep in sync OR delete from zip.
├── CHANGELOG.md                       # Keep-a-Changelog format. Bump on every release.
├── agents.md                          # This file.
├── development.md                     # Human dev guide.
├── .distignore                        # (PLANNED Phase 1) Lists everything excluded from the .org zip.
├── assets/
│   ├── css/admin.css                  # Admin styles. Add classes here, NOT inline.
│   ├── js/admin.js                    # Admin behaviour. NOT in inline <script>.
│   └── vendor/                        # (PLANNED Phase 1) Vendored Select2 / Flatpickr / SweetAlert
├── includes/
│   ├── class-scheduler.php            # Core scheduling math. Treat as load-bearing.
│   ├── class-author-manager.php       # Random/preserved author logic. Small, clean.
│   ├── class-notifications.php        # Email composition. Will be split into templates/email/* in Phase 2.
│   ├── class-settings.php             # Admin UI + AJAX handlers + form processing. SCHEDULED FOR SPLIT.
│   └── class-ai-order.php             # External API client. Read audit/04 before changing timeout logic.
├── languages/schedulely.pot           # Regenerate with wp-cli before each release.
└── vendor/                            # (REMOVED for wp.org build)
```

When adding a new feature:
- New class file → `includes/class-{name}.php`, prefix `Schedulely_`
- New template → `templates/{section}/{name}.php`
- New JS → extend `assets/js/admin.js` or add a new file with proper enqueue
- New CSS → extend `assets/css/admin.css`
- New option → update activation, sanitizer, save handler, render, uninstall.php, default constant

---

## 11. Testing expectations

Before considering any change done:

### Manual
1. Activate the plugin on a fresh install
2. Visit `Tools → Schedulely`
3. Click "Run Schedule Now" with a real draft pool — confirm posts get scheduled
4. Wait for cron tick (or trigger via wp-cli `wp cron event run schedulely_auto_schedule`) — confirm posts get scheduled
5. Deactivate — confirm cron is unscheduled (`wp cron event list`)
6. Reactivate — confirm settings preserved
7. Uninstall — confirm options gone (`wp option list --search='schedulely_*'`)

### When changing the scheduler
- Test overnight window (start 2pm, end 3am)
- Test same-day window (start 5pm, end 11pm)
- Test with a single post type and with multiple
- Test with a small draft pool (3 posts) and a medium one (50)
- Test with author randomization on/off and excluded/preserved combinations

### When changing AI ordering
- Test with valid API key and reachable endpoint
- Test with invalid key — confirm graceful fallback
- Test with timeout — confirm graceful fallback
- Test with malformed response — confirm reconciliation works
- Test with `WP_DEBUG_LOG` on — confirm log entry created

### When changing UI
- Test on a non-English locale
- Test with an admin user who is not the site admin
- Test with WordPress 6.8 and the latest major
- Test with object cache (Redis) enabled
- Confirm no console errors, no PHP notices in `debug.log`

### Phase 2+ — when tests exist
Run `vendor/bin/phpunit`. All tests pass. New code requires new tests.

---

## 12. Don'ts: quick reference

- Don't add new external HTTP calls without a Privacy Policy update
- Don't add a CDN dependency
- Don't introduce a custom table without discussion
- Don't ship anything to wp.org that isn't in the latest `.distignore`
- Don't use `wp_cache_flush()`
- Don't break the activate→deactivate→uninstall lifecycle (each must work standalone)
- Don't hardcode user-visible strings
- Don't bypass `wp_unslash` + sanitizer + escape pattern
- Don't trust `$_GET`/`$_POST` past the sanitization boundary
- Don't pretend you tested something you didn't
- **Don't use PowerShell commands, `sed`, `awk`, or any shell string-replacement tool to edit plugin files.** These tools have caused encoding corruption and silent data loss in this codebase. Use the dedicated file-writing and str_replace tools exclusively for all file edits.

---

## 13. When in doubt

The audit (`audit/`) is the authoritative source for "what's wrong and what to do about it." This file is the operational rules; the audit is the diagnosis. `IMPLEMENTATION_PLAN.md` is the execution sequence. If a rule here conflicts with a finding in the audit, the audit wins and this file gets updated.

---

## 14. WP 7.0 AI integration

The WP 7.0 AI APIs (`wp_ai_client_prompt()`, `wp_register_ability()`, the Connectors screen) change how AI features are built in this plugin. Full diagnosis is in `audit-schedulely-wp70-ai.md`. Quick rules:

### When using AI in PHP

```php
// Always check WP 7.0 availability first
if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
    // Fall back to legacy path or fail gracefully
    return new WP_Error( 'schedulely_ai_unavailable', '...' );
}

$builder = wp_ai_client_prompt( $prompt )
    ->using_system_instruction( $system )
    ->using_temperature( 0.7 );

// Always confirm the connected provider supports the operation
if ( ! $builder->is_supported_for_text_generation() ) {
    // Fall back; surface a link to admin_url('options-connectors.php')
    return new WP_Error( 'schedulely_ai_unsupported', '...' );
}

try {
    $text = $builder->generate_text();
} catch ( \Throwable $e ) {
    // Always wrap; the WP 7.0 client may throw
}
```

### Never store credentials yourself on WP 7.0

- Never add a new `schedulely_*_api_key` option
- Never read API keys from `wp_options` for new features
- Never render API keys into HTML
- The Connectors screen owns credentials; reference it via `admin_url( 'options-connectors.php' )`

### Use Abilities for any capability worth exposing

If a capability is something an admin would want to invoke from the command palette, REST, or via an AI agent, register it as an Ability:

```php
add_action( 'init', function () {
    wp_register_ability( 'schedulely/run-schedule', array(
        'label'               => __( 'Run scheduling pass', 'schedulely' ),
        'category'            => 'schedulely',
        'input_schema'        => array( /* JSON Schema */ ),
        'output_schema'       => array( /* JSON Schema */ ),
        'execute_callback'    => array( $instance, 'execute' ),
        'permission_callback' => array( $instance, 'permission' ),
        'meta'                => array( 'show_in_rest' => true ),
    ) );
} );
```

Schedulely abilities all use the `schedulely/` namespace. The category is registered once via `wp_register_ability_category()`.

### Ability registration rules

- Always include both `input_schema` and `output_schema`
- Always include `permission_callback`
- Always set `meta.show_in_rest = true` for admin-triggerable abilities
- Permission callback returns `bool|WP_Error`, not `wp_die`
- Don't extend `Schedulely_AI_Abstract_Ability` (Phase 5+ class) for non-AI abilities — extend `WP_Ability` directly to avoid the AI client's guideline-loading overhead

### Filter `wpai_preferred_text_models` to express model preferences

When a Schedulely operation has model-specific needs (e.g. JSON-mode for the reorder, low temperature for diagnostic summaries):

```php
add_filter( 'wpai_preferred_text_models', function ( $models ) {
    // Only override during Schedulely's reorder flow
    if ( ! did_action( 'schedulely_pre_ai_reorder' ) ) {
        return $models;
    }
    return array(
        array( 'openai', 'gpt-5.4-mini' ),
        array( 'google', 'gemini-3-flash-preview' ),
        array( 'anthropic', 'claude-sonnet-4-6' ),
    );
} );
```

### When AI calls happen

- **Manual `Schedule Now`:** AI calls run synchronously (admin is waiting; timeouts are visible)
- **Cron-driven runs:** never run AI calls synchronously — use Action Scheduler or skip AI on this path entirely
- See `IMPLEMENTATION_PLAN.md` Phase 3 P3-T3 for the boundary

### Privacy disclosure

When adding any AI feature that sends user data to a provider, update `readme.txt`'s Privacy Policy section in the same PR. The WP 7.0 disclosure is shorter than the legacy disclosure but still required:

> When AI ordering is enabled, post titles are sent to the AI provider configured in Settings → Connectors. Refer to your provider's privacy policy.
