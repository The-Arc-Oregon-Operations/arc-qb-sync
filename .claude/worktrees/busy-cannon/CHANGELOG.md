# Changelog

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
