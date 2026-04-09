# Claude Code Task: Restructure and Extend arc-qb-sync Plugin (v1.0)

## What you are doing and why

You are restructuring this WordPress plugin repo and writing new PHP code to extend its capabilities. The current repo contains a single-file plugin (`arc-training-details/arc-training-details.php`, v0.4.0) that fetches event data from Quickbase and exposes it as shortcodes for Elementor pages on thearcoregon.org.

The goal of this session is:

1. **Restructure the repo** into a properly organized WordPress plugin development environment, renamed to `arc-qb-sync`.
2. **Migrate all existing code** into a modular file structure — without changing any behavior or shortcode names. Everything currently on the live `/training-details/` page must keep working.
3. **Add the Courses module** — new PHP code that fetches Course Catalog records from Quickbase, provides shortcodes for a course detail page, and provides a `[course_catalog]` shortcode that renders a filterable grid for the `/training` page.
4. **Add supporting infrastructure** — WP Transient caching for the catalog, a REST endpoint for cache invalidation, client-side JS for tag filtering, and a build script that packages the plugin as a zip for WordPress upload.

Read all of this prompt before writing any code. The sequence matters.

---

## Current state of this repo

```
arc-training-details/          ← repo root
  arc-training-details/        ← plugin folder (current)
    arc-training-details.php   ← the entire plugin, single file, v0.4.0
  README.md
  .gitignore
  LICENSE
```

Read `arc-training-details/arc-training-details.php` in full before proceeding. It contains:
- A QB API call function (`arc_td_get_current_record`) using `wp_remote_post`
- A field value helper (`arc_td_get_field_value`)
- Individual shortcode functions for each field
- An Elementor custom query hook for the trainer loop
- All shortcodes registered on `init`

---

## Target repo structure

```
arc-qb-sync/                          ← new plugin folder (installable)
  arc-qb-sync.php                     ← main file: plugin header + require_once chain
  includes/
    qb-api.php                        ← shared QB API request function
    events.php                        ← Events module (migrated from current plugin)
    courses.php                       ← Courses module (new)
    cache-rest.php                    ← REST endpoint for cache bust
    shortcodes-events.php             ← [event_*] shortcodes (migrated, unchanged)
    shortcodes-courses.php            ← [course_*] shortcodes (new)
    shortcodes-catalog.php            ← [course_catalog] grid shortcode (new)
  assets/
    js/
      course-catalog.js               ← client-side tag filter
    css/
      course-catalog.css              ← minimal grid layout styles
docs/
  setup.md                            ← wp-config.php constants + first-install steps
  field-mapping-events.md             ← Events table field IDs ↔ shortcodes
  field-mapping-courses.md            ← Course Catalog field IDs ↔ shortcodes
  webhook-zapier.md                   ← QB webhook + Zapier Zap configuration guide
build.sh                              ← packages arc-qb-sync/ into arc-qb-sync.zip
CHANGELOG.md
README.md
.gitignore
LICENSE                               ← move existing LICENSE here
```

The old `arc-training-details/` folder should remain in place during this session — do not delete it. The new `arc-qb-sync/` folder is created alongside it. Deletion of the old folder is a manual step done after the new plugin is verified on the live site.

---

## Quickbase data model

### App
- **App ID:** `bc7mmze9h`
- **Realm:** `otac.quickbase.com`

### Events table (existing — unchanged)
- **Table ID:** `bc7mmze9k` (set via `QB_TABLE_ID` constant in wp-config.php)
- This table powers the existing `/training-details/?event-id=nnnn` pages.
- All existing field IDs are documented in the current plugin file. Do not change them.

### Course Catalog table (new)
- **Table ID:** `bc7mmze9m` (set via new `QB_COURSES_TABLE_ID` constant)

**Fields used in this integration:**

