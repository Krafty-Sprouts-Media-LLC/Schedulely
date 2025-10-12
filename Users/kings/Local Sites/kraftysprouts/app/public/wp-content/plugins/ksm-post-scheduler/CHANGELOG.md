# Changelog

All notable changes to the KSM Post Scheduler plugin will be documented in this file.

## [2.0.2] - 06/10/2025

### Critical Bug Fixes
- **FIXED**: Smart backfill feature consuming all available posts, preventing multi-day scheduling
- **FIXED**: `detect_and_record_deficit()` function call bug in cron job - was called without required parameters
- **ENHANCED**: Completely redesigned backfill algorithm to only allocate exact posts needed for deficits
- **IMPROVED**: Multi-day scheduling now works correctly even when deficit days exist
- **ADDED**: New `detect_and_record_yesterday_deficit()` function for proper cron job deficit detection

### Technical Details
- Replaced percentage-based backfill allocation with precise deficit-based allocation
- Backfill now only takes the exact number of posts needed for each deficit date
- Added proper parameter handling for deficit detection in cron jobs
- Improved logging to show actual posts allocated vs. total available
- Fixed issue where backfill could allocate 70% of posts to a single day instead of filling actual shortfalls

## [2.0.1] - 06/10/2025

### Bug Fixes
- **FIXED**: Missing progress report functionality in manual scheduling - progress messages now display correctly
- **ENHANCED**: Progress report now includes intermediate scheduling messages (daily limits, day transitions, etc.)

## [2.0.0] - 06/10/2025

### MAJOR REFACTORING: NATIVE WORDPRESS SCHEDULING
- **BREAKING CHANGE**: Removed custom publication hooks system that bypassed WordPress core
- **REMOVED**: `publish_scheduled_post()` function that directly updated database and bypassed `wp_transition_post_status`
- **REMOVED**: `register_publication_hooks()` function that created custom hooks on every page load
- **REMOVED**: Custom `ksm_ps_publish_post_{post_ID}` cron events in favor of WordPress native scheduling
- **ENHANCED**: Now uses WordPress native `post_status = 'future'` and `post_date` for automatic publication
- **FIXED**: Compatibility issues with EditFlow notifications, Newsbreak integration, and other plugins
- **FIXED**: Premature publication issues caused by custom cron timing conflicts
- **IMPROVED**: Plugin performance by removing database queries on every page load
- **IMPROVED**: Integration with WordPress ecosystem by following standard post scheduling practices

### Technical Changes
- Refactored `schedule_posts()` to rely on WordPress core publication system
- Removed all `wp_schedule_single_event()` calls for custom publication hooks
- Eliminated direct database updates that bypassed WordPress hooks
- Simplified cron system to focus only on scheduling, not publishing
- Enhanced compatibility with third-party plugins that depend on standard WordPress hooks

### Bug Fixes
- **FIXED**: JavaScript context issue in admin.js that prevented manual scheduling button from working
- **FIXED**: Event binding problems that caused "this.validateForm is not a function" error
- **IMPROVED**: All admin JavaScript event handlers now properly preserve object context

## [1.9.6] - 03/10/2025

### MAJOR FEATURE: AUTO-COMPLETION SYSTEM
- **NEW**: Smart Auto-Completion feature that automatically fills missed daily quotas
- **ADDED**: Deficit tracking system to monitor incomplete days and their post shortfalls
- **ADDED**: Smart backfill algorithm that prioritizes older deficits when scheduling new posts
- **ADDED**: Admin interface showing deficit status, incomplete days count, and recent deficit history
- **ENHANCED**: Daily cron job now detects and records when quotas are not met
- **IMPROVED**: Scheduling logic now automatically allocates available posts to fill past deficits before scheduling for future dates
- **VISUAL**: New "Auto-Completion Status" section in admin sidebar with color-coded deficit indicators

### Technical Implementation
- Added deficit tracking methods: `get_deficits()`, `record_deficit()`, `clear_deficit()`, `get_total_deficit()`
- Implemented smart backfill algorithm with `apply_smart_backfill()` and `schedule_backfill_posts()`
- Enhanced cron job with `detect_and_record_deficit()` for automatic deficit detection
- Added WordPress options-based storage for deficit data with 30-day cleanup
- Integrated backfill logic into main `schedule_posts()` method for seamless operation

## [1.9.5] - 03/10/2025

### CRITICAL AUTHOR EXCLUSION FIX
- **FIXED**: "Eligible Authors" count now properly excludes selected authors from the total
- **ADDED**: Missing "Excluded Authors" section to display count of excluded users in sidebar
- **FIXED**: Author filtering logic to ensure excluded users are not counted as eligible authors
- **IMPROVED**: Author assignment accuracy by ensuring proper exclusion logic in admin display

### Technical Details
- Fixed array filtering in admin-page.php to properly subtract excluded users from eligible count
- Added conditional display of "Excluded Authors" section when excluded users exist
- Used array_filter() with anonymous function for clean exclusion logic
- Maintains proper count accuracy: Total = Eligible + Excluded

## [1.9.4] - 03/10/2025

### Enhanced
- **UI IMPROVEMENT**: Moved "Time Analysis & Scheduling Validation" section from main content area to sidebar under "Scheduling Overview"
- **BETTER VISIBILITY**: Time Analysis now displays alongside other status indicators for improved live update visibility
- **ENHANCED UX**: Consolidated scheduling information in the sidebar for better organization and user experience

## [1.9.3] - 03/10/2025

### CRITICAL TIMEZONE FIX
- **FIXED**: CRON scheduling now uses WordPress timezone instead of server timezone
- **FIXED**: Replaced `strtotime('tomorrow midnight')` with WordPress timezone-aware `DateTime` object
- **FIXED**: CRON now correctly schedules for 1:00 AM in WordPress timezone instead of midnight in server timezone
- **IMPROVED**: Eliminated timezone conversion issues that caused CRON to run at unexpected times
- **ENHANCED**: Consistent timezone handling throughout the plugin using `wp_timezone()`

## [1.9.2] - 02/10/2025

### Fixed
- **Admin Notice Dismissal**: Fixed dismissible notice functionality to properly persist dismissal state
  - Added AJAX handler `ksm_ps_dismiss_notice` to handle notice dismissal requests
  - Implemented user meta storage to remember dismissed notices per user
  - Added JavaScript event handler for notice dismissal with proper AJAX communication
  - Version update notices now stay dismissed after page reload
  - Enhanced notice system with unique data attributes for proper identification

