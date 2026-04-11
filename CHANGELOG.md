# Changelog

## [2.2.0] — 2026-04-11

### Added
- New course fields synced from QB and exposed as shortcodes: FID 20 `course_hours`, FID 84 `course_details_url`, FID 89 `course_attribution`, FID 90 `course_use_attribution`
- FID 92 (Slug for Website) synced and used as `post_name` for all course CPT posts
- New course shortcodes: `course_id`, `course_base_rate`, `course_hours`, `course_details_url`, `course_attribution`, `course_use_attribution`, `course_learning_objectives2`
- New constructed shortcode `course_request_url` — builds org training request CTA URL from QB record ID
- New event shortcodes: `event_id` (FID 3), `event_length` (FID 361)
- Sync ghost removal: full sync now demotes published course posts to draft when their QB record ID is not returned by the sync query (course removed from QB public listing or deleted)
- Admin sync UI now reports count of posts drafted by ghost removal alongside created/updated counts

### Changed
- All event shortcodes renamed to consistent `event_` prefix convention; old names registered as deprecated aliases — no breaking change for live pages
- `course_payment` → `course_base_rate` (shortcode and meta key); QB field relabeled "Base Rate"
- FID 92 (Slug for Website) now drives `post_name` for course posts — URLs change from title-derived slugs to QB-managed slugs
- Generic event shortcode: `arc_training_field` → `event_field` (old name kept as alias)
- Generic course shortcode: `arc_qb_course_field` → `course_field`
- Loop context shortcode: `arc_trainer_title` → `loop_trainer_title` (old name kept as alias)

### Removed
- `shortcodes-catalog.php` — deprecated no-op since v2.0; removed
- FID 7 (Description + Learning Objectives) removed from sync; FID 85 (Learning Objectives - HTML) is the authoritative source

### Fixed
- Helper functions `arc_qb_get_course_field()`, `arc_qb_parse_tags()`, `arc_qb_format_duration()` moved from `courses.php` to `qb-api.php`
- `docs/webhook-zapier.md` updated to document current `/sync-course` endpoint (replaces obsolete `/bust-cache` documentation)

## [2.1.0] — 2026-04-09

(See prior session notes — no CHANGELOG entry was written at the time.)

## [1.0.0] — 2026-04-08

### Changed
- Plugin renamed from `arc-training-details` to `arc-qb-sync`
- Restructured from single-file to modular include-based architecture
- Shared QB API request logic extracted to `includes/qb-api.php`

### Added
- Courses module: single course detail page support via `?course-id=nnnn`
- Course Catalog module: `[course_catalog]` shortcode with filterable grid
- WP Transient caching for the course catalog (15-minute TTL)
- REST endpoint `POST /wp-json/arc-qb-sync/v1/bust-cache` for cache invalidation
- Client-side tag filter (course-catalog.js)
- `build.sh` packaging script
- `docs/` folder with setup, field mapping, and webhook documentation

### Preserved (no breaking changes)
- All v0.4.0 shortcode names and behavior unchanged
- Elementor trainer query hook unchanged

## [0.4.0] — prior

See legacy plugin file (`arc-training-details/arc-training-details.php`) for history.
