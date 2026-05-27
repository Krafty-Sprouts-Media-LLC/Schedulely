# development.md — Human Developer Guide for Schedulely

The single source of truth for human developers working on this plugin. The companion AI-agent guide is `agents.md`. The diagnostic backing this document is in `audit/`. The WP 7.0 AI integration audit is in `audit-schedulely-wp70-ai.md`. **The execution plan with phased tasks, dependencies, and exit gates is in `IMPLEMENTATION_PLAN.md` at the plugin root — start there for any non-trivial work.**

---

## 1. Plugin overview

**Purpose.** Schedulely takes WordPress posts in a configurable status (`draft`, `pending`, `private`, etc.) and automatically schedules them at random times within configurable daily windows, daily quotas, minimum intervals, active days, and optional AI-driven queue reordering.

**Operational modes.**
- **Manual:** admin clicks `Tools → Schedulely → Run Schedule Now`
- **Automatic:** WP-Cron event `schedulely_auto_schedule` runs on the `twicedaily` recurrence

**Architecture (current).** Procedural bootstrap in `schedulely.php` plus five class files in `includes/`. There is no autoloader, no namespace, no Composer, no build step. All admin assets are currently loaded from third-party CDNs (this is being fixed in Phase 1 — see `audit/02-wporg-compliance.md`).

**Tech stack.**
- PHP 8.2+
- WordPress 6.8+
- jQuery + vanilla JS (admin only)
- Plain CSS in `assets/css/admin.css`
- External: SweetAlert2, Select2, Flatpickr (vendoring under way — see Phase 1)
- External API: OpenAI-compatible Chat Completions (default DeepSeek) for optional AI queue ordering

---

## 2. Development environment setup

### Prerequisites
- A WordPress 6.8+ install (Local, wp-env, Docker, or any host)
- PHP 8.2+
- WP-CLI (recommended for cron testing)
- Git

### Get the code running locally
```
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/Krafty-Sprouts-Media-LLC/Schedulely.git schedulely
```
Activate via wp-admin or:
```
wp plugin activate schedulely
```

Visit `Tools → Schedulely`. There are no build steps — edit any file, refresh.

### Recommended dev setup
- `WP_DEBUG = true` and `WP_DEBUG_LOG = true` in `wp-config.php`. The plugin logs under `[Schedulely]` prefix to `wp-content/debug.log`.
- Object cache (Redis or Memcached) installed locally — many performance issues only surface with object cache active.
- Query Monitor plugin for inspecting hooks, queries, and HTTP calls during development.
- A draft pool of 50–100 posts of various lengths. Use:
  ```
  wp post generate --count=100 --post_status=draft
  ```

### Recommended editor config
- A phpcs ruleset based on WordPress-Coding-Standards (Phase 2 will add `phpcs.xml`)
- An `.editorconfig` matching the existing 4-space indent (Phase 2 will add the file)
- An ESLint config for `assets/js/` (Phase 2 will add)

---

## 3. Versioning & `@since` tags

### Current version

The active development version is **1.6.0**. All work from Phase 0 through Phase 4 of the implementation plan accumulates into this single release.

Do not write `1.5.10` in new code, and don't invent a future version number. If in doubt, read `Version:` in `schedulely.php`.

### `@since` rules

`@since` means "this was **introduced** at this version." It is never retroactively rewritten.

| Situation | What to write |
|---|---|
| New function, class, method, or constant | `@since 1.6.0` |
| New parameter added to an existing method | `@since 1.6.0` on that `@param` line only |
| Existing function whose **behaviour you are changing** | Keep the original `@since`. Add `@since 1.6.0 Description of what changed.` on a new line below |
| Existing function you are only reading or calling | Touch nothing |
| Deprecated function | Add `@deprecated 1.6.0 Use replacement_function() instead.` |

**Examples:**

