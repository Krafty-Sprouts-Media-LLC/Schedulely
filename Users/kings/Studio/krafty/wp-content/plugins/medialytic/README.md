# Medialytic – Media Counter & Manager

Medialytic unifies Krafty Sprouts Media’s internal media-management tooling into a single WordPress plugin. It combines analytics, featured-image workflows, duplicate detection, automatic remote-image ingestion, and attachment metadata optimization—so sites ship with one well‑maintained, security-reviewed codebase instead of a patchwork of third-party plugins.

## Key capabilities

- **Media analytics** – Count images/videos/embeds per post, store historical breakdowns, and expose sortable admin columns for quick auditing.
- **Featured image suite** – Duplicate finder, fallback assignment, admin list thumbnails (with inline media-modal editing), RSS image injection, and background bulk initialization.
- **Auto upload images** – Seamlessly import external images referenced in post content, attach them to the Media Library, and rewrite `src` / `srcset` / `alt` attributes. Based on _Auto Upload Images_ by Ali Irani.
- **Image title & alt optimizer** – Clean filenames become SEO-friendly titles, captions, descriptions, and alt text, with configurable capitalization and filename renaming. Based on _Auto Image Title & Alt_ by Diego de Guindos.
- **Attachment tools** – One-click “Optimize title & tags” actions inside both the Media Library list view and the attachment edit screen.

## Modules at a glance

| Module Slug              | Class                                   | Description |
|--------------------------|-----------------------------------------|-------------|
| `image-counter`          | `Medialytic_Image_Counter`              | Counts images per post and surfaces totals in admin columns. |
| `video-counter`          | `Medialytic_Video_Counter`              | Video detection/analytics. |
| `embed-counter`          | `Medialytic_Embed_Counter`              | Tracks embeds/iframes. |
| `duplicate-finder`       | `Medialytic_Duplicate_Finder`           | Media Library UI for locating/removing duplicates (ported from KSM tooling). |
| `featured-image-manager` | `Medialytic_Featured_Image_Manager`     | Fallbacks, RSS injection, admin thumbnails, cache init. |
| `auto-upload-images`     | `Medialytic_Auto_Upload_Images`         | Imports remote images on save; exposes full settings UI. |
| `image-title-alt`        | `Medialytic_Image_Title_Alt`            | Cleans titles/alt/captions + file renaming + AJAX actions. |

Each module is registered with the internal `Medialytic_Module_Manager`, so you can programmatically enable/disable or extend modules without touching the bootstrapper.

## Installation

1. Copy the `medialytic` directory into `wp-content/plugins/` (or deploy via your build pipeline).
2. Activate “Medialytic – Media counter and manager” from **Plugins → Installed Plugins**.
3. Visit the following settings pages to tailor each subsystem:
   - `Settings → Media Analytics` (core controls via `Medialytic_Admin`)
   - `Settings → Featured Images`
   - `Settings → Auto Upload Images`
   - `Settings → Image Title & Alt`
4. Clear caches or run any deployment hooks required by your environment.

## Configuration highlights

- **Featured Image Manager**
  - Choose post types, fallback image, RSS position/caption, and toggle admin column visibility.
  - Inline “Change / Remove” actions appear on every list-table row once enabled.
- **Auto Upload Images**
  - Override the CDN/base URL, define filename/alt templates (e.g., `%postname%`), set max width/height, and exclude domains or post types.
- **Image Title & Alt**
  - Pick which fields to update (title, alt, caption, description), choose capitalization, and decide whether to rename images only, all files, or none.
  - Use the “Optimize” buttons in the Media Library to retroactively clean existing attachments.

## Uninstall / cleanup

Running the built-in WordPress uninstall routine removes:

- `medialytic_settings`
- `medialytic_image_counter_needs_init`
- `medialytic_image_counter_initialized`
- `medialytic_auto_upload_images`
- `medialytic_image_title_alt`
- All Medialytic transients and database tables (`medialytic_media_counts`, `medialytic_media_history`)

## Credits

- **Auto Upload Images** – Original plugin by [Ali Irani](https://github.com/airani/wp-auto-upload) (GPLv2+). Ported with enhancements (AVIF support, CDN overrides, WP coding standards).
- **Auto Image Title & Alt** – Original plugin by [Diego de Guindos](https://wordpress.org/plugins/auto-image-title-alt/) (GPLv2+). Rebuilt to match Medialytic’s UX, AJAX, and settings conventions.
- **Duplicate finder & featured image tooling** – Internal Krafty Sprouts Media utilities, now consolidated here.

Medialytic itself is GPLv2+—feel free to submit pull requests or open issues on [GitHub](https://github.com/Krafty-Sprouts-Media-LLC/medialytic). Contributions should follow the WordPress Coding Standards (PHPCS) and include updates to this README plus the changelog entry for each release.

