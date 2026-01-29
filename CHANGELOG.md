# Changelog

All notable changes to Medialytic will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.8.1] - 2025-11-14

### Fixed
- Prevent fallback featured images from overriding real thumbnails by moving the `_thumbnail_id` filter to `default_post_metadata`, ensuring placeholders only appear when posts truly lack a featured image.

## [1.8.0] - 2025-11-14

### Added
- Ported SS88 LLC’s Media Library File Size plugin into the new `media-file-size` module so the Media Library gains a sortable “File Size” column, total library badge, and variant modal
- Added “Index Media” and “Reindex Media” controls with AJAX-powered batch processing (100 attachments at a time) plus success/error messaging inside Medialytic
- Brought over the variant viewer modal so editors can inspect width/height/file size for every generated image size and download each variant in a click

### Changed
- Localized assets (`assets/css/media-file-size.css`, `assets/js/media-file-size.js`) now ship with Medialytic, honouring our admin color palette and notices instead of third-party libraries
- `Medialytic::uninstall()` delegates to module uninstall callbacks ensuring `SS88MLFS` / `SS88MLFSV` meta keys are purged during plugin removal
- README now documents the Media File Size workflow, configuration steps, and credits the upstream GPL plugin

## [1.7.1] - 2025-11-14

### Added
- Authored a project-level `README.md` that documents every module, configuration surface, uninstall behaviour, and upstream credits so teams can onboard quickly

### Changed
- `Medialytic::uninstall()` now imports the feature modules and removes all related options/tables so the plugin cleans up after itself without relying on a standalone `uninstall.php`

## [1.7.0] - 2025-11-14

### Added
- Integrated the Auto Image Title & Alt plugin by Diego de Guindos so uploads now inherit clean, SEO-friendly titles/captions/descriptions/alt text with configurable capitalization and per-field targeting
- Added file-renaming rules (images-only or all files) that convert filenames to lowercase ASCII slugs prior to upload
- Media Library rows and the attachment edit screen now include “Optimize title & tags” buttons backed by AJAX for one-click retroactive updates

### Changed
- Module loader exposes the new Image Title & Alt service; legacy plugin files were removed after migration

## [1.6.1] - 2025-11-14

### Added
- Auto-upload pipeline now recognises AVIF (`image/avif`) responses, ensuring modern formats are saved with the correct extension

## [1.6.0] - 2025-11-14

### Added
- Auto Upload Images module ports the long-unmaintained (3+ years) plugin by Ali Irani into Medialytic with a full settings page, filename/alt templates, resizing controls, domain and post-type exclusions, and CDN base URL overrides
- Remote image processing now attaches files to the Media Library, generates metadata, and replaces `src`/`srcset`/`alt` attributes inline during post save

### Changed
- Module loader exposes the new auto-upload service so teams can toggle the behaviour without code changes

## [1.5.0] - 2025-11-14

### Added
- Featured image admin column now includes inline media modal controls, sortable headers, and AJAX updates, inspired by the legacy Featured Image Admin Thumb workflow
- Default featured image handling now mirrors the classic Default Featured Image plugin by transparently supplying `_thumbnail_id` values and rendering fallbacks across the front end, RSS feeds, and list tables
- Settings → Media now includes a bridge pointing to Medialytic’s Featured Image Manager for continuity

### Changed
- RSS injection now defaults to full-size images ahead of content, matching the legacy snippet behaviour, while still allowing configurable captions, HTTPS enforcement, and positioning
- Fallback image previews are reused wherever possible so third-party code, list tables, and feeds always see a consistent image source

## [1.4.0] - 2025-11-14

### Added
- Featured Image Manager module delivering admin list thumbnails, fallback featured image automation, and RSS feed injection with captions and HTTPS enforcement

### Changed
- Medialytic now bundles former stand-alone Smart Featured Image Manager capabilities directly via the new feature module

## [1.3.0] - 2025-11-14

### Added
- Central module manager that discovers, boots, and exposes Medialytic feature modules with shared lifecycle hooks

### Changed
- Plugin initialization, activation, deactivation, and uninstall paths now iterate module definitions, paving the way for future managers like the upcoming Featured Image Manager

## [1.2.0] - 2025-11-14

### Added
- Duplicate image finder module with Media Library UI, bulk deletion, and dismissible groups
- Cached scan persistence with AJAX-powered retrieval, clearing, and timestamp indicators
- Dedicated CSS/JS assets for the duplicate finder experience with localized messaging

### Changed
- Promoted the duplicate finder as part of Medialytic’s core toolkit and wired it into plugin activation/uninstall lifecycle
- Aligned admin instantiation and shared asset loading with the expanded component stack

## [1.1.0] - 2025-11-14

### Added
- Integrated the legacy KSM image counter engine with DOM parsing, regex fallback, and Gutenberg/gallery awareness for higher accuracy
- Added background bulk initialization with admin notice, AJAX dismissal, and caching to populate image counts for historical posts
- Introduced dedicated sortable "Images" admin column leveraging cached post meta for faster list-table rendering

### Changed
- Renamed the plugin to **Medialytic - Media counter and manager** with updated descriptions to better reflect the expanded scope
- Centralized image counting in `Medialytic_Core` to reuse the enhanced analyzer while preserving legacy fallback logic

## [1.0.0] - 2025-08-18

