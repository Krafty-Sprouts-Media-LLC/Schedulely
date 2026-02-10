# Changelog

All notable changes to Schedulely will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.5] - 10/02/2026

### Improved
- **Dashboard UI Clarifications** - Renamed "Last Scheduled Date" to "Furthest Scheduled Date" to prevent confusion with execution time.
- **System Health Status** - Added "Last Run" timestamp to System Health card to show when the scheduler actually last executed.

### Technical Details
- Updated `class-settings.php` to fetch `schedulely_last_run` option and display it in the System Health Stat card.
- Changed label for Stat 3 to "Furthest Scheduled Date".

---

## [1.3.4] - 25/01/2026

### Fixed
- **Fixed "View All" URL Error** - Resolved "Invalid post type" error when multiple post types are selected by implementing a dropdown menu instead of invalid comma-separated URL parameter
- Email notification "View All Scheduled Posts" link now works correctly with multiple post types

### Added
- **Smart "View All" Link** - When multiple post types are selected, "View All" becomes a dropdown menu allowing users to view scheduled posts by specific post type or all types

### Technical Details
- Added `get_scheduled_posts_url()` helper method in `Schedulely_Settings` class to properly handle single vs multiple post type URLs
- Updated "View All" link in settings page to show dropdown menu when multiple post types are selected
- Updated notification email link generation to handle multiple post types correctly

---

## [1.3.3] - 22/01/2026

### Added
- **Custom Post Type Support** - Plugin now supports scheduling posts from custom post types, not just the default 'post' type
- **Post Type Selection** - New multi-select field in settings to choose which post types to include in scheduling
- All registered public post types are now available for selection in the settings page

### Fixed
- **CRITICAL:** Fixed issue where plugin only queried 'post' post type, ignoring custom post types
- Plugin now correctly finds and schedules posts from all selected post types
- Statistics and dashboard now show accurate counts for all selected post types
- Scheduled posts queries now include all selected post types

### Changed
- Default post type setting changed from hardcoded 'post' to configurable array (defaults to ['post'] for backward compatibility)
- All database queries updated to support multiple post types

### Technical Details
- Added `schedulely_post_types` option to store selected post types (array)
- Added `sanitize_post_types()` method to validate and sanitize post type selections
- Updated `get_available_posts()`, `get_last_scheduled_date()`, `count_posts_on_date()`, and `get_scheduled_times_for_date()` methods in `Schedulely_Scheduler` class to use selected post types
- Updated `get_statistics()` and `render_upcoming_posts_list()` methods in `Schedulely_Settings` class
- Added Select2 initialization for post type multi-select field
- All SQL queries now use `IN` clause with prepared statements for multiple post types

### Migration
- Existing installations default to ['post'] for backward compatibility
- Users can now select multiple post types from the settings page
- No data migration required - existing scheduled posts are unaffected

---

## [1.3.2] - 19/01/2026

### Changed
- **Documentation Cleanup** - Consolidated and organized documentation
- **Repository Structure** - Moved internal documentation to `docs/` folder and excluded from public repository
- **Removed Outdated Files** - Deleted old version notes, bug reports, and deployment checklists that are no longer relevant

### Technical Details
- Added `docs/` folder to `.gitignore` to keep internal documentation private
- Removed 18 outdated documentation files from git tracking
- Kept essential documentation files: `INSTALL.md`, `PROJECT_SUMMARY.md`, `QUICK_REFERENCE.md`, `AI_INTEGRATION_OPPORTUNITIES.md` (in docs folder, not tracked)

---

## [1.3.1] - 19/01/2026

### Fixed
- **Security Vulnerabilities** - Fixed all critical and high-priority security issues identified in security audit
- **Capability Check** - Added capability verification in form submission handler to prevent unauthorized access
- **SQL Injection Prevention** - Converted all direct SQL queries to use `$wpdb->prepare()` for proper parameter binding
- **XSS Prevention** - Escaped JavaScript context outputs with `esc_js()` for nonce values
- **Uninstall Security** - Fixed uninstall script to use prepared statements with proper escaping

### Technical Details
- Fixed `includes/class-settings.php`:
  - Added `current_user_can('manage_options')` check inside form submission handler (Line 308-311)
  - Escaped JavaScript nonce outputs with `esc_js()` (Lines 718, 726)
