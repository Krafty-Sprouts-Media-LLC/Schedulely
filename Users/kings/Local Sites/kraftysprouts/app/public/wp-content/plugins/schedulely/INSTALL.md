# Schedulely Installation Guide

## Quick Start

### Requirements
- WordPress 6.8 or higher
- PHP 8.2 or higher
- MySQL 5.7+ or MariaDB equivalent

### Installation Methods

#### Method 1: WordPress Admin (Recommended)
1. Download `schedulely.zip`
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file
4. Click **Install Now**
5. Click **Activate**

#### Method 2: FTP/Manual Upload
1. Extract the `schedulely.zip` file
2. Upload the `schedulely` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find "Schedulely" and click **Activate**

#### Method 3: WP-CLI
```bash
wp plugin install schedulely.zip --activate
```

## Initial Configuration

### Step 1: Access Settings
Navigate to **Tools → Schedulely** in your WordPress admin.

### Step 2: Configure Scheduling Settings
- **Post Status to Monitor:** Select which status to schedule from (draft, pending, or private)
- **Posts Per Day:** Set your daily quota (e.g., 8 posts)
- **Publishing Time Window:** Define when posts should publish (e.g., 5:00 PM - 11:00 PM)
- **Minimum Interval:** Set minimum minutes between posts (e.g., 40 minutes)
- **Active Days:** Choose which days of the week to schedule posts

### Step 3: Configure Author Settings (Optional)
- Check **"Randomize Post Authors"** if you want random author assignment
- Select authors to exclude from randomization

### Step 4: Configure Automation
- Check **"Enable Automatic Scheduling"** for hourly WordPress cron scheduling
- Check **"Enable Email Notifications"** to receive scheduling reports
- Enter notification email address

### Step 5: Save Settings
Click **"Save Changes"** button.

## First Run

### Manual Scheduling
1. Ensure you have posts in the configured status (e.g., draft posts)
2. Click the **"Schedule Now"** button in the dashboard
3. Confirm the action
4. View results in the notification and upcoming posts list

### Automatic Scheduling
If you enabled automatic scheduling, WordPress cron will run the scheduler hourly. You'll receive email notifications when scheduling completes.

## Verification

### Check Scheduled Posts
1. Go to **Posts → All Posts**
2. Click the **"Scheduled"** tab to view all scheduled posts
3. Verify posts are scheduled at correct dates/times

### Check Deficit Status
In the Schedulely settings page, scroll to the **"Deficit Status"** section to see any tracked deficits.

## Troubleshooting

### No Posts Being Scheduled?
- Verify you have posts with the configured status
- Check that time window is valid (start time before end time)
- Ensure at least one active day is selected
- Check WordPress debug log for errors

### Cron Not Running?
- Verify **"Enable Automatic Scheduling"** is checked
- Check if WordPress cron is functioning: Visit `yoursite.com/wp-cron.php`
- Consider using a real cron job instead of WordPress cron for reliability

### Email Notifications Not Received?
- Verify **"Enable Email Notifications"** is checked
- Check notification email address is correct
- Test WordPress email functionality with a plugin like WP Mail SMTP
- Check spam folder

### Time Zone Issues?
- Schedulely uses WordPress timezone settings
- Go to **Settings → General** and verify timezone is correct
- All times are displayed and scheduled in your site's timezone

## Advanced Configuration

### Custom Post Status
By default, Schedulely works with draft, pending, and private statuses. To add custom statuses, you'll need to modify the plugin code or request this feature.

### Deficit Management
Schedulely automatically tracks deficits. If a day doesn't meet its quota, it will be filled in the next scheduling run before new dates are scheduled.

### Performance Optimization
- Plugin processes up to 500 posts per run
- Cron runs hourly by default
- For large sites, consider running cron less frequently

## Uninstallation

### Clean Removal
1. Deactivate the plugin from **Plugins** page
2. Click **Delete** next to Schedulely
3. Confirm deletion

All plugin data (settings, deficits, etc.) will be automatically removed from the database.

### Preserve Scheduled Posts
Scheduled posts will remain scheduled after uninstallation. They will publish at their scheduled times even without the plugin.

## Support

If you encounter any issues:
- Check the [FAQ in README.txt](README.txt)
- Review [CHANGELOG.md](CHANGELOG.md) for known issues
- Contact support: support@kraftysprouts.com
- Visit: https://kraftysprouts.com/contact

---

**Need Help?** Visit [https://kraftysprouts.com](https://kraftysprouts.com) for support and documentation.

