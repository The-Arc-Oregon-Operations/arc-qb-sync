# Claude Code Task: arc-qb-sync v2.1.0 → v2.2.0

## What you are doing and why

You are updating the arc-qb-sync WordPress plugin from v2.1.0 to v2.2.0. Read this entire prompt before writing any code. The sequence matters.

This session covers eight areas of work:

1. **Cleanup** — remove the deprecated `shortcodes-catalog.php`, move shared helper functions from the deprecated `courses.php` into `qb-api.php` where they belong, and clean up the stale deprecation comment in the main plugin file.
2. **Events shortcode renaming** — bring all event shortcodes in line with a consistent `event_` prefix convention. The site is live for Events, so every renamed shortcode must keep its old name as a working alias. No functional logic changes — aliases only.
3. **New Events shortcodes** — add named shortcodes for two FIDs that are already being fetched but have no named shortcode.
4. **Courses shortcode renaming** — rename `course_payment` to `course_base_rate`. Courses are not yet live so no aliases are needed.
5. **New course fields** — add five new QB fields to the sync and expose them as shortcodes. Add one constructed shortcode that builds a CTA URL from the record ID.
6. **FID 92 → post_name** — use the QB "Slug for Website" field as the WordPress post slug for all course CPT posts.
7. **Sync ghost removal** — when a full sync runs, any published course post whose QB record ID was not returned by the sync query must be demoted to draft. Never delete posts.
8. **Docs + version** — update `docs/webhook-zapier.md` to reflect the current endpoint, bump the version to 2.2.0, and write a complete CHANGELOG entry.

---

## Read these files first

Before writing a single line of code, read these files in full:

1. `arc-qb-sync/arc-qb-sync.php`
2. `arc-qb-sync/includes/qb-api.php`
3. `arc-qb-sync/includes/courses.php`
4. `arc-qb-sync/includes/sync-courses.php`
5. `arc-qb-sync/includes/shortcodes-courses.php`
6. `arc-qb-sync/includes/shortcodes-events.php`
7. `arc-qb-sync/includes/events.php`
8. `docs/webhook-zapier.md`

---

## Quickbase data model reference

### Events table (QB_TABLE_ID: `bc7mmze9k`)

No new QB fields are added in this session. FID 3 and FID 361 are already in the select array in `events.php` — confirm this before touching anything. The work here is shortcode registration only.

**Complete events shortcode map after 2.2.0:**

| FID | QB Field Label | New shortcode | Old shortcode (alias — keep) | Status |
|-----|---------------|---------------|------------------------------|--------|
| 3 | Record ID# | `event_id` | — | New (no prior shortcode) |
| 14 | Add Registration URL | `event_reg_url` | `add_registration_url` | Renamed |
| 19 | Event Title | `event_title` | — | Unchanged |
| 29 | Venue Name | `event_venue` | `venue_name` | Renamed |
| 45 | Event Date(s) | `event_dates` | — | Unchanged |
| 89 | Event Time | `event_time` | — | Unchanged |
| 267 | Flyer URL | `event_flyer` | `flyer_url` | Renamed |
| 271 | Instructor(s) | `event_instructors` | `instructors` | Renamed |
| 361 | Credit Hours | `event_length` | — | New (no prior shortcode) |
| 413 | Day(s) of Week | `event_days_of_week` | — | Unchanged |
| 440 | Event Description | `event_description` | — | Unchanged |
| 449 | Instructor Slugs | `event_instructor_slugs` | `instructor_slugs` | Renamed |
| 450 | Training Cost | `event_price` | `training_cost` | Renamed |
| 453 | Is Multi-Day | `event_is_multiday` | `is_multiday` | Renamed |
| 454 | Is Multi-Session | `event_is_multisession` | `is_multisession` | Renamed |
| 458 | Event Mode | `event_mode` | — | Unchanged |
| 461 | Featured Image URL | `event_image_url` | `featured_image_url` | Renamed |

**Generic/special shortcodes:**

| Old slug | New slug | Alias needed |
|----------|----------|--------------|
| `arc_training_field` | `event_field` | Yes — live site |
| `arc_trainer_title` | `loop_trainer_title` | Yes — live Elementor loops |

`loop_trainer_title` calls `get_the_title()` on the current post in an Elementor Loop context. It is not a QB field shortcode. The alias `arc_trainer_title` must remain registered and working.

---

### Course Catalog table (QB_COURSES_TABLE_ID: `bc7mmze9m`)

**Complete field list after 2.2.0:**