## [1.9.1] - 02/10/2025

### Fixed
- **CRITICAL: Prevented Immediate Publication**: Added future timestamp validation before `wp_schedule_single_event()`
  - Fixed critical bug where posts with past timestamps would be published immediately when plugin is active
  - Added validation to ensure scheduled timestamps are always in the future before cron scheduling
  - Enhanced error logging for posts with invalid timestamps
  - Ensures plugin activation does not trigger unintended publications

### Enhanced
- **WordPress Timezone Consistency**: Completed transition to exclusive WordPress timezone usage
  - Replaced remaining `date('l')` with `current_time('l')` for day determination
  - All scheduling now uses WordPress timezone functions consistently
  - Eliminated server timezone dependencies for improved reliability

## [1.9.0] - 02/10/2025

### Added
- **Real-Time Validation Warnings**: Implemented comprehensive real-time validation in admin interface
  - Added "Time Analysis & Scheduling Validation" section that dynamically calculates scheduling feasibility
  - Real-time warnings when time window is too narrow for specified posts per day
  - Multi-day scheduling analysis showing days needed and effective posts per day
  - Smart suggestions to resolve time conflicts (reduce posts, extend time window, reduce interval)
  - Visual indicators for scheduling validity with detailed warning messages

### Enhanced
- **Pre-Scheduling Validation**: Added comprehensive validation before manual scheduling execution
  - Prevents manual scheduling when settings would result in incomplete scheduling
  - Displays detailed error messages with actionable suggestions
  - Form validation checks for time inputs, posts per day, and minimum intervals
  - Integration with real-time validation system for consistent user feedback

### Fixed
- **Duplicate Progress Reports**: Resolved issue where multiple progress reports could appear after manual scheduling
  - Added proper DOM clearing before displaying new results
  - Implemented request deduplication to prevent multiple simultaneous AJAX calls
  - Enhanced result container management with proper class and content clearing
  - Fixed AJAX response handling to ensure single progress report display

### Improved
- **Error Handling**: Enhanced error handling throughout the admin interface
  - Better timeout handling for long-running scheduling operations (60-second timeout)
  - Improved error messages with specific status codes and descriptions
  - WordPress-style notice formatting for all success and error messages
  - Automatic status refresh after successful manual scheduling

### Technical
- **JavaScript Enhancements**: Improved admin.js functionality
  - Added `window.ksmSchedulingValid` and `window.ksmValidationWarnings` global state tracking
  - Enhanced `updateTimeCalculator()` function with comprehensive validation logic
  - Improved `runNow()` function with better error handling and duplicate prevention
  - Fixed AJAX variable references and response data structure handling

## [1.8.9] - 02/10/2025

### Removed
- **Custom "Ready to Schedule" Status**: Completely removed the custom post status functionality
  - Removed `register_custom_post_status()` method and its registration
  - Removed all Gutenberg-related methods and REST API integrations
  - Removed `gutenberg-status.js` file entirely
  - Cleaned up all references to `ksm_scheduled` status throughout the codebase
  - Updated default post status monitoring to use 'draft' instead of custom status
  - Simplified admin interface by removing custom status explanations

### Changed
- **Default Configuration**: Plugin now defaults to monitoring 'draft' posts instead of custom status
- **Admin Interface**: Updated status selection dropdown and descriptions to reflect removal of custom status
- **Code Simplification**: Streamlined codebase by removing complex custom status integration code

### Note
- Users requiring advanced post status management should use dedicated third-party plugins
- Existing posts with custom status will need to be manually updated to standard WordPress statuses

## [1.8.8] - 02/10/2025

### Fixed
- **"Ready to Schedule" Status Visibility**: Fixed issue where the "Ready to Schedule" status was not appearing in WordPress native status dropdowns
  - Completely rewrote custom post status integration to work with WordPress native UI
  - Removed invalid `post_type` parameter from `register_post_status()` function
  - Implemented proper JavaScript integration for post edit screens, quick edit, and bulk edit
  - Added comprehensive status dropdown handling for all WordPress admin screens
  - Status now appears correctly in single post edit, new post, and posts list screens

### Technical Changes
- Replaced old `add_custom_status_to_edit_screen()` method with new `add_status_to_dropdown()` method
- Added proper admin_head hooks for post.php, post-new.php, and edit.php screens
- Enhanced JavaScript to handle dynamic status dropdown population
- Improved status display text handling and visual styling
- Removed redundant bulk edit methods and consolidated functionality

## [1.8.7] - 02/10/2025

### Fixed
- **Critical Scheduling Limit Bug**: Fixed issue where only 35 posts were being scheduled when more posts were available
  - Removed artificial limit of `posts_per_day * 7` that was restricting post retrieval
  - Changed `numberposts` parameter from limited value to `-1` to retrieve all available posts
  - Now properly schedules all available posts regardless of quantity
  - Resolves issue where posts beyond the 35-post limit were being ignored

### Technical Changes
- Modified `schedule_posts()` function to remove the `$max_posts_to_schedule` limitation
- Updated `get_posts()` query to use `numberposts => -1` for unlimited post retrieval
- Enhanced scheduling logic to handle any number of posts across multiple days

## [1.8.6] - 02/10/2025

### Removed
- **Upcoming Scheduled Posts Widget**: Removed the "Upcoming Scheduled Posts" widget from the admin interface as it was not displaying any posts and was not functioning properly
- **Related Code Cleanup**: Removed `get_upcoming_scheduled_posts()` function, associated CSS styles, JavaScript code, and template rendering logic
- **Performance Improvement**: Eliminated unnecessary database queries and AJAX calls related to the removed widget

### Technical Changes
- Removed widget HTML template from `admin-page.php`
- Removed widget update JavaScript from `admin.js`
- Removed CSS styles for `.ksm-ps-upcoming-box`, `.ksm-ps-upcoming-list`, `.ksm-ps-upcoming-item`, and `.ksm-ps-no-posts`
- Removed `upcoming_posts` data from AJAX responses and template variables

## [1.8.5] - 02/10/2025