```php
// New function written in 1.6.0
/**
 * Check whether WP 7.0 AI is available on this install.
 *
 * @since 1.6.0
 * @return bool
 */
function schedulely_wp_ai_available(): bool { ... }

// Existing function whose behaviour changed in 1.6.0
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
 *                      committing changes. Default false. @since 1.6.0
 * @return array Results of scheduling operation.
 */
public function run_schedule( bool $dry_run = false ): array { ... }
```

### Version bump rules

- All Phase 0–4 work ships as **one release: 1.6.0**
- Do not bump the version mid-phase
- The bump happens once, at Phase 4 exit gate (task P4-T8)
- Exactly three places change: `Version:` header in `schedulely.php`, `SCHEDULELY_VERSION` constant in `schedulely.php`, `Stable tag:` in `readme.txt`
- All three must match exactly

### CHANGELOG.md convention

Entries go under `## [Unreleased]` until the release. Use these prefixes in the entry lines:

| Prefix | When to use |
|---|---|
| `Added:` | New feature or capability |
| `Changed:` | Behaviour change to existing feature |
| `Fixed:` | Bug fix |
| `Removed:` | Something deliberately deleted |
| `Security:` | Security-related fix |
| `Deprecated:` | Something marked for removal in a future version |

At release time, rename `## [Unreleased]` to `## [1.6.0] - YYYY-MM-DD` and create a fresh empty `## [Unreleased]` section above it.

---

## 4. Coding standards

### Which standards apply
- **PHP:** WordPress Coding Standards (WPCS) for PHP. Naming follows the WP convention (snake_case functions, snake_case variables, Class_Like_This for classes).
- **HTML/CSS/JS:** WordPress Coding Standards. Inside `admin.js`, single-quotes in JS, semicolons mandatory, jQuery wrapped in `(function($) { ... })(jQuery)`.

### Documented exceptions
- The plugin uses 4-space indent (matches existing code; WPCS officially prefers tabs but 4-space is widespread)
- The dashboard layout uses `<div class="dashboard-grid">` rather than `<table>` — by design
- The Settings API (`do_settings_sections`) is intentionally bypassed — by design (`agents.md` § 3)

### How to lint (Phase 2 — when wired up)
```
composer install
vendor/bin/phpcs --standard=phpcs.xml includes/ schedulely.php uninstall.php
vendor/bin/phpcbf --standard=phpcs.xml ...   # Auto-fix where possible
npx eslint assets/js/
```
Until Phase 2, lint is by hand and code review.

### Naming conventions (recap from `agents.md`)
- Classes: `Schedulely_*`
- Functions: `schedulely_*`
- Options: `schedulely_*`
- Hooks (actions/filters published by this plugin): `schedulely_*`
- CSS classes: `schedulely-*` for new code; `dash-*` exists for legacy

---

## 5. Security standards

These are mandatory. Code that violates them will be rejected at review.

### Input handling
```php
// Pattern: unslash → sanitize → use
$value = isset( $_POST['x'] ) ? wp_unslash( $_POST['x'] ) : '';
$value = sanitize_text_field( $value );
update_option( 'schedulely_x', $value );
```
Never combine `wp_unslash` and `sanitize_text_field` into a single call where they're applied in the wrong order. Sanitizers do not unslash.

### Output escaping
| Context | Function |
|---|---|
| HTML body text | `esc_html()` / `esc_html__()` / `esc_html_e()` |
| HTML attribute value | `esc_attr()` / `esc_attr__()` / `esc_attr_e()` |
| URL in `href`, `src`, `action` | `esc_url()` / `esc_url_raw()` for storage |
| HTML containing allowed markup | `wp_kses_post()` |
| Inline JS data (avoid; prefer localize) | `esc_js()` |

**Rule:** every dynamic value gets an escape function, no exceptions. Even values you "control today" — the next person to touch the code may not control them.