- Fixed `includes/class-scheduler.php`:
  - Converted `get_last_scheduled_date()` query to use `$wpdb->prepare()` with parameter binding (Lines 151-160)
- Fixed `uninstall.php`:
  - Converted transient deletion queries to use `$wpdb->prepare()` with `$wpdb->esc_like()` for LIKE patterns (Lines 37-42)

### Security
- All critical security vulnerabilities from security audit have been resolved
- Plugin now fully complies with WordPress security coding standards
- All SQL queries use prepared statements
- All output is properly escaped for context

---

## [1.3.0] - 19/01/2026

### Added
- **GitHub Update Integration** - Integrated plugin-update-checker library for automatic updates from GitHub
- **Automatic Update Notifications** - Users will now receive update notifications when new versions are released on GitHub
- **GitHub Release Workflow** - Enhanced release workflow to automatically create zip files for plugin updates
- **One-Click Updates** - Users can update the plugin directly from WordPress admin using the standard WordPress update interface

### Technical Details
- Integrated plugin-update-checker library in `schedulely.php`:
  - Library located in `vendor/plugin-update-checker/` folder (organized structure)
  - Initializes update checker on `plugins_loaded` hook with priority 5
  - Connects to GitHub repository: `https://github.com/Krafty-Sprouts-Media-LLC/Schedulely`
  - Enables release assets (zip files) for automatic updates
  - Uses plugin slug `schedulely` for update identification
- Updated `.github/workflows/release.yml`:
  - Added step to create plugin zip file excluding git files and unnecessary files
  - Zip file is automatically attached to GitHub releases
  - Zip file naming: `schedulely-{VERSION}.zip`
  - Excludes: `.git*`, `.github*`, `.DS_Store`, `node_modules`, existing zip files, lock files

### Notes
- Updates are delivered via GitHub releases
- The update checker checks for updates every 12 hours
- Users can manually check for updates by clicking "Check for updates" on the Plugins page
- Update notifications appear in the WordPress admin dashboard

---

## [1.2.10] - 19/01/2026

### Fixed
- **Auto Schedule Toggle Not Working** - Fixed auto schedule toggle button not saving state when clicked
- Toggle now saves immediately via AJAX without requiring form submission
- Cron job is properly scheduled/unscheduled when toggle is changed
- Added visual feedback with success toast notification when toggle is changed

### Technical Details
- Added `ajax_toggle_auto_schedule()` method in `includes/class-settings.php`:
  - Handles AJAX request to save auto schedule toggle state
  - Manages WordPress cron job scheduling/unscheduling based on toggle state
  - Returns success/error messages for user feedback
- Added `initAutoScheduleToggle()` function in `assets/js/admin.js`:
  - Handles toggle change event
  - Sends AJAX request to save state immediately
  - Shows success toast notification
  - Reloads page to update status display
  - Includes error handling with toggle revert on failure

---

## [1.2.9] - 05/01/2026

### Fixed
- **WordPress admin notices display issue** - Fixed notices being cut off and not displaying at full width
- Admin notices now properly appear outside the constrained plugin content area
- Restructured HTML wrapper to separate `.wrap` (full width) from `.schedulely-wrap` (constrained content)
- Changed from inline notice output to WordPress `add_settings_error()` and `settings_errors()` functions for proper notice handling

### Technical Details
- Modified `includes/class-settings.php`:
  - Replaced inline `echo` for success notice with `add_settings_error()` function
  - Added `settings_errors('schedulely_messages')` call to display notices properly
  - Separated `.wrap` container from `.schedulely-wrap` container in HTML structure
  - Added HTML comments for better code clarity
- Updated `assets/css/admin.css`:
  - Added `.wrap` styles with full width (margin: 0, padding: 0)
  - Clarified `.schedulely-wrap` as plugin content wrapper with max-width constraint
  - Added comments to distinguish between WordPress admin wrapper and plugin content wrapper

---

## [1.2.8] - 05/01/2026


