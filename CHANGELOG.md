# Changelog

## [3.6.2] — 2026-04-20

### Changed
- `elementor-dynamic-tags.php`: Elementor dropdown labels cleaned up after human review of the field reference doc
  - Course Meta: `Short description (HTML)` → `Description (HTML)`; `Learning objectives / full description (HTML)` → `Learning Obj (HTML)`; `Length (formatted, e.g. "6.5 hours")` → `Length`; `Hours (numeric)` → `Hours`; `Target audience (HTML)` → `Target Audience (HTML)`; `Tags (HTML pill spans)` → `Tags`
  - Instructor Meta: `Title / position` → `Title`; `contact_url` entry removed (field not yet implemented — will be re-added in a future release)

---

## [3.6.0] — 2026-04-20

### Added
- `includes/elementor-dynamic-tags.php`: three custom Elementor dynamic tags — **Event Meta**, **Course Meta**, and **Instructor Meta** — registered in the "Post" group of Elementor's dynamic tag picker
  - Each tag exposes a labeled **Field** dropdown populated with all user-facing display fields for the respective CPT (`arc_event`, `course`, `instructor`)
  - Render delegates to the existing `[event_*]` / `[course_*]` / `[instructor_*]` shortcodes, keeping escaping and post-type gating centralized
  - Graceful degradation: file returns early if Elementor (or `\Elementor\Core\DynamicTags\Tag`) is not loaded
  - Fields excluded from dropdowns by design: internal QB ID / slug fields, boolean flags, and legacy bridge fields
- `docs/elementor-field-keys.md`: added header note and per-CPT callout explaining that dynamic tags now replace the manual Custom Key workflow

### Changed
- Admin sync pages moved from **WP Admin → Tools** to submenus under their respective CPT menu entries:
  - **QB Event Sync** now lives under Events (matching the "QB Event Sync" label preference)
  - **QB Course Sync** now lives under Courses
  - **QB Instructor Sync** now lives under Instructors
  - Matches the pattern used in `summit-qb-sync` (Sync under Sessions / Presenters)
  - No slug or callback changes — bookmarked admin URLs with `page=arc-qb-*-sync` still resolve correctly

### Why
- Elementor Pro's built-in "Post Custom Field" tag does not reliably populate its Key dropdown in Theme Builder template context (empty even with a real preview post and populated meta)
- This ports the same fix shipped in `summit-qb-sync` v1.8.0 to the arc-qb-sync plugin

---

## [3.6.1] — 2026-04-20

### Fixed
- `course` CPT rewrite slug changed from `course` to `courses` in `cpt-courses.php`. The slug `course` conflicted with any WordPress page whose slug started with `course` (e.g. `course-catalog`), causing those pages to redirect to their old top-level URL even after a parent page was correctly set. Individual course posts move from `/course/[slug]/` to `/courses/[slug]/`; a 301 backward-compat redirect in `cpt-courses.php` covers any existing links. **Flush permalinks after deploying.**
- `elementor-dynamic-tags.php` load deferred to `plugins_loaded` priority 20 in `arc-qb-sync.php`. Loading the file at plugin-include time caused the `class_exists('Elementor\Plugin')` guard to return false and bail out silently — "arc" sorts before "elementor" alphabetically, so arc-qb-sync is included before Elementor registers its autoloader. Deferring to `plugins_loaded` priority 20 ensures Elementor (priority 10) has fully initialized before the class checks run.

---

## [3.5.0] — 2026-04-12

### Added
- `_arc_event_schedule` computed meta field: days_of_week + ` • ` + dates (FIDs 413 + 45); stored on every event sync; `array_filter` omits bullet if either value is empty
- `[event_schedule]` shortcode reads `_arc_event_schedule` from post meta; no deprecated alias (new field)
- `includes/elementor-queries.php`: Elementor Pro Loop Grid custom query hook `arc_event_instructors` — queries `instructor` CPT posts whose slugs match `_arc_event_instructor_slugs_legacy` on the current `arc_event`; set Loop Grid → Query → Query ID to `arc_event_instructors`

### Removed
- Instructor slot fields (instructor1/2/3 name, headshot URL, headshot alt) removed from sync and shortcodes — 9 meta fields, 18 `add_shortcode` calls, 9 callbacks, 9 `define()` constants — never deployed in any live template; cleanly cut

---

## [3.3.0] — 2026-04-13

### Added
- Instructors: FID 29 (Pronouns) — synced to `_arc_instructor_pronouns`; shortcode `[instructor_pronouns]`
- Instructors: FID 31 (Trainer Role(s)) — synced to `_arc_instructor_trainer_roles`; shortcode `[instructor_trainer_roles]`
- Courses: FID 109 (Offer delivery Online) — synced to `_arc_course_offers_online`; shortcode `[course_offers_online]`
- Courses: FID 110 (Offer delivery In-Person) — synced to `_arc_course_offers_inperson`; shortcode `[course_offers_inperson]`

### Fixed
- `[instructor_bio]` shortcode no longer applies `wpautop()` to bio content that arrives pre-formatted from QB
- Generic instructor field shortcode (`[arc_instructor_field format="html"]`) likewise no longer double-applies `wpautop()`

### Docs
- `docs/field-mapping-instructors.md`: added FID 29 and FID 31; notes on pre-formatted HTML fields and concatenated Instructor Name
- `docs/field-mapping-courses.md`: added FID 109 and FID 110; updated synced FID list and generic shortcode map

