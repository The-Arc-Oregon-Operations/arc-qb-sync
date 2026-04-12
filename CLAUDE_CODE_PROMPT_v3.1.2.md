# Claude Code Task: Remove FID Constants from wp-config Dependency (v3.1.2)

## What you are doing and why

The plugin currently expects several QB field ID constants to be defined in `wp-config.php`:

- `ARC_QB_EVENT_FEATURED_IMAGE_FID`, `ARC_QB_EVENT_HERO_IMAGE_FID` (events image lookups)
- Nine instructor slot FIDs on the Events table (`ARC_QB_EVENT_INSTRUCTOR1_*`, `ARC_QB_EVENT_INSTRUCTOR2_*`, `ARC_QB_EVENT_INSTRUCTOR3_*`)
- `ARC_QB_INSTRUCTOR_PROFILE_FID` (headshot lookup on Instructors table)
- `ARC_QB_COURSE_FEATURED_IMAGE_FID`, `ARC_QB_COURSE_HERO_IMAGE_FID` (course image lookups)

These are Quickbase field IDs — stable schema values that belong in the plugin, not in server configuration. The spec has moved on: **no FID constants in wp-config.php**. Table IDs (`QB_TABLE_ID`, `QB_COURSES_TABLE_ID`, `QB_INSTRUCTORS_TABLE_ID`) remain in wp-config.php because they are environment-specific.

The current code in `sync-events.php` uses the event and instructor FID constants **without any `defined()` guard**, which causes a PHP fatal error on load if they are not in wp-config.php. `sync-instructors.php` has the same problem for `ARC_QB_INSTRUCTOR_PROFILE_FID`. `sync-courses.php` uses `defined()` guards so it degrades silently rather than crashing, but the approach is still wrong.

The fix is to hardcode all FIDs as literal integers in the plugin files and remove all dependency on these wp-config constants. Update the docs to match.

Read all of this prompt before making any changes. The version bump and CHANGELOG entry come last.

---

## Files to change

### 1. `arc-qb-sync/includes/sync-events.php`

**In `arc_qb_fetch_all_event_records()`**, replace the unguarded constant block (currently lines ~71–84):

```php
// Image lookup FIDs — all non-zero (constants are fully defined).
$select[] = ARC_QB_EVENT_FEATURED_IMAGE_FID; // 464
$select[] = ARC_QB_EVENT_HERO_IMAGE_FID;     // 466

// Instructor slot FIDs — all non-zero (constants are fully defined).
$select[] = ARC_QB_EVENT_INSTRUCTOR1_NAME_FID;          // 482
$select[] = ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID;      // 483
$select[] = ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID;  // 484
$select[] = ARC_QB_EVENT_INSTRUCTOR2_NAME_FID;          // 486
$select[] = ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID;      // 487
$select[] = ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID;  // 494
$select[] = ARC_QB_EVENT_INSTRUCTOR3_NAME_FID;          // 491
$select[] = ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID;      // 492
$select[] = ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID;  // 493
```

Replace with literal integers appended directly to the base `$select` array:

```php
// Image and instructor lookup FIDs — hardcoded (stable QB schema, not wp-config).
$select = array_merge( $select, array( 464, 466, 482, 483, 484, 486, 487, 491, 492, 493, 494 ) );
```

**In `arc_qb_upsert_event()`**, replace the constant references in the `update_post_meta` calls with literal integers:

| Replace | With |
|---|---|
| `ARC_QB_EVENT_FEATURED_IMAGE_FID` | `464` |
| `ARC_QB_EVENT_HERO_IMAGE_FID` | `466` |
| `ARC_QB_EVENT_INSTRUCTOR1_NAME_FID` | `482` |
| `ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID` | `483` |
| `ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID` | `484` |
| `ARC_QB_EVENT_INSTRUCTOR2_NAME_FID` | `486` |
| `ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID` | `487` |
| `ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID` | `494` |
| `ARC_QB_EVENT_INSTRUCTOR3_NAME_FID` | `491` |
| `ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID` | `492` |
| `ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID` | `493` |

Also update the docblock comment at the top of the file to remove the constant notation. Change lines like:

```
 *   ARC_QB_EVENT_FEATURED_IMAGE_FID (464) → _arc_event_featured_image_url
```