### Added
- **Modern Integrated Dashboard UI** - Complete redesign of settings page with dashboard grid layout
- **Dashboard Statistics Cards** - Four stat cards showing Drafts Available, Next Scheduled, Last Scheduled Date, and System Health
- **Insight Panel** - Collapsible informational panel explaining how random scheduling works
- **Dynamic Capacity Alerts** - Visual alert box with capacity meter showing scheduling capacity in real-time
- **Resolution Center** - Actionable suggestion cards with "Apply Fix" buttons for capacity issues
- **Quick Toggles Section** - Modern CSS-only toggle switches for Auto-Schedule and Email Alerts settings
- **Upcoming Posts Activity Feed** - Side panel showing next 5 scheduled posts with timestamps
- **Comprehensive Form Styling** - Professional WordPress-admin-style inputs, selects, and checkboxes

### Changed
- **Header Redesign** - Removed "Pro" badge, updated subtitle to "Intelligent Post Scheduling for WordPress"
- **Form Layout Optimization** - Active Days now aligned horizontally with Time Window for better space utilization
- **Toggle Switch Implementation** - Replaced basic checkboxes with animated CSS toggle switches
- **Select2 Integration** - Updated to properly target all author selection fields (excluded and preserved)
- **Removed Duplicate Headings** - "Recommended Fixes" heading now added dynamically by JavaScript only when suggestions exist

### Enhanced
- **Visual Hierarchy** - Improved header styling with proper font weights and sizing
- **Form Field Styling** - Added focus states, borders, and transitions to all form elements
- **Checkbox Styling** - Day selection checkboxes now have consistent styling with proper labels
- **Responsive Design** - All new UI elements adapt properly to different screen sizes
- **Color Coding** - Stat cards use color indicators (green for success, red for warnings) for quick status recognition

### Technical Details
- Modified `includes/class-settings.php`:
  - Completely rewrote `render_settings_page()` method with new HTML structure
  - Added `render_upcoming_posts_list()` private method for activity feed
  - Removed legacy `render_dashboard()`, `render_upcoming_posts()`, and `render_last_date_status()` methods
  - Updated form field structure to match new grid layout
  - Integrated dynamic stat card data from `get_statistics()` method
- Updated `assets/css/admin.css`:
  - Added 175+ lines of new CSS for form elements, toggle switches, and UI components
  - Added `.form-grid`, `.form-group`, `.form-label` classes for consistent form styling
  - Added `.toggle-switch`, `.toggle-slider` classes for animated toggle switches
  - Added `.day-checkbox`, `.quick-settings-title` classes for improved UI elements
  - Added comprehensive input/select/checkbox styling with focus states
- Updated `assets/js/admin.js`:
  - Modified `initAuthorSelect()` to target `.schedulely-author-select` class for both excluded and preserved authors
  - Fixed Select2 detection to use `$.fn.select2` for proper library checking
  - Ensured all dynamic UI components (capacity checker, suggestions, insight panel) work with new HTML structure

### UI/UX Improvements
- Dashboard now provides at-a-glance overview of scheduling status
- Capacity issues are immediately visible with visual meter and percentage
- Suggestions are presented as actionable cards instead of plain text
- Toggle switches provide clear on/off visual feedback
- Form fields have consistent, professional styling throughout
- Better visual separation between configuration sections

---

## [1.2.7] - 05/01/2026

### Added
- **Preserved Authors feature** - New setting to protect specific authors from randomization
- Posts currently assigned to preserved authors will keep their author when scheduling
- Only posts NOT assigned to preserved authors will be randomized among all eligible authors
- Example: If Author A is preserved and has 15 articles, all 15 will remain with Author A when Schedulely runs
- Posts assigned to non-preserved authors will be randomly assigned to any eligible author (including potentially the same author)

### Technical Details
- Added `schedulely_preserved_authors` option to store preserved author IDs
- Added `get_preserved_authors()` and `is_author_preserved()` methods to `Schedulely_Author_Manager` class
- Modified scheduler to check if post's current author is preserved before randomization
- If author is preserved, post keeps its current author; otherwise, proceeds with full randomization among all eligible authors
- Added "Preserved Authors" multi-select field in settings page with clear description
- Works in conjunction with "Excluded Authors" setting for complete author control

---

## [1.2.6] - 01/12/2025

### Enhanced
- **Email notifications now include scheduling context** - Added time window and run timestamp to email summary
- Email summary now displays when the scheduler ran (date and time)
- Email summary now shows the configured time window (start time - end time) used for scheduling posts
- Provides complete context about scheduling operations for better tracking and transparency

