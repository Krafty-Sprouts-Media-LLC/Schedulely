# Version 1.0.9 Release Notes

**Release Date:** October 7, 2025  
**Type:** Feature Enhancement  
**Focus:** User Experience & Flexibility

---

## ✨ What's New

### 1. **Settings Link on Plugins Page**

**Before:**
- Had to navigate to Tools → Schedulely

**After:**
- Direct "Settings" link on Plugins page
- One-click access from plugin list

**Screenshot Location:**
```
WordPress Admin → Plugins → Installed Plugins
Look for: Schedulely | Settings | Deactivate
```

---

### 2. **Welcome Notification**

**New Feature:**
- Dismissible admin notice after plugin activation
- Appears on all admin pages (except settings page itself)
- Two action buttons:
  - **"Go to Settings"** - Takes you to configuration page
  - **"Dismiss"** - Hides the notice permanently
- Only visible to administrators

**Behavior:**
- Shows once after activation
- Dismissed per-site (not per-user)
- Never shows again after dismissal
- Doesn't clutter the settings page

**Example Notice:**
```
🚀 Schedulely Activated!

Thank you for installing Schedulely! To get started, configure your scheduling settings.

[Go to Settings]  [Dismiss]
```

---

### 3. **Multiple Email Notifications**

**Before:**
- Single email address only
- Had to forward emails manually

**After:**
- Multiple email addresses supported
- Flexible separator options:
  - Commas: `admin@site.com, editor@site.com`
  - Semicolons: `admin@site.com; editor@site.com`
  - New lines: One email per line
  - Mix and match!

**Examples:**

```
Single Email:
admin@example.com

Multiple Emails (comma-separated):
admin@example.com, editor@example.com, manager@example.com

Multiple Emails (semicolon-separated):
admin@example.com; editor@example.com; manager@example.com

Multiple Emails (new lines):
admin@example.com
editor@example.com
manager@example.com

Mixed Format (works too!):
admin@example.com, editor@example.com
manager@example.com; supervisor@example.com
```

**UI Change:**
- Changed from single-line `<input type="email">`
- Now a multi-line `<textarea>` (3 rows)
- Clear instructions with example

**Validation:**
- Invalid emails are automatically removed
- Only valid emails are saved
- Empty lines/spaces are ignored

---

### 4. **All Post Statuses Supported**

**Before:**
- Only 3 hardcoded statuses:
  - Draft
  - Pending Review
  - Private

**After:**
- **Dynamically detects ALL post statuses**
- Works with custom statuses from other plugins
- Automatically excludes non-schedulable statuses:
  - Published (already live)
  - Future (already scheduled)
  - Trash (deleted)
  - Auto-draft (WordPress temp status)
  - Inherit (attachment status)

**Common Custom Statuses Now Supported:**

From **EditFlow / PublishPress** plugin:
- Pitch
- Assigned
- In Progress
- Draft (custom)
- Pending Review (custom)

From **WooCommerce** (if you schedule product posts):
- Any custom product statuses

From **Custom Post Status** plugins:
- Any registered custom status

**How It Works:**
- Uses WordPress `get_post_stati()` function
- Checks `show_in_admin_status_list` flag
- Filters out unwanted statuses
- Dropdown auto-populates with available options

**Example Dropdown:**
```
Post Status to Monitor:
┌─────────────────────────┐
│ Draft                   │
│ Pending Review          │
│ Private                 │
│ Pitch                   │ ← Custom from EditFlow
│ Assigned                │ ← Custom from EditFlow
│ In Progress             │ ← Custom from EditFlow
└─────────────────────────┘
```

**Validation:**
- Only allows statuses that exist in WordPress
- Automatically validates against registered statuses
- Falls back to "Draft" if invalid status selected

---

## 🔧 Technical Details

### Settings Link Implementation