### Nonces
- AJAX: `check_ajax_referer( 'schedulely_admin', 'nonce' )` first thing
- Forms: `wp_nonce_field( 'action_name' )` in markup, `check_admin_referer( 'action_name' )` in handler
- Each form gets a unique action name. The 1.5.6 fix was about a duplicate `_wpnonce` field — don't repeat.
- `admin-post.php` actions use their own nonce field, not `_wpnonce`

### Capability checks
Every privileged action:
```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error([ 'message' => __( 'Insufficient permissions', 'schedulely' ) ]);
}
```
The plugin uses `manage_options` consistently for the settings page and AJAX handlers.

### SQL
- All `$wpdb` queries go through `prepare()`
- For dynamic IN clauses, use `array_fill` to build placeholders
- All current queries hit the WP core `type_status_date` index

### Credentials
- The AI API key lives in `wp_options` as `schedulely_ai_api_key`
- **Don't render it in plaintext into HTML.** Mask it (first 4 + last 4 chars) and provide an explicit reveal AJAX endpoint with fresh-nonce check
- The `.org` upload of the plugin must include a clear Privacy Policy section disclosing what's sent to the configured endpoint

---

## 6. Performance standards

Derived from real production issues. See `audit/04-performance.md` for the diagnosis.

### Hard prohibitions
- **No `wp_cache_flush()`.** Single biggest performance footgun in the plugin's history. The current call sites are being removed; do not add new ones.
- **No unbounded `posts_per_page => -1`** — use `wp_count_posts` for counts, paginate for iteration
- **No synchronous external HTTP inside the cron tick** without a hard <30s timeout (the current AI call has up to 1200s timeout — being addressed in Phase 3)
- **No DB queries in render loops.** Prime caches once, iterate over IDs.
- **No `version_compare` migrations on every front-end request** — use `admin_init` or activation gating

### Required practices
- Cap any post-iteration loop to 200 by default (the current `MAX_POSTS_PER_RUN = 1500` is being lowered)
- Memoize per-request data (eligible authors, last scheduled date, statistics) in member variables
- Cache user/author lookups for the lifetime of a single scheduling run
- Use `wp_count_posts` for any counting

### Caching policy
| Operation | Strategy | TTL |
|---|---|---|
| Eligible authors (per scheduling run) | Member variable on `Schedulely_Author_Manager` | Single request |
| Eligible authors (dashboard) | Object cache + transient fallback | 5 minutes |
| Last scheduled date (dashboard) | Memoize in renderer | Single request |
| AI ordering (when input set is identical) | Transient keyed by hash of input post IDs | 24 hours |

### Deferral policy
- Only run AI calls during the manual `Schedule Now` flow (admin is waiting, timeouts are visible)
- Cron-driven runs skip AI ordering until Action Scheduler is integrated

---

## 7. Database standards

### Schema overview
**No custom tables.** All persistence in `wp_options` (see `agents.md` § 6 for the inventory).

The plugin reads from but does not write to:
- `wp_posts` (post status changes via `wp_update_post`)
- `wp_users` / `wp_usermeta` (via `get_users` / `get_userdata`)