### Technical Details
- Modified `build_notification_message()` in `Schedulely_Notifications` class
- Added `$run_datetime` variable using WordPress `current_time()` function
- Added `$start_time` and `$end_time` variables from plugin settings
- Updated email HTML template to display new information in SUMMARY section
- Format: "Scheduler Ran: Sunday, Dec 1, 2025 at 8:34 AM"
- Format: "Time Window: 5:00 PM - 11:00 PM"

---

## [1.2.5] - 01/12/2025

### Fixed - CRITICAL
- **Incomplete first date bug** - Fixed rare edge case where failed post scheduling could leave the first date incomplete
- When `schedule_post()` failed for a single post, the scheduler would skip it and move to the next post without counting it toward the day's quota
- This caused dates to end up with fewer posts than the configured "Posts Per Day" setting (e.g., 9/10 instead of 10/10)
- Most commonly occurred when scheduling future dates where random time generation or WordPress database issues caused a single post to fail

### Root Cause
- When a post failed to schedule (line 310-312), the code logged an error but did NOT increment `$posts_scheduled_today` counter
- The scheduler would continue trying to fill the same date, but eventually move to the next day without completing the quota
- The "complete each date before moving" feature was present but broken due to improper failure handling

### Solution Implemented

#### Retry Logic with Different Times (Lines 310-365)
- **Before:** Single attempt to schedule each post - if it failed, just log error and continue
- **After:** Up to 3 attempts per post with different random times on the same date
  - First attempt fails → Retry with new random time
  - Second attempt fails → Retry again with different time
  - Third attempt fails → Count toward quota and move on

#### Proper Quota Counting
- **Before:** Failed posts didn't count toward `$posts_scheduled_today`, leaving days incomplete
- **After:** After all retries fail, the post is counted toward the day's quota to ensure scheduler moves to next day properly
- Prevents the scheduler from getting stuck trying to fill an incomplete date indefinitely

### Impact
- ✅ Each date now gets its full quota of posts (or maximum retry attempts)
- ✅ No more incomplete first dates due to random failures
- ✅ Better resilience against WordPress database hiccups or random time generation issues
- ✅ Detailed logging when retries succeed for debugging purposes
- ✅ Scheduler won't get stuck on one date - will move forward even if posts fail

### Technical Details
- Modified `schedule_posts_from_date()` method in `class-scheduler.php`
- Added retry loop with up to 2 additional attempts per failed post
- Each retry generates a new random time using `generate_random_time()`
- Successful retries are logged to debug.log when WP_DEBUG_LOG is enabled
- Failed posts (after all retries) increment `$posts_scheduled_today` to maintain quota logic
- No database changes required

### Example Scenario
**Before (v1.2.4):**
- Dec 1: Posts 1-9 succeed, Post 10 fails → Dec 1 ends with 9/10 posts ❌
- Dec 2-10: All complete with 10/10 posts ✅

**After (v1.2.5):**
- Dec 1: Posts 1-9 succeed, Post 10 fails → Retry with new time → Success! ✅
- Dec 1: 10/10 posts complete ✅
- Dec 2-10: All complete with 10/10 posts ✅

---

## [1.2.4] - 13/10/2025

### Fixed
- **Dashboard stats text wrapping** - Fixed PM/AM wrapping to another line by increasing column width from 200px to 250px and adjusting font size
- Improved stat value display with better line height and overflow handling

### Changed
- Stats grid minimum column width increased from 200px to 250px
- Stat value font size reduced from 28px to 20px for better fit
- Added white-space: nowrap to prevent text wrapping

---

## [1.2.3] - 13/10/2025

### Fixed - CRITICAL
- **Random time generator exhausting attempts before reaching capacity promise** - Fixed critical mismatch between capacity calculator and actual scheduling
- Plugin was promising 15 posts per day but only scheduling 8 posts due to insufficient retry attempts
- Capacity calculator showed "fits approximately 15 posts" but scheduling stopped at 8 posts

### Root Cause
- Random time generator had hardcoded limit of **100 attempts** to find valid time slots
- With small intervals (e.g., 24 minutes) and high quotas (e.g., 15 posts), collision probability increases exponentially
- After scheduling 8 posts, the generator couldn't find valid 24-minute gaps within 100 attempts
- Generator gave up and moved to next day, leaving dates incomplete at 8/15 posts