### Fixed
- **Complete Post Scheduling**: Fixed critical scheduling algorithm bug where the last few posts would be left unscheduled when the total number of posts wasn't evenly divisible by "Posts Per Day" setting
- **Accurate Post Count Calculation**: Corrected `posts_to_generate` calculation to use `min(posts_per_day, remaining_posts)` instead of always using `posts_per_day`
- **Progress Report Accuracy**: Updated progress report messages to reflect the actual number of posts being scheduled for each day

### Technical Improvements
- Modified scheduling loop to calculate remaining posts correctly for each day
- Enhanced `generate_random_times()` function integration to handle variable post counts per day
- Improved scheduling logic to ensure all posts get scheduled across available days

### Example Fix
- **Before**: 36 posts with 5 per day setting would schedule only 35 posts, leaving 1 unscheduled
- **After**: All 36 posts are correctly scheduled across 8 days (7 days with 5 posts, 1 day with 1 post)

## [1.8.4] - 02/10/2025

### Fixed
- **Post Numbering in Progress Report**: Fixed post numbering in scheduling progress report to display sequential numbers (1, 2, 3, 4, 5) in chronological order instead of random numbers based on processing order
- **Chronological Display**: Posts are now numbered correctly based on their scheduled time order, with the earliest scheduled post being #1, second earliest being #2, and so on

### Technical Improvements
- Modified post numbering logic to assign sequential numbers after sorting posts by timestamp
- Enhanced progress report generation to ensure consistent and logical post numbering

## [1.8.3] - 02/10/2025

### Improved
- **Post Status Clarity**: Updated custom post status label from "Scheduled for Publishing" to "Ready to Schedule" to avoid confusion with WordPress's built-in scheduled status
- **Admin Interface**: Added comprehensive explanation of different post statuses in the admin interface
- **User Experience**: Added informational notice explaining the difference between "Ready to Schedule", "Scheduled (WordPress)", and "Draft" statuses

### Fixed
- **Status Confusion**: Resolved confusion between the plugin's custom status and WordPress's native scheduled status
- **User Interface**: Improved clarity in status selection with detailed descriptions and explanations

## [1.8.2] - 02/10/2025

### Added
- **Comprehensive Error Handling**: Implemented robust error handling throughout the plugin with user-friendly messages
- **Input Validation**: Added extensive validation for plugin configuration settings including posts per day limits (1-50), time format validation, and active days validation
- **Database Error Handling**: Enhanced error handling for `wp_update_post` operations with detailed error logging and user feedback
- **AJAX Error Management**: Added try-catch blocks and comprehensive error handling for all AJAX functions with proper nonce verification
- **Cron Scheduling Validation**: Added error handling for `wp_schedule_single_event` failures with detailed logging

### Improved
- **User Feedback**: Enhanced return messages with internationalized strings and detailed progress reports
- **Error Logging**: Improved error logging with specific error codes and detailed context information
- **Post Validation**: Added validation for post existence, status verification, and author assignment before processing
- **Configuration Validation**: Enhanced validation for empty or invalid plugin configurations with clear error messages
- **Production Readiness**: Removed all debug statements and optimized for production use

### Fixed
- **Error Recovery**: Improved error recovery mechanisms to prevent plugin crashes and provide graceful degradation
- **Data Integrity**: Enhanced data validation to prevent invalid configurations and ensure consistent plugin behavior
- **Security**: Strengthened security with proper permission checks and nonce verification in all AJAX handlers

## [1.8.1] - 02/10/2025

### Added
- **Enhanced User Interface**: Added search functionality for excluded users selection to handle sites with many users
- **Bulk Selection Tools**: Added "Select All Visible" and "Deselect All" buttons for easier user management
- **Improved User Display**: Enhanced user information display with roles and better visual organization

### Improved
- **Contributor Role Support**: Added 'contributor' role to default allowed author roles for more comprehensive user coverage
- **Scalable UI**: Optimized excluded users interface with scrollable container (max 300px height) and search filtering
- **User Experience**: Better organization of user selection with individual user cards and role information
- **Performance**: Increased user query limit to 500 while maintaining reasonable performance

### Fixed
- **Version Consistency**: Updated KSM_PS_VERSION constant from outdated 1.4.4 to current 1.8.1
- **Role Coverage**: Ensured all standard WordPress content creation roles are included by default

### Technical Improvements
- Added real-time search filtering with jQuery for instant user filtering
- Implemented responsive user selection interface with better visual hierarchy
- Enhanced user role display to show which roles each user has within allowed roles
- Added user count display to show total available users

## [1.8.0] - 02/10/2025

### Added
- **Custom Post Status**: Introduced dedicated "Post Scheduler" post status instead of using WordPress default "draft" status for better organization and clarity
- **Individual User Exclusion**: Added ability to exclude specific individual users from author assignment, providing granular control over who can be assigned as authors
- **Enhanced User Interface**: New admin interface section for selecting individual users to exclude from author assignment with clear user identification (display name and username)

### Improved
- **Post Status Management**: Custom post status is automatically registered during plugin activation and properly cleaned up during deactivation
- **Author Assignment Logic**: Enhanced both random and round-robin author assignment to respect individual user exclusions in addition to role-based filtering
- **Admin Experience**: Improved admin interface with better organization of author assignment settings and clearer user selection options

### Technical Improvements
- Added `register_custom_post_status()` method to register the "ksm_scheduled" post status with proper labels and capabilities
- Enhanced `get_random_author()` and round-robin logic to filter out excluded users from the `excluded_users` setting
- Updated plugin activation to set default post status to custom "ksm_scheduled" status
- Added proper sanitization for `excluded_users` array in settings validation
- Implemented automatic conversion of custom status posts back to draft during plugin deactivation
- Updated admin page to display users with allowed roles for exclusion selection

### Fixed
- **Status Consistency**: Resolved potential conflicts with WordPress default post statuses by using dedicated custom status
- **User Management**: Improved handling of user exclusions to prevent assignment of unwanted authors

## [1.7.0] - 02/10/2025

### Added
- **Author Assignment System**: Comprehensive author assignment feature that allows random or round-robin assignment of post authors during scheduling
- **Role-Based Author Selection**: Configure which user roles (Author, Editor, Administrator, etc.) can be assigned as post authors
- **Assignment Strategies**: Choose between "Random" assignment or "Round Robin" rotation for consistent author distribution
- **Author Assignment Status**: Real-time display of author assignment status, strategy, and eligible author count in the scheduling overview
- **Progress Tracking**: Enhanced progress reports now show author changes during scheduling operations

