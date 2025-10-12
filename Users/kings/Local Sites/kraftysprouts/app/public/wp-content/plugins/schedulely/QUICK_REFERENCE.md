# Schedulely - Quick Reference Guide

## 🎯 Quick Start (60 seconds)

1. **Install & Activate** the plugin
2. Go to **Tools → Schedulely**
3. Set **Posts Per Day** (e.g., 8)
4. Set **Time Window** (e.g., 5:00 PM - 11:00 PM)
5. Click **Save Changes**
6. Click **Schedule Now** button

Done! Your posts are scheduled.

---

## ⚙️ Default Settings

| Setting | Default Value |
|---------|--------------|
| Post Status | Draft |
| Posts Per Day | 8 |
| Start Time | 5:00 PM |
| End Time | 11:00 PM |
| Min Interval | 40 minutes |
| Active Days | All days (Mon-Sun) |
| Auto Schedule | Enabled |
| Email Notifications | Enabled |

---

## 📍 Menu Location

**Tools → Schedulely**

---

## 🔑 Key Concepts

### Deficit Tracking
If a day doesn't meet its quota, Schedulely remembers and fills it next time.

**Example:**
- Quota: 8 posts/day
- Oct 10: Only 5 posts scheduled
- **Deficit:** 3 posts for Oct 10
- Next run: Fills Oct 10 deficit first (3 posts), then schedules to new dates

### Random Time Distribution
Posts are scheduled at random times within your window.

**Example:**
- Window: 5:00 PM - 11:00 PM (6 hours)
- Post 1: 5:23 PM
- Post 2: 6:47 PM
- Post 3: 8:12 PM
- etc.

### Minimum Interval
Ensures posts don't publish too close together.

**Example:**
- Min Interval: 40 minutes
- Post 1: 5:00 PM
- Post 2: Can't be before 5:40 PM

---

## 🎨 Admin Interface

### Dashboard Stats
- **Available Posts:** Count of posts with monitored status
- **Next Scheduled:** Date/time of next post to publish
- **Active Deficits:** Number of dates with deficits
- **Last Run:** When scheduler last ran

### Action Buttons
- **Schedule Now:** Manual scheduling trigger
- **View All Scheduled Posts:** Links to WordPress scheduled posts

### Sections
1. **Scheduling Overview** - Stats and actions
2. **Scheduling Settings** - Core configuration
3. **Author Assignment** - Random author options
4. **Automation Settings** - Cron and notifications
5. **Upcoming Scheduled Posts** - Next 20 posts
6. **Deficit Status** - Current deficits

---

## 📞 Common Tasks

### Schedule Posts Manually
1. Go to **Tools → Schedulely**
2. Click **Schedule Now** button
3. Confirm the action
4. Wait for success message

### Change Posting Schedule
1. Go to **Tools → Schedulely**
2. Update **Posts Per Day** or **Time Window**
3. Click **Save Changes**
4. New settings apply to next scheduling run

### Enable Author Randomization
1. Go to **Tools → Schedulely**
2. Check **"Randomize Post Authors"**
3. Optionally select authors to exclude
4. Click **Save Changes**

### Disable Automatic Scheduling
1. Go to **Tools → Schedulely**
2. Uncheck **"Enable Automatic Scheduling"**
3. Click **Save Changes**
4. Use **Schedule Now** button manually

### View Scheduled Posts
1. Go to **Posts → All Posts**
2. Click **Scheduled** tab
3. View all future posts

### Clear Deficits
Deficits are automatically filled when scheduling runs. Just run **Schedule Now** or wait for automatic cron run.

---

## 🐛 Troubleshooting

### No Posts Scheduled?
- ✅ Check you have posts in the monitored status
- ✅ Verify time window is valid (start < end)
- ✅ Ensure at least one active day is selected
- ✅ Check WordPress debug log

### Cron Not Running?
- ✅ Verify auto-scheduling is enabled
- ✅ Visit `yoursite.com/wp-cron.php` to trigger
- ✅ Consider using real cron instead of WP-Cron

### No Email Notifications?
- ✅ Check email notifications are enabled
- ✅ Verify email address is correct
- ✅ Check spam folder
- ✅ Test WordPress email with WP Mail SMTP

### Wrong Time Zone?
- ✅ Go to **Settings → General**
- ✅ Set correct timezone
- ✅ Schedulely uses WordPress timezone

---

## 💡 Pro Tips

### Best Practices
1. **Start Small** - Test with 5-10 posts first
2. **Wide Time Window** - Give room for random distribution
3. **Reasonable Interval** - 30-60 minutes works well
4. **Monitor Deficits** - Check weekly and adjust quota if needed
5. **Use Drafts** - Keep published posts separate

### Optimization
- Set posts per day to match your content calendar
- Use time windows that match audience activity
- Enable notifications to stay informed
- Review upcoming posts regularly

### Workflow
1. Create posts in Draft status
2. Let Schedulely handle scheduling automatically
3. Review scheduled posts weekly
4. Adjust settings based on performance

---

## 📊 Database Options

All stored with `schedulely_` prefix in `wp_options` table:

```
schedulely_post_status
schedulely_posts_per_day
schedulely_start_time
schedulely_end_time
schedulely_active_days
schedulely_min_interval
schedulely_randomize_authors
schedulely_excluded_authors
schedulely_auto_schedule
schedulely_email_notifications
schedulely_notification_email
schedulely_deficit_tracker
schedulely_last_run
schedulely_version
```

---

## 🔒 Security Notes

- ✅ Only administrators can access settings
- ✅ All forms use WordPress nonces
- ✅ Input is sanitized, output is escaped
- ✅ SQL injection protected
- ✅ No data transmitted externally

---

## 📝 Version Info

**Current Version:** 1.0.1  
**Release Date:** 06/10/2025  
**WordPress:** 6.8+  
**PHP:** 8.2+

---

## 🆘 Quick Support

**Email:** support@kraftysprouts.com  
**Website:** https://kraftysprouts.com  
**Docs:** README.md, INSTALL.md

---

## ⌨️ Keyboard Shortcuts

None currently implemented.

---

## 🎓 Learning Resources

1. Read **INSTALL.md** for detailed setup
2. Read **README.md** for developer info
3. Check **CHANGELOG.md** for version history
4. Review **README.txt** for WordPress.org info

---

**Made with ❤️ by [Krafty Sprouts Media](https://kraftysprouts.com)**