### Solution Implemented

#### 1. Dynamic Max Attempts (Lines 585-592)
- **Before:** Fixed 100 attempts for all scenarios
- **After:** Dynamic scaling based on scheduling density
  - Base: 200 attempts (doubled)
  - Additional: +50 attempts per already-scheduled post
  - Example: 0 posts = 200 attempts, 8 posts = 600 attempts, 15 posts = 950 attempts
- Accounts for exponentially increasing collision probability as more posts are placed

#### 2. Interval-Based Efficiency Factors (Lines 432-445)
- **Before:** Fixed 70% efficiency for all interval sizes
- **After:** Dynamic efficiency based on interval difficulty
  - Large intervals (60+ min): 70% efficiency
  - Medium intervals (30-59 min): 65% efficiency
  - Small intervals (20-29 min): 55% efficiency
  - Tiny intervals (<20 min): 50% efficiency
- Capacity calculator now accurately reflects actual scheduling performance

### Impact
- ✅ High-density scheduling (small intervals + many posts) now works correctly
- ✅ Capacity calculator shows realistic numbers that match actual scheduling
- ✅ No more "8/15 posts" incomplete dates - will fill to promised capacity
- ✅ User settings like "3:00 PM - 11:59 PM, 24min interval, 15 posts" now work as expected

### Technical Details
- Modified `generate_random_time()` method to use dynamic max_attempts
- Modified `calculate_capacity()` method to use interval-based efficiency
- Updated suggestion algorithms to use dynamic efficiency factors
- No database changes required

### Example: User's Settings
**Before (v1.2.2):**
- Settings: 3:00 PM - 11:59 PM, 24min interval, 15 posts/day
- Capacity Calculator: "fits approximately 15 posts" ✅
- Actual Scheduling: Only 8 posts scheduled ❌
- Result: 8/15 posts (NEEDS 7 MORE) on every date

**After (v1.2.3):**
- Settings: Same (3:00 PM - 11:59 PM, 24min interval, 15 posts/day)
- Capacity Calculator: "fits approximately 12 posts" (more realistic)
- Actual Scheduling: 12 posts scheduled ✅
- Result: Full date completion or accurate deficit tracking

---

## [1.2.2] - 12/10/2025

### Fixed
- **Capacity expansion suggestions now intelligently handle both start and end times**
- Plugin previously only suggested extending the end time to accommodate more articles
- End time hard limit of 11:59 PM created a ceiling that couldn't be exceeded
- Now provides three expansion strategies:
  1. Extend end time only (when space available before 11:59 PM)
  2. Start earlier AND extend to 11:59 PM (when end time is near limit)
  3. Start earlier only (when end time is already at or near 11:59 PM)
- Users can now fit more articles by adjusting either start time, end time, or both

### Changed
- Improved capacity calculation logic in `calculate_capacity()` method
- Smarter "Expand Time Window" suggestions based on available time at day's end
- More descriptive messages explaining why start time needs to change
- Better user experience when configuring scheduling windows

### Technical Details
- Modified `Schedulely_Scheduler::calculate_capacity()` in `class-scheduler.php`
- Added logic to calculate available minutes between current end time and 11:59 PM
- Three-tiered suggestion strategy based on `minutes_available_at_end`
- Respects 11:59 PM hard limit while maximizing scheduling capacity

---

## [1.2.1] - 12/10/2025

### Fixed - CRITICAL
- **Post count resetting bug in email notifications** - Email notifications now correctly count ALL posts scheduled for each date, not just posts from the current run
- Previously, if 5 posts were scheduled on Oct 16th, then 1 more was added later, the notification would incorrectly show "1/8 posts" instead of "6/8 posts"
- Fix ensures accurate date completion status reporting across multiple scheduling runs

### Technical Details
- Modified `build_notification_message()` in `Schedulely_Notifications` class
- Now uses `Schedulely_Scheduler::count_posts_on_date()` to query database for accurate total counts
- Eliminated reliance on `$results['scheduled_posts']` array which only contained current run's posts

### Impact
- Users will now see accurate cumulative post counts in email notifications
- Date completion status will correctly reflect all posts scheduled for a date, not just the most recent batch
- No changes to actual scheduling logic - only affects notification reporting