| FID | QB Field Label | QB Type | WP Meta Key | Shortcode | Notes |
|-----|---------------|---------|-------------|-----------|-------|
| 3 | Record ID# | Record ID | `_arc_qb_record_id` | `course_id` | Sync key |
| 6 | Course Title | Text | `post_title` | `course_title` | Unchanged |
| 14 | Hours of Instruction | Duration | `_arc_course_length_ms` (raw ms) + `_arc_course_length` (formatted) | `course_length` | Unchanged |
| 20 | Length Num | Numeric | `_arc_course_hours` | `course_hours` | **NEW** |
| 36 | Public Listing | Checkbox | → `post_status` | (none) | Visibility gate — not a shortcode |
| 39 | Base Rate | Currency | `_arc_course_base_rate` | `course_base_rate` | Renamed from `_arc_course_payment` / `course_payment` |
| 40 | Delivery Method | Text | `_arc_course_delivery_method` | `course_delivery_method` | Unchanged |
| 43 | Category | Text | `_arc_course_category` | `course_category` | Unchanged |
| 46 | Description Short | Multi-line Text | `post_excerpt` | `course_short_description` | Unchanged |
| 50 | Target Audience - English | Multi-line Text | `_arc_course_target_audience` | `course_target_audience` | Unchanged |
| 56 | Keywords / Tags | Multi-line Text | `course_tag` taxonomy + `_course_tag_slugs` | `course_tags` | Unchanged |
| 62 | Learning Objectives | Multi-line Text | `_arc_course_learning_objectives_html` | `course_learning_objectives` | Unchanged |
| 84 | Link to Course Overview Page | URL | `_arc_course_details_url` | `course_details_url` | **NEW** |
| 85 | Learning Objectives - HTML | Rich Text | `_arc_course_learning_objectives` | `course_learning_objectives2` | New named shortcode; testing name — both 62 and 85 remain |
| 88 | Featured Image URL | URL | `_arc_course_image_url` | `course_image_url` | Unchanged |
| 89 | Attribution | Text | `_arc_course_attribution` | `course_attribution` | **NEW** ⚠️ Note: FID 89 also exists in the Events table as "Event Time" — these are separate QB tables with independent FID numbering; code comments must make this clear |
| 90 | Use Attribution | Checkbox | `_arc_course_use_attribution` | `course_use_attribution` | **NEW** — checkbox; store as "1" or "0" |
| 92 | Slug for Website | Text | `_arc_course_slug` → also `post_name` | (none — drives post_name only) | **NEW** — not a shortcode |

**FID 7 is removed.** It was previously synced as `_arc_course_description_fallback`. Remove its `update_post_meta` call from `arc_qb_upsert_course()` and remove it from all select arrays and field maps. Do not remove the meta key from existing posts — just stop writing it.

**Constructed shortcode — not a QB field:**

`course_request_url` — builds the org training request CTA URL. Pattern:
```
https://thearcoregon.org/organization-training-request/?course=[_arc_qb_record_id]
```
Reads `_arc_qb_record_id` from post meta. No QB fetch needed.

**Generic shortcode rename:**

| Old slug | New slug | Alias needed |
|----------|----------|--------------|
| `arc_qb_course_field` | `course_field` | No — courses not live |

---

## Task 1: Cleanup

### 1a. Move helpers to qb-api.php

Move these three functions verbatim from `includes/courses.php` to the bottom of `includes/qb-api.php`. Do not change their signatures or logic:

- `arc_qb_get_course_field( $record, $field_id )`
- `arc_qb_parse_tags( $raw )`
- `arc_qb_format_duration( $ms )`

After moving, these functions must no longer be defined in `courses.php`. `sync-courses.php` and `shortcodes-courses.php` call these functions — after the move, they will resolve from `qb-api.php`, which is loaded first. Confirm the load order in `arc-qb-sync.php` before proceeding.

`courses.php` still contains `arc_qb_get_course()` and `arc_qb_get_public_courses()` — leave those in place. The file stays, just minus the three helpers.

### 1b. Update the deprecation comment in arc-qb-sync.php

Find the comment block that explains why `courses.php` is being kept despite being deprecated. Replace it with a simple inline comment:

```php
require_once ARC_QB_SYNC_DIR . 'includes/courses.php'; // Legacy v1 course fetch functions — kept for backward compatibility
```

### 1c. Remove shortcodes-catalog.php

1. Delete the file `arc-qb-sync/includes/shortcodes-catalog.php`.
2. Remove its `require_once` line from `arc-qb-sync/arc-qb-sync.php`.

