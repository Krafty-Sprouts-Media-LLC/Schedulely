# 🚨 EMERGENCY FIX - Version 1.0.8

**Date:** October 7, 2025, 4:06 AM  
**Severity:** CRITICAL  
**Type:** Security + Data Loss Prevention

---

## 💥 What Happened

At 4:00 AM on October 7, 2025, a user activated Schedulely v1.0.7 on their live site.

**Within 5 minutes, the plugin published all their draft posts.**

### The Bug Chain

1. ✅ Plugin activated at 4:00 AM
2. ✅ Activation set `schedulely_auto_schedule = true` (default)
3. ✅ Scheduled WordPress cron for "now" (4:00 AM)
4. ✅ User navigated/page reloaded
5. ✅ WordPress fired the cron event immediately
6. ✅ Scheduler ran automatically
7. ❌ **BUG:** Posts were scheduled with times that WordPress interpreted as past times
8. 💥 **RESULT:** WordPress auto-published all drafts instead of scheduling them

### Root Causes

**Primary Bug:** Auto-scheduling enabled by default on activation
- Cron fires immediately after activation
- User has no chance to review/configure
- No explicit user consent to schedule

**Secondary Bug:** No safety check for past times
- If time calculation goes wrong, posts publish immediately
- No validation that scheduled time is in the future
- No minimum buffer before publish time

---

## ✅ The Fix - Version 1.0.8

### Fix #1: Disable Auto-Schedule by Default

```php
// schedulely.php line 73
add_option('schedulely_auto_schedule', false); // Changed from true
```

**Impact:**
- Plugin activation no longer triggers auto-scheduling
- User must explicitly enable OR click "Schedule Now"
- Gives user control before any posts are touched

### Fix #2: Safety Check for Past Times

```php
// includes/class-scheduler.php line 343-360
$scheduled_timestamp = strtotime($datetime);
$now = current_time('timestamp');
$safety_buffer = 30 * 60; // 30 minutes
$minimum_future_time = $now + $safety_buffer;

if ($scheduled_timestamp < $minimum_future_time) {
    schedulely_log_error('CRITICAL: Attempted to schedule post too close to present or in the past', [...]);
    return false; // Refuse to schedule
}
```

**Impact:**
- Posts MUST be scheduled at least 30 minutes in the future
- Any attempt to schedule in the past is rejected and logged
- Prevents accidental publishing due to time bugs

### Fix #3: Comprehensive Error Logging

All time-related rejections now log:
- Post ID
- Attempted datetime
- Current timestamp
- Time difference
- Detailed error context

---

## 🚀 Deployment Instructions

### Step 1: Backup

```bash
# Backup your database
mysqldump -u user -p database > backup_before_1.0.8.sql

# Backup plugin files (if modified)
cp -r schedulely schedulely-backup
```

### Step 2: Upload New Files

Upload these updated files to your live site:

```
schedulely.php                  (v1.0.8 - auto-schedule disabled)
includes/class-scheduler.php    (safety checks added)
README.txt                      (version updated)
CHANGELOG.md                    (fix documented)
```

### Step 3: Clear WordPress Caches

```php
// Via WP-CLI
wp cache flush

// Via plugin (if you have caching)
// Clear all caches

// Via code (functions.php temporary)
wp_cache_flush();
delete_transient('all');
```

### Step 4: Verify Settings

1. Go to **Tools → Schedulely**
2. Check "Automatic Scheduling" is **UNCHECKED** ✅
3. Review all other settings
4. Make adjustments if needed

### Step 5: Test Manually

1. Click **"Schedule Now"** button
2. Verify posts are in **"Scheduled"** status (not "Published")
3. Check scheduled times are in the FUTURE
4. Check email notification (if enabled)

### Step 6: Enable Auto-Scheduling (Optional)

Only after testing:
1. Check "Enable automatic scheduling"
2. Save settings
3. Monitor for first few cron runs

---

## 🔍 How to Verify the Fix

### Check #1: Auto-Schedule Default

```sql
-- Should return 0 (false) for new installations
SELECT option_value FROM wp_options WHERE option_name = 'schedulely_auto_schedule';
```

### Check #2: Posts Are Scheduled (Not Published)

```sql
-- All Schedulely posts should have post_status = 'future'
SELECT ID, post_title, post_date, post_status 
FROM wp_posts 
WHERE post_type = 'post' 
AND post_modified > '2025-10-07 04:00:00'
ORDER BY post_modified DESC;
```