to:

```
 *   464 → _arc_event_featured_image_url
```

Do the same for all 11 FID entries in the docblock. Remove any inline comment that says "constants are fully defined."

---

### 2. `arc-qb-sync/includes/sync-instructors.php`

**Replace all three uses of `ARC_QB_INSTRUCTOR_PROFILE_FID`** with the literal `15`.

There are two: one in the `$select` array inside `arc_qb_fetch_all_instructor_records()`, and one in `update_post_meta` inside `arc_qb_upsert_instructor()`.

Also update the comment on the line that currently reads:

```php
// ARC_QB_INSTRUCTOR_PROFILE_FID (15) — Headshot URL lookup — defined in wp-config.php.
```

Change it to:

```php
// FID 15 — Headshot URL lookup (Image Assets table).
```

And update the docblock at the top:

```
 *   ARC_QB_INSTRUCTOR_PROFILE_FID      (15) → _arc_instructor_headshot_url
```

→

```
 *   15 → _arc_instructor_headshot_url
```

---

### 3. `arc-qb-sync/includes/sync-courses.php`

There are three locations where `ARC_QB_COURSE_FEATURED_IMAGE_FID` and `ARC_QB_COURSE_HERO_IMAGE_FID` appear behind `defined()` guards: twice in `$select` array building (in `arc_qb_get_course()` and `arc_qb_fetch_all_course_records()`), and once in `arc_qb_upsert_course()`.

**In both `$select` array locations**, replace:

```php
if ( defined( 'ARC_QB_COURSE_FEATURED_IMAGE_FID' ) && ARC_QB_COURSE_FEATURED_IMAGE_FID > 0 ) {
    $select[] = ARC_QB_COURSE_FEATURED_IMAGE_FID;
}
if ( defined( 'ARC_QB_COURSE_HERO_IMAGE_FID' ) && ARC_QB_COURSE_HERO_IMAGE_FID > 0 ) {
    $select[] = ARC_QB_COURSE_HERO_IMAGE_FID;
}
```

With:

```php
$select[] = 94; // Featured Image URL [lookup]
$select[] = 96; // Hero Image URL [lookup]
```

**In `arc_qb_upsert_course()`**, replace:

```php
// Image Asset lookup fields (FIDs defined in wp-config.php after QB Build Spec v3.0)
if ( defined( 'ARC_QB_COURSE_FEATURED_IMAGE_FID' ) && ARC_QB_COURSE_FEATURED_IMAGE_FID > 0 ) {
    update_post_meta( $post_id, '_arc_course_featured_image_url',
        esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_COURSE_FEATURED_IMAGE_FID ) ) );
}
if ( defined( 'ARC_QB_COURSE_HERO_IMAGE_FID' ) && ARC_QB_COURSE_HERO_IMAGE_FID > 0 ) {
    update_post_meta( $post_id, '_arc_course_hero_image_url',
        esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_COURSE_HERO_IMAGE_FID ) ) );
}
```

With:

```php
// Image Asset lookup FIDs — hardcoded (stable QB schema).
update_post_meta( $post_id, '_arc_course_featured_image_url',
    esc_url_raw( arc_qb_get_course_field( $record, 94 ) ) );
update_post_meta( $post_id, '_arc_course_hero_image_url',
    esc_url_raw( arc_qb_get_course_field( $record, 96 ) ) );
```

---

### 4. `docs/setup.md`

Remove the entire **"New constants (required for v3.0.0 features)"** section. That includes the Instructors table subsection, both Image Asset FID tables (Courses and Events), the Instructor lookup FID table, and the Instructor profile FID row.

**Keep:** `QB_INSTRUCTORS_TABLE_ID` — it is a table ID (environment-specific), not a FID. Move it up into the "New constants (required for Courses module)" section or create a simple "New constants (required for v3.0.0)" block that contains only `QB_INSTRUCTORS_TABLE_ID`.

Update the **Example wp-config.php block** to remove all `ARC_QB_*` FID constant lines. The block should include only:

