=== Schedulely ===
Contributors: kraftysprouts
Tags: schedule, posts, automation, publishing, cron
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Intelligently schedule posts with smart deficit tracking, random timing, and automatic author assignment.

== Description ==

Schedulely helps you automatically schedule hundreds of posts across multiple days with intelligent features:

* **Last Date Completion** - Automatically completes the last scheduled date if it didn't meet quota
* **Smart Continuation** - Resumes scheduling from where it left off
* **Random Time Distribution** - Creates natural posting patterns within your defined time windows
* **Author Randomization** - Assign random authors to posts with exclusion options
* **Flexible Scheduling** - Custom time windows, daily limits, and active days
* **Minimum Intervals** - Ensure posts don't publish too close together
* **WordPress Native** - Uses WordPress's built-in cron and timezone settings
* **Beautiful Dashboard** - Clean, modern admin interface with real-time statistics
* **Email Notifications** - Get notified when scheduling runs complete

= Key Features =

**Last Date Completion**
If the last scheduled date doesn't meet its post quota, Schedulely automatically completes it before scheduling to new dates. This ensures consistent publishing and no gaps in your schedule.

**Random Time Windows**
Set a time window (e.g., 5:00 PM - 11:00 PM) and Schedulely will randomly distribute posts within that range, respecting minimum intervals for natural posting patterns.

**Author Management**
Enable random author assignment and optionally exclude specific users. Perfect for multi-author sites wanting varied attribution.

**Flexible Control**
- Choose which post status to monitor (draft, pending, private)
- Set posts per day quota
- Define active days of the week
- Configure minimum intervals between posts
- Manual or automatic scheduling via WordPress cron

**Professional Dashboard**
View available posts, next scheduled publication, active deficits, and last run statistics at a glance.

= Perfect For =

* Content marketers managing large content calendars
* Multi-author blogs wanting automated scheduling
* Sites with consistent publishing schedules
* Anyone tired of manually scheduling posts

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/schedulely` or install through WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Tools → Schedulely** to configure settings
4. Click "Schedule Now" to run manual scheduling or enable automatic scheduling via WordPress cron

= Updating the Plugin =

**Your settings are always preserved during updates!** The plugin uses WordPress best practices to ensure your configuration is never lost when you update to a new version. You can update with confidence.

== Frequently Asked Questions ==

= How does last date completion work? =

Schedulely checks the last/furthest scheduled date. If that date has fewer posts than your daily quota (e.g., 5 posts when quota is 8), it automatically completes that date first (adds 3 more posts) before scheduling to new future dates. This ensures consistent publishing without gaps.

= Can I exclude certain authors from randomization? =

Yes! In the Author Assignment section, you can select specific users to exclude from random assignment. These users will never be assigned to scheduled posts.

= Does this work with custom post types? =

Currently, Schedulely only works with standard WordPress posts. Custom post type support is planned for a future release.

= What happens if I don't have enough posts? =

Schedulely schedules as many posts as possible from your available pool and tracks any remaining deficits for the next run.

= How do time windows work? =

You set a start and end time (e.g., 5:00 PM - 11:00 PM). Schedulely generates random times within this window for each post, ensuring minimum intervals are respected.

= Can I schedule posts immediately? =

Yes! Use the "Schedule Now" button on the settings page to trigger immediate scheduling. Alternatively, enable automatic scheduling to let WordPress cron handle it hourly.

= Will this work with my timezone? =

Yes! Schedulely uses WordPress's timezone settings from Settings → General, so all times are in your site's configured timezone.

= How do I disable automatic scheduling? =

Uncheck "Enable Automatic Scheduling" in the Automation Settings section. You can still use the manual "Schedule Now" button.

== Planned Features ==

Future enhancements under consideration:

= Sequential Scheduling Mode =
* Perfect even spacing between posts (100% efficiency)
* Posts scheduled at exact intervals (e.g., 5:00, 5:45, 6:30, 7:15...)
* Predictable, uniform distribution
* Ideal for users who prefer structured, evenly-spaced publishing
* Trade-off: Looks more "robotic" vs natural random placement

= Hybrid Scheduling Approach =
* Best of both worlds: random appearance with better distribution
* Divides time window into equal slots (e.g., 8 slots for 8 posts)
* Randomizes post time within each slot
* Example: Slot 1 (5:00-5:45) → random time like 5:17 PM
* Result: More even distribution while maintaining natural/random appearance
* Prevents large gaps and unused time at end of window

= Redistribute Scheduled Posts =
* One-click rebalancing of already-scheduled posts
* Takes existing scheduled posts and redistributes them evenly
* Useful when capacity settings change or uneven distribution occurs
* Preview before applying changes
* Safety checks to prevent disruption

Note: Current version uses natural random scheduling (70% efficiency) for organic appearance. These features would provide alternatives for different use cases.

== Screenshots ==

1. Main settings dashboard with statistics and overview
2. Scheduling settings configuration
3. Author assignment options
4. Upcoming scheduled posts view
5. Deficit status tracking

== Changelog ==

= 1.0.4 - 07/10/2025 =
* Added: SweetAlert2 for beautiful modal dialogs (replaces browser confirm)
* Added: Loading states with animated spinners
* Added: Better success/error notifications
* Changed: Complete UI refresh with clean WordPress native styling
* Changed: Simplified CSS with WordPress admin colors
* Improved: Better responsive design and mobile experience
* Improved: Form validation with SweetAlert2 alerts

= 1.0.3 - 06/10/2025 =
* Fixed: Contributors not appearing in author randomization list
* Changed: Capability check from 'publish_posts' to 'edit_posts'
* Now includes Contributors, Authors, Editors, and Administrators in author selection

= 1.0.2 - 06/10/2025 =
* Fixed: CRITICAL - Removed flawed deficit logic that tried to schedule to past dates (WordPress rejects this)
* Changed: Complete refactor to "last date completion" logic - simpler and more reliable
* Changed: Checks only the LAST scheduled date and completes it if needed (and not in past)
* Removed: Deficit tracker class and database storage (no longer needed)
* Updated: Admin UI now shows "Last Scheduled Date" status instead of deficits
* Updated: Email notifications show last date completion status

= 1.0.1 - 06/10/2025 =
* Fixed: Admin menu not appearing in WordPress dashboard
* Fixed: Changed initialization hook from admin_menu to plugins_loaded
* Plugin settings now correctly accessible at Tools → Schedulely

= 1.0.0 - 06/10/2025 =
* Initial release
* Smart deficit tracking and auto-completion
* Random author assignment with exclusions
* Customizable time windows and intervals
* WordPress cron integration
* Email notifications
* Beautiful admin dashboard
* Manual and automatic scheduling

== Upgrade Notice ==

= 1.0.2 =
CRITICAL UPDATE: Fixes fundamental flaw in deficit logic. Old logic tried to schedule to past dates (WordPress rejects this). New "last date completion" logic is simpler and works correctly. ALL USERS MUST UPDATE.

= 1.0.1 =
Critical fix: Resolves admin menu not appearing. All users should update immediately.

= 1.0.0 =
Initial release of Schedulely. Install and configure to start intelligent post scheduling!

== Privacy Policy ==

Schedulely does not collect, store, or transmit any personal data. All scheduling data is stored locally in your WordPress database.

== Support ==

For support, feature requests, or bug reports, please visit:
* Website: https://kraftysprouts.com/contact
* Email: support@kraftysprouts.com

== Credits ==

Developed by Krafty Sprouts Media, LLC
https://kraftysprouts.com

Third-party libraries:
* Select2 - MIT License
* Flatpickr - MIT License

