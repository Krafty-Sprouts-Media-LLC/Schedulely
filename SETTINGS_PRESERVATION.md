# Settings Preservation During Updates

## ✅ Your Settings Are Safe

**Schedulely is designed to preserve ALL user settings during plugin updates.** You can update with complete confidence that your configuration will remain intact.

---

## How It Works

### 1. **Activation Hook (First Install Only)**
```php
function schedulely_activate() {
    // Uses add_option() - only adds if option doesn't exist
    add_option('schedulely_posts_per_day', 8);
    // ... more options
}
```

- The activation hook **only runs on first install**, not on updates
- Uses `add_option()` instead of `update_option()`
- `add_option()` will NOT overwrite existing values
- If the option exists, it does nothing

### 2. **Version Check System**
```php
function schedulely_check_version() {
    $current_version = get_option('schedulely_version', '0');
    
    if (version_compare($current_version, SCHEDULELY_VERSION, '<')) {
        schedulely_upgrade($current_version);
        update_option('schedulely_version', SCHEDULELY_VERSION);
    }
}
```

- Runs on every page load via `plugins_loaded` hook
- Detects version changes
- Calls upgrade function for migrations
- Logs the upgrade if `WP_DEBUG` is enabled

### 3. **Upgrade Function**
```php
function schedulely_upgrade($from_version) {
    // Version-specific migrations
    // Only ADDS new options, never overwrites existing ones
    
    if (version_compare($from_version, '2.0.0', '<')) {
        add_option('schedulely_new_feature', 'default_value');
    }
}
```

- Handles version-specific data migrations
- Only uses `add_option()` for new settings
- Never uses `update_option()` for existing settings
- Preserves all user configuration

---

## What Gets Preserved

### ✅ Always Preserved During Updates:

- **Scheduling Settings**
  - Posts per day quota
  - Start and end times
  - Minimum interval
  - Active days of the week
  - Post status to monitor

- **Author Settings**
  - Randomize authors toggle
  - Excluded authors list

- **Automation Settings**
  - Auto-schedule toggle
  - Email notifications toggle
  - Notification email address

- **Runtime Data**
  - Last run timestamp
  - Version number
  - Scheduled posts (in WordPress posts table)

### ⚠️ Reset During Updates:

- **Nothing!** All settings are preserved.

---

## Update Process Flow

```
User clicks "Update" in WordPress
    ↓
WordPress downloads new plugin files
    ↓
Old files are replaced with new files
    ↓
WordPress does NOT run activation hook
    ↓
schedulely_check_version() runs on next page load
    ↓
Detects new version
    ↓
Runs schedulely_upgrade() for migrations (if needed)
    ↓
Updates version number in database
    ↓
✅ All user settings remain unchanged
```

---

## Developer Guidelines

### When Adding New Settings

**DO:**
```php
// In upgrade function
if (version_compare($from_version, '1.1.0', '<')) {
    add_option('schedulely_new_setting', 'default_value');
}
```

**DON'T:**
```php
// This would overwrite user settings!
update_option('schedulely_posts_per_day', 10); // ❌ NEVER DO THIS
```

### Version Migration Examples

```php
function schedulely_upgrade($from_version) {
    // Example 1: Add new option in version 2.0.0
    if (version_compare($from_version, '2.0.0', '<')) {
        add_option('schedulely_new_feature', true);
    }
    
    // Example 2: Migrate data structure in version 2.1.0
    if (version_compare($from_version, '2.1.0', '<')) {
        $old_value = get_option('schedulely_old_format');
        $new_value = transform_data($old_value);
        update_option('schedulely_new_format', $new_value);
        // Only update if it's a data transformation, not a reset
    }
    
    // Example 3: Ensure critical settings are present
    if (version_compare($from_version, '2.2.0', '<')) {
        if (!get_option('schedulely_critical_setting')) {
            add_option('schedulely_critical_setting', 'default');
        }
    }
}
```

---

## Testing Settings Preservation

### Manual Test Steps:

1. **Install version 1.0.5**
2. **Configure all settings** (change from defaults)
3. **Note your settings**:
   - Posts per day: 12
   - Time window: 8:00 AM - 6:00 PM
   - Interval: 30 minutes
   - Excluded authors: [List of IDs]
4. **Create dummy version 1.0.6** (change version number)
5. **"Update" the plugin** (replace files)
6. **Check settings page** - all values should match step 3

### Automated Test (Future):

```php
// Test that settings are preserved
public function test_settings_preserved_during_update() {
    // Set custom settings
    update_option('schedulely_posts_per_day', 15);
    update_option('schedulely_start_time', '9:00 AM');
    
    // Simulate upgrade
    update_option('schedulely_version', '1.0.4');
    schedulely_check_version(); // This should detect upgrade
    
    // Verify settings preserved
    $this->assertEquals(15, get_option('schedulely_posts_per_day'));
    $this->assertEquals('9:00 AM', get_option('schedulely_start_time'));
}
```

---

## User Communication

### README.txt Section
✅ Added under "Installation" section:

> **Your settings are always preserved during updates!** The plugin uses WordPress best practices to ensure your configuration is never lost when you update to a new version. You can update with confidence.

### In-Plugin Notice (Optional for Future)
Could add an admin notice on update:

```php
if (get_transient('schedulely_updated')) {
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p><strong>Schedulely updated successfully!</strong> All your settings have been preserved.</p>';
    echo '</div>';
    delete_transient('schedulely_updated');
}
```

---

## Backup Recommendations

While settings are preserved automatically, best practices include:

1. **WordPress Backup Plugin**: Use a backup plugin to backup your entire site regularly
2. **Database Backup**: Backup WordPress database before major updates
3. **Export Settings** (Future feature): Allow users to export/import settings as JSON

---

## Support & Troubleshooting

### If Settings Are Lost (Shouldn't Happen)

1. Check if user deactivated then reactivated (doesn't reset settings)
2. Check if user manually deleted options from database
3. Check error logs for any PHP errors during update
4. Restore from database backup

### Debug Logging

Enable WordPress debug logging to track updates:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for:
```
[Schedulely] Schedulely upgraded from version 1.0.4 to 1.0.5. User settings preserved.
```

---

## Conclusion

✅ **Settings are automatically preserved**  
✅ **No user action required**  
✅ **WordPress best practices followed**  
✅ **Fully documented for developers**  
✅ **User confidence assured**

**Update with confidence!** 🚀