```php
// Arc QB Sync — Quickbase integration
define( 'QB_REALM_HOST',           'otac.quickbase.com' );
define( 'QB_TABLE_ID',             'bc7mmze9k' );   // Training Events table
define( 'QB_USER_TOKEN',           'your-token-here' );
define( 'QB_COURSES_TABLE_ID',     'bc7mmze9m' );   // Course Catalog table
define( 'ARC_QB_CACHE_BUST_TOKEN', 'your-random-secret-here' );
define( 'QB_INSTRUCTORS_TABLE_ID', 'bvx9ae9x8' );   // Instructors table
```

Add a note below the block: "Field IDs (FIDs) are hardcoded in the plugin. No FID constants are required in wp-config.php."

---

### 5. `docs/field-mapping-events.md`

In the **Events CPT Shortcodes** table, update the "Source FID" column entries that currently show constant names:

| Change from | Change to |
|---|---|
| `` `ARC_QB_EVENT_FEATURED_IMAGE_FID` (464) `` | `464` |
| `` `ARC_QB_EVENT_HERO_IMAGE_FID` (466) `` | `466` |
| `` `ARC_QB_EVENT_INSTRUCTOR1_NAME_FID` (482) `` | `482` |
| `` `ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID` (483) `` | `483` |
| `` `ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID` (484) `` | `484` |
| `` `ARC_QB_EVENT_INSTRUCTOR2_NAME_FID` (486) `` | `486` |
| `` `ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID` (487) `` | `487` |
| `` `ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID` (494) `` | `494` |
| `` `ARC_QB_EVENT_INSTRUCTOR3_NAME_FID` (491) `` | `491` |
| `` `ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID` (492) `` | `492` |
| `` `ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID` (493) `` | `493` |

---

### 6. `docs/field-mapping-instructors.md`

In the **Field Mapping** table, update the `ARC_QB_INSTRUCTOR_PROFILE_FID` row. Change the "QB FID Constant" column entry from `` `ARC_QB_INSTRUCTOR_PROFILE_FID` `` to just the FID integer `15`. Update the table header for that column if appropriate.

Remove the note that reads:
> **Note:** `ARC_QB_INSTRUCTOR_PROFILE_FID` (15) is defined in `wp-config.php`. All other `ARC_QB_INSTRUCTOR_FID_*` constants are defined at the top of `sync-instructors.php`.

Replace with:
> **Note:** All `ARC_QB_INSTRUCTOR_FID_*` constants are defined at the top of `sync-instructors.php`. FIDs are hardcoded in the plugin — none are required in `wp-config.php`.

---

### 7. Version bump and CHANGELOG

**`arc-qb-sync/arc-qb-sync.php`:** Change version to `3.1.2` in both the plugin header and the `ARC_QB_SYNC_VERSION` constant.

**`CHANGELOG.md`:** Add at the top:

```
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
```

---

## Rebuild

After all file edits are complete, run `./build.sh` from the repo root. Rename the output:

```bash
mv arc-qb-sync.zip arc-qb-sync-v3.1.2.zip
```

---

## Verification

After completing all edits:

1. Grep for any remaining `ARC_QB_EVENT_INSTRUCTOR` or `ARC_QB_EVENT_FEATURED` or `ARC_QB_EVENT_HERO` in all `.php` files — expect zero results.
2. Grep for `ARC_QB_INSTRUCTOR_PROFILE_FID` in all `.php` files — expect zero results.
3. Grep for `ARC_QB_COURSE_FEATURED_IMAGE_FID` or `ARC_QB_COURSE_HERO_IMAGE_FID` in all `.php` files — expect zero results.
4. Grep for `defined(` in `sync-events.php` — should only find the `ABSPATH` guard at the top, nothing else.
5. Confirm `arc-qb-sync-v3.1.2.zip` exists at the repo root.
6. Confirm `CHANGELOG.md` has the `[3.1.2]` entry at the top.

## What NOT to do

- Do not remove `QB_TABLE_ID`, `QB_COURSES_TABLE_ID`, `QB_INSTRUCTORS_TABLE_ID`, `QB_REALM_HOST`, `QB_USER_TOKEN`, or `ARC_QB_CACHE_BUST_TOKEN` from wp-config.php requirements — these are environment-specific and must stay.
- Do not change any shortcode names, meta keys, or sync logic.
- Do not add new wp-config constants of any kind.
- Do not change any other files beyond those listed above.