### Improved
- **Unified Settings Interface**: Consolidated author assignment settings into a clean, intuitive admin interface
- **Enhanced Validation**: Robust validation ensures only users with appropriate capabilities can be assigned as authors
- **Better Error Handling**: Comprehensive error handling for edge cases like no eligible authors or invalid role configurations

### Technical Improvements
- Added `get_random_author()` helper method for intelligent author selection with exclusion logic
- Enhanced `sanitize_settings()` method to validate author roles and assignment strategies
- Improved scheduling algorithm to integrate author assignment seamlessly into the post scheduling workflow
- Added comprehensive logging for author assignment operations and debugging

### Removed
- **Legacy User Rotation**: Removed old user rotation system in favor of the new unified author assignment system

## [1.6.8] - 02/10/2025

### Fixed
- **Progress Report Chronological Ordering**: Fixed progress report to display scheduled posts in chronological order by publication time instead of processing order
- **Enhanced Report Readability**: Improved day-by-day breakdown to show posts sorted by their actual scheduled times within each day

### Technical Improvements
- Added `$scheduled_posts_details` array to collect post scheduling information with timestamps
- Implemented `usort()` function to sort posts chronologically before display
- Enhanced progress report generation to group posts by date and sort by time within each day

## [1.6.7] - 02/10/2025

### Fixed
- **Critical Scheduling Bug**: Fixed issue where posts intended for inactive days (Saturday/Sunday) were incorrectly accumulating on the next active day (Monday)
- **Day Progression Logic**: Improved day advancement logic to properly skip inactive days without adding extra posts to active days
- **Post Distribution**: Ensured posts are evenly distributed across active days only, preventing overload on specific days

### Technical Improvements
- Enhanced day-change logic in `schedule_posts()` function to find the next truly active day instead of just incrementing day offset
- Added safeguards to prevent infinite loops when searching for next active day
- Improved debug logging to track day advancement and active day detection
- Fixed scheduling algorithm to respect inactive day settings and maintain proper post distribution

## [1.6.6] - 02/10/2025

### Improved
- **UI Clarity**: Enhanced time field descriptions to be more user-friendly and accurate
- **Start Time Field**: Changed description to "Earliest time posts can be published each day. This defines when your posts will start going live."
- **End Time Field**: Changed description to "Latest time posts can be published each day. This defines when your posts will stop going live."
- **Terminology Fix**: Corrected confusing language that mixed "posting" and "scheduling" concepts

### Technical Improvements
- Updated admin page template with clearer field descriptions for better user experience
- Improved terminology consistency to distinguish between scheduling process and publication times

## [1.6.5] - 02/10/2025

### Fixed
- **Manual Scheduling Distribution**: Fixed manual scheduling to properly distribute posts across future dates instead of being restricted to the current day only
- **Daily Limits Enforcement**: Manual scheduling now respects daily limits and distributes posts across multiple days when necessary
- **Future Date Scheduling**: Removed artificial restriction that prevented manual scheduling from scheduling posts beyond the current day

### Technical Improvements
- Modified `schedule_posts()` function to allow manual scheduling to use the same distribution logic as automatic scheduling
- Updated progress messages to accurately reflect that manual scheduling now distributes posts across future dates
- Enhanced debug logging to properly indicate when manual scheduling distributes posts across multiple days

## [1.6.4] - 02/10/2025

### Changed
- **UI Terminology Update**: Changed "Manual Testing" to "Manual Scheduling" throughout the interface
- **Button Text Update**: Changed "Run Now" button to "Schedule Posts Now" for clearer functionality indication
- **Documentation Update**: Updated README.md to reflect manual scheduling functionality instead of testing terminology
- **Code Comments**: Updated all internal comments to use "manual scheduling" terminology for consistency

### Technical Improvements
- Updated admin page template with proper button styling (changed from secondary to primary button)
- Enhanced button description to clearly indicate immediate post scheduling functionality
- Improved code documentation consistency across PHP files

## [1.6.3] - 02/10/2025

### Added
- **Progress Reporting for Manual Scheduling**: Added detailed progress reports when using the "Run Now" button to show day-by-day post distribution
- **Visual Feedback Enhancement**: Improved admin interface to display scheduling progress with formatted output showing:
  - Total posts to schedule
  - Successfully scheduled posts count
  - Day-by-day breakdown with post counts
  - Clear indication when moving to next day due to daily limits
- **Enhanced User Experience**: Better visibility into how the plugin distributes posts across multiple days during manual scheduling

### Technical Improvements
- Added `formatProgressReport()` JavaScript function for better HTML formatting of progress messages
- Enhanced CSS styling for progress report display with proper typography and color coding
- Improved AJAX response handling to display formatted HTML instead of plain text

## [1.6.2] - 02/10/2025

### Fixed
- **Root Cause Fix for Past Timestamps**: Fixed the core issue where `generate_random_times()` was using the original start time instead of the adjusted start time for current day scheduling, which was causing past-dated articles
- **Improved Time Calculation Logic**: Enhanced timestamp calculation to properly handle current day scheduling with a 5-minute buffer to guarantee future scheduling
- **Removed WordPress Hook Workaround**: Eliminated the temporary `transition_post_status` hook disabling since the root cause has been resolved
- **Better Edge Case Handling**: Improved logic for handling time calculations that would go past midnight or when insufficient time remains for scheduling

### Technical Improvements
- Replaced direct database updates with proper WordPress `wp_update_post()` function calls
- Enhanced debugging messages to clearly differentiate between manual and automatic scheduling modes
- Streamlined code by removing unnecessary safety checks that were compensating for the timestamp bug

## [1.6.1] - 01/10/2025

### Critical Fix
- **IMMEDIATE PUBLISHING BUG**: Fixed critical issue where draft posts were being published immediately instead of being scheduled for future dates
- **ROBUST SCHEDULING**: Replaced problematic `wp_update_post()` calls with direct database updates to bypass WordPress hook interference
- **ENHANCED RELIABILITY**: Implemented `wp_schedule_single_event()` for actual publication scheduling, ensuring posts are properly scheduled without conflicts
- **IMPROVED DEBUGGING**: Added comprehensive logging for scheduling operations to track post status changes and identify potential issues
- **HOOK MANAGEMENT**: Added proper cleanup for dynamic publication hooks during plugin deactivation