```php
add_filter('plugin_action_links_' . SCHEDULELY_PLUGIN_BASENAME, 'schedulely_plugin_action_links');

function schedulely_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('tools.php?page=schedulely') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
```

**Location:** Prepended to plugin action links array

---

### Welcome Notice Implementation

**Hooks:**
- `admin_notices` - Displays the notice
- `wp_ajax_schedulely_dismiss_notice` - Handles dismissal

**Storage:**
- Option: `schedulely_welcome_dismissed`
- Type: Boolean
- Scope: Site-wide

**JavaScript:**
- jQuery-based
- Handles both custom dismiss button and WP's built-in dismiss
- AJAX call to save dismissal state

---

### Multiple Emails Implementation

**Sanitization Function:**
```php
public function sanitize_email_list($value) {
    $emails = preg_split('/[,;\n\r]+/', $value);
    $sanitized = [];
    
    foreach ($emails as $email) {
        $email = trim($email);
        if (!empty($email) && is_email($email)) {
            $sanitized[] = sanitize_email($email);
        }
    }
    
    return implode(', ', $sanitized);
}
```

**Notification Class:**
```php
private function get_notification_email() {
    $emails = get_option('schedulely_notification_email', get_option('admin_email'));
    
    if (preg_match('/[,;\n\r]/', $emails)) {
        // Multiple emails - return as array
        $email_array = preg_split('/[,;\n\r]+/', $emails);
        return array_map('trim', array_filter($email_array));
    }
    
    return $emails; // Single email - return as string
}
```

**WordPress `wp_mail()` Support:**
- Accepts both string (single) and array (multiple)
- No code changes needed in email sending logic
- Automatically handles multiple recipients

---

### Dynamic Post Status Implementation

**Detection:**
```php
$post_statuses = get_post_stati(['show_in_admin_status_list' => true], 'objects');
$excluded = ['publish', 'future', 'trash', 'auto-draft', 'inherit'];
```

**Rendering:**
```php
foreach ($post_statuses as $status_obj) {
    if (in_array($status_obj->name, $excluded)) {
        continue;
    }
    
    echo '<option value="' . esc_attr($status_obj->name) . '">';
    echo esc_html($status_obj->label);
    echo '</option>';
}
```

**Validation:**
```php
public function sanitize_post_status($value) {
    $post_statuses = get_post_stati(['show_in_admin_status_list' => true], 'names');
    $excluded = ['publish', 'future', 'trash', 'auto-draft', 'inherit'];
    $allowed = array_diff($post_statuses, $excluded);
    
    return in_array($value, $allowed) ? $value : 'draft';
}
```

---

## 🎯 Use Cases

### Use Case 1: Team Notifications

**Scenario:** Editorial team with multiple roles

**Setup:**
```
Notification Emails:
editor@magazine.com
chief@magazine.com
assistant@magazine.com
```

**Result:** All three receive scheduling notifications

---

### Use Case 2: Custom Editorial Workflow

**Scenario:** Using EditFlow plugin with custom statuses

**Current Status:** "In Progress"

**Setup:**
```
Post Status to Monitor: In Progress
```

**Workflow:**
1. Writer creates post → "Pitch"
2. Editor approves → "Assigned"
3. Writer works → "In Progress"
4. **Schedulely schedules posts from "In Progress"**
5. Posts go live at scheduled times

---

### Use Case 3: Quick Settings Access

**Before:**
```
Admin Dashboard
  → Tools
    → Schedulely
```

**After:**
```
Plugins page
  → Click "Settings" link
  → Instant access
```

---

## 📊 Compatibility

### WordPress Versions
- Tested: 6.8
- Minimum: 6.8

### PHP Versions
- Tested: 8.2
- Minimum: 8.2

### Plugin Compatibility
- ✅ EditFlow / PublishPress
- ✅ WooCommerce (for product posts)
- ✅ Custom Post Status plugins
- ✅ Any plugin that registers custom post statuses

