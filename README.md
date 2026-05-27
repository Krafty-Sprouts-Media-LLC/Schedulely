# Schedulely

WordPress plugin for intelligent post scheduling with multiple scheduling algorithms, AI-powered queue ordering, and flexible automation.

**Version:** 1.6.0 · **Requires WP:** 6.8 · **Requires PHP:** 8.2 · **License:** GPL v2 or later

---

## What it does

Schedulely takes posts in a configurable status (`draft`, `pending`, etc.) and assigns them future publish times within a configurable daily time window, respecting quotas, minimum intervals between posts, and active weekdays.

### Scheduling modes

| Mode | How it works | Efficiency |
|---|---|---|
| **Random** | Posts land at random times in the window | ~70% — organic appearance |
| **Sequential** | Perfectly even spacing across the window | 100% — predictable |
| **Hybrid** | Window divided into equal slots, random time within each slot | ~95% — even distribution, natural appearance |

### Other features

- **AI queue ordering** — reorders the post pool by title similarity before scheduling, so series posts are spaced apart (WP 7.0 native or legacy DeepSeek/OpenAI-compatible API)
- **Random author assignment** with excluded and preserved author lists
- **Configurable pool size** — how many eligible posts are loaded per run
- **Auto-schedule** via WP-Cron (`twicedaily`) or manual `Schedule Now` button
- **Email notifications** with optional AI-generated summary (WP 7.0+)
- **Multi-post-type** support
- **Overnight time windows** (e.g. 10pm–3am)
- **WordPress Abilities** — exposes scheduling actions to the WP 7.0 command palette, REST API, and AI agents

---

## Requirements

- **WordPress:** 6.8+
- **PHP:** 8.2+

---

## Installation

```bash
# Via WP-CLI
wp plugin install schedulely.zip --activate

# Or upload via Plugins → Add New → Upload Plugin
```

Go to **Tools → Schedulely** to configure.

---

## File structure

```
schedulely/
├── schedulely.php               # Bootstrap, constants, cron, activation
├── uninstall.php                # Cleanup on delete
├── readme.txt                   # wp.org listing
├── assets/
│   ├── css/admin.css
│   ├── js/admin.js
│   └── vendor/                  # Vendored: SweetAlert2, Select2, Flatpickr
├── includes/
│   ├── autoloader.php           # spl_autoload_register for Schedulely_* classes
│   ├── class-defaults.php       # All option defaults as constants
│   ├── class-scheduler.php      # Core scheduling engine (Random/Sequential/Hybrid)
│   ├── class-ai-order.php       # AI queue ordering (WP 7.0 + legacy paths)
│   ├── class-author-manager.php # Random/preserved author assignment
│   ├── class-notifications.php  # Email notifications
│   ├── class-settings.php       # Sanitizers, form handler, renderer coordinator
│   ├── class-admin-menu.php     # Admin menu registration
│   ├── class-admin-assets.php   # Asset enqueuing
│   ├── class-admin-notices.php  # Welcome notice
│   ├── class-ajax-handlers.php  # All AJAX + admin-post handlers
│   └── class-abilities.php      # WP 7.0 Abilities registration
├── templates/
│   └── admin/settings-page.php  # Settings page HTML template
└── languages/schedulely.pot
```

---

## Developer hooks

### Filters

```php
// Maximum posts loaded per scheduling run (default 1500)
add_filter( 'schedulely_max_posts_per_run', fn() => 500 );

// Override scheduling efficiency (for custom capacity display)
add_filter( 'schedulely_capacity_efficiency', fn( $eff ) => 0.8, 10, 5 );

// Override AI model preferences for reordering
add_filter( 'wpai_preferred_text_models', function( $models ) {
    if ( ! did_action( 'schedulely_pre_ai_reorder' ) ) return $models;
    return [ [ 'deepseek', 'deepseek-v4-flash' ], ... ];
} );

// Gate premium features (pro plugin hooks these)
add_filter( 'schedulely_feature_ai_ordering', '__return_false' );
add_filter( 'schedulely_feature_scheduling_modes', '__return_false' );

// Safety buffer before the window opens (default 30 min)
add_filter( 'schedulely_schedule_safety_buffer_seconds', fn() => 900 );
```

### Actions

```php
// Fires just before AI reorder request — use to register model preferences
add_action( 'schedulely_pre_ai_reorder', function() { ... } );

// Third-party cache invalidation after scheduling pass
add_action( 'schedulely_clear_cache', function() { ... } );
```

### WordPress Abilities (WP 7.0+)

| Ability slug | What it does |
|---|---|
| `schedulely/run-schedule` | Trigger a scheduling pass |
| `schedulely/check-capacity` | Check if settings fit the quota |
| `schedulely/get-furthest-scheduled-date` | Get the furthest future date |
| `schedulely/preview-next-slot` | Preview next N datetimes without committing |
| `schedulely/run-ai-reorder` | Preview AI queue ordering without scheduling |

```php
// Call from PHP
$result = wp_get_ability( 'schedulely/run-schedule' )->execute( [ 'dry_run' => true ] );
```

---

## WP-Cron

- Hook: `schedulely_auto_schedule`
- Recurrence: `twicedaily` (~every 12 hours)
- Trigger: `wp cron event run schedulely_auto_schedule`

---

## Debugging

```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
// Schedulely logs to wp-content/debug.log with prefix [Schedulely]
```

---

## Support

- Website: [kraftysprouts.com](https://kraftysprouts.com)
- Email: support@kraftysprouts.com