### Technical Details
- Direct database updates for setting post status to 'future' to avoid WordPress core hook conflicts
- Dynamic hook registration system for scheduled post publication
- Enhanced error logging and debugging capabilities for troubleshooting scheduling issues

## [1.6.0] - 02/10/2025

### Major UI Overhaul
- **UNIFIED INTERFACE**: Completely redesigned admin interface by merging "Current Status" and "Scheduling Preview" into single "Scheduling Overview" box
- **ELIMINATED DUPLICATES**: Removed all duplicate information display (e.g., "Posts in Monitored Status" vs "Posts waiting to be scheduled")
- **IMPROVED ORGANIZATION**: Restructured content into three logical sections:
  - Status & Timing: Scheduler status, last/next cron runs
  - Queue & Configuration: Posts waiting, daily limits, time windows, active days
  - Schedule Preview: 5-day scheduling preview with daily breakdowns

### Enhanced
- **VISUAL HIERARCHY**: Added subtle visual separators between sections for better readability
- **CSS IMPROVEMENTS**: New `.ksm-ps-overview-box` styling with proper spacing and organization
- **AJAX FUNCTIONALITY**: Updated refresh mechanism to update entire unified overview section
- **DATA CONSISTENCY**: Enhanced AJAX handler to return comprehensive scheduling data including options, preview data, and cron timing

### Technical Improvements
- Updated `ajax_get_status()` to return complete dataset for unified interface
- Enhanced JavaScript `updateStatusDisplay()` function for comprehensive UI updates
- Improved CSS with new overview box styles and visual separators
- Maintained backward compatibility while streamlining user experience

## [1.5.0] - 02/10/2025

### Changed
- **UI CONSOLIDATION**: Consolidated redundant scheduling sections in admin interface
- Removed duplicate "Scheduling Configuration" section that displayed identical information
- Moved comprehensive "Scheduling Preview" section to appear directly after "Current Status"
- **IMPROVED LAYOUT**: Reorganized admin interface for better information hierarchy and user experience
- Scheduling preview now appears before "Upcoming Scheduled Posts" for logical flow

### Enhanced
- **USER EXPERIENCE**: Eliminated confusion from duplicate scheduling information display
- Streamlined admin interface with single, comprehensive scheduling overview
- Better visual organization of plugin status and scheduling information

## [1.4.9] - 02/10/2025

### Fixed
- **ADMIN INTERFACE ERRORS**: Fixed "Array to string conversion" PHP warnings in admin template
- Fixed daily preview display to properly handle array data structure instead of attempting string conversion
- **DUPLICATE SECTIONS**: Removed duplicate "Scheduling Preview" functionality from "Scheduling Configuration" section
- Consolidated scheduling preview into dedicated section with comprehensive data display
- **TEMPLATE OPTIMIZATION**: Streamlined admin template to eliminate redundant code and improve maintainability

### Enhanced
- **SCHEDULING PREVIEW**: Improved scheduling preview display with proper array handling
- Enhanced daily preview to show posts count and time windows for each day
- Better separation of concerns between configuration display and preview functionality

## [1.4.8] - 02/10/2025

### Fixed
- **CRITICAL SCHEDULING BUG**: Fixed issue where recently added articles were being published immediately instead of being scheduled
- Modified `schedule_posts()` function to properly respect "Posts Per Day" limits during manual testing
- Fixed manual testing button exceeding daily post limits by implementing proper day-based scheduling validation
- **MANUAL TESTING LIMITS**: Enforced "Posts Per Day" limit for manual testing to prevent over-scheduling on the current day
- Added validation to ensure manual testing only schedules posts for the current day and respects daily limits
- **SCHEDULING LOGIC**: Enhanced scheduling logic to differentiate between cron runs (7 days worth) and manual testing (current day only)

### Added
- **LAST CRON RUN DISPLAY**: Added "Last Cron Run" information to complement existing "Next Cron Run" display
- Implemented tracking of last cron execution time in plugin options
- **ENHANCED STATUS DISPLAY**: Significantly improved "Current Status" section to show detailed scheduling configuration
- Added scheduling preview showing posts per day, time window, active days, and minimum interval
- Added today's scheduling status showing posts already scheduled and remaining capacity
- **COMPREHENSIVE SCHEDULING INFO**: Added detailed configuration display including:
  - Posts per day setting
  - Active scheduling days
  - Time window (start to end time)
  - Minimum interval between posts
  - Current day's scheduling status

### Enhanced
- **ADMIN INTERFACE**: Improved admin page layout with better organization of scheduling information
- Added CSS styles for new scheduling configuration display and status information
- Enhanced visual presentation of scheduling preview and today's status
- **SCHEDULING VALIDATION**: Improved scheduling logic to prevent articles from being published instead of scheduled
- Added proper parameter handling to distinguish between cron runs and manual testing

### Technical
- Updated `random_post_scheduler_daily_cron()` to record last cron run time
- Modified `schedule_posts()` function to accept `$is_cron_run` parameter for proper limit handling
- Enhanced `ajax_run_now()` to pass correct parameters for manual testing validation
- Added new CSS classes for scheduling info display and configuration grid
- Updated admin page template with comprehensive status and configuration sections

## [1.4.7] - 01/10/2025

### Fixed
- **VALIDATION LOGIC**: Fixed overly strict time validation that prevented scheduling when available time was exactly sufficient
- Changed validation condition from `>=` to `>` in both PHP and JavaScript to allow exact time matches
- Resolved "Not enough time" error that occurred even when time was precisely adequate for scheduled posts

### Enhanced
- **ERROR MESSAGES**: Significantly improved error messages with detailed calculations and actionable suggestions
- Added specific time breakdowns showing available vs required time in both hours/minutes and total minutes
- Included smart suggestions to reduce posts per day, extend end time, or reduce minimum interval
- **REAL-TIME CALCULATOR**: Added dynamic time analysis display that updates as users modify settings
- Shows available time window, required time for posts, and current status (sufficient/insufficient)
- Provides instant feedback without requiring form submission
- **SMART SUGGESTIONS**: Implemented intelligent recommendation system with clickable buttons
- Auto-calculates optimal settings: fewer posts per day, extended end times, or reduced intervals
- One-click application of suggestions with visual feedback and automatic recalculation