### Check #3: Times Are in Future

```sql
-- All scheduled posts should have future dates
SELECT ID, post_title, post_date, 
  TIMESTAMPDIFF(MINUTE, NOW(), post_date) as minutes_until_publish
FROM wp_posts 
WHERE post_status = 'future' 
AND post_type = 'post'
ORDER BY post_date ASC;
```

Should show positive numbers in `minutes_until_publish` column.

### Check #4: Error Logs

Enable WordPress debugging:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check `/wp-content/debug.log` for any "CRITICAL" messages from Schedulely.

---

## 🛡️ Prevention Measures

### For Developers

**Never enable auto-features by default on activation!**

```php
// ❌ BAD
add_option('plugin_auto_feature', true);

// ✅ GOOD
add_option('plugin_auto_feature', false);
```

**Always validate time-sensitive operations:**

```php
// ❌ BAD
wp_update_post(['post_date' => $calculated_time]);

// ✅ GOOD
if (strtotime($calculated_time) > time() + 1800) { // 30 min buffer
    wp_update_post(['post_date' => $calculated_time]);
}
```

### For Users

1. **Always test on staging first**
2. **Never activate plugins on live without testing**
3. **Keep backups before any plugin installation**
4. **Monitor WordPress debug logs**
5. **Use WP Crontrol to see scheduled events**

---

## 📊 Impact Assessment

### Who Was Affected?

- Users who activated v1.0.5, 1.0.6, or 1.0.7 on a live site
- Users with auto-scheduling enabled (default)
- Sites with many draft posts ready to publish

### What Was Lost?

- Draft posts were published prematurely
- Publishing schedule was disrupted
- Content may have gone live before final review
- SEO/marketing timing may be affected

### How to Recover?

```sql
-- Find posts published by Schedulely in the last 24 hours
SELECT ID, post_title, post_date 
FROM wp_posts 
WHERE post_status = 'publish'
AND post_modified > DATE_SUB(NOW(), INTERVAL 24 HOUR)
AND post_type = 'post';

-- Revert to draft
UPDATE wp_posts 
SET post_status = 'draft'
WHERE ID IN (...post IDs...);
```

---

## 🔮 Future Improvements

### Version 1.0.9 (Planned)

1. **User confirmation dialog** on first activation
2. **Setup wizard** to configure before scheduling
3. **Dry-run mode** to preview what would be scheduled
4. **Better timezone handling** with explicit timezone selection
5. **Scheduling preview** showing exact dates/times before commit

### Version 1.1.0 (Planned)

1. **Scheduling queue system** - posts go to queue first, user approves
2. **Rollback feature** - undo last scheduling run
3. **Smart time detection** - analyzes site timezone vs server timezone
4. **Email alerts** - notify before auto-scheduling runs
5. **Schedule locks** - prevent overlapping schedule runs

---

## 📞 Support

If you experienced this bug:

1. **Immediate:** Revert published posts to draft
2. **Update:** Upgrade to v1.0.8 immediately
3. **Test:** Use "Schedule Now" manually before enabling auto-schedule
4. **Monitor:** Watch first few scheduling runs closely
5. **Report:** If issues persist, enable WP_DEBUG and share logs

---

## ✅ Verification Checklist

After deploying v1.0.8:

- [ ] Files uploaded to live site
- [ ] Cache cleared
- [ ] Plugin version shows 1.0.8
- [ ] Auto-scheduling is disabled by default
- [ ] Manual "Schedule Now" works correctly
- [ ] Posts show as "Scheduled" not "Published"
- [ ] Scheduled times are at least 30 minutes in future
- [ ] No errors in WordPress debug log
- [ ] Email notifications working (if enabled)
- [ ] Only enable auto-scheduling after successful test

---

## 🎯 Summary

**The Bug:** Plugin would auto-publish drafts immediately upon activation

**The Fix:** 
1. Auto-scheduling disabled by default
2. Safety checks prevent past-time scheduling
3. 30-minute minimum buffer enforced

**The Result:** 
Users have full control. No surprises. No automatic actions without consent.

**Upgrade Path:**
v1.0.7 → v1.0.8 (MANDATORY)

**Time to Deploy:** 5 minutes  
**Risk Level:** LOW (fix only - no new features)  
**Testing Required:** YES (manual "Schedule Now" test)

---

**Thank you for your patience during this emergency fix.**

🔒 **Version 1.0.8 is now safe for production deployment.**

