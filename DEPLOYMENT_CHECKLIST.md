# Schedulely - Pre-Deployment Checklist

## 📋 Before Going Live

### 1. ✅ Plugin Activation
- [ ] Upload plugin to `/wp-content/plugins/schedulely/`
- [ ] Activate via WordPress Plugins page
- [ ] Verify menu appears at **Tools → Schedulely**

### 2. ⚙️ Configure Settings

#### Scheduling Settings
- [ ] **Post Status to Monitor**: Choose draft/pending/private
- [ ] **Posts Per Day**: Set your daily quota (e.g., 8)
- [ ] **Time Window**: Set start and end times (e.g., 5:00 PM - 11:00 PM)
- [ ] **Minimum Interval**: Set spacing between posts (e.g., 40 minutes)
- [ ] **Active Days**: Select which days to schedule (Mon-Sun)

**IMPORTANT:** Check the **Capacity Check** section!
- If it shows ⚠️ warning, adjust your settings
- Make sure capacity meets or exceeds your quota

#### Author Assignment (Optional)
- [ ] Enable "Randomize Post Authors" if desired
- [ ] Exclude any authors you don't want assigned

#### Automation Settings
- [ ] **Automatic Scheduling**: ✅ ENABLED (default - recommended)
- [ ] **Email Notifications**: ✅ ENABLED (so you know it's working)
- [ ] **Notification Email**: Verify correct email address

### 3. 🧪 Test First!

**CRITICAL: Don't wait for cron!**

- [ ] Click **"Schedule Now"** button manually
- [ ] Verify posts are scheduled correctly
- [ ] Check **"Upcoming Scheduled Posts"** section
- [ ] Navigate to **Posts → Scheduled** in WordPress
- [ ] Verify times are within your window
- [ ] Verify intervals are respected
- [ ] Check author assignments (if enabled)

### 4. 📧 Verify Email Notifications

After clicking "Schedule Now":
- [ ] Check your email for notification
- [ ] Verify it shows correct count
- [ ] Check spam folder if not received

### 5. ⏰ Understand Cron Behavior

**How WordPress Cron Works:**
```
WordPress cron runs when someone visits your site
NOT on a fixed schedule like server cron
If no visitors, cron doesn't run
```

**Schedulely Cron:**
- **Frequency**: Hourly (when WP cron fires)
- **Action**: Schedules available posts automatically
- **Requirement**: "Automatic Scheduling" must be enabled
- **First Run**: Next hourly cron after activation

**To Trigger Immediately:**
1. Click "Schedule Now" button (recommended for first run)
2. OR wait for next site visit + hourly cron trigger
3. OR use a real server cron (see below)

### 6. 🔄 Verify Cron is Scheduled

**Check if cron is registered:**

Option A: Use plugin (recommended)
- Install "WP Crontrol" plugin
- Go to Tools → Cron Events
- Look for `schedulely_auto_schedule`
- Should show "Hourly" schedule

Option B: Via code
```php
// Add to functions.php temporarily
$timestamp = wp_next_scheduled('schedulely_auto_schedule');
if ($timestamp) {
    echo 'Next run: ' . date('Y-m-d H:i:s', $timestamp);
} else {
    echo 'NOT SCHEDULED!';
}
```

### 7. ⚡ For Reliable Scheduling (Recommended)

WordPress cron is unreliable on low-traffic sites. Consider:

**Option A: Real Server Cron (Best)**
```bash
# Add to your server crontab (runs every hour)
0 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

OR

```bash
0 * * * * curl -s https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

**Option B: External Cron Service**
- Use UptimeRobot, EasyCron, or similar
- Hit `https://yoursite.com/wp-cron.php` every hour
- Free tier is usually sufficient

**Option C: Disable WP-Cron, Use Real Cron**
```php
// In wp-config.php
define('DISABLE_WP_CRON', true);
```
Then set up server cron as shown in Option A.

### 8. 📊 Monitor First Few Runs

**After Activation:**
- [ ] Check "Last Run" timestamp on settings page
- [ ] Verify it updates hourly (or when you click "Schedule Now")
- [ ] Check "Upcoming Scheduled Posts" count increases
- [ ] Verify email notifications arrive

**First 24 Hours:**
- [ ] Monitor scheduled posts count
- [ ] Verify posts publish at scheduled times
- [ ] Check for any gaps in schedule
- [ ] Review email notifications for errors

### 9. 🐛 Troubleshooting

**If Cron Doesn't Run:**
1. Install "WP Crontrol" plugin
2. Manually run `schedulely_auto_schedule` event
3. Check for PHP errors in debug.log
4. Verify site is getting traffic
5. Consider real server cron (see #7)

**If No Posts Are Scheduled:**
1. Verify you have posts in monitored status (draft/pending/private)
2. Check capacity calculator - might need to adjust settings
3. Look for errors in WordPress admin
4. Enable WP_DEBUG and check debug.log
5. Check email notifications for error messages

**If Wrong Number of Posts:**
1. Check capacity calculator warning
2. Verify time window is sufficient
3. Adjust interval or expand window
4. See CAPACITY_CALCULATION_EXPLAINED.md

### 10. 🔒 Security Checks

- [ ] Only admins can access settings (built-in)
- [ ] AJAX requests are nonce-protected (built-in)
- [ ] All inputs are sanitized (built-in)
- [ ] No direct file access allowed (built-in)

### 11. 🎯 Performance Considerations

**For Large Sites (1000+ posts to schedule):**
- [ ] Scheduler limits to 500 posts per run (built-in)
- [ ] Multiple runs will handle remaining posts
- [ ] Consider increasing PHP max_execution_time if needed
- [ ] Monitor server resources during scheduling

**Memory Considerations:**
- Scheduler is optimized with `fields => 'ids'`
- No post meta/term cache loaded unnecessarily
- Should handle large batches efficiently

### 12. 📝 Final Checklist

Before going live:
- [ ] All settings configured correctly
- [ ] Capacity check shows green or warning understood
- [ ] Manual "Schedule Now" test successful
- [ ] Email notifications received
- [ ] Scheduled posts visible in WordPress
- [ ] Post times are correct
- [ ] Author assignments correct (if enabled)
- [ ] Cron is registered and will run
- [ ] Consider real server cron for reliability
- [ ] Have backup of database
- [ ] Documentation bookmarked (CAPACITY_CALCULATION_EXPLAINED.md, etc.)

---

## 🚀 Deployment Steps

### Quick Deployment (5 minutes)

1. **Upload & Activate**
   ```
   - Upload to /wp-content/plugins/schedulely/
   - Activate in Plugins page
   ```

2. **Configure**
   ```
   - Go to Tools → Schedulely
   - Set your time window
   - Set posts per day
   - Check capacity calculator
   ```

3. **Test**
   ```
   - Click "Schedule Now"
   - Verify posts scheduled
   - Check email notification
   ```

4. **Enable Auto-Scheduling**
   ```
   - Already enabled by default
   - Will run hourly automatically
   ```

5. **Done!**
   ```
   - Monitor for first 24 hours
   - Adjust settings as needed
   ```

---

## ⏰ Cron Schedule Expectations

### With WordPress Cron (Default)
- **First Run**: Within 1 hour after someone visits your site
- **Subsequent Runs**: Every hour when site is visited
- **Reliability**: Depends on traffic
- **Best For**: Sites with regular traffic

### With Real Server Cron (Recommended)
- **First Run**: Top of next hour (e.g., 3:00 PM if set up at 2:30 PM)
- **Subsequent Runs**: Exactly every hour
- **Reliability**: 100% reliable
- **Best For**: All sites, especially low-traffic ones

---

## 📧 Email Notification Example

When scheduling runs, you'll receive:

```
Subject: Schedulely: Posts Scheduled Successfully

Successfully scheduled 8 posts.

Last Scheduled Date: October 10, 2025
Posts Scheduled: 8/8 (Complete ✓)

Last Run: October 7, 2025 - 3:00 PM
```

If there's an issue:
```
Subject: Schedulely: Scheduling Report

No posts available in draft status to schedule.

Last Run: October 7, 2025 - 3:00 PM
```

---

## 🆘 Emergency Contacts

**If Something Goes Wrong:**

1. **Disable Auto-Scheduling**: Uncheck in settings
2. **Check Error Logs**: wp-content/debug.log
3. **Deactivate Plugin**: Won't affect already-scheduled posts
4. **Review Documentation**: All .md files in plugin folder

**Scheduled Posts Are Safe:**
- Stored in WordPress database
- Plugin deactivation doesn't delete them
- They'll still publish at scheduled times

---

## 💡 Pro Tips

### Tip 1: Start Conservative
- Begin with lower posts per day
- Test for a few days
- Increase gradually

### Tip 2: Monitor Capacity
- Check capacity calculator regularly
- If you see warnings, adjust settings
- Better to schedule fewer posts reliably

### Tip 3: Use Real Cron
- WordPress cron is unreliable
- Real server cron is worth the 5-minute setup
- Your posts will schedule on time, every time

### Tip 4: Keep Drafts Ready
- Maintain a buffer of draft posts
- Plugin can only schedule what exists
- Auto-scheduling works best with steady draft supply

### Tip 5: Watch Email Notifications
- Quick way to monitor plugin health
- Shows if runs are successful
- Alerts you to issues immediately

---

## ✅ You're Ready!

Once you've completed this checklist, your plugin is production-ready.

**Quick Start (If You're in a Hurry):**
1. Activate plugin
2. Set time window and posts per day
3. Click "Schedule Now" to test
4. Enable auto-scheduling (already on by default)
5. Done!

**Remember:** The first run happens when you click "Schedule Now" OR wait for next hourly cron.

**Don't wait for cron** - test immediately with "Schedule Now" button!

---

## 📚 Documentation References

- `CAPACITY_CALCULATION_EXPLAINED.md` - Understand how capacity works
- `SETTINGS_PRESERVATION.md` - Settings are safe during updates
- `VERSION_1.0.7_NOTES.md` - Latest release information
- `CHANGELOG.md` - Complete version history

---

**Good luck with your deployment! 🚀**