### Improved
- **USER EXPERIENCE**: Enhanced admin interface with better guidance and real-time feedback
- Added comprehensive time calculator showing detailed breakdowns of scheduling requirements
- Implemented responsive design for suggestion buttons and calculator display
- Added smooth transitions and hover effects for better interaction feedback
- **VALIDATION FEEDBACK**: Replaced generic error messages with specific, actionable guidance
- Real-time validation updates as users type or change settings
- Clear visual indicators for valid vs invalid configurations

### Technical
- Updated validation logic in `ksm-post-scheduler.php` line 444 and `admin.js` line 244
- Added `updateTimeCalculator()` and `generateSmartSuggestions()` functions to admin.js
- Implemented `applySuggestion()` method for one-click setting adjustments
- Enhanced CSS with new styles for calculator display and suggestion buttons
- Added proper event handling for dynamic suggestion interactions

## [1.4.6] - 01/10/2025

### Fixed
- **CONFIGURATION BUG**: Fixed issue where default start and end times were being forced on every plugin update/activation
- Modified activation logic to only set default times if they are empty or invalid, preserving user-configured times
- **POSTS PER DAY LIMIT**: Fixed critical bug where posts per day limit was not respected when certain days were unchecked
- Corrected `$current_day_offset` increment logic to properly advance to next valid day when daily limit is reached
- Resolved issue where all posts would be scheduled on Monday when Saturday/Sunday were unchecked
- **TIMESTAMP CALCULATION**: Fixed timestamp conversion bug using proper WordPress timezone handling with `DateTime::createFromFormat`

### Improved
- Enhanced day distribution logic to work correctly with any combination of checked/unchecked days
- Added comprehensive error handling for datetime parsing failures
- Verified scheduling works properly for weekdays only, weekends only, specific days, and single-day configurations
- Improved timezone handling to prevent "Calculated time is in the past" errors

### Technical
- Updated `activate()` method to preserve existing start/end time configurations
- Fixed `$current_day_offset` increment in scheduling loop to ensure proper day progression
- Replaced `strtotime()` with `DateTime::createFromFormat()` for more reliable timestamp conversion
- Added proper WordPress timezone support using `wp_timezone()` instead of string concatenation

## [1.4.5] - 01/10/2025

### Fixed
- **SCHEDULING BUG**: Fixed critical bug in time slot generation where `generate_random_times()` was called with total `posts_per_day` instead of actual `posts_to_generate` for today's scheduling
- Corrected calculation to use the actual number of posts that can fit within remaining time for current day
- Resolved issue where scheduler would generate 0 time slots despite having sufficient time available

## [1.4.4] - 01/10/2025

### Removed
- **BUFFER FUNCTIONALITY**: Removed all 30-minute buffer functionality from scheduling logic
- Eliminated `$buffer_minutes` variable and related calculations
- Removed buffer considerations from `can_schedule_today` logic
- Simplified effective start time calculations by removing buffer from current time

### Improved
- **SIMPLIFIED SCHEDULING**: Streamlined scheduling logic to rely solely on "Minimum Interval Between Posts" setting
- Enhanced performance by removing redundant buffer calculations
- Cleaner code with fewer variables and simpler time calculations
- More predictable scheduling behavior without buffer interference

### Technical
- Updated `$latest_scheduling_time` to use `$end_minutes` directly instead of `$end_minutes - $buffer_minutes`
- Modified `$effective_start_time` calculation to use `max($current_time_minutes, $start_minutes)` instead of adding buffer
- Simplified today's start time adjustment to use current time directly without buffer addition

## [1.4.3] - 01/10/2025

### Fixed
- **CRITICAL**: Fixed day offset initialization bug that was causing all posts to be scheduled for the same day again
- Corrected initialization logic where `$current_day_offset` was incorrectly set to 1 when `can_schedule_today` was false
- Fixed double-offset calculation that was causing posts to start from 2 days ahead instead of tomorrow
- Ensured `$current_day_offset` always starts at 0 and lets `get_next_valid_day()` handle the proper offset calculation

### Improved
- Enhanced debug logging to show proper day offset calculations
- Verified day distribution works correctly with comprehensive testing
- Maintained backward compatibility with existing scheduling logic

## [1.4.2] - 01/10/2025

### Fixed
- **CRITICAL**: Fixed day distribution bug where all posts were being scheduled for the same date despite "Posts Per Day" limit
- Corrected day offset calculation in `get_next_valid_day()` function
- Fixed logic where `$base_offset` calculation was redundant and incorrect
- Ensured proper day progression when posts per day limit is reached

### Improved
- Enhanced day offset logic to properly handle both "can schedule today" and future scheduling scenarios
- Added comprehensive test validation for day distribution functionality
- Improved debug logging for day offset calculations

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.1] - 01/10/2025
### CRITICAL START TIME LOGIC FIX
- **FIXED**: Critical issue where start time was not properly considered when determining if posts can be scheduled today
- **IMPROVED**: Scheduling logic now correctly evaluates both start time AND end time when checking feasibility for today
- **ENHANCED**: Consistent 30-minute buffer handling between feasibility check and time generation
- **FIXED**: Effective start time calculation now properly accounts for current time + buffer vs configured start time
- **IMPROVED**: Better debug logging to show effective start time, latest scheduling time, and remaining time calculations
- **VALIDATED**: Logic now correctly handles scenarios: before start time, between start/end times, and after end time

## [1.4.0] - 01/10/2025
### MAJOR SCHEDULING LOGIC OVERHAUL
- **FIXED**: Critical issue where newly added drafts were being published immediately when manual button was clicked
- **FIXED**: Scheduling logic now properly spreads posts across multiple days instead of scheduling everything for one day
- **FIXED**: Maximum posts per day limit now properly enforced (respects the configured limit setting)
- **IMPROVED**: Smart "today vs tomorrow" logic - posts can be scheduled for today only if there's sufficient time remaining within scheduling hours
- **ENHANCED**: Better time calculation using WordPress timezone functions with proper buffer handling
- **IMPROVED**: More accurate post distribution across active days with proper day offset calculations
- **FIXED**: Posts are now correctly limited per batch (posts_per_day * 7) to prevent overwhelming the system
- **ENHANCED**: Comprehensive debug logging for better troubleshooting of scheduling issues
- **IMPROVED**: More robust error handling and validation throughout the scheduling process

## [1.3.1] - 01/10/2025
### FIXED
- **FIXED**: Persistent 24-hour format values in database options
- **ENHANCED**: Plugin activation now forces time values to 12-hour format defaults
- **IMPROVED**: Removed unnecessary 24-hour to 12-hour conversion functions
- **CLEANED**: Simplified codebase with pure 12-hour format implementation

