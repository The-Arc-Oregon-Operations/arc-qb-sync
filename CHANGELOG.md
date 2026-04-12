# Changelog

## [3.1.1] — 2026-04-11

### Fixed
- Legacy `?event-id=NNNN` redirect disabled — `add_action` hook commented out in `cpt-events.php`. Was firing unconditionally once Event Sync populated the `arc_event` CPT, breaking existing `/training-details/` pages. Uncomment to restore when ready to cut over.

## [3.1.0] — 2026-04-11

### Changed
- Instructor shortcodes migrated to clean `instructor_*` prefix to match `course_*` and `event_*` conventions
- `arc_instructor_*` shortcodes remain registered as deprecated aliases — no breaking change for live pages

### Deprecated
- `[arc_instructor_id]`, `[arc_instructor_name]`, `[arc_instructor_first_name]`, `[arc_instructor_last_name]`, `[arc_instructor_title]`, `[arc_instructor_organization]`, `[arc_instructor_credentials]`, `[arc_instructor_slug]`, `[arc_instructor_bio]`, `[arc_instructor_headshot_url]`, `[arc_instructor_contact_url]`, `[arc_instructor_field]` — use `instructor_*` equivalents for all new work

## [3.0.0] — 2026-04-11

### Added
- Events CPT (`arc_event`): full sync engine, admin page, ghost removal, `[arc_event_*]` shortcodes reading WP post meta
- Instructors CPT (`instructor`): full sync engine, admin page, ghost removal, `[arc_instructor_*]` shortcodes
- Legacy URL redirect for Events: `?event-id=NNNN` → CPT permalink (301)
- Image Asset lookup field sync for Courses: `_arc_course_featured_image_url`, `_arc_course_hero_image_url`
- Image Asset lookup field sync for Events: `_arc_event_featured_image_url`, `_arc_event_hero_image_url`
- Image Asset lookup field sync for Instructors: `_arc_instructor_headshot_url` (headshot only; header image deferred)
- New course shortcodes: `[course_featured_image_url]`, `[course_hero_image_url]`
- `Arc Event Sync` and `Arc Instructor Sync` admin pages under WP Settings
- New wp-config.php constants: `QB_INSTRUCTORS_TABLE_ID`, `ARC_QB_COURSE_FEATURED_IMAGE_FID`, `ARC_QB_COURSE_HERO_IMAGE_FID`, `ARC_QB_EVENT_FEATURED_IMAGE_FID`, `ARC_QB_EVENT_HERO_IMAGE_FID`, `ARC_QB_INSTRUCTOR_PROFILE_FID`, `ARC_QB_EVENT_INSTRUCTOR1_NAME_FID`, `ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID`, `ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID`, `ARC_QB_EVENT_INSTRUCTOR2_NAME_FID`, `ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID`, `ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID`, `ARC_QB_EVENT_INSTRUCTOR3_NAME_FID`, `ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID`, `ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID`

### Changed
- Course CPT (`course`) registration migrated from ACF to plugin (`cpt-courses.php`)
  ⚠️ Requires removing ACF CPT registration before activating — see docs/setup.md
- Plugin version: 3.0.0

### Preserved (no breaking changes)
- All existing event shortcodes (`event_*`, deprecated aliases) unchanged — live pages not affected
- Existing `trainer` CPT unchanged — Elementor trainer loops continue working
- Existing `arc_training_field` / `event_field` generic shortcodes unchanged

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
- `shortcodes-catalog.php` — deprecated no-op since