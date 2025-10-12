# Bug Report - Post Count Resetting in Email Notifications

**Report Date:** 12/10/2025  
**Fixed in Version:** 1.2.1  
**Severity:** CRITICAL  
**Status:** RESOLVED

---

## Bug Description

Email notifications were incorrectly counting only posts scheduled in the **current run** instead of the **total posts** on each date, causing counts to reset across multiple runs.

## Symptoms

**Observed Behavior:**
- On Oct 7th: "Thursday, Oct 16, 2025: 5/8 posts (NEEDS 3 MORE)"
- On Oct 12th: After scheduling 1 more post: "Thursday, Oct 16, 2025: 1/8 posts (NEEDS 7 MORE)"
- **Expected:** Should have shown "6/8 posts (NEEDS 2 MORE)"

## Root Cause

The `build_notification_message()` method in `Schedulely_Notifications` class was counting posts from `$results['scheduled_posts']` array, which only contains posts scheduled in the current run, not all posts on that date.

### Buggy Code (Before Fix)

```php
// Count posts per date
$posts_per_date = [];
foreach ($dates as $date) {
    $posts_per_date[$date] = 0;
}
foreach ($results['scheduled_posts'] as $post) {
    if (isset($posts_per_date[$post['date']])) {
        $posts_per_date[$post['date']]++;  // ❌ Only counts current run
    }
}
```

## Fix Applied

Modified the method to query the database for actual total counts using the `count_posts_on_date()` method.

### Fixed Code (After Fix)

```php
// CRITICAL FIX: Count TOTAL posts per date (not just from current run)
// This fixes the bug where counts reset instead of accumulating
$scheduler = new Schedulely_Scheduler();
$posts_per_date = [];
foreach ($dates as $date) {
    $posts_per_date[$date] = $scheduler->count_posts_on_date($date);  // ✅ Counts ALL posts on date
}
```

## Technical Details

### Files Modified

1. **includes/class-notifications.php**
   - Updated `build_notification_message()` method (lines 123-129)
   - Added file header with version 1.2.1

2. **includes/class-scheduler.php**
   - Updated file header with version 1.2.1

3. **schedulely.php**
   - Bumped version to 1.2.1 (line 6 and line 26)

4. **CHANGELOG.md**
   - Added version 1.2.1 entry with detailed fix documentation

## Impact

### User Impact
- ✅ Email notifications now show accurate cumulative post counts
- ✅ Date completion status correctly reflects all scheduled posts, not just the most recent batch
- ✅ Users can now trust the email reports to show true scheduling status

### Technical Impact
- ✅ No changes to actual scheduling logic
- ✅ Only affects notification reporting
- ✅ Database query added per date in notification (minimal performance impact)

## Testing

### To Verify the Fix:

1. Schedule 5 posts to a future date (e.g., Oct 16th)
2. Wait or manually trigger another scheduling run
3. Schedule 1 more post to the same date
4. Check email notification

**Expected Result:** Should show "6/8 posts (NEEDS 2 MORE)" not "1/8 posts (NEEDS 7 MORE)"

## Prevention

This bug occurred because the notification system relied on the `$results` array which only contained information about the current run. Going forward, any display of post counts should query the database directly using `count_posts_on_date()` to ensure accuracy.

## Related Code

The fix uses the existing `Schedulely_Scheduler::count_posts_on_date()` method:

```php
public function count_posts_on_date($date) {
    global $wpdb;
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$wpdb->posts} 
         WHERE post_status = 'future' 
         AND post_type = 'post'
         AND DATE(post_date) = %s",
        $date
    ));
    
    return (int) $count;
}
```

This method correctly queries all posts with `post_status = 'future'` on the specified date, regardless of when they were scheduled.

---

## GitHub Issue Template

**When creating the GitHub repository, create an issue with this information:**

**Title:** [BUG] Post count resetting in email notifications - showing only current run instead of total

**Labels:** `bug`, `critical`, `notifications`, `resolved`

**Body:** Copy the "Bug Description" through "Impact" sections above

**Status:** Close immediately with note "Fixed in v1.2.1"