## [1.3.0] - 01/10/2025
### MAJOR UPDATE: 12-HOUR FORMAT IMPLEMENTATION
- **CHANGED**: Admin interface now uses 12-hour format (AM/PM) for start and end times
- **REMOVED**: All 12-hour to 24-hour conversion logic throughout the plugin
- **IMPROVED**: Direct 12-hour format handling using WordPress's built-in timezone functions
- **ENHANCED**: JavaScript validation updated to work with 12-hour format input
- **FIXED**: Scheduling logic now uses WordPress's `strtotime()` and `get_gmt_from_date()` functions
- **IMPROVED**: Better integration with WordPress's internal timezone handling
- **UPDATED**: README documentation to reflect 12-hour format usage
- **ENHANCED**: More reliable time parsing using WordPress core functions

## [1.2.0] - 01/10/2025
### CRITICAL TIMEZONE FIX
- **FIXED**: Posts being published immediately instead of staying in 'future' status
- **FIXED**: Timezone handling inconsistencies causing timestamp comparison errors
- **FIXED**: Mixed use of server time vs WordPress time in scheduling calculations
- **IMPROVED**: Consistent use of `current_time('timestamp')` throughout scheduling logic
- **IMPROVED**: Proper WordPress timezone handling with `wp_timezone()` and `wp_date()`
- **ENHANCED**: Increased safety buffer from 5 to 10 minutes for future scheduling
- **ENHANCED**: Better debugging with timezone and timestamp tracking
- **FIXED**: Day calculation using WordPress timezone-aware functions

## [1.1.9] - 01/10/2025
### CRITICAL FIX
- **FIXED**: Drafts being published immediately due to timezone calculation errors
- **FIXED**: Replaced `strtotime()` with WordPress timezone-aware `DateTime` objects
- **IMPROVED**: Added 5-minute buffer to ensure all scheduled posts are in the future
- **ENHANCED**: Better error handling for DateTime creation failures
- **IMPROVED**: More detailed debug logging for timestamp calculations

## [1.1.8] - 01/10/2025

### Fixed
- **CRITICAL**: Fixed issue where recent drafts were being published immediately instead of scheduled for future dates
- **CRITICAL**: Fixed scheduling logic to properly distribute posts across different future dates instead of using the same date
- Improved timestamp calculation to prevent scheduling posts in the past
- Enhanced date distribution algorithm to respect active days configuration
- Added comprehensive safety checks to prevent past-date scheduling
- Fixed timezone handling in scheduling calculations

### Added
- New `get_next_valid_day()` method to properly calculate valid scheduling days
- Enhanced debug logging for better troubleshooting
- Pre-generation of daily schedules for improved distribution
- Better error handling for edge cases in scheduling

### Changed
- Refactored `schedule_posts()` method for more reliable date distribution
- Improved scheduling algorithm to handle day transitions more accurately
- Enhanced validation to ensure posts are always scheduled in the future[1.1.7] - 01/10/2025

### Fixed
- **Critical Bug Fix**: Resolved fatal error "Cannot redeclare KSM_PS_Main::time_to_minutes()"
  - Removed duplicate function declaration that was causing plugin crashes
  - Maintained the newer, more robust implementation of the time conversion function
- **Manual Testing Bug Fix**: Fixed issue where drafts were being published instead of scheduled during manual testing
  - Corrected timestamp recalculation logic when posts are moved to the next day
  - Ensured posts are properly scheduled as 'future' status instead of being immediately published
  - Added proper timestamp recalculation after date adjustments

### Updated
- **SweetAlert2 Library**: Updated to version 11.23.0 for latest features and security improvements

## [1.1.6] - 01/10/2025

### Added
- **SweetAlert2 Integration**: Replaced browser alerts with modern SweetAlert2 notifications for better user experience
  - Added SweetAlert2 library from CDN for beautiful, customizable notifications
  - Implemented proper error, success, and warning message displays
  - Enhanced visual feedback for validation errors and form submissions

### Enhanced
- **Improved Validation System**: Comprehensive client-side and server-side validation
  - Real-time validation for time inputs, posts per day, and minimum interval settings
  - Proper form validation that prevents submission when errors are present
  - Enhanced time window validation to ensure sufficient time for scheduled posts with minimum intervals
  - Better error messaging with specific validation rules and constraints

### Fixed
- **Error Handling**: Replaced intrusive browser prompts with user-friendly notifications
  - Fixed issue where users could save invalid settings despite validation errors
  - Improved server-side validation in `sanitize_settings()` function
  - Added proper error display using WordPress settings errors API
  - Enhanced validation for time format conversion and range checking

### Technical
- Added comprehensive validation helper functions in admin.js
- Implemented `addValidationError()`, `removeValidationError()`, and `showValidationError()` methods
- Enhanced server-side validation with proper error collection and display
- Added `time_to_minutes()` helper function for time calculations
- Improved form submission handling with validation state tracking

## [1.1.5] - 01/10/2025

### Fixed
- **Posts Per Day Distribution**: Fixed critical issue where all posts were being scheduled for the same day instead of being distributed across multiple days
  - Modified `schedule_posts()` function to properly distribute posts across days based on the "Posts Per Day" setting
  - Posts now correctly move to the next day when the daily limit is reached
  - Added intelligent day calculation that respects the posts per day limit

### Added
- **Dynamic Schedule Re-adjustment**: Added automatic re-adjustment of existing scheduled posts when "Posts Per Day" setting changes
  - New `readjust_scheduled_posts()` function that resets and reschedules existing future posts
  - New `handle_posts_per_day_change()` function that detects setting changes and triggers re-adjustment
  - Enhanced user feedback with success/error messages when re-adjustment occurs

### Enhanced
- **Improved Scheduling Logic**: Enhanced the scheduling algorithm to handle multi-day distribution
  - Added day counter and posts-per-day tracking within the scheduling loop
  - Better handling of time conflicts when moving between days
  - More comprehensive debug logging for multi-day scheduling

### Technical
- Modified `schedule_posts()` to retrieve all available posts instead of limiting by posts_per_day
- Added day tracking variables (`$current_day`, `$posts_scheduled_today`) for proper distribution
- Enhanced `sanitize_settings()` to detect posts_per_day changes and trigger re-adjustment
- Added WordPress action hook integration for seamless settings change handling

