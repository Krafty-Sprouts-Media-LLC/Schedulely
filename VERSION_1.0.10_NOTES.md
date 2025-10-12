# Schedulely Version 1.0.10 Release Notes
**Release Date:** October 7, 2025

## 🎯 Overview
Version 1.0.10 brings a significant UX improvement to notification settings by replacing the manual email input field with an intelligent user selection dropdown, consistent with the author exclusion interface.

---

## ✨ What's New

### 1. **Select2 User Picker for Notifications**
- Replaced textarea email input with beautiful Select2 multi-select dropdown
- Same elegant interface as author exclusion feature
- Displays user name and email for easy identification
- Search functionality for large user lists
- No more email typos or formatting issues

### 2. **Capability-Based Filtering**
- Only shows users with `publish_posts` capability
- **Includes:** Administrators, Editors, Authors
- **Excludes:** Contributors (who can only edit, not publish)
- Ensures notifications go to appropriate team members

### 3. **Dynamic Email Resolution**
- Stores user IDs instead of email addresses
- Fetches current email addresses at notification time
- Automatically updates if user changes their email
- No stale email addresses

---

## 🔄 Breaking Changes

### Database Storage Format Changed
- **Old:** `schedulely_notification_email` (comma-separated email strings)
- **New:** `schedulely_notification_users` (array of user IDs)

### Migration Impact
- Existing email settings **will not carry over** (incompatible data formats)
- Plugin defaults to current admin user if no selection made
- Users must re-select notification recipients in settings after update

---

## 💻 Technical Changes

### New/Changed Code

#### `includes/class-settings.php`
```php
// New sanitization method
public function sanitize_notification_users($value) {
    if (!is_array($value)) {
        return [];
    }
    
    $sanitized = [];
    foreach ($value as $user_id) {
        $user_id = absint($user_id);
        $user = get_user_by('id', $user_id);
        if ($user && user_can($user, 'publish_posts')) {
            $sanitized[] = $user_id;
        }
    }
    
    return $sanitized;
}
```

#### `includes/class-notifications.php`
```php
// Fetches emails from user IDs
private function get_notification_email() {
    $user_ids = get_option('schedulely_notification_users', []);
    
    if (empty($user_ids)) {
        return get_option('admin_email');
    }
    
    $emails = [];
    foreach ($user_ids as $user_id) {
        $user = get_user_by('id', $user_id);
        if ($user && !empty($user->user_email)) {
            $emails[] = $user->user_email;
        }
    }
    
    return count($emails) === 1 ? $emails[0] : $emails;
}
```

#### `assets/js/admin.js`
```javascript
// Initialize Select2 for notification users
const $notificationSelect = $('.schedulely-notification-select');
if ($notificationSelect.length && typeof $notificationSelect.select2 === 'function') {
    $notificationSelect.select2({
        placeholder: 'Select users to notify',
        allowClear: true,
        width: '100%'
    });
}
```

### Database Changes
- **Added:** `schedulely_notification_users` (serialized array)
- **Legacy:** `schedulely_notification_email` cleaned up on uninstall

---

## 🎨 User Interface

### Before (Version 1.0.9)
```
┌─────────────────────────────────────────┐
│ Notification Emails                     │
│ ┌─────────────────────────────────────┐ │
│ │ admin@site.com,                     │ │
│ │ editor@site.com,                    │ │
│ │ manager@site.com                    │ │
│ └─────────────────────────────────────┘ │
│ Separate multiple emails with commas    │
└─────────────────────────────────────────┘
```

### After (Version 1.0.10)
```
┌─────────────────────────────────────────┐
│ Notification Recipients                 │
│ ┌─────────────────────────────────────┐ │
│ │ ✓ John Admin (john@site.com)    [×] │ │
│ │ ✓ Jane Editor (jane@site.com)   [×] │ │
│ │ ✓ Bob Manager (bob@site.com)    [×] │ │
│ │ ⌄ Select users to notify...         │ │
│ └─────────────────────────────────────┘ │
│ Only users with publish capability      │
└─────────────────────────────────────────┘
```

---

## ✅ Benefits

### For Users
1. **No typos** - Select from dropdown, don't type emails
2. **Visual confirmation** - See name + email before selecting
3. **Search** - Find users quickly in large teams
4. **Consistent UI** - Matches author exclusion interface
5. **Auto-updates** - Emails always current

### For Developers
1. **Cleaner data** - User IDs vs strings
2. **Validation** - Only valid users with proper caps
3. **Maintainable** - Consistent with WordPress patterns
4. **Future-proof** - Easy to extend with user metadata

---

## 🧪 Testing Checklist

### Fresh Installation
- [ ] Install plugin on new site
- [ ] Activate plugin
- [ ] Go to settings → Notification Recipients
- [ ] Verify only Admins/Editors/Authors shown
- [ ] Select multiple users
- [ ] Save settings
- [ ] Trigger notification (manual schedule)
- [ ] Verify all selected users receive email

### Upgrade from 1.0.9
- [ ] Have existing email settings in 1.0.9
- [ ] Upgrade to 1.0.10
- [ ] Settings page loads without errors
- [ ] Email field replaced with Select2 dropdown
- [ ] Current admin user pre-selected by default
- [ ] Can select/deselect users
- [ ] Save and verify notifications work

### Edge Cases
- [ ] Test with no users selected (defaults to admin email)
- [ ] Test with single user (returns string, not array)
- [ ] Test with multiple users (returns array)
- [ ] Test user search functionality
- [ ] Test with site having only Contributors (none shown)
- [ ] Test uninstall removes both old and new options

---

## 📝 User Actions Required

### After Upgrade
1. Go to **Schedulely → Settings**
2. Scroll to **Notification Settings**
3. Select users from **Notification Recipients** dropdown
4. Click **Save Settings**
5. Test by clicking **Schedule Now** manually

### Important Notes
- Previous email settings will not carry over
- Default selection is current admin user
- You must manually select notification recipients
- Only users who can publish posts are shown

---

## 🐛 Known Issues
None reported.

---

## 📚 Documentation Updates

### User Guide
- Updated screenshots showing new Select2 interface
- Added section on user selection
- Documented capability requirements

### Developer Guide
- Updated database schema documentation
- Added migration notes for custom integrations
- Documented new sanitization method

---

## 🔜 What's Next?

### Planned for 1.0.11
- Custom notification templates
- Per-user notification preferences
- Notification frequency settings (digest vs immediate)

---

## 🙏 Credits
Suggested by user feedback requesting consistent UI with author selection and eliminating email typos.

---

## 📞 Support
For issues or questions:
- GitHub Issues: [github.com/kraftysprouts/schedulely](https://github.com/kraftysprouts/schedulely)
- Support Email: support@kraftysprouts.com

---

## 📄 Full Changelog
See [CHANGELOG.md](CHANGELOG.md) for complete version history.