---

## Task 2: Events shortcode renaming and new shortcodes

**File: `arc-qb-sync/includes/shortcodes-events.php`**

### 2a. Renames with aliases

For every rename, register the new canonical name AND the old name pointing to the same callback. Old names are deprecated aliases — add a comment on each alias line. Rename the PHP callback function itself to match the new canonical slug (snake_case, `arc_td_shortcode_[new_slug]`), but the alias `add_shortcode()` call still points to the renamed function.

Example pattern:
```php
function arc_td_shortcode_event_venue( $atts ) {
    $record = arc_td_get_current_record();
    if ( is_wp_error( $record ) ) return '';
    return esc_html( arc_td_get_field_value( $record, 29 ) );
}
add_shortcode( 'event_venue', 'arc_td_shortcode_event_venue' );
add_shortcode( 'venue_name',  'arc_td_shortcode_event_venue' ); // Deprecated alias — remove in future version
```

Apply this to all renamed shortcodes in the Events table above, and to `event_field` / `arc_training_field` and `loop_trainer_title` / `arc_trainer_title`.

### 2b. New shortcodes (no alias — these did not exist before)

Add these two shortcodes. Both FIDs are already in the select array in `events.php` — verify before adding them to be certain.

```php
// [event_id] — QB Record ID for the current event
function arc_td_shortcode_event_id( $atts ) {
    $record = arc_td_get_current_record();
    if ( is_wp_error( $record ) ) return '';
    return esc_html( arc_td_get_field_value( $record, 3 ) );
}
add_shortcode( 'event_id', 'arc_td_shortcode_event_id' );

// [event_length] — Credit Hours for the current event (FID 361)
function arc_td_shortcode_event_length( $atts ) {
    $record = arc_td_get_current_record();
    if ( is_wp_error( $record ) ) return '';
    return esc_html( arc_td_get_field_value( $record, 361 ) );
}
add_shortcode( 'event_length', 'arc_td_shortcode_event_length' );
```

---

## Task 3: Course sync — update field handling

**File: `arc-qb-sync/includes/sync-courses.php`**

### 3a. Update the field mapping comment block

At the top of the file, update the QB field mapping doc comment to reflect the full 2.2.0 field list. Add new FIDs, update FID 39 meta key, remove FID 7.

### 3b. Update QB select arrays

The select array appears in two functions: `arc_qb_fetch_course_record()` and `arc_qb_fetch_all_course_records()`. Update both to:

```php
'select' => array( 3, 6, 14, 20, 36, 39, 40, 43, 46, 50, 56, 62, 84, 85, 88, 89, 90, 92 ),
```

Changes from current: removed FID 7; added FIDs 20, 84, 89, 90, 92.

### 3c. Update arc_qb_upsert_course()

**Remove:** the line writing `_arc_course_description_fallback` from FID 7.

**Update:** the FID 39 meta key from `_arc_course_payment` to `_arc_course_base_rate`:
```php
update_post_meta( $post_id, '_arc_course_base_rate', sanitize_text_field( arc_qb_get_course_field( $record, 39 ) ) );
```

**Add FID 92 → post_name.** Extract the slug before building `$post_data` and include it in the array:
```php
$course_slug = sanitize_title( arc_qb_get_course_field( $record, 92 ) );
```
Add `'post_name' => $course_slug` to `$post_data`. This must be present for both insert and update paths.

**Add new meta writes** after the existing block:
```php
// FID 20 — Length Num (numeric hours value)
update_post_meta( $post_id, '_arc_course_hours', sanitize_text_field( arc_qb_get_course_field( $record, 20 ) ) );

// FID 84 — Link to Course Overview Page
update_post_meta( $post_id, '_arc_course_details_url', esc_url_raw( arc_qb_get_course_field( $record, 84 ) ) );

// FID 89 — Attribution
// Note: FID 89 in the Events table is "Event Time" — separate table, independent FID numbering.
update_post_meta( $post_id, '_arc_course_attribution', sanitize_text_field( arc_qb_get_course_field( $record, 89 ) ) );

// FID 90 — Use Attribution (checkbox)
$use_attribution = arc_qb_get_course_field( $record, 90 );
update_post_meta( $post_id, '_arc_course_use_attribution', ( $use_attribution && 'false' !== strtolower( (string) $use_attribution ) ) ? '1' : '0' );

// FID 92 — Slug for Website (also drives post_name — see $post_data above)
update_post_meta( $post_id, '_arc_course_slug', $course_slug );
```

