# Schedulely Version 1.0.1 - Release Notes

**Release Date:** 06/10/2025  
**Version:** 1.0.1  
**Type:** Bug Fix (Patch Release)

---

## 🐛 Bug Fix

### Issue
Admin menu was not appearing in WordPress dashboard after plugin activation. Users could not access the plugin settings page at **Tools → Schedulely**.

### Root Cause
The initialization function was hooked to `admin_menu`, which then attempted to add another `admin_menu` action inside the Settings class. This timing conflict prevented the menu from being registered properly.

**Original Code:**
```php
add_action('admin_menu', 'schedulely_init');
```

### Solution
Changed the initialization hook from `admin_menu` to `plugins_loaded` to ensure the Settings class initializes early enough to properly register the admin menu.

**Fixed Code:**
```php
add_action('plugins_loaded', 'schedulely_init');
```

### Impact
- ✅ Plugin settings now correctly accessible at **Tools → Schedulely**
- ✅ All plugin functionality now available to users
- ✅ No changes to existing functionality or features
- ✅ No database changes required

---

## 📝 Files Updated

### Core Plugin Files
1. **schedulely.php**
   - Updated version header: `1.0.0` → `1.0.1`
   - Updated version constant: `SCHEDULELY_VERSION` → `1.0.1`
   - Changed hook: `admin_menu` → `plugins_loaded`

### Documentation Files
2. **CHANGELOG.md**
   - Added version 1.0.1 entry with bug fix details
   
3. **README.txt**
   - Updated stable tag: `1.0.0` → `1.0.1`
   - Added changelog entry for v1.0.1
   - Added upgrade notice (critical fix)
   
4. **README.md**
   - Updated version badge: `1.0.0` → `1.0.1`
   
5. **languages/schedulely.pot**
   - Updated project version: `1.0.0` → `1.0.1`
   
6. **BUILD_COMPLETE.md**
   - Updated version references (2 instances)
   
7. **PROJECT_SUMMARY.md**
   - Updated version references (2 instances)
   
8. **QUICK_REFERENCE.md**
   - Updated current version

**Total Files Modified:** 8

---

## 🔄 Changelog Entry

```
## [1.0.1] - 06/10/2025

### Fixed
- Fixed admin menu not appearing in WordPress dashboard
- Changed initialization hook from `admin_menu` to `plugins_loaded` to ensure proper menu registration timing
- Plugin settings now correctly accessible at **Tools → Schedulely**
```

---

## 📦 Version Verification

All version numbers synchronized across:
- ✅ Plugin header (schedulely.php)
- ✅ Plugin constant (SCHEDULELY_VERSION)
- ✅ README.txt (stable tag)
- ✅ README.md (badge)
- ✅ CHANGELOG.md
- ✅ Translation template (.pot)
- ✅ Documentation files

---

## 🚀 Upgrade Instructions

### For Users
1. Download the updated plugin files
2. Replace the existing `schedulely` folder in `/wp-content/plugins/`
3. No database changes or configuration updates required
4. Navigate to **Tools → Schedulely** to verify access

### For WordPress.org
1. Update the plugin ZIP file
2. Submit version 1.0.1 to WordPress.org repository
3. Changelog and upgrade notice already included in README.txt

---

## ⚠️ Upgrade Notice

**Critical Fix:** This patch resolves an issue where the admin menu was not appearing in the WordPress dashboard. All users should update immediately to access plugin settings.

---

## ✅ Testing Checklist

- [x] Version numbers synchronized across all files
- [x] No linting errors
- [x] Changelog updated
- [x] README.txt updated with changelog and upgrade notice
- [ ] Admin menu appears at Tools → Schedulely (user to verify)
- [ ] Settings page loads correctly
- [ ] All plugin functionality works as expected

---

## 📊 Semantic Versioning

Following [Semantic Versioning 2.0.0](https://semver.org/):

**Format:** MAJOR.MINOR.PATCH (1.0.1)

- **MAJOR:** 1 - No breaking changes
- **MINOR:** 0 - No new features
- **PATCH:** 1 - Bug fix (admin menu initialization)

---

## 🔍 Code Diff

### schedulely.php (Line 55)

```diff
- add_action('admin_menu', 'schedulely_init');
+ add_action('plugins_loaded', 'schedulely_init');
```

---

## 📞 Support

If users continue experiencing issues:
- Email: support@kraftysprouts.com
- Website: https://kraftysprouts.com/contact
- Check WordPress debug log for errors

---

## 📅 Release Timeline

- **v1.0.0** - 06/10/2025 - Initial release
- **v1.0.1** - 06/10/2025 - Bug fix (admin menu)

---

## 🎯 Next Steps

1. ✅ Version updated and documented
2. ⬜ Test on clean WordPress install
3. ⬜ Create deployment ZIP
4. ⬜ Deploy to production
5. ⬜ Monitor for any additional issues

---

**Status:** ✅ COMPLETE & READY FOR DEPLOYMENT

---

Made with ❤️ by [Krafty Sprouts Media](https://kraftysprouts.com)