---

## [3.2.1] — 2026-04-12

### Fixed
- Resolved all unresolved git merge conflicts in `sync-courses.php`, `sync-instructors.php`, `CHANGELOG.md`, and `docs/setup.md`
- `arc_qb_request()` no longer treats a valid empty QB response as an error
- `[course_request_url]` shortcode now uses the shared context resolver — works correctly on legacy `?course-id=` pages
- `[course_field]` generic shortcode field map updated with FIDs 92, 94, 96
- Event `post_name` is now set only on insert, not on update — prevents URL changes when an event title is corrected in QB
- `ARC_QB_INSTRUCTOR_PROFILE_FID` constant now used consistently in `sync-instructors.php`
- Section comment numbering corrected in `cpt-courses.php`
- Admin page path updated in `docs/setup.md` (Settings → Tools)

### Changed
- `error_log()` calls added at all sync failure points for WP_DEBUG_LOG visibility

### Docs
- `docs/field-mapping-courses.md` rewritten to reflect current field set and shortcode names
- `docs/setup.md` merge conflicts resolved; clean six-constant wp-config block

---

## [3.2.0] — 2026-04-12

### Changed
- `event_*` shortcode names are now the primary CPT event shortcodes (previously `arc_event_*`)
- `arc_event_*` names remain registered as deprecated aliases — no breaking change for any templates already using them
- `[event_flyer_url]` is the correct primary name for the flyer URL shortcode (the old legacy name `[event_flyer]` no longer exists in either branch)
- `shortcodes-events-cpt.php`: added `arc_qb_get_event_post_id()` context resolver — CPT event shortcodes now work on both native CPT pages and legacy `?event-id=` pages during the transition period
- `shortcodes-events.php`: removed all `event_*` shortcode registrations; legacy QB-fetch functions preserved but deregistered; pre-v2.2.0 aliases (`venue_name`, `instructors`, etc.) unchanged

### Fixed
- CPT event shortcodes previously used bare `get_the_ID()` with no fallback; they now use the context resolver and work correctly on legacy URL-pattern pages

---

## [3.1.2] — 2026-04-12

### Fixed
- Hardcoded all image and instructor lookup FIDs in plugin files; removed dependency on
  `ARC_QB_COURSE_FEATURED_IMAGE_FID`, `ARC_QB_COURSE_HERO_IMAGE_FID`,
  `ARC_QB_EVENT_FEATURED_IMAGE_FID`, `ARC_QB_EVENT_HERO_IMAGE_FID`,
  `ARC_QB_EVENT_INSTRUCTOR*_FID` constants, and `ARC_QB_INSTRUCTOR_PROFILE_FID`
  from wp-config.php. These constants are no longer read or required.
  On PHP 8, undefined constants cause a fatal error on load — this was the
  root cause of the broken build in v3.1.1.
- Removed `defined()` guards from sync-courses.php image FID calls (no longer needed).
- Updated docs/setup.md to remove FID constant requirements from wp-config block.
- Updated field mapping docs to use literal FIDs instead of constant names.

---

## [3.1.1] — 2026-04-11

### Fixed
- Legacy `?event-id=NNNN` redirect disabled — `add_action` hook commented out in `cpt-events.php`. Was firing unconditionally once Event Sync populated the `arc_event` CPT, breaking existing `/training-details/` pages. Uncomment to restore when ready to cut over.

---

## [3.1.0] — 2026-04-11

### Changed
- Instructor shortcodes migrated to clean `instructor_*` prefix to match `course_*` and `event_*` conventions
- `arc_instructor_*` shortcodes remain registered as deprecated aliases — no breaking change for live pages

### Deprecated
- `[arc_instructor_id]`, `[arc_instructor_name]`, `[arc_instructor_first_name]`, `[arc_instructor_last_name]`, `[arc_instructor_title]`, `[arc_instructor_organization]`, `[arc_instructor_credentials]`, `[arc_instructor_slug]`, `[arc_instructor_bio]`, `[arc_instructor_headshot_url]`, `[arc_instructor_contact_url]`, `[arc_instructor_field]` — use `instructor_*` equivalents for all new work

---

## [3.0.2] — 2026-04-11

### Changed
- Admin pages moved from Settings to Tools menu (`add_management_page`)
- Pages renamed: QB Course Sync, QB Event Sync, QB Instructor Sync
- Language throughout admin pages updated: "Arc QB Sync / Arc Event Sync / Arc Instructor Sync" → "QB Course Sync / QB Event Sync / QB Instructor Sync"

### Added
- Preview Sync button on all three admin pages — fetches QB records and compares with WP state, reports counts (new / update / ghost) without writing anything

---

## [3.0.1] — 2026-04-11

### Changed
- All Quickbase field IDs (FIDs) moved from wp-config.php into the plugin — `sync-events.php`, `sync-courses.php`, `sync-instructors.php` now define their own FID constants
- `sync-courses.php` select arrays simplified: image FID conditionals removed now that the constants are always defined
- `docs/setup.md` rewritten with a clean 6-constant wp-config block (credentials and table IDs only); FID tables removed

### Fixed
- `arc-qb-sync.php` completed — was truncated mid-write during the v3.0.0 session, missing the Events CPT, Instructors CPT, and REST endpoint require_once lines

---

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

---

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
- `shortcodes-catalog.php` — deprecated no-op since v2.0; removed entirely