### 3d. Sync ghost removal in arc_qb_sync_all_courses()

After the `foreach` loop that calls `arc_qb_upsert_course()` on each record, add a ghost-removal pass.

**Collect synced IDs inside the loop** — add this at the start of the foreach:
```php
$synced_ids = array();
// inside foreach:
$qb_id = intval( arc_qb_get_course_field( $record, 3 ) );
if ( $qb_id > 0 ) {
    $synced_ids[] = $qb_id;
}
```

**After the loop**, add:
```php
// Ghost removal: draft any published course posts not returned by this sync.
// These are courses that no longer appear in QB's public listing (deleted or unpublished).
// We draft rather than delete to preserve content history.
$ghosted = 0;
$published_courses = get_posts( array(
    'post_type'      => 'course',
    'post_status'    => 'publish',
    'numberposts'    => -1,
    'meta_key'       => '_arc_qb_record_id',
    'fields'         => 'ids',
) );

foreach ( $published_courses as $wp_post_id ) {
    $post_qb_id = intval( get_post_meta( $wp_post_id, '_arc_qb_record_id', true ) );
    if ( $post_qb_id > 0 && ! in_array( $post_qb_id, $synced_ids, true ) ) {
        wp_update_post( array(
            'ID'          => $wp_post_id,
            'post_status' => 'draft',
        ) );
        $ghosted++;
    }
}
```

**Update the return value** to include `ghosted`:
```php
return array(
    'synced'   => $synced,
    'errors'   => $errors,
    'ghosted'  => $ghosted,
    'messages' => $messages,
);
```

**Update the admin UI** in `arc_qb_render_sync_settings_page()` to display the ghosted count in the success notice. Example:
```php
printf(
    esc_html__( 'Sync complete. %d courses synced, %d drafted (removed from QB public listing).', 'arc-qb-sync' ),
    $sync_result['synced'],
    $sync_result['ghosted']
);
```

---

## Task 4: Course shortcodes update

**File: `arc-qb-sync/includes/shortcodes-courses.php`**

### 4a. Update the field → meta key map

This map is used by the generic `course_field` shortcode. Update it to reflect 2.2.0:

- Remove FID 7 entry
- Update FID 39: `'_arc_course_payment'` → `'_arc_course_base_rate'`
- Add new entries:
```php
20  => '_arc_course_hours',
84  => '_arc_course_details_url',
85  => '_arc_course_learning_objectives',   // meta key unchanged; shortcode name is course_learning_objectives2
89  => '_arc_course_attribution',
90  => '_arc_course_use_attribution',
```

### 4b. Register new named shortcodes

Add functions and registrations for:

| Shortcode | Meta key | Output |
|-----------|----------|--------|
| `course_id` | `_arc_qb_record_id` | `esc_html()` |
| `course_base_rate` | `_arc_course_base_rate` | `esc_html()` |
| `course_hours` | `_arc_course_hours` | `esc_html()` |
| `course_details_url` | `_arc_course_details_url` | `esc_url()` |
| `course_attribution` | `_arc_course_attribution` | `esc_html()` |
| `course_use_attribution` | `_arc_course_use_attribution` | Returns `"1"` or `"0"` — `esc_html()` |
| `course_learning_objectives2` | `_arc_course_learning_objectives` | `wp_kses_post( wpautop() )` |

All course shortcodes read from WP post meta via `get_post_meta( get_the_ID(), $meta_key, true )`. Follow the same pattern as existing shortcodes in this file.

### 4c. Register course_request_url

```php
/**
 * [course_request_url]
 * Returns the URL to the organization training request form pre-populated with this course.
 * Reads _arc_qb_record_id from WP post meta — no QB fetch required.
 */
function arc_qb_shortcode_course_request_url( $atts ) {
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return '';
    }
    $record_id = intval( get_post_meta( $post_id, '_arc_qb_record_id', true ) );
    if ( $record_id <= 0 ) {
        return '';
    }
    // Base URL for the organization training request form.
    // Update this constant if the form URL changes.
    $base_url = 'https://thearcoregon.org/organization-training-request/';
    return esc_url( add_query_arg( 'course', $record_id, $base_url ) );
}
add_shortcode( 'course_request_url', 'arc_qb_shortcode_course_request_url' );
```

### 4d. Rename generic shortcode

Register both names:
```php
add_shortcode( 'course_field',        'arc_qb_shortcode_course_field_generic' );
add_shortcode( 'arc_qb_course_field', 'arc_qb_shortcode_course_field_generic' ); // Deprecated alias
```

