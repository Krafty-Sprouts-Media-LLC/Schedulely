# Handover Note — US Timezone-Aware Queue Ordering Bug

**Date:** May 28, 2026
**Plugin:** Schedulely v1.7.6
**Repo:** https://github.com/Krafty-Sprouts-Media-LLC/Schedulely
**Tag:** v1.7.6

---

## The Problem

US Timezone-Aware Queue Ordering is enabled and the AI reorder runs successfully (1044 or 200 posts), but **all posts are assigned to "general"** — no timezone groups are returned. The email shows:

```
US Timezone Distribution: General: 200
```

Expected:
```
US Timezone Distribution: Eastern: 52 · Central: 45 · Mountain: 28 · Pacific: 40 · General: 35
```

---

## What Has Been Tried

### v1.7.5 Fix — User prompt updated
The user prompt was only asking for `{"ordered_ids":[...]}`. Updated to explicitly request both keys:
```
Respond with JSON only: {"ordered_ids":[...], "timezone_groups":{"id":"group",...}}
```
**Result:** Still returns General: 200. The AI is ignoring the `timezone_groups` instruction.

### v1.7.6 Fix — Timeout raised
The WP 7.0 AI client had a 30-second default timeout (`wp_ai_client_default_request_timeout`). Added a filter to raise it to 120–1200s based on post count.
**Result:** No more timeout errors, but timezone_groups still not returned.

---

## Root Cause Analysis

The AI (DeepSeek via WP 7.0 Connectors) is receiving the correct system instruction AND user prompt requesting `timezone_groups`, but is only returning `ordered_ids`. The graceful fallback in `process_timezone_response()` then assigns all posts to "general".

**Evidence from WP AI Usage Log:**
```
Source: schedulely (plugin)
Source File: schedulely/includes/class-ai-order.php
Provider: deepseek
Model: deepseek-v4-flash
Input Preview: [system] You reorder WordPress posts for a US audience...
  "timezone_groups" — object mapping each post ID (as string key) to its group string...
```

The system instruction IS being sent correctly. The model is receiving it. But the response only contains `ordered_ids`.

**Possible causes:**
1. The WP 7.0 AI client may be stripping or not forwarding `response_format: {"type": "json_object"}` to DeepSeek — without JSON mode, the model may not reliably produce structured JSON with both keys.
2. The DeepSeek provider plugin (`ai-provider-for-deepseek` by Sajjad67) may not support passing `response_format` through the WP AI client abstraction.
3. The model may be hitting an output token limit imposed by the WP AI client or the provider plugin that's lower than the 40,960 we set on the legacy path.
4. With 200+ posts, the combined output (ordered_ids array + timezone_groups object) may be too large for the model to produce in one response, so it truncates to just ordered_ids.

---

## Key Files

| File | What it does |
|---|---|
| `includes/class-ai-order.php` | AI reorder logic — both WP 7.0 and legacy paths |
| `includes/class-scheduler.php` | Scheduling engine — uses timezone_queue for band assignment |
| `templates/admin/settings-page.php` | Settings UI — timezone toggle |
| `includes/class-notifications.php` | Email with timezone distribution |
| `.wp-ai/ai-provider-for-deepseek/` | Third-party DeepSeek provider for WP 7.0 Connectors |

---

## Key Methods

| Method | File | Purpose |
|---|---|---|
| `reorder_post_ids_with_timezone()` | class-ai-order.php | Entry point for timezone reorder |
| `reorder_via_wp_ai_timezone()` | class-ai-order.php | WP 7.0 path — calls wp_ai_client_prompt() |
| `get_timezone_system_instruction()` | class-ai-order.php | The full timezone prompt with state→zone map |
| `build_user_prompt($lines, $count, true)` | class-ai-order.php | User prompt requesting both JSON keys |
| `process_timezone_response()` | class-ai-order.php | Parses response, falls back to general if timezone_groups missing |
| `get_timezone_active_overlap()` | class-scheduler.php | Computes window/active-hours intersection |

---

## What to Investigate

1. **Does the WP 7.0 AI client pass `response_format` to the provider?**
   - Check `WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel`
   - The legacy path explicitly sends `"response_format": {"type": "json_object"}` in the HTTP body
   - The WP 7.0 path uses `wp_ai_client_prompt()->generate_text()` which may not support JSON mode

2. **Is there a `using_output_mime_type('application/json')` method on the builder?**
   - The DeepSeek provider metadata shows it supports `outputMimeType: ['text/plain', 'application/json']`
   - If the builder has `->using_output_mime_type('application/json')`, adding it may force JSON mode

3. **Is the output being truncated?**
   - Check if the WP AI client has a max_tokens setting or if the provider plugin caps it
   - The response for 200 posts with timezone_groups needs ~5,000 output tokens
   - If capped at 4,096 (common default), it would truncate and only return ordered_ids

4. **Test with the legacy path as a workaround:**
   - Temporarily disable WP 7.0 detection in `wp_ai_available()` (return false)
   - Configure the DeepSeek API key directly in Schedulely's legacy fields
   - The legacy path has full control over `response_format`, `max_tokens`, and timeout
   - If this works, the issue is confirmed to be in the WP 7.0 abstraction layer

---

## Quick Workaround (Legacy Path)

To bypass the WP 7.0 client entirely and use the legacy direct-HTTP path:

1. In `class-ai-order.php`, method `wp_ai_available()`, temporarily change:
   ```php
   // Force legacy path for debugging
   $available = false;
   return false;
   ```

2. In Schedulely settings (AI & Notifications tab), fill in:
   - API Base URL: `https://api.deepseek.com/v1`
   - Model: `deepseek-v4-flash`
   - API Key: (your DeepSeek key from platform.deepseek.com)

3. Run Schedule Now — the legacy path sends `response_format: {"type": "json_object"}` and `max_tokens: 40960` directly in the HTTP body.

If this produces correct timezone_groups, the fix is to add JSON mode to the WP 7.0 path (likely `->using_output_mime_type('application/json')` on the builder).

---

## Environment

- WordPress 7.0 (with AI Connectors)
- PHP 8.2+
- DeepSeek connected via Settings → Connectors (API key stored centrally)
- DeepSeek provider plugin: `ai-provider-for-deepseek` by Sajjad67
- Model: `deepseek-v4-flash`
- Site timezone: WAT (Africa/Lagos, UTC+1)
- Publishing window: 2:00 PM → 5:00 AM WAT (overnight)
- Pool size: 200–1500 posts
- All posts are US state-specific legal content with state names in titles and slugs

---

## Configuration That Works

- AI ordering: ✅ enabled and working (series spacing applied)
- Timezone ordering: ✅ enabled in settings, prompt sent correctly
- Timeout: ✅ fixed in v1.7.6 (no more cURL 28 errors)
- The ONLY issue: the AI response doesn't include `timezone_groups`

---

## AGENTS.md

The full operating rules for AI agents are in `AGENTS.md` at the plugin root. Section 15 covers the timezone feature. Section 16 covers the release process.