| Field ID | Label | QB Type | Notes |
|---|---|---|---|
| 3 | Record ID# | Record ID | `?course-id=` URL param; filter key |
| 6 | Course Title | Text | Tile + detail page heading |
| 7 | Description+Learning Objectives | Text | Detail page fallback if field 85 empty |
| 14 | Hours of Instruction | Duration | Tile "Length" label |
| 36 | Public Listing | Checkbox | Visibility flag — TRUE = show on website |
| 39 | Payment | Currency | Detail page: base price for org delivery |
| 40 | Delivery Method | text-multiple-choice | Detail page |
| 43 | Category | text-multiple-choice | Detail page |
| 46 | Description, Short | text-multi-line | Tile preview + detail page intro |
| 50 | Target Audience - English | text-multi-line | Detail page |
| 56 | Keywords / Tags | Multi-line Text | Tag pills on tile; filter source ⚠️ see below |
| 62 | Learning Objectives | text-multi-line | Detail page (use if 85 empty) |
| 85 | Learning Objectives - HTML | Rich Text | Detail page (preferred) |
| 88 | Featured Image URL | URL | Tile image + detail page hero |

**⚠️ Tags field (field 56):** The output format from the QB API is not yet confirmed for this field type. Implement the tag parsing with a helper function that splits on `\n` (newline) as the first assumption, trims whitespace, and filters empty strings. Add a comment in the code noting that the delimiter may need adjustment after a live API test. Make the delimiter a named constant or variable (not hardcoded inline) so it is easy to change.

---

## wp-config.php constants

The plugin reads these constants. Do not hardcode values — always use `defined()` checks. Document all constants in `docs/setup.md`.

```php
// Existing (already in wp-config.php on the live site)
QB_REALM_HOST       // 'otac.quickbase.com'
QB_TABLE_ID         // 'bc7mmze9k'  — Events table
QB_USER_TOKEN       // [secret]

// New (must be added to wp-config.php before the Courses module works)
QB_COURSES_TABLE_ID     // 'bc7mmze9m'  — Course Catalog table
ARC_QB_CACHE_BUST_TOKEN // [shared secret] — validates Zapier → WP REST cache-bust requests
```

---

## File-by-file instructions

### `arc-qb-sync/arc-qb-sync.php`

Plugin header block:
```
Plugin Name:  Arc Oregon QB Sync
Description:  Integrates Quickbase Event Management with WordPress. Provides shortcodes for event detail pages and a public course catalog for The Arc Oregon.
Version:      1.0.0
Author:       Alan Lytle at The Arc Oregon
```

Define:
```php
define( 'ARC_QB_SYNC_VERSION', '1.0.0' );
define( 'ARC_QB_SYNC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARC_QB_SYNC_URL', plugin_dir_url( __FILE__ ) );
```

Then `require_once` all includes files in this order:
1. `qb-api.php`
2. `events.php`
3. `courses.php`
4. `cache-rest.php`
5. `shortcodes-events.php`
6. `shortcodes-courses.php`
7. `shortcodes-catalog.php`

---

### `includes/qb-api.php`

Extract the shared API logic from the current plugin. Provide one function:

```php
/**
 * Send a query to the Quickbase Records API.
 *
 * @param array $body  The request body (from, select, where, options, sortBy, etc.)
 * @return array|\WP_Error  Parsed 'data' array on success, WP_Error on failure.
 */
function arc_qb_request( array $body ) { ... }
```

This function:
- Checks that QB_REALM_HOST and QB_USER_TOKEN are defined; returns WP_Error if not
- Builds auth headers (same as current plugin)
- Calls `wp_remote_post` to `https://api.quickbase.com/v1/records/query`
- Validates response: checks for WP_Error, checks HTTP status is 200, checks `$data['data']` is a non-empty array
- Returns `$data['data']` (the array of records) on success
- Returns a `WP_Error` on any failure, with a descriptive message

Both `events.php` and `courses.php` call this function.

---

### `includes/events.php`

Migrate the event-fetching logic from the current plugin:
- `arc_td_has_quickbase_config()` — keep as-is (checks QB_REALM_HOST, QB_TABLE_ID, QB_USER_TOKEN)
- `arc_td_get_current_record()` — refactor to call `arc_qb_request()` instead of making its own `wp_remote_post` call. Preserve the static cache, the `?event-id=` URL param logic, and the trainer slug query var. Field select list stays identical.
- `arc_td_get_field_value()` — keep as-is

---

### `includes/shortcodes-events.php`

Move all shortcode functions and the `add_shortcode` registrations from the current plugin here, unchanged. Move the `elementor/query/trainers` action hook here too.

**All existing shortcode names must be preserved exactly:**
`event_title`, `event_dates`, `event_time`, `venue_name`, `instructors`, `training_cost`, `event_description`, `add_registration_url`, `event_days_of_week`, `event_mode`, `featured_image_url`, `flyer_url`, `instructor_slugs`, `is_multiday`, `is_multisession`, `arc_training_field`, `arc_trainer_title`

---

### `includes/courses.php`

Two public functions:

**1. `arc_qb_get_course()`** — Single course record lookup.

- Reads `?course-id=` from `$_GET`, intval, must be > 0
- Checks QB_REALM_HOST, QB_USER_TOKEN, QB_COURSES_TABLE_ID are defined
- Uses a static variable cache (same per-request pattern as events)
- Calls `arc_qb_request()` with: `from = QB_COURSES_TABLE_ID`, select = `[3, 6, 7, 14, 36, 39, 40, 43, 46, 50, 56, 62, 85, 88]`, where = `{3.EX.[course-id]}`, options top = 1
- Returns the single record array or WP_Error

**2. `arc_qb_get_public_courses()`** — Full catalog fetch with transient cache.

- Transient key: `arc_qb_public_courses`
- Check transient first; return cached value if present
- If cache miss: call `arc_qb_request()` with: `from = QB_COURSES_TABLE_ID`, select = `[3, 6, 14, 46, 56, 88]` (tile fields only — no need for full description in the catalog list), where = `{36.EX.true}`, sortBy = `[{fieldId: 6, order: 'ASC'}]`
- On success: `set_transient( 'arc_qb_public_courses', $courses, 15 * MINUTE_IN_SECONDS )`
- Return the array of records (may be empty array if no public courses)
- On API error: return WP_Error

**Helper: `arc_qb_get_course_field( $record, $field_id )`** — extracts `$record[(string)$field_id]['value']`, returns empty string if missing. Used by shortcodes-courses.php.

---

### `includes/cache-rest.php`

Register a REST route on `rest_api_init`:

- **Namespace:** `arc-qb-sync/v1`
- **Route:** `/bust-cache`
- **Method:** POST
- **Permission callback:** validates `Authorization` header equals `Bearer [ARC_QB_CACHE_BUST_TOKEN]`. Return `true` if valid, `WP_Error` with 401 if not. If `ARC_QB_CACHE_BUST_TOKEN` is not defined, deny all requests.
- **Callback:** deletes transient `arc_qb_public_courses`, returns `rest_ensure_response( ['success' => true, 'message' => 'Cache cleared.'] )`

---

### `includes/shortcodes-courses.php`

Register these shortcodes on `init`. Each reads from `arc_qb_get_course()`. Use `arc_qb_get_course_field()` to extract values.

| Shortcode | Field ID | Output treatment |
|---|---|---|
| `[course_title]` | 6 | `esc_html()` |
| `[course_short_description]` | 46 | `wp_kses_post( wpautop() )` |
| `[course_description]` | 85 | `wp_kses_post( wpautop() )` — fallback to field 7 if empty |
| `[course_length]` | 14 | `esc_html()` — QB Duration type, output as-is |
| `[course_tags]` | 56 | Parse using tag helper (see below), output as `<span class="arc-tag">` pills |
| `[course_image_url]` | 88 | `esc_url()` |
| `[course_delivery_method]` | 40 | `esc_html()` |
| `[course_target_audience]` | 50 | `wp_kses_post( wpautop() )` |
| `[course_category]` | 43 | `esc_html()` |
| `[course_payment]` | 39 | `esc_html()` — Currency field, QB returns numeric; format as needed |
| `[course_learning_objectives]` | 85 / 62 | Same fallback logic as `[course_description]` |

Also register the generic escape hatch (same pattern as events):
```
[arc_qb_course_field id="N" format="text|html"]
```

**Tag parsing helper** (define in courses.php, used by both shortcodes-courses.php and shortcodes-catalog.php):

```php
/**
 * Parse the Keywords / Tags field value into a clean array of tag strings.
 * ⚠️ Delimiter TBD — assumes newline. Change $delimiter if API returns differently.
 *
 * @param string $raw  Raw value from QB field 56.
 * @return string[]    Array of trimmed, non-empty tag strings.
 */
function arc_qb_parse_tags( $raw ) {
    $delimiter = "\n"; // TODO: verify against live QB API response for field 56
    $tags = array_filter(
        array_map( 'trim', explode( $delimiter, (string) $raw ) )
    );
    return array_values( $tags );
}
```

---

### `includes/shortcodes-catalog.php`

Register `[course_catalog]` on `init`.

The shortcode function:

1. Call `arc_qb_get_public_courses()`. If WP_Error or empty array, return a graceful message: `<p class="arc-catalog-empty">No courses are currently available. Please check back soon.</p>`

2. Extract all unique tags across all courses (loop through records, call `arc_qb_parse_tags()` on field 56 for each, merge, deduplicate, sort alphabetically).

3. Build the filter pill HTML:
```html
<div class="arc-catalog-filters" role="group" aria-label="Filter courses by topic">
  <button class="arc-filter-pill is-active" data-filter="all">All</button>
  <!-- one button per unique tag -->
  <button class="arc-filter-pill" data-filter="[tag-slug]">[Tag Label]</button>
</div>
```
Tag slug: `sanitize_title( $tag )` for the data attribute; original tag text for the label.

4. Build the course grid HTML:
```html
<div class="arc-catalog-grid">
  <!-- one tile per course -->
  <div class="arc-catalog-tile" data-tags="[comma-separated tag slugs]">
    <div class="arc-catalog-tile__image">
      <img src="[field 88]" alt="[field 6]" loading="lazy">
    </div>
    <div class="arc-catalog-tile__body">
      <h3 class="arc-catalog-tile__title">[field 6]</h3>
      <p class="arc-catalog-tile__length">[field 14]</p>
      <div class="arc-catalog-tile__tags">
        <span class="arc-tag">[tag]</span> ...
      </div>
      <a href="/course-catalog/?course-id=[field 3]" class="arc-catalog-tile__cta">Learn More</a>
    </div>
  </div>
</div>
```

5. Embed course data as JSON for the JS:
```html
<script id="arc-catalog-data" type="application/json">
  [json_encode of course array — include fields 3, 6, 14, 46, 56, 88 only]
</script>
```

6. Enqueue `course-catalog.js` and `course-catalog.css` using `wp_enqueue_script` / `wp_enqueue_style` with `ARC_QB_SYNC_URL` and `ARC_QB_SYNC_VERSION`. The JS should be enqueued in the footer.

7. Return the concatenated HTML string (filter pills + grid + JSON script block).

---

### `assets/js/course-catalog.js`

Vanilla JS (no jQuery dependency). Runs on `DOMContentLoaded`.

Logic:
1. Read and parse the JSON from `#arc-catalog-data`.
2. Attach click handlers to all `.arc-filter-pill` buttons.
3. On pill click:
   - Remove `is-active` from all pills; add to clicked pill.
   - If `data-filter === 'all'`: show all `.arc-catalog-tile` elements.
   - Otherwise: for each tile, check if its `data-tags` attribute (comma-separated slugs) includes the clicked filter value. Show matching tiles, hide non-matching (`display: none` / remove class).
4. "Show" / "hide" via a CSS class (`arc-tile--hidden`) rather than inline styles, so Elementor can override if needed.

---

### `assets/css/course-catalog.css`

Minimal. Elementor handles most visual styling. Provide only structural layout:

```css
/* Arc Oregon Course Catalog — structural styles */

.arc-catalog-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.arc-filter-pill {
    cursor: pointer;
    /* Visual styling intentionally left to Elementor/theme */
}

.arc-filter-pill.is-active {
    /* Indicate active state — override in Elementor as needed */
    font-weight: bold;
}

.arc-catalog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.arc-catalog-tile {
    display: flex;
    flex-direction: column;
}

.arc-catalog-tile__image img {
    width: 100%;
    aspect-ratio: 1 / 1;
    object-fit: cover;
    display: block;
}

.arc-catalog-tile__body {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.arc-catalog-tile__cta {
    margin-top: auto;
}

.arc-catalog-tile__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-bottom: 0.75rem;
}

.arc-tile--hidden {
    display: none !important;
}
```

---

### `build.sh`

```bash
#!/bin/bash
# Packages the arc-qb-sync plugin for WordPress installation.
# Usage: ./build.sh (run from repo root)
# Output: arc-qb-sync.zip

set -e
PLUGIN_DIR="arc-qb-sync"
OUTPUT="arc-qb-sync.zip"

if [ ! -d "$PLUGIN_DIR" ]; then
  echo "Error: $PLUGIN_DIR directory not found. Run from repo root."
  exit 1
fi

echo "Building $OUTPUT..."
rm -f "$OUTPUT"
zip -r "$OUTPUT" "$PLUGIN_DIR/" \
  --exclude "*.git*" \
  --exclude "*/.DS_Store" \
  --exclude "*/Thumbs.db" \
  --exclude "*.map"

SIZE=$(du -sh "$OUTPUT" | cut -f1)
echo "Done: $OUTPUT ($SIZE)"
echo "Upload at: WP Admin > Plugins > Add New > Upload Plugin"
```

Make it executable after creating it.

---

### `docs/setup.md`

Document:
- All 5 wp-config.php constants with descriptions, which are existing vs. new, and where to find the values in QB
- First-install steps (deactivate old plugin, upload zip, activate new plugin)
- How to verify the Events module is working (load a training-details URL)
- How to verify the Courses module is working (load a course-catalog URL with a valid course-id)

### `docs/field-mapping-events.md`

A reference table: QB field ID → field label → shortcode name → notes. Pull from the current plugin's field list. This is documentation, not code.

### `docs/field-mapping-courses.md`

Same format. Course Catalog field IDs from the table in this prompt.

### `docs/webhook-zapier.md`

Step-by-step guide:
1. QB: Create webhook on Course Catalog table, trigger on record save, post to Zapier webhook URL
2. Zapier: Trigger = Webhooks Catch Hook; Action = Webhooks POST to `https://thearcoregon.org/wp-json/arc-qb-sync/v1/bust-cache` with `Authorization: Bearer [ARC_QB_CACHE_BUST_TOKEN]` header
3. How to test: trigger a QB save, check Zapier task history, verify transient was cleared

---

### `CHANGELOG.md`

```
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

See legacy plugin file for history.
```

### `README.md`

Replace the existing minimal README with:
- Plugin name and purpose
- Shortcodes: two tables (Events module, Courses module)
- `[course_catalog]` usage
- Build instructions (`./build.sh`)
- Deploy instructions (zip upload to WP)
- wp-config.php constants reference (link to `docs/setup.md`)

---

## Verification checklist

After completing all files, do the following checks before finishing:

1. Read `arc-qb-sync/arc-qb-sync.php` and confirm all 7 includes files are required in the correct order.
2. Confirm no shortcode names from v0.4.0 were changed or removed.
3. Confirm `arc_qb_parse_tags()` is defined in `courses.php` and called in both `shortcodes-courses.php` and `shortcodes-catalog.php`.
4. Confirm `ARC_QB_SYNC_DIR` and `ARC_QB_SYNC_URL` constants are used in includes and asset enqueueing respectively.
5. Confirm `build.sh` is present at the repo root and references `arc-qb-sync/` correctly.
6. Confirm the old `arc-training-details/` folder was NOT deleted.
7. Confirm `docs/setup.md` documents all 5 wp-config.php constants.

---

## What NOT to do

- Do not delete or modify `arc-training-details/arc-training-details.php`.
- Do not rename existing shortcodes.
- Do not add OOP classes — keep the functional style consistent with the existing plugin.
- Do not hardcode QB table IDs, tokens, or URLs — always read from defined constants.
- Do not add jQuery as a dependency — `course-catalog.js` must be vanilla JS.
- Do not create a README.txt in WordPress plugin directory format (this is a private plugin).