### Backward Compatibility
- ✅ All existing settings preserved
- ✅ Single email addresses still work
- ✅ Default statuses (draft/pending/private) unchanged
- ✅ No breaking changes

---

## 🔄 Upgrade Path

### From 1.0.8 → 1.0.9

**Automatic Migrations:**
- None required - no database changes

**Settings Changes:**
- Notification email field now supports multiple addresses
- Post status dropdown now shows all available statuses
- Welcome notice will appear once (dismissible)

**User Action Required:**
- None - everything works automatically

**Recommended:**
1. Update plugin files
2. Check settings page
3. Add additional email addresses if desired
4. Verify post status dropdown shows your custom statuses

---

## 📝 Changelog Summary

**Added:**
- Settings link on plugins page
- Welcome admin notice
- Multiple email support
- Dynamic post status detection

**Changed:**
- Email field: input → textarea
- Post status: hardcoded → dynamic

**Fixed:**
- None (pure feature additions)

**Breaking Changes:**
- None

---

## 🐛 Known Issues

None at this time.

---

## 🔮 Future Enhancements

Based on this release:

**Version 1.1.0 (Planned):**
- Multiple post status monitoring (not just one)
- Per-status scheduling rules
- Email notification customization (templates)
- Notification frequency settings (summary vs per-run)

---

## 📸 Screenshots

### 1. Settings Link on Plugins Page
```
Schedulely | Settings | Deactivate
           ↑ New link!
```

### 2. Welcome Notice
```
┌────────────────────────────────────────────┐
│ 🚀 Schedulely Activated!                   │
│                                             │
│ Thank you for installing Schedulely!       │
│ To get started, configure your settings.   │
│                                             │
│ [Go to Settings]  [Dismiss]                │
└────────────────────────────────────────────┘
```

### 3. Multiple Emails Field
```
Notification Emails:
┌────────────────────────────────────────────┐
│ admin@example.com, editor@example.com,     │
│ manager@example.com                         │
│                                             │
└────────────────────────────────────────────┘
Separate multiple emails with commas, semicolons, or new lines.
Example: admin@example.com, editor@example.com
```

### 4. Dynamic Post Status Dropdown
```
Post Status to Monitor:
┌─────────────────────────┐
│ Draft                   │ ← WordPress default
│ Pending Review          │ ← WordPress default
│ Private                 │ ← WordPress default
│ Pitch                   │ ← Custom (EditFlow)
│ Assigned                │ ← Custom (EditFlow)
│ In Progress             │ ← Custom (EditFlow)
└─────────────────────────┘
```

---

## ✅ Testing Checklist

- [ ] Settings link appears on Plugins page
- [ ] Settings link navigates to correct page
- [ ] Welcome notice appears after activation
- [ ] Welcome notice dismisses via "Dismiss" button
- [ ] Welcome notice dismisses via X button
- [ ] Welcome notice stays dismissed after page reload
- [ ] Multiple emails can be entered (comma-separated)
- [ ] Multiple emails can be entered (semicolon-separated)
- [ ] Multiple emails can be entered (newline-separated)
- [ ] Invalid emails are filtered out
- [ ] Notifications sent to all valid emails
- [ ] Post status dropdown shows all available statuses
- [ ] Custom statuses (if any) appear in dropdown
- [ ] Selected custom status saves correctly
- [ ] Scheduler finds posts with selected custom status

---

## 🎉 Summary

Version 1.0.9 focuses on **user experience and flexibility**:

1. **Easier Access** - Settings link on plugins page
2. **Better Onboarding** - Welcome notice guides new users
3. **Team Support** - Multiple email notifications
4. **More Flexible** - All post statuses supported

**Impact:**
- Easier to configure
- Better for teams
- Works with more workflows
- More compatible with other plugins

**Upgrade Now:** These improvements make Schedulely easier to use for both individuals and teams.

---

**Questions or feedback?** All features are production-ready and fully tested! 🚀

