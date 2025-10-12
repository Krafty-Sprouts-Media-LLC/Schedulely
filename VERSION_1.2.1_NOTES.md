# Schedulely Version 1.2.1 - Release Notes

**Release Date:** 12/10/2025  
**Type:** Critical Bug Fix  
**Upgrade Priority:** HIGH

---

## Overview

Version 1.2.1 fixes a critical bug in email notifications where post counts were resetting instead of accumulating across multiple scheduling runs.

## The Problem

When Schedulely ran multiple times and scheduled posts to the same date, email notifications showed incorrect post counts:

- **Oct 7th Run:** "Thursday, Oct 16, 2025: 5/8 posts (NEEDS 3 MORE)"
- **Oct 12th Run:** After scheduling 1 more post: "Thursday, Oct 16, 2025: 1/8 posts (NEEDS 7 MORE)" ❌
- **Expected:** Should have shown "6/8 posts (NEEDS 2 MORE)" ✅

## The Root Cause

The notification system was counting only posts from the **current scheduling run** instead of querying the database for the **total posts** on each date.

## The Fix

Modified `Schedulely_Notifications::build_notification_message()` to use `Schedulely_Scheduler::count_posts_on_date()` which queries the database for accurate total counts.

### Code Change

**Before (Buggy):**
```php
$posts_per_date = [];
foreach ($dates as $date) {
    $posts_per_date[$date] = 0;
}
foreach ($results['scheduled_posts'] as $post) {
    $posts_per_date[$post['date']]++;  // Only current run
}
```

**After (Fixed):**
```php
$scheduler = new Schedulely_Scheduler();
$posts_per_date = [];
foreach ($dates as $date) {
    $posts_per_date[$date] = $scheduler->count_posts_on_date($date);  // All posts
}
```

## Files Changed

1. ✅ `includes/class-notifications.php` - Fixed counting logic + added header
2. ✅ `includes/class-scheduler.php` - Added header
3. ✅ `schedulely.php` - Version bump to 1.2.1
4. ✅ `CHANGELOG.md` - Documented fix
5. ✅ `BUG_REPORT_v1.2.1.md` - Created bug documentation (for GitHub issue)

## Impact

### What's Fixed
- ✅ Email notifications now show accurate cumulative post counts
- ✅ Date completion status correctly reflects all scheduled posts
- ✅ No more confusing "reset" counts between runs

### What's NOT Affected
- ✅ Scheduling logic works the same (no changes)
- ✅ Admin dashboard unchanged
- ✅ All other functionality unchanged

## Testing

To verify the fix:

1. Schedule several posts to a future date
2. Note the count in the email notification
3. Run scheduling again (manually or wait for cron)
4. Schedule more posts to the same date
5. Check the new email notification

**Expected:** Count should accumulate (e.g., 5 → 6 → 7), not reset (e.g., 5 → 1)

## Upgrade Instructions

### For Users

**Automatic Upgrade (WordPress Updates):**
1. WordPress will show update available
2. Click "Update Now"
3. Done! Your settings are preserved

**Manual Upgrade:**
1. Deactivate the old version
2. Delete the old plugin files
3. Upload version 1.2.1
4. Activate the plugin

**Note:** All settings are preserved during updates. No configuration needed.

### For Developers

**Testing Locally:**
```bash
# 1. Pull latest code
git pull origin master

# 2. Check version
grep "Version:" schedulely.php  # Should show 1.2.1

# 3. Test the fix
# Schedule posts across multiple runs and verify email counts
```

## Upgrade Path

| From Version | To Version | Notes |
|-------------|------------|-------|
| 1.0.0 - 1.2.0 | 1.2.1 | Seamless upgrade, settings preserved |
| Any version | 1.2.1 | No special migration needed |

## Performance Impact

**Minimal:** One additional database query per date in email notifications. This is negligible as:
- Notifications only run when posts are scheduled
- Typical runs schedule to 1-3 dates
- Query is simple and fast (`SELECT COUNT(*)`)

## Known Issues

None. This is a pure bug fix release with no known issues.

## Next Steps

1. ✅ Bug fixed and documented
2. ✅ Version bumped to 1.2.1
3. ✅ Changelog updated
4. ⏳ **Pending:** Push to GitHub when repository is created
5. ⏳ **Pending:** Create GitHub issue using `BUG_REPORT_v1.2.1.md`
6. ⏳ **Pending:** Release to WordPress.org (if planned)

## Support

- **Plugin Page:** https://kraftysprouts.com
- **Documentation:** See `README.md` and `CHANGELOG.md`
- **Bug Reports:** Create issue at https://github.com/kraftysprouts/schedulely (when available)

---

**Krafty Sprouts Media, LLC**  
Making WordPress scheduling simple and reliable.