### Added
- **Initial Release**: Complete media analytics and counting for WordPress
- **Comprehensive Media Tracking**: Advanced counting of images, videos, and embeds
  - Automatic detection of img tags and WordPress image blocks
  - Gallery shortcode image counting with ID extraction
  - Video tag and WordPress video block detection
  - Iframe and embed block counting
  - Social media embed shortcode support (YouTube, Vimeo, Twitter, etc.)
- **Database Integration**: Robust data storage and historical tracking
  - Custom database tables for media counts and history
  - Post-specific media analytics storage
  - Daily, monthly, and yearly historical data
  - Automatic data retention and cleanup
- **Admin Integration**: Seamless WordPress admin experience
  - Media count columns in post lists
  - Sortable media count columns
  - Real-time media counting on post save
  - Comprehensive admin settings page
- **Dashboard Analytics**: Overview statistics and insights
  - Total media counts across all content
  - Breakdown by media type (images, videos, embeds)
  - Historical trends and analytics
  - Performance metrics and statistics
- **Performance Optimized**: Efficient media detection and processing
  - Regex-based media detection
  - Database optimization with proper indexing
  - Caching system for improved performance
  - Batch processing capabilities

### Settings & Configuration
- **General Settings**:
  - Enable/disable media analytics
  - Post type selection
  - Debug mode for troubleshooting
- **Media Type Settings**:
  - Individual control for images, videos, and embeds
  - Selective media type counting
  - Custom media type definitions
- **Display Options**:
  - Admin column display toggle
  - Dashboard widget configuration
  - Statistics display preferences
- **Historical Data**:
  - Enable/disable historical tracking
  - Data retention settings
  - Automatic cleanup scheduling
- **Performance Settings**:
  - Cache duration control
  - Processing optimization
  - Memory management

### Database Structure
- **Media Counts Table**: `wp_medialytic_media_counts`
  - Post-specific media counts
  - Image, video, embed, and total counts
  - Last updated timestamps
  - Unique constraints for data integrity
- **Media History Table**: `wp_medialytic_media_history`
  - Historical media count records
  - Date-based tracking (year, month, day)
  - Trend analysis data
  - Performance metrics storage

### Admin Interface
- **Settings Page**: Complete configuration under Settings → Media Analytics
- **Post Type Selection**: WordPress native post type picker
- **Media Type Controls**: Individual toggles for each media type
- **Quick Actions**:
  - Reset to defaults
  - Clear historical data
  - Bulk recount media
- **Status Indicators**: Real-time plugin status display
- **Statistics Overview**: Comprehensive analytics dashboard

### Media Detection
- **Image Detection**: Advanced image counting algorithms
  - HTML img tag detection
  - WordPress image block parsing
  - Gallery shortcode processing
  - Featured image inclusion
  - Custom image formats support
- **Video Detection**: Comprehensive video identification
  - HTML video tag detection
  - WordPress video block parsing
  - Video shortcode processing
  - Embedded video detection
  - Multiple video format support
- **Embed Detection**: Universal embed recognition
  - Iframe embed detection
  - WordPress embed block parsing
  - Social media embed shortcodes
  - Custom embed format support
  - Third-party service integration

### Analytics Features
- **Real-time Counting**: Automatic media detection on content save
- **Historical Tracking**: Comprehensive historical data storage
- **Trend Analysis**: Media usage trends over time
- **Performance Metrics**: Media optimization insights
- **Comparative Analytics**: Cross-post media comparisons

### Technical Features
- **WordPress Integration**: Native WordPress compatibility
  - Custom post type support
  - WordPress block editor integration
  - Shortcode processing
  - Theme compatibility
- **Database Optimization**: Efficient data storage
  - Proper database indexing
  - Optimized query performance
  - Data integrity constraints
  - Automatic cleanup processes
- **Security**: Built with WordPress security standards
  - Nonce verification
  - Capability checks
  - Data sanitization
  - SQL injection prevention
- **Performance**: Optimized for speed and efficiency
  - Efficient regex patterns
  - Minimal resource usage
  - Smart caching strategies
  - Background processing

### Content Compatibility
- **Universal Processing**: Works with all content types
  - Post content analysis
  - Page content processing
  - Custom post type support
  - Widget content analysis
- **Block Editor Support**: Full Gutenberg compatibility
  - Image block detection
  - Video block processing
  - Embed block recognition
  - Custom block support
- **Classic Editor**: Legacy editor compatibility
  - Shortcode processing
  - HTML tag detection
  - Mixed content support
  - Backward compatibility

### Reporting & Analytics
- **Comprehensive Reports**: Detailed media analytics
  - Post-level media breakdowns
  - Site-wide media statistics
  - Historical trend reports
  - Performance analytics
- **Export Capabilities**: Data export functionality
  - CSV export support
  - JSON data export
  - Historical data export
  - Custom report generation

### Technical Details
- **WordPress Compatibility**: 5.0+
- **PHP Compatibility**: 7.4+
- **Database**: Custom tables with proper indexing
- **Architecture**: Object-oriented with separation of concerns
- **Standards**: WordPress coding standards compliant
- **Translation Ready**: Full internationalization support
- **Performance**: Optimized for large-scale deployments

### Error Handling
- **Graceful Degradation**: Robust error management
  - Invalid content handling
  - Database error recovery
  - Processing error logging
  - Debug information system
- **Validation**: Comprehensive input validation
  - Content format validation
  - Database integrity checks
  - Settings validation
  - Data consistency verification

---

**Initial release of Medialytic - Advanced Media Analytics**

Medialytic provides comprehensive media analytics and counting for WordPress with automatic detection, historical tracking, and detailed insights. Built for content creators and site administrators who need detailed media usage analytics.