## [1.1.4] - 01/10/2025

### Fixed
- **Critical Scheduling Bug**: Fixed issue where posts were being published immediately instead of being scheduled for future publication
  - Improved timezone handling and timestamp calculations to ensure proper future scheduling
  - Enhanced time comparison logic using proper timestamp comparison instead of string comparison
  - Added multiple safety checks to prevent scheduling posts in the past
  - Added comprehensive debugging logs to track scheduling process and identify issues
  - Fixed wp_update_post() error handling to properly detect and report scheduling failures
  - Added post-update verification to ensure posts are correctly set to 'future' status

### Enhanced
- **Debugging System**: Added extensive debug logging throughout the scheduling process
  - Logs current WordPress time, server time, and timestamps for accurate troubleshooting
  - Tracks each post's scheduling process with detailed status updates
  - Verifies post status after updates to ensure proper scheduling
  - Added safety checks to prevent accidental immediate publication

### Technical
- Modified schedule_posts() function with improved timestamp-based time calculations
- Enhanced error handling in wp_update_post() calls with proper error detection
- Added multiple validation layers to ensure scheduled times are always in the future
- Improved debugging output for better maintenance and troubleshooting

## [1.1.3] - 01/10/2025

### Fixed
- **Manual Testing Button**: Fixed critical bug where "Manual Testing" button was immediately publishing posts instead of scheduling them
  - Changed scheduling logic to use tomorrow's date instead of today's date
  - Prevents WordPress from immediately publishing posts when scheduled time has already passed today
  - Ensures all posts are properly scheduled for future publication, not immediately published
  - Resolves issue where fresh draft posts were published instantly while old published posts (returned to draft) were correctly scheduled

### Technical
- Modified schedule_posts() function to use `strtotime('+1 day')` for scheduling date calculation
- Added explanatory comment for future maintenance clarity

## [1.1.2] - 01/10/2025

### Added
- **12-Hour Time Format Support**: Enhanced time input fields to use user-friendly 12-hour format
  - Start and End time inputs now accept 12-hour format (e.g., "9:00 AM", "6:30 PM")
  - Automatic conversion between 12-hour display format and 24-hour storage format
  - Client-side validation and formatting with real-time feedback
  - Backward compatibility with existing 24-hour format settings
  - Enhanced user experience with intuitive time entry

### Enhanced
- **Time Input Validation**: Improved time validation with better error messages and format guidance
- **JavaScript Functions**: Added comprehensive time conversion utilities for seamless format handling
- **Admin Interface**: Updated time input fields with placeholders and pattern validation

### Technical
- Added convert_24_to_12() and convert_12_to_24() PHP methods for server-side time conversion
- Enhanced sanitize_settings() to handle both display and storage time formats
- Added JavaScript time conversion functions with robust format parsing
- Maintained 24-hour format for internal storage and cron scheduling

## [1.1.1] - 01/10/2025

### Fixed
- **Duplicate Settings Message**: Fixed issue where "Settings saved." message was appearing twice on the admin page
  - Modified admin-page.php to only display settings_errors() when there are actual validation errors
  - WordPress now handles success messages automatically, preventing duplication
  - Improved user experience with cleaner settings feedback

## [1.1.0] - 01/10/2025

### Added
- **Comprehensive Uninstall System**: Added proper uninstall.php file for complete plugin cleanup
- **Enhanced Activation Process**: Added WordPress and PHP version compatibility checks
- **Version Upgrade Handling**: Automatic detection and handling of plugin updates
- **Admin Notices**: Success notifications for activation and update processes
- **Better Data Management**: Improved option handling with version tracking and installation date
- **Multisite Support**: Full compatibility with WordPress multisite installations

### Enhanced
- **Activation Hook**: Now includes version checking, compatibility validation, and better error handling
- **Deactivation Hook**: Enhanced cleanup process with transient removal and cache flushing
- **Security**: Added proper capability checks and validation throughout install/uninstall process

### Technical
- Added `register_uninstall_hook()` for proper WordPress uninstall handling
- Enhanced database cleanup with comprehensive option and meta data removal
- Added transient management for better performance and cleanup
- Improved error logging and debugging capabilities
- Added flush_rewrite_rules() for better WordPress integration

## [1.0.0] - 01/10/2025

### Added
- Initial release of KSM Post Scheduler plugin
- WordPress plugin with proper header and metadata
- Admin settings page with comprehensive configuration options:
  - Post Status to Monitor (select dropdown)
  - Posts Per Day (number input, default: 5)
  - Start Time (time input, default: 9:00 AM)
  - End Time (time input, default: 6:00 PM)
  - Days Active (checkboxes for each day of week)
  - Minimum Interval Between Posts (number in minutes, default: 30)
  - Enable/Disable toggle with modern switch design
- WordPress cron job functionality:
  - Daily cron job that runs at midnight
  - Function name: random_post_scheduler_daily_cron
  - Automatic scheduling of posts with random publish times
  - Respect for minimum interval between posts
  - Only schedules specified number of posts per day
- Manual "Run Now" button for testing purposes
- Activation/deactivation hooks for proper cron job management
- Security features:
  - Nonces for CSRF protection
  - Input sanitization and validation
  - Proper capability checks (manage_options)
- Status dashboard showing:
  - Current count of posts in monitored status
  - List of next 10 upcoming scheduled posts with titles and times
  - Scheduler enable/disable status
  - Next cron run time
- Modern admin interface with:
  - Responsive design
  - Professional styling
  - AJAX functionality for real-time updates
  - Form validation and user feedback
- Complete file structure:
  - Main plugin file (ksm-post-scheduler.php)
  - Admin page template (templates/admin-page.php)
  - CSS styling (assets/admin.css)
  - JavaScript functionality (assets/admin.js)
  - Documentation (CHANGELOG.md)

### Technical Details
- Requires WordPress 5.0 or higher
- Requires PHP 7.4 or higher
- Uses KSM_PS namespace for all functions and classes
- Follows WordPress coding standards and best practices
- Implements proper error handling and user feedback
- Uses WordPress hooks and filters appropriately
- Includes comprehensive inline documentation

### Security
- All user inputs are properly sanitized
- CSRF protection via WordPress nonces
- Capability checks for admin access
- No direct file access allowed
- Secure AJAX implementations