---

## [1.2.0] - 07/10/2025

### Added
- **"How Random Scheduling Works" notice** - Informative explanation in Capacity Check section explaining minimum intervals, variable gaps, and 70% efficiency
- **User education** - Clear explanation that random scheduling creates uneven spacing for natural appearance

### Fixed - CRITICAL
- **Cron schedule not updating** - Sites with hourly cron from old versions now properly migrate to twice-daily
- **Capacity calculator showing incorrect capacity** - Removed erroneous `+1` from formula and adjusted multiplier to 0.70 (70% efficiency)
- **Capacity vs actual scheduling mismatch** - Calculator now accurately predicts how many posts will actually be scheduled
- **Email notification incomplete** - Now shows FULL date status report with all dates and their completion status, highlighting any deficits

### Changed
- **Capacity calculation formula** - Changed from `floor(total_minutes / interval) + 1` to `floor(total_minutes / interval)`
- **Random scheduling efficiency** - Reduced from 75% to 70% to match real-world performance
- **Cron migration logic** - Upgrade function now forcibly clears old hourly schedule and reschedules with twicedaily
- **Email notification format** - Complete overhaul showing ALL dates with individual completion status and deficit warnings

### Technical Details
- **Capacity Formula Fix:**
  - Old (incorrect): `floor(360/35) + 1 = 11 * 0.75 = 8` (claimed 8, scheduled 7)
  - New (correct): `floor(360/35) = 10 * 0.70 = 7` (claims 7, schedules 7)
- **Cron Fix:** Upgrade from <1.0.8 now calls `wp_unschedule_event()` before `wp_schedule_event()`
- **Email Enhancement:** Full date-by-date report showing complete/incomplete status for EVERY scheduled date with clear deficit warnings

### Impact
- Users will now see accurate capacity estimates that match actual scheduling results
- Existing sites will automatically migrate to twice-daily cron on next page load
- Email notifications provide clearer feedback on scheduling status per date

---

## [1.0.10] - 07/10/2025

### Changed
- **BREAKING CHANGE:** Notification email field replaced with user selection dropdown
- Email notifications now use Select2 multi-select (same as author exclusion)
- Only users with `publish_posts` capability shown (Authors, Editors, Administrators - excludes Contributors)
- Emails fetched dynamically from selected users (no more typos!)
- Consistent UI across plugin settings
- Database storage changed from emails to user IDs

### Improved
- Better UX: Select users instead of typing emails
- No email typos possible
- Automatic email updates when users change their email
- Search functionality via Select2
- Visual consistency with author selection

### Technical
- Changed option from `schedulely_notification_email` to `schedulely_notification_users`
- Added `sanitize_notification_users()` method
- Updated `get_notification_email()` to fetch emails from user IDs
- Validates users have `publish_posts` capability
- Legacy option cleaned up on uninstall
- Select2 initialization added for notification users

### Migration
- Existing email settings won't carry over (different data format)
- Plugin defaults to current admin user if no selection
- Users need to select notification recipients in settings

---

## [1.0.9] - 07/10/2025

### Added
- **Settings link on Plugins page** - Quick access to Schedulely settings directly from the plugins list
- **Welcome notification** - Shows dismissible admin notice after activation with link to settings page
- **Multiple email support** - Notification emails now support multiple recipients (comma, semicolon, or newline separated)
- **All post statuses supported** - Plugin now detects and supports ALL registered post statuses, not just draft/pending/private
- Custom post statuses from other plugins are now automatically available

### Changed
- Post Status dropdown now dynamically loads all available statuses
- Notification email field changed from single-line input to textarea for multiple emails
- Welcome notice appears on all admin pages (except settings page) until dismissed
- Improved email sanitization to handle multiple formats

### Technical
- Added `schedulely_plugin_action_links()` filter for Settings link
- Added `show_welcome_notice()` and `ajax_dismiss_notice()` methods to Settings class
- Updated `sanitize_post_status()` to dynamically validate against all registered statuses
- Added `sanitize_email_list()` method for multiple email validation
- Updated `get_notification_email()` in Notifications class to return array for multiple recipients
- Uses WordPress `get_post_stati()` for dynamic status detection

---

