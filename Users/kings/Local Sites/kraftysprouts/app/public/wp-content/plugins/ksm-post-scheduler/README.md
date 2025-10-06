# KSM Post Scheduler

A WordPress plugin that automatically schedules posts from a specific status to publish at random times throughout the day.

## Description

KSM Post Scheduler helps you maintain a consistent posting schedule by automatically scheduling your draft posts (or posts with any custom status) to publish at random times within your specified time range. This creates a more natural posting pattern and helps maintain audience engagement.

## Features

- **Smart Auto-Completion**: Automatically fills missed daily quotas by prioritizing older deficits
- **Deficit Tracking**: Monitors incomplete days and tracks post shortfalls
- **Flexible Post Status Monitoring**: Choose which post status to monitor for scheduling
- **Customizable Schedule**: Set daily post limits, time ranges, and active days
- **Random Timing**: Posts are scheduled at random times to create natural posting patterns
- **Minimum Intervals**: Ensure posts don't publish too close together
- **Manual Scheduling**: Run the scheduler manually to schedule posts immediately
- **Status Dashboard**: View current statistics, upcoming scheduled posts, and deficit status
- **WordPress Cron Integration**: Uses WordPress's built-in cron system
- **Security First**: Includes proper nonces, sanitization, and capability checks

## Installation

1. Upload the `ksm-post-scheduler` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Post Scheduler to configure the plugin

## Configuration

### Settings Page

Navigate to **Settings > Post Scheduler** in your WordPress admin to configure:

#### Basic Settings
- **Enable Scheduler**: Toggle to enable/disable the automatic scheduler
- **Post Status to Monitor**: Select which post status to monitor (Draft, Pending, etc.)
- **Posts Per Day**: Maximum number of posts to schedule per day (1-50)

#### Timing Settings
- **Start Time**: Earliest time to schedule posts (12-hour format, e.g., 9:00 AM)
- **End Time**: Latest time to schedule posts (12-hour format, e.g., 6:00 PM)
- **Minimum Interval**: Minimum time between posts in minutes (5-1440)

#### Schedule Settings
- **Days Active**: Select which days of the week the scheduler should run

### Status Dashboard

The settings page includes a status dashboard showing:
- Current scheduler status (enabled/disabled)
- Number of posts in the monitored status
- Next cron job run time
- List of upcoming scheduled posts (next 10)

## How It Works

1. **Daily Cron Job**: The plugin runs a cron job daily at midnight
2. **Status Check**: It checks if the scheduler is enabled and if today is an active day
3. **Post Query**: Retrieves posts with the specified status
4. **Random Scheduling**: Generates random publish times within your specified range
5. **Interval Respect**: Ensures posts are scheduled with the minimum interval between them
6. **Status Update**: Changes post status to 'future' with the calculated publish time

## Auto-Completion System

The plugin includes an intelligent auto-completion system that ensures your daily posting quotas are always met:

### How It Works
- **Deficit Detection**: The daily cron job automatically detects when daily quotas are not met
- **Smart Backfill**: When scheduling new posts, the system prioritizes filling past deficits before scheduling for future dates
- **Oldest First**: Deficits are filled in chronological order, ensuring the oldest missed days are completed first
- **Automatic Cleanup**: Deficit records older than 30 days are automatically removed

### Admin Interface
The settings page displays your auto-completion status:
- **Total Deficit**: Shows the total number of posts needed to complete all incomplete days
- **Incomplete Days**: Number of days that didn't meet their quota
- **Recent History**: List of the 5 most recent incomplete days with their deficit counts
- **Color Coding**: Green indicates all quotas are up to date, red shows active deficits

## Manual Scheduling

Use the "Schedule Posts Now" button on the settings page to manually execute the scheduler and schedule all pending draft posts immediately. This allows you to:
- Schedule all draft posts without waiting for the automatic cron
- See detailed progress reports of the scheduling process
- Verify your configuration and timing settings work as expected
- Automatically fill any existing deficits with available posts

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## Security

The plugin follows WordPress security best practices:
- All user inputs are sanitized and validated
- CSRF protection via WordPress nonces
- Proper capability checks (`manage_options`)
- No direct file access allowed
- Secure AJAX implementations

## Technical Details

### Hooks and Filters

The plugin uses the following WordPress hooks:
- `register_activation_hook`: Sets up default options and schedules cron job
- `register_deactivation_hook`: Cleans up scheduled cron jobs
- `admin_menu`: Adds the settings page
- `admin_init`: Registers settings
- `ksm_ps_daily_cron`: Custom cron hook for daily scheduling

### Cron Job

- **Hook Name**: `ksm_ps_daily_cron`
- **Schedule**: Daily at midnight
- **Function**: `random_post_scheduler_daily_cron`

### AJAX Endpoints

- `wp_ajax_ksm_ps_run_now`: Manual scheduler execution
- `wp_ajax_ksm_ps_get_status`: Status information refresh

## Troubleshooting

### Cron Jobs Not Running

If the scheduler isn't working:
1. Check if WordPress cron is working properly
2. Verify the plugin is enabled in settings
3. Ensure you have posts in the monitored status
4. Check that today is an active day
5. Verify time range allows for the specified number of posts

### Posts Not Scheduling

Common issues:
- Not enough time between start and end time for all posts with minimum interval
- No posts available in the monitored status
- Scheduler disabled in settings
- Today not selected as an active day

## Support

For support and feature requests, please contact the plugin developer.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed list of changes and version history.

## Version

Current version: 1.0.0