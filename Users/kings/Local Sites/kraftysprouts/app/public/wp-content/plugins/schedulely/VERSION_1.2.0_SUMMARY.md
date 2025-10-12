# Version 1.2.0 Quick Summary

## 🎯 What's New

### Added
✅ **"How Random Scheduling Works" Info Box** - Right in the Capacity Check section
- Explains minimum interval vs actual gaps
- Shows why 70% efficiency is expected
- Provides real-world example of random distribution
- Educates users that gaps vary (30-120+ minutes is normal)

### Fixed - CRITICAL
✅ **Cron Migration** - Old hourly schedules now properly update to twice-daily  
✅ **Capacity Formula** - Removed `+1` error, changed 75% → 70% for accuracy  
✅ **Email Reports** - Full date-by-date breakdown showing ALL incomplete dates  

---

## 📊 Version Bump Rationale

**1.0.11 → 1.2.0** (Minor version bump)

**Why:**
- **Feature addition** (educational notice) = minor version bump
- Not just bug fixes anymore
- User-facing improvement in understanding
- Following semantic versioning: MAJOR.MINOR.PATCH

---

## 🎨 What Users See

**In Settings → Capacity Check:**
```
ℹ️ How Random Scheduling Works

Posts are scheduled at random times within your time window for 
a natural appearance. The minimum interval (e.g., 30 minutes) is 
the shortest gap allowed between posts — actual gaps may be larger 
(45 min, 60 min, or more) due to random placement.

✅ Posts are at least X minutes apart (never closer)
✅ Gaps between posts vary randomly (some 30 min, some 60+ min)
✅ There may be unused time at the end of your window
✅ Random scheduling achieves ~70% efficiency

Example: 5:14 PM → 5:47 PM (33 min) → 6:23 PM (36 min) → 
         7:15 PM (52 min) → 8:42 PM (87 min gap!)
```

---

## 🚀 Deploy Ready

All files updated:
- ✅ schedulely.php (v1.2.0)
- ✅ README.txt (stable: 1.2.0)
- ✅ CHANGELOG.md (added education feature)
- ✅ includes/class-settings.php (info notice added)
- ✅ VERSION_1.0.11_NOTES.md (content updated to 1.2.0)
- ✅ All critical bugs fixed

**No breaking changes. Safe to deploy!**