## [1.0.8] - 07/10/2025

### Fixed
- **CRITICAL SECURITY FIX:** Auto-scheduling now disabled by default on plugin activation
- **CRITICAL BUG FIX:** Added safety check to prevent scheduling posts in the past
- **CRITICAL BUG FIX:** Added 30-minute minimum buffer before scheduled publish time
- Plugin will no longer auto-run immediately upon activation
- Posts can no longer be accidentally published due to past-time scheduling
- Comprehensive error logging for all time-related issues

### Changed
- `schedulely_auto_schedule` default changed from `true` to `false`
- Users must now explicitly enable auto-scheduling or click "Schedule Now"
- Added detailed logging when posts are rejected due to time issues
- **Cron frequency changed from hourly to twice daily** (12-hour intervals)
- UI updated to show "twice daily" instead of "hourly"

### Security
- Prevents automatic mass-publishing of drafts on plugin activation
- Ensures all scheduled posts have future times with safety buffer
- Protects against timezone-related publishing bugs

### Why This Fix
On October 7, 2025, a critical bug was discovered where activating the plugin would immediately trigger auto-scheduling, and due to a time calculation issue, posts could be published instead of scheduled. This version completely prevents that scenario by:
1. Disabling auto-schedule by default (requires user action)
2. Adding multiple safety checks to refuse past times
3. Implementing comprehensive error logging

**This is a mandatory security update. Update immediately.**

---

## [1.0.7] - 07/10/2025

### Fixed
- **CRITICAL:** Capacity calculator now accounts for random time placement inefficiency
- Capacity calculation reduced by 25% to reflect realistic random scheduling (not perfect spacing)
- Example: 6-hour window with 45-min interval now shows ~7 posts (realistic) instead of 9 (theoretical maximum)
- All suggestions now use adjusted capacity calculations for accuracy
- Prevents the plugin from promising more posts than it can actually deliver

### Changed
- UI now says "approximately X posts" to set realistic expectations
- Added note: "Estimate accounts for random time placement"
- Warning messages clarified: "With random time scheduling, fewer posts will fit"

### Technical
- Applied 0.75 multiplier to theoretical capacity for realistic estimate
- Special handling for small windows (1-3 posts) with more conservative calculations
- Updated all suggestion algorithms to account for randomness overhead
- Created CAPACITY_CALCULATION_EXPLAINED.md with full technical documentation

## [1.0.6] - 07/10/2025

### Fixed
- Success dialog "View Scheduled Posts" button now actually navigates to WordPress scheduled posts page
- Button previously just reloaded the page, now properly opens `edit.php?post_status=future&post_type=post`

### Added
- Added "Stay Here" option after scheduling to remain on settings page and view updated Upcoming Posts list
- Two-button choice gives users control over post-scheduling workflow

### Technical
- Added `scheduled_posts_url` to localized script variables
- Updated success dialog with `showCancelButton` and proper navigation logic

## [1.0.5] - 07/10/2025

### Added
- **Capacity Calculator**: Real-time validation that checks if your time window can actually fit your desired posts per day
- Live capacity checking as you adjust settings (with 500ms debounce)
- Visual feedback with color-coded notices (✅ success, ⚠️ warning, ❌ error)
- Smart suggestions when capacity is insufficient with three fix options:
  1. Reduce minimum interval between posts
  2. Reduce posts per day quota
  3. Expand time window duration
- One-click "Apply" buttons to automatically fix capacity issues
- Warning dialog before scheduling if settings won't fit desired quota
- Detailed capacity information display (total minutes, posts that can fit, etc.)

### Changed
- Schedule Now button now checks capacity first before confirming
- Improved user experience with proactive validation
- Settings page now shows capacity status in real-time
- Capacity Check now displays within Scheduling Settings card for better context and immediate feedback

### Technical
- Added `calculate_capacity()` method to `Schedulely_Scheduler` class
- Added AJAX endpoint `schedulely_check_capacity` for real-time validation
- New JavaScript functions: `initCapacityChecker()`, `checkCapacity()`, `displayCapacityResult()`
- Enhanced capacity warning in scheduling confirmation dialog
- New CSS styling for capacity notices and suggestions
- Responsive design for capacity suggestions on mobile devices
- Improved documentation for settings preservation during updates
- Added upgrade logging to debug log when WP_DEBUG is enabled
- Enhanced `schedulely_upgrade()` function with cron recovery

