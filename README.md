# Schedulely WordPress Plugin

![Version](https://img.shields.io/badge/version-1.0.4-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.8+-green.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-red.svg)

Intelligently schedule posts from any status with smart deficit tracking, random author assignment, and customizable time windows.

## 🚀 Features

### Core Functionality
- **Last Date Completion** - Automatically completes the last scheduled date if it didn't meet quota
- **Smart Continuation** - Resumes scheduling from where it left off
- **Random Time Distribution** - Creates natural posting patterns within defined time windows
- **Author Randomization** - Assign random authors with exclusion capability
- **Flexible Scheduling** - Custom time windows, daily limits, and active days
- **Minimum Intervals** - Ensure posts don't publish too close together
- **WordPress Native** - Uses WordPress's built-in cron and timezone functions
- **Email Notifications** - Admin notifications for scheduling events

### Technical Highlights
- Clean, object-oriented architecture
- WordPress coding standards compliant
- Secure (nonces, sanitization, escaping, capability checks)
- Internationalization ready (i18n)
- Responsive admin interface
- External libraries: Select2 & Flatpickr
- Complete uninstall cleanup

## 📋 Requirements

- **WordPress:** 6.8 or higher
- **PHP:** 8.2 or higher
- **MySQL:** 5.7 or higher (or MariaDB equivalent)

## 📦 Installation

### Via WordPress Admin
1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin

### Via FTP/Manual
1. Extract the ZIP file
2. Upload `schedulely` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin

### Via WP-CLI
```bash
wp plugin install schedulely.zip --activate
```

## ⚙️ Configuration

1. Navigate to **Tools → Schedulely** in WordPress admin
2. Configure your settings:
   - **Post Status to Monitor:** Choose draft, pending, or private
   - **Posts Per Day:** Set your daily quota (1-100)
   - **Time Window:** Define start and end times (12hr format)
   - **Active Days:** Select which days to schedule posts
   - **Minimum Interval:** Set minimum minutes between posts
   - **Author Settings:** Enable randomization and set exclusions
   - **Automation:** Enable/disable auto-scheduling and notifications

3. Click **Save Changes**

## 🎯 Usage

### Manual Scheduling
Click the **"Schedule Now"** button on the settings page to immediately schedule available posts.

### Automatic Scheduling
Enable "Automatic Scheduling" in settings. WordPress cron will run the scheduler hourly.

### How It Works

```
1. Plugin checks for posts with configured status (e.g., draft)
2. Identifies any deficit dates (days that didn't meet quota)
3. Fills deficits first (oldest dates prioritized)
4. Schedules remaining posts to future dates
5. Applies random times within configured window
6. Respects minimum intervals between posts
7. Optionally randomizes authors
8. Sends email notification (if enabled)
```

## 📁 File Structure

```
schedulely/
├── schedulely.php              # Main plugin file
├── uninstall.php              # Cleanup on uninstall
├── README.txt                 # WordPress.org readme
├── README.md                  # Developer readme
├── CHANGELOG.md               # Version history
├── assets/
│   ├── css/
│   │   └── admin.css         # Admin styles
│   └── js/
│       └── admin.js          # Admin scripts
├── includes/
│   ├── class-scheduler.php         # Main scheduling engine
│   ├── class-deficit-tracker.php   # Deficit management
│   ├── class-author-manager.php    # Author assignment
│   ├── class-settings.php          # Admin interface
│   └── class-notifications.php     # Email system
└── languages/
    └── schedulely.pot         # Translation template
```

## 🔧 Developer Information

### Hooks & Filters

**Actions:**
```php
// After scheduling completes
do_action('schedulely_clear_cache');

// Custom scheduling trigger
do_action('schedulely_auto_schedule');
```

**Filters:**
```php
// Modify available posts query args
apply_filters('schedulely_available_posts_args', $args);

// Modify scheduling results
apply_filters('schedulely_scheduling_results', $results);
```

### Database Schema

All settings stored in `wp_options` table with prefix `schedulely_`:

```php
schedulely_post_status          // Post status to monitor
schedulely_posts_per_day        // Daily quota
schedulely_start_time           // Start time (12hr format)
schedulely_end_time             // End time (12hr format)
schedulely_active_days          // Array of active days
schedulely_min_interval         // Minimum minutes between posts
schedulely_randomize_authors    // Boolean
schedulely_excluded_authors     // Array of user IDs
schedulely_auto_schedule        // Boolean
schedulely_email_notifications  // Boolean
schedulely_notification_email   // Email address
schedulely_deficit_tracker      // JSON array of deficits
schedulely_last_run            // Unix timestamp
schedulely_version             // Plugin version
```

### Constants

```php
SCHEDULELY_VERSION       // Plugin version
SCHEDULELY_PLUGIN_DIR    // Plugin directory path
SCHEDULELY_PLUGIN_URL    // Plugin URL
SCHEDULELY_PLUGIN_BASENAME // Plugin basename
```

### Classes

- **Schedulely_Scheduler** - Main scheduling logic
- **Schedulely_Deficit_Tracker** - Deficit management
- **Schedulely_Author_Manager** - Author randomization
- **Schedulely_Settings** - Admin interface
- **Schedulely_Notifications** - Email notifications

## 🔒 Security

- ✅ Nonce verification on all forms and AJAX requests
- ✅ Capability checks (`manage_options`)
- ✅ Input sanitization and output escaping
- ✅ SQL injection prevention via `$wpdb->prepare()`
- ✅ Direct file access prevention
- ✅ XSS and CSRF protection

## 🌍 Internationalization

The plugin is translation-ready. To translate:

1. Use the `schedulely.pot` file in `/languages/`
2. Create `.po` and `.mo` files for your language
3. Place in `/languages/` directory

Text domain: `schedulely`

## 🐛 Debugging

Enable WordPress debug mode:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Schedulely will log errors to `wp-content/debug.log` with prefix `[Schedulely]`.

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## 🤝 Support

- **Website:** [https://kraftysprouts.com](https://kraftysprouts.com)
- **Email:** support@kraftysprouts.com
- **Documentation:** [Plugin Documentation](https://kraftysprouts.com/docs/schedulely)

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2025 Krafty Sprouts Media, LLC

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 👨‍💻 Author

**Krafty Sprouts Media, LLC**  
Website: [https://kraftysprouts.com](https://kraftysprouts.com)

---

Made with ❤️ by [Krafty Sprouts Media](https://kraftysprouts.com)