---

## Task 5: Update docs/webhook-zapier.md

Rewrite this file. The plugin no longer uses a `/bust-cache` endpoint. The guide should document the current `/sync-course` endpoint, which performs an incremental upsert when called by Zapier.

Cover:

1. **QB webhook setup** — create a webhook on the Course Catalog table, trigger on record save, POST to Zapier webhook URL
2. **Zapier Zap** — Trigger: Webhooks Catch Hook; Action: Webhooks POST to:
   - URL: `https://thearcoregon.org/wp-json/arc-qb-sync/v1/sync-course`
   - Header: `Authorization: Bearer [ARC_QB_CACHE_BUST_TOKEN]`
   - Body (JSON): `{"record_id": "{{3}}"}`
   - Confirm that the QB webhook payload includes field values and that Record ID# is accessible as `{{3}}` in Zapier's data mapper; add an intermediate QB lookup step if not
3. **What the endpoint does** — fetches the single record from QB by ID and upserts the WP post (create or update). If Public Listing = false, existing posts are demoted to draft.
4. **How to test** — save a record in QB, check Zapier task history, verify WP post was created or updated
5. **Important note** — the old `/bust-cache` endpoint no longer exists as of v2.2.0. Any existing Zaps pointing to it must be updated.

---

## Task 6: Version and CHANGELOG

**File: `arc-qb-sync/arc-qb-sync.php`**

Update both occurrences:
```php
 * Version:      2.2.0
```
```php
define( 'ARC_QB_SYNC_VERSION', '2.2.0' );
```

**File: `CHANGELOG.md`**

Prepend a new entry above the existing `[1.0.0]` entry. Do not modify any existing entries.

```markdown
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
```

---

## What NOT to do

- Do not remove deprecated alias shortcodes for Events — live pages depend on them
- Do not remove `courses.php` — the legacy v1 fetch functions inside it may still be in use externally
- Do not delete any WP posts during ghost removal — draft only, never delete
- Do not add `post_name` derivation from the post title — FID 92 is the only source for course slugs
- Do not add OOP classes — keep the functional style throughout
- Do not hardcode QB table IDs or auth tokens — always read from defined constants
- Do not expose FID 92 as a shortcode — it drives `post_name` only

---

## Verification checklist

Work through this list after completing all tasks.

1. **Cleanup:** `arc-qb-sync.php` has no `require_once` for `shortcodes-catalog.php`; `courses.php` require_once has a simple inline comment only
2. **Helpers:** `arc_qb_get_course_field`, `arc_qb_parse_tags`, `arc_qb_format_duration` are defined in `qb-api.php` and NOT defined in `courses.php`
3. **Events aliases:** every renamed event shortcode has both the new name and the old name registered; confirm `arc_trainer_title` and `loop_trainer_title` both resolve to the same function
4. **New event shortcodes:** `event_id` and `event_length` are registered; FIDs 3 and 361 are confirmed present in the select array in `events.php`
5. **Course select arrays:** both `arc_qb_fetch_course_record()` and `arc_qb_fetch_all_course_records()` select FIDs `3, 6, 14, 20, 36, 39, 40, 43, 46, 50, 56, 62, 84, 85, 88, 89, 90, 92`; FID 7 is absent from both
6. **Upsert:** `arc_qb_upsert_course()` sets `post_name` from FID 92; writes `_arc_course_base_rate` (not `_arc_course_payment`); writes all new meta keys; does NOT write `_arc_course_description_fallback`
7. **Ghost removal:** `arc_qb_sync_all_courses()` collects `$synced_ids` during the loop; runs ghost-removal pass after; returns `ghosted` key; admin UI displays drafted count
8. **course_request_url:** shortcode is registered; URL pattern is `https://thearcoregon.org/organization-training-request/?course=[record_id]`
9. **Course field map:** generic `course_field` shortcode map includes FIDs 20, 84, 89, 90; FID 39 maps to `_arc_course_base_rate`; FID 7 is absent
10. **course_learning_objectives2:** registered and reads from `_arc_course_learning_objectives` meta
11. **Docs:** `docs/webhook-zapier.md` references `/sync-course` endpoint with `record_id` in body; no reference to `/bust-cache`
12. **Version:** `2.2.0` in both plugin header `Version:` tag and `ARC_QB_SYNC_VERSION` constant
13. **CHANGELOG:** 2.2.0 entry is present and complete; prior entries are untouched