## [1.0.4] - 07/10/2025

### Added
- Added SweetAlert2 for beautiful, modern confirmation and notification dialogs
- Replaced browser confirm() with professional modal dialogs
- Added loading states with animated spinners during scheduling
- Added success/error notifications with better UX

### Changed
- Complete UI refresh with clean WordPress native styling
- Removed custom color schemes in favor of WordPress admin colors
- Simplified CSS from 400+ lines to clean, maintainable styles
- Better responsive design for mobile devices
- Improved button styling and spacing
- Enhanced card layouts and typography
- Better form validation with SweetAlert2 alerts

### Technical
- Added SweetAlert2 CDN integration (v11)
- Updated admin.js with new dialog handlers
- Complete admin.css rewrite with WordPress-native design
- Added spin animation for loading states
- Improved Select2 and Flatpickr theme integration

## [1.0.3] - 06/10/2025

### Fixed
- Fixed author randomization not including Contributors (capability changed from `publish_posts` to `edit_posts`)
- Contributors, Authors, Editors, and Administrators now all appear in author selection
- Updated both random assignment logic and settings page dropdown to show all eligible users

## [1.0.2] - 06/10/2025

### Fixed
- **CRITICAL:** Fixed fundamental deficit logic flaw that attempted to schedule posts to past dates
- WordPress rejects scheduling to past dates, rendering old deficit tracking ineffective

### Changed
- **Complete logic refactor:** Replaced multi-date deficit tracking with "last date completion" approach
- Now checks only the LAST/FURTHEST scheduled date and completes it if incomplete (and not in past)
- Simpler, more reliable logic that works with WordPress's scheduling limitations
- Removed `Schedulely_Deficit_Tracker` class (no longer needed)
- Updated admin UI: Replaced "Deficit Status" with "Last Scheduled Date" display
- Updated email notifications: Show last date completion status instead of deficit count
- Removed deficit tracker database option

### Technical Details
- Deleted: `includes/class-deficit-tracker.php`
- Refactored: `Schedulely_Scheduler` class with new methods:
  - `get_last_scheduled_date()` - Find furthest scheduled date
  - `count_posts_on_date()` - Count posts on specific date
  - `get_next_scheduling_date()` - Determine starting date
  - `schedule_posts_from_date()` - Schedule posts from specific date
  - `get_scheduled_times_for_date()` - Get existing times for date
- Updated: Settings class dashboard and statistics display
- Updated: Email notifications with new completion status

## [1.0.1] - 06/10/2025

### Fixed
- Fixed admin menu not appearing in WordPress dashboard
- Changed initialization hook from `admin_menu` to `plugins_loaded` to ensure proper menu registration timing
- Plugin settings now correctly accessible at **Tools → Schedulely**

## [1.0.0] - 06/10/2025

### Added
- Initial release of Schedulely
- Smart deficit tracking system that automatically fills missed daily quotas
- Random time distribution within user-defined windows
- Minimum interval enforcement between scheduled posts
- Random author assignment with user exclusion capability
- Manual scheduling via "Schedule Now" button
- Automatic scheduling via WordPress cron (hourly)
- Beautiful admin dashboard with real-time statistics
- Email notifications for scheduling events
- Configurable post status monitoring (draft, pending, private)
- Customizable daily post quotas
- Flexible time window configuration (12-hour format)
- Active days selection (choose which days to schedule)
- Upcoming scheduled posts display (shows next 20 posts)
- Deficit status tracking and display
- Settings validation and sanitization
- Complete uninstall cleanup
- WordPress coding standards compliance
- Internationalization support (i18n ready)
- Responsive admin interface
- Select2 integration for author selection
- Flatpickr integration for time picking
- Professional documentation (README.txt)

### Technical Details
- Minimum WordPress version: 6.8
- Minimum PHP version: 8.2
- Uses WordPress native time functions
- Implements proper security measures (nonces, sanitization, escaping)
- Optimized database queries
- Cache management
- Error logging support
- GPL v2 or later license

---

**Plugin:** Schedulely  
**Author:** Krafty Sprouts Media, LLC  
**Website:** https://kraftysprouts.com

