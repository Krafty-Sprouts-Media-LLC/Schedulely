# Schedulely Version 1.2.2 - Release Notes

**Release Date:** 12/10/2025  
**Type:** Enhancement Fix  
**Upgrade Priority:** MEDIUM

---

## Overview

Version 1.2.2 fixes a limitation in the capacity expansion suggestions where the plugin only recommended extending the end time to accommodate more articles, ignoring the 11:59 PM hard limit. The plugin now intelligently suggests adjusting both start and end times based on available time.

## The Problem

When the capacity calculator determined that more time was needed to fit the desired posts per day:

- **Previous Behavior:** Only suggested extending the end time (e.g., "Extend from 5:00 PM-9:00 PM to 5:00 PM-11:30 PM")
- **Issue:** If the end time was already near 11:59 PM, there was no suggestion to start earlier
- **Result:** Users hit a hard ceiling and couldn't fit more articles even though starting earlier would solve the problem

Example:
- Time window: 9:00 PM - 11:00 PM (2 hours)
- Desired quota: 8 posts/day
- Min interval: 35 minutes
- Plugin suggested: "Extend to 9:00 PM - 11:59 PM" (still only ~4 posts)
- Never suggested: "Start at 5:00 PM - 11:00 PM" (would fit 8+ posts)

## The Solution

The plugin now provides **three intelligent expansion strategies**:

### Strategy 1: Extend End Time Only
**When:** Enough space available before 11:59 PM to fit all posts
```
Current: 5:00 PM - 9:00 PM
Suggestion: 5:00 PM - 11:00 PM
Message: "Extend end time (space available before 11:59 PM)"
```

### Strategy 2: Start Earlier + Extend to 11:59 PM
**When:** Some space at end, but not enough - need both adjustments
```
Current: 7:00 PM - 10:00 PM
Suggestion: 4:00 PM - 11:59 PM
Message: "Extend from 7:00 PM-10:00 PM to 4:00 PM-11:59 PM (start earlier + extend to 11:59 PM)"
```

### Strategy 3: Start Earlier Only
**When:** End time already at or near 11:59 PM - can only start earlier
```
Current: 10:00 PM - 11:59 PM
Suggestion: 5:00 PM - 11:59 PM
Message: "Start earlier from 10:00 PM-11:59 PM to 5:00 PM-11:59 PM (end time cannot extend past 11:59 PM)"
```

## The Fix

Modified the `calculate_capacity()` method in the `Schedulely_Scheduler` class to:

1. Calculate available minutes between current end time and 11:59 PM
2. Determine best expansion strategy based on `minutes_available_at_end`
3. Provide appropriate suggestions with clear explanations

### Code Changes

**File:** `includes/class-scheduler.php`

**Added Logic:**
```php
// Calculate how much to add (needed - current)
$minutes_to_add = $needed_minutes - $total_minutes;

// Hard limit: end time cannot go past 11:59 PM
$max_end_timestamp = strtotime($date . ' 11:59 PM');
$minutes_available_at_end = ($max_end_timestamp - $end_timestamp) / 60;

// Decide strategy based on available space at end
if ($minutes_to_add <= $minutes_available_at_end) {
    // Strategy 1: Extend end time only
} elseif ($minutes_available_at_end > 0 && $minutes_to_add > $minutes_available_at_end) {
    // Strategy 2: Start earlier + extend to 11:59 PM
} else {
    // Strategy 3: Start earlier only
}
```

## Files Changed

1. ✅ `includes/class-scheduler.php` - Enhanced capacity expansion logic
2. ✅ `schedulely.php` - Version bump to 1.2.2
3. ✅ `CHANGELOG.md` - Documented enhancement

## Impact

### What's Improved
- ✅ Capacity expansion suggestions now consider both start and end time adjustments
- ✅ Respects the 11:59 PM hard limit intelligently
- ✅ Users can fit more articles by starting earlier when end time is maxed out
- ✅ Clear explanations for why each adjustment is recommended
- ✅ No more hitting artificial capacity ceilings

### What's NOT Affected
- ✅ Actual scheduling logic unchanged
- ✅ Capacity calculations remain the same
- ✅ All other features work as before
- ✅ No database changes

## Testing

To verify the enhancement:

### Test Case 1: End Time Has Space
1. Set time window: 5:00 PM - 9:00 PM
2. Set posts per day: 10
3. Check capacity suggestions
4. **Expected:** Suggests extending end time only (e.g., to 11:30 PM)

### Test Case 2: End Time Near Limit
1. Set time window: 8:00 PM - 11:00 PM
2. Set posts per day: 10
3. Check capacity suggestions
4. **Expected:** Suggests starting earlier AND extending to 11:59 PM

### Test Case 3: End Time at Limit
1. Set time window: 10:00 PM - 11:59 PM
2. Set posts per day: 10
3. Check capacity suggestions
4. **Expected:** Suggests starting earlier only (end time already maxed)

## Upgrade Instructions

### For Users

**Automatic Upgrade (WordPress Updates):**
1. WordPress will show update available
2. Click "Update Now"
3. Done! Your settings are preserved

**Manual Upgrade:**
1. Deactivate the old version
2. Delete the old plugin files
3. Upload version 1.2.2
4. Activate the plugin

**Note:** All settings are preserved during updates. No configuration needed.

### For Developers

**Testing Locally:**
```bash
# 1. Pull latest code
git pull origin master

# 2. Check version
grep "Version:" schedulely.php  # Should show 1.2.2

# 3. Test the enhancement
# Try different time windows near 11:59 PM and verify suggestions
```

## Upgrade Path

| From Version | To Version | Notes |
|-------------|------------|-------|
| 1.2.1 | 1.2.2 | Seamless upgrade, settings preserved |
| 1.2.0 | 1.2.2 | Seamless upgrade, settings preserved |
| 1.0.0 - 1.1.x | 1.2.2 | No special migration needed |
| Any version | 1.2.2 | Settings preserved automatically |

## Performance Impact

**None.** This is a pure UI/suggestion enhancement. The capacity calculation and suggestion logic runs only when:
- User adjusts settings in the admin panel
- User views the capacity check section

No impact on:
- Actual post scheduling
- Database queries
- Frontend performance
- Cron jobs

## Known Issues

None. This is a pure enhancement release with no known issues.

## Next Steps

1. ✅ Enhancement implemented and tested
2. ✅ Version bumped to 1.2.2
3. ✅ Changelog updated
4. ✅ Pushed to GitHub
5. ⏳ **Pending:** Release to WordPress.org (if planned)
6. ⏳ **Pending:** User documentation update

## Technical Notes

### Why Three Strategies?

The three-tiered approach ensures users always get the most practical suggestion:

1. **Extend End Only:** Simplest solution when possible (fewer changes to existing schedule)
2. **Both Adjustments:** Optimal when you need more space and can use both directions
3. **Start Earlier Only:** Only option when end time is maxed (respects hard limit)

### Why 11:59 PM Hard Limit?

WordPress posts scheduled for 12:00 AM or later would technically be on the next day, breaking the "posts per day" quota logic. The 11:59 PM limit ensures all posts scheduled on a given date fall within that calendar day.

## Support

- **Plugin Page:** https://kraftysprouts.com
- **Documentation:** See `README.md` and `CHANGELOG.md`
- **Bug Reports:** https://github.com/Krafty-Sprouts-Media-LLC/Schedulely/issues
- **Source Code:** https://github.com/Krafty-Sprouts-Media-LLC/Schedulely

---

**Krafty Sprouts Media, LLC**  
Making WordPress scheduling simple and reliable.