The plugin writes to:
- `wp_options` (settings, log, version)
- `wp_options` (cron registry, via WP core's `wp_schedule_event`)

### Query rules
1. Every query goes through `$wpdb->prepare`
2. `WP_Query` and `get_posts` queries use `'fields' => 'ids'` and `'no_found_rows' => true` when full posts aren't needed
3. Filter post types to `public => true` when surfacing CPT options to the user
4. For Polylang sites, pass `'lang' => ''` to fetch across all languages (already done in `Schedulely_Scheduler::get_available_posts`)
5. Use `update_post_meta_cache` and `update_post_term_cache` set to `false` on queries that don't need them

### Migration approach
Schema changes (when they happen) go through `schedulely_upgrade()` in `schedulely.php`. The function receives the previous version string and applies version-specific migrations:
```php
if ( version_compare( $from_version, '1.6.0', '<' ) ) {
    // Migrate option shape, run dbDelta if needed, etc.
}
```
The current pattern uses `add_option` (preserves user settings) rather than `update_option` (clobbers). This is correct.

### Adding an option (checklist)
- [ ] `add_option('schedulely_x', $default)` in `schedulely_activate()`
- [ ] Sanitizer method on `Schedulely_Settings`
- [ ] `update_option` line in the form save handler
- [ ] `delete_option` line in `uninstall.php`
- [ ] Form field in the render output, properly escaped
- [ ] Default constant in the future `Schedulely_Defaults` class
- [ ] Updated options list in `agents.md` § 6

### Adding a custom table (checklist)
**This requires discussion first.** If approved:
- [ ] Discuss in the issue tracker
- [ ] `dbDelta()` schema in activation hook
- [ ] Drop in `uninstall.php`
- [ ] Indexes on every column used in WHERE/ORDER BY
- [ ] Schema documentation in this file's "Custom Tables" subsection (none currently)
- [ ] Migration path in `schedulely_upgrade()`

Prefer Action Scheduler's existing tables for queue-shaped data. Do not invent your own queue.

---

## 8. Freemium architecture

**Current state:** no premium gating. All features are free. All code is in the wp.org-bound plugin.

**Future state (only if pursued — see `audit/03-freemium.md`):**
- Premium code lives in a separate plugin or under `pro/` (excluded from wp.org build)
- A single filter — `apply_filters( 'schedulely_pro_is_active', false )` — is the gate
- Premium features must be **additive**: disabling the premium plugin must leave the free plugin fully functional with no fatal errors, no broken UI, no missing data
- Free features must never depend on premium hooks

### Adding a feature (decision tree)
1. Does this feature exist for the user's benefit alone, with no operational cost to you? → Free.
2. Does this feature require infrastructure you must run and pay for? → Premium.
3. Is this feature primarily for power users / agencies / multi-author sites? → Likely premium, but evaluate.
4. When in doubt, free first; promote to premium later if the audience justifies it.

### Adding a premium feature gate
```php
// In a free-tier file:
public function maybe_run_advanced_ordering() {
    if ( ! apply_filters( 'schedulely_pro_is_active', false ) ) {
        return; // Free version simply doesn't run this
    }
    // Premium plugin is loaded; do the work via hooks the premium plugin provides
    do_action( 'schedulely_pro_run_advanced_ordering', $context );
}
```
The free-tier file MUST contain no premium logic — only the hook.

---

## 9. wp.org compliance checklist

Run this checklist before every wp.org submission. The full diagnosis is in `audit/02-wporg-compliance.md`. Each item below is a hard blocker.

- [ ] No CDN URLs in any `wp_enqueue_*` call (all assets in `assets/vendor/` or `assets/`)
- [ ] No `vendor/plugin-update-checker/` in the release zip
- [ ] No `.wp-ai/`, `.skills/`, `.agent-skills/`, `.vscode/`, `assets/demos/`, `docs/`, `audit/`, `.gitignore`, `.distignore` in the release zip
- [ ] `readme.txt` Privacy Policy accurately discloses every external request
- [ ] Every user-visible string wrapped in `__()`/`esc_html__()`/`_e()`/`esc_html_e()` family
- [ ] `.pot` regenerated from current source (`wp i18n make-pot . languages/schedulely.pot --slug=schedulely`)
- [ ] Stable Tag in `readme.txt` matches `Version` header in `schedulely.php` exactly
- [ ] `Tested up to:` matches the current WP major
- [ ] No inline `<script>` tags in PHP-rendered markup
- [ ] No inline `on*=""` event handlers
- [ ] Every dynamic `echo` uses an `esc_*` or `wp_kses_*` function
- [ ] Bundled libraries declared in `readme.txt` Credits section with name + version + license
- [ ] Activation, deactivation, uninstall all clean up correctly (verified by manual test)
- [ ] Plugin loads without errors on a fresh WordPress install
- [ ] Plugin loads without errors on a WordPress install with `WP_DEBUG = true`

---

## 10. Contribution workflow

### Branching
- `main` — stable, tagged releases
- `develop` — integration branch for the next release
- `feat/<short-name>` — new features
- `fix/<short-name>` — bug fixes
- `chore/<short-name>` — dependencies, tooling, internal changes

PRs target `develop`. Releases merge `develop` into `main` and tag.

### Commit messages
Conventional Commits style preferred:
```
feat(scheduler): add holiday skip-date support
fix(settings): correct auto_schedule default mismatch
chore(deps): vendor SweetAlert 11.10.0
docs(readme): rewrite Privacy Policy section
```

### Pull request expectations
- One concern per PR (refactor + feature in one PR is rejected)
- Description includes: what changed, why, manual test steps performed, any compliance impact
- Updated `CHANGELOG.md` entry under `[Unreleased]`
- Updated `readme.txt` Changelog section if user-visible
- Updated `audit/` if the fix resolves a finding (mark the finding as resolved, don't delete it)
- All `agents.md` rules followed
- All `development.md` standards followed

### Code review
A reviewer must check:
1. Does the diff add any of the wp.org rejection patterns? (See `agents.md` § 8)
2. Are inputs sanitized, outputs escaped, nonces and caps in place?
3. Does the change introduce performance regressions per § 6?
4. Does the lifecycle (activate/deactivate/uninstall) still work after the change?
5. Are user-visible strings translatable?
6. Is the code style consistent with the rest of the file?

### Changelog maintenance
The plugin uses two changelog files for two different audiences:
- `CHANGELOG.md` — for developers, in Keep-a-Changelog format
- `readme.txt` Changelog section — for users, on the wp.org listing

Both must be updated for user-visible changes. Internal refactors only need `CHANGELOG.md`.

### Release process
1. `develop` is at the desired state
2. Bump `Version:` in `schedulely.php` and `Stable tag:` in `readme.txt` to the same value
3. Update `CHANGELOG.md`: move `[Unreleased]` items to a new versioned section
4. Update `readme.txt` Changelog section
5. Regenerate `.pot`
6. Manual smoke test (activate → settings → schedule → deactivate → reactivate → uninstall)
7. Merge `develop` → `main`
8. Tag the merge commit `v<version>`
9. Push the tag — GitHub Actions builds the release zip
10. **For wp.org submission** (separate from GitHub release): use `svn co` against the wp.org repository, copy in the wp.org-clean files (respecting `.distignore`), `svn ci`. The wp.org-clean files exclude `vendor/plugin-update-checker/` and the items listed in compliance checklist § 9.

---

## 11. Known issues & technical debt

This is the honest record of what the May 2026 audits found and the current resolution status. Update as items get resolved. Phase references map to `IMPLEMENTATION_PLAN.md`.

### Critical — must fix before wp.org submission

| # | Finding | Status | Plan task |
|---|---|---|---|
| 1 | Bundled GitHub update checker (`vendor/plugin-update-checker/`) — disqualifying | Open | P1-T2 |
| 2 | All admin assets loaded from CDN (Select2/Flatpickr/SweetAlert/Lato) — disqualifying | Open | P1-T3 |
| 3 | `readme.txt` Privacy Policy is incorrect (says no external data sent; the AI feature does send data) — **resolution path changed by WP 7.0 audit** | Open | P1-T9 |
| 4 | `.wp-ai/` directory ships in release zip (3.5 MB of unrelated WordPress plugins with `extract()`, `base64_decode`, custom tables) | Open | P1-T1 |
| 5 | `assets/demos/` ships in release zip | Open | P1-T1 |
| 6 | `wp_cache_flush()` after every scheduling pass — wipes site object cache | Open | P0-T1, P0-T2 |
| 7 | API key rendered as plaintext in HTML (readonly text input on settings page) — **resolution path changed by WP 7.0 audit (migration eliminates the option entirely on 7.0)** | Open | P1-T5, P1-T19 |
| 8 | Many `_e()` and raw `echo` sites are not escaped | Open | P1-T11, P1-T12 |
| 9 | `auto_schedule` default mismatch: `add_option(..., false)` but `get_option(..., true)` everywhere else | Open | P0-T3 |
| 10 | Heredoc email templates with raw `{$var}` interpolation | Open | P1-T13 |

### High — fix in Phase 2/3

| # | Finding | Status | Plan task |
|---|---|---|---|
| 11 | `Schedulely_Settings` is a 1326-line God class | Open — rebuild trigger lives in this work | P2-T3..T11 |
| 12 | Settings API half-used: `register_setting` then `$_POST` direct read | Open — drop `register_setting` per audit recommendation | P2-T5 |
| 13 | `posts_per_page => -1` in dashboard statistics | Open | P0-T4 |
| 14 | `MAX_POSTS_PER_RUN = 1500` is unbounded in practice | Open | P3-T1 |
| 15 | AI HTTP call runs synchronously inside cron tick with up to 1200s timeout | Open — fixed by removing AI from cron path | P3-T3 |
| 16 | AI reorder log stored as serialized array in `wp_options` | Tracked — defer to WP 7.0 AI Request Logging experiment per `audit-schedulely-wp70-ai.md` § 1 | Phase 5 |
| 17 | Hardcoded English strings in PHP and JS bypass i18n | Open | P1-T17 |
| 18 | `.pot` file is at version 1.0.4; current code is 1.5.10 | Open | P1-T18, P4-T2 |
| 19 | `current_time('timestamp')` deprecated since WP 5.3 | Open | P0-T5 |
| 20 | `date()` (server timezone) used for output instead of `wp_date()` | Open | P3-T6 |
| 21 | `get_eligible_authors()` uncached, called once per scheduled post (1500 `get_users` calls) | Open | P3-T5 |
| 22 | `Schedulely_Scheduler` instantiated 6+ times per settings page render | Open | P2-T7 |

### Medium

| # | Finding | Status | Plan task |
|---|---|---|---|
| 23 | Inline CSS styles pervasive in PHP markup | Open | P2-T10 |
| 24 | Inline `<script>` block in welcome notice | Open | P1-T14 |
| 25 | Inline `onchange="..."` in post-type dropdown | Open | P1-T15 |
| 26 | jQuery `.closest('tr')` in admin.js — broken because layout is flex divs | Open | P1-T16 |
| 27 | Email Alerts toggle is in Quick Toggles but doesn't save instantly (inconsistent with Auto-Schedule toggle) | Open | Phase 2 nice-to-have |
| 28 | Dashboard duplicates SQL queries already done in `get_statistics()` | Open | P2-T7 |
| 29 | `count_posts_on_date` called once per date in email composition (N queries per email) | Open | Phase 3 nice-to-have |
| 30 | `error_log(print_r(...))` per-retry — log spam under stress | Open | P3-T7 |
| 31 | No type hints anywhere despite PHP 8.2 requirement | Open | Phase 5C |
| 32 | No automated tests | Open — phpunit setup in P2-T18 (optional but recommended) | P2-T18 |
| 33 | README.md badge says version 1.0.4, content references hourly cron and a deleted file | Open | P4-T5 |
| 34 | `readme.txt` "Planned Features" section advertises non-existent functionality | Open | P1-T10 |
| 35 | FAQ says "Custom post type support is planned" — already exists | Open | P1-T10 |
| 36 | Error notification email template (`send_error_notification`) is defined but never called | Open — wire it via the cron try/catch | P4-T4 |
| 37 | Legacy `schedulely_notification_email` option is cleaned up at uninstall but never migrated forward at upgrade | Open | P4-T3 |

### WP 7.0 AI integration (from `audit-schedulely-wp70-ai.md`)

| # | Item | Status | Plan task |
|---|---|---|---|
| 38 | Migrate `class-ai-order.php` to `wp_ai_client_prompt()` on WP 7.0 | Open | P1-T4..T8 |
| 39 | Mask legacy API key display | Open | P1-T19 |
| 40 | Register `schedulely/run-schedule` ability | Open | P2-T13 |
| 41 | Register `schedulely/check-capacity` ability | Open | P2-T14 |
| 42 | Register `schedulely/get-furthest-scheduled-date` ability | Open | P2-T15 |
| 43 | Register `schedulely/preview-next-slot` ability | Open | P2-T16 |
| 44 | Register `schedulely/run-ai-reorder` ability | Open | P2-T17 |
| 45 | Add AI-generated email summary opportunity | Open | P3-T9 |
| 46 | Add AI capacity hint opportunity | Open | P3-T10 |
| 47 | Defer Opportunity 2 (terms-aware reorder), Opportunity 3 (publish-readiness), Opportunity 5 (holiday detection) | Tracked | Phase 5B |

### Resolved (kept for history)

- 1.0.2 — Removed broken deficit-tracking logic that tried to schedule into the past
- 1.0.8 — Cron migrated from hourly to twicedaily
- 1.5.3 — Timezone math switched to `wp_timezone()` and `wp_date()` for scheduling logic (output paths still use `date()` — see #20)
- 1.5.6 — Duplicate `_wpnonce` field bug in clear-AI-log form fixed
- 1.5.9 — AI ordered_ids reconciliation when model returns extra/duplicate IDs

---

## 12. Where to look for what

| When you need to… | Read this |
|---|---|
| Understand a wp.org rejection risk | `audit/02-wporg-compliance.md` |
| Understand a performance complaint | `audit/04-performance.md` |
| Understand the security posture | `audit/06-security.md` |
| Plan a refactor | `audit/00-summary-and-verdict.md` |
| Plan a premium feature | `audit/03-freemium.md` |
| Look up the operating rules | `agents.md` |
| Look up the dev workflow | this file |
| Find the options inventory | `uninstall.php` (authoritative) and `agents.md` § 6 |
| Find the cron event name | `schedulely.php` (`schedulely_auto_schedule`) |
| Find a hook the plugin exposes | `apply_filters` and `do_action` calls (no central registry yet — Phase 2 plans one) |
| Plan or execute work | `IMPLEMENTATION_PLAN.md` (master plan) |
| Understand WP 7.0 AI integration | `audit-schedulely-wp70-ai.md` |

---

## 13. Final notes

This codebase is salvageable but borderline. The scheduler engine is genuinely well-designed and worth keeping. The settings layer is the rebuild candidate. The compliance issues are mechanical and can all be fixed in Phase 1.

Read `audit/00-summary-and-verdict.md` if you've never opened the audit. The verdict is "refactor with rebuild threshold" — meaning you can iterate on this codebase, but if Phase 2 (settings split) reveals that the form handler, sanitization, and rendering can't be cleanly decoupled, switch to a clean rewrite of the admin layer at that point.

If something in this guide conflicts with the audit, the audit wins and this document gets updated.

---

## 14. File editing rules

**Never use PowerShell commands (`Set-Content`, `(Get-Content) -replace`, etc.), `sed`, `awk`, or any shell string-replacement pipeline to edit plugin PHP, JS, CSS, or markdown files.** These tools have caused encoding corruption, silent data loss, and broken line endings in this codebase in the past. They are not recoverable without git.

Use the dedicated file tools exclusively:

| Need | Correct tool |
|---|---|
| Create a new file | `fs_write` |
| Edit part of an existing file | `str_replace` — provide exact old text and new text |
| Append to an existing file | `fs_append` |
| Read before editing | `read_file` or `readCode` |

PowerShell is permitted for **read-only** operations only — listing directories, grepping for patterns, checking file sizes. The moment it writes to a file it must be replaced with a dedicated file tool.
