# Claude Code Task: arc-qb-sync v3.5.0

## What You Are Doing and Why

You are upgrading arc-qb-sync from v3.4.1 to v3.5.0. Three changes:

1. **New `_arc_event_schedule` field** — a concatenated display string combining Days of Week and Event Dates, separated by a bullet character. Stored during sync; exposed via `[event_schedule]` shortcode. Replaces the need to concat these two fields in Elementor.

2. **Elementor Loop Grid query for event instructors** — a new `elementor-queries.php` file registers the `elementor/query/arc_event_instructors` hook. When a Loop Grid on an `arc_event` single template uses query ID `arc_event_instructors`, it queries `instructor` CPT posts whose slugs match the pipe-separated list in `_arc_event_instructor_slugs_legacy`.

3. **Remove instructor slot fields** — the individual instructor1/2/3 name/headshot/alt fields (9 total) are removed from sync and shortcodes. These were never deployed in any live template and can be cleanly cut.

Read this entire prompt before writing any code. The sequence matters.

**Prerequisites — confirm before starting:**
- Plugin version in `arc-qb-sync.php` reads `3.4.1`

---

## Read These Files First

Before writing a single line of code, read these files in full:

1. `arc-qb-sync/arc-qb-sync.php`
2. `arc-qb-sync/includes/sync-events.php`
3. `arc-qb-sync/includes/shortcodes-events-cpt.php`
4. `arc-qb-sync/includes/qb-api.php`
5. `docs/field-mapping-events.md`
6. `docs/elementor-field-keys.md`

---

## Target File Structure Changes

```
arc-qb-sync/
  arc-qb-sync.php                     ← update: add require for elementor-queries.php, bump to 3.5.0
  includes/
    sync-events.php                   ← update: add _arc_event_schedule; remove instructor1/2/3 slots
    shortcodes-events-cpt.php         ← update: add [event_schedule]; remove instructor1/2/3 shortcodes
    elementor-queries.php             ← NEW: arc_event_instructors Elementor query hook
docs/
  elementor-field-keys.md             ← update: add _arc_event_schedule; remove instructor1/2/3 rows
  field-mapping-events.md             ← update: add schedule field; mark instructor slots removed
CHANGELOG.md                          ← update
```

---

## Task 1: New `_arc_event_schedule` Field — Sync

**File: `arc-qb-sync/includes/sync-events.php`**

After the existing `update_post_meta` calls for `_arc_event_days_of_week` (FID 413) and `_arc_event_dates` (FID 45), add a computed meta field that concatenates the two with a bullet separator.

The values are already being stored individually. Capture them before writing to meta, then compute the schedule string:

```php
$days_of_week = sanitize_text_field( arc_qb_get_course_field( $record, 413 ) );
$event_dates  = sanitize_text_field( arc_qb_get_course_field( $record, 45 ) );

update_post_meta( $post_id, '_arc_event_days_of_week', $days_of_week );
update_post_meta( $post_id, '_arc_event_dates',        $event_dates );

// Concatenated schedule display string.
$parts = array_filter( array( $days_of_week, $event_dates ) );
update_post_meta( $post_id, '_arc_event_schedule', implode( ' • ', $parts ) );
```

`array_filter` drops empty strings, so if one value is missing the bullet is omitted. If both are empty the field stores an empty string.

**Note:** The existing `update_post_meta` lines for `_arc_event_days_of_week` and `_arc_event_dates` should be **replaced** (not duplicated) by the block above. Do not call `arc_qb_get_course_field` twice for the same FIDs.

---

## Task 2: New `[event_schedule]` Shortcode

**File: `arc-qb-sync/includes/shortcodes-events-cpt.php`**

Add a shortcode callback and registration following the existing pattern in this file. The shortcode reads `_arc_event_schedule` from post meta and returns it as plain escaped text — the bullet character is already embedded in the stored value.

**Callback:**

```php
function arc_qb_sc_event_schedule() {
    $post_id = arc_qb_get_event_post_id();
    if ( ! $post_id ) {
        return '';
    }
    return esc_html( (string) get_post_meta( $post_id, '_arc_event_schedule', true ) );
}
```

**Registration** (add to `arc_qb_register_event_shortcodes()`):

```php
add_shortcode( 'event_schedule', 'arc_qb_sc_event_schedule' );
```

No deprecated alias needed — this is a new field with no prior name.

**Update the file's docblock** at the top to include:

```
 *   [event_schedule] — _arc_event_schedule (computed: days_of_week + ' • ' + dates)
```

---

## Task 3: Remove Instructor Slot Fields — Sync

**File: `arc-qb-sync/includes/sync-events.php`**

Remove the following nine `update_post_meta` calls entirely (three instructor slots, three fields each):

```php
// Instructor slot 1.
update_post_meta( $post_id, '_arc_event_instructor1_name', ... );
update_post_meta( $post_id, '_arc_event_instructor1_headshot_url', ... );
update_post_meta( $post_id, '_arc_event_instructor1_headshot_alt', ... );

// Instructor slot 2.
update_post_meta( $post_id, '_arc_event_instructor2_name', ... );
update_post_meta( $post_id, '_arc_event_instructor2_headshot_url', ... );
update_post_meta( $post_id, '_arc_event_instructor2_headshot_alt', ... );

// Instructor slot 3.
update_post_meta( $post_id, '_arc_event_instructor3_name', ... );
update_post_meta( $post_id, '_arc_event_instructor3_headshot_url', ... );
update_post_meta( $post_id, '_arc_event_instructor3_headshot_alt', ... );
```

Also remove the section comment block that introduced these (e.g., `// Image and instructor lookup FIDs` or similar) if it is specific to these nine fields. Do not remove comments that document other fields.

Also remove the corresponding FID entries from the file's docblock at the top (the `*   482 → _arc_event_instructor1_name` lines and so on for all nine).

**Do not remove** `_arc_event_instructors_legacy` (FID 271) or `_arc_event_instructor_slugs_legacy` (FID 449). Those remain — the slugs field is the source for the Loop Grid query.

---

## Task 4: Remove Instructor Slot Shortcodes

**File: `arc-qb-sync/includes/shortcodes-events-cpt.php`**

Remove the following nine shortcode callback functions:

- `arc_qb_sc_event_instructor1_name()`
- `arc_qb_sc_event_instructor1_headshot_url()`
- `arc_qb_sc_event_instructor1_headshot_alt()`
- `arc_qb_sc_event_instructor2_name()`
- `arc_qb_sc_event_instructor2_headshot_url()`
- `arc_qb_sc_event_instructor2_headshot_alt()`
- `arc_qb_sc_event_instructor3_name()`
- `arc_qb_sc_event_instructor3_headshot_url()`
- `arc_qb_sc_event_instructor3_headshot_alt()`

Remove all corresponding `add_shortcode()` registrations for both primary and deprecated alias names (18 `add_shortcode` lines total).

Update the file's docblock at the top to remove the nine shortcode entries.

---

## Task 5: New `elementor-queries.php`

**New file: `arc-qb-sync/includes/elementor-queries.php`**

```php
<?php
/**
 * Elementor custom query hooks for arc-qb-sync CPTs.
 *
 * Registers named query hooks consumed by Elementor Pro Loop Grid widgets.
 * Each hook modifies a WP_Query instance based on the current post context.
 *
 * Query IDs (enter in Loop Grid → Query → Query ID field):
 *   arc_event_instructors — filters instructor CPT posts linked to the current arc_event
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Query ID: arc_event_instructors
 *
 * Filters a Loop Grid query to show only `instructor` CPT posts whose
 * post_name (slug) appears in the current event's instructor slugs field.
 *
 * Source: _arc_event_instructor_slugs_legacy (pipe-separated, e.g. "nkaasa|ldutton").
 * Returns no posts if the field is empty or no matching slugs are found.
 *
 * Usage: Set Loop Grid → Query → Source to "Custom" and Query ID to "arc_event_instructors".
 * This hook only modifies the query when the current post is an arc_event — it is safe
 * to leave the Loop Grid set to this query ID on any single template.
 */
add_action( 'elementor/query/arc_event_instructors', 'arc_qb_elementor_query_arc_event_instructors' );

function arc_qb_elementor_query_arc_event_instructors( $query ) {
    $post_id = get_the_ID();

    if ( ! $post_id || 'arc_event' !== get_post_type( $post_id ) ) {
        return;
    }

    $slugs_raw = get_post_meta( $post_id, '_arc_event_instructor_slugs_legacy', true );

    if ( empty( $slugs_raw ) ) {
        // No instructors on this event — return an empty result set.
        $query->set( 'post__in', array( 0 ) );
        return;
    }

    $slugs = array_values(
        array_filter(
            array_map( 'trim', explode( '|', (string) $slugs_raw ) )
        )
    );

    if ( empty( $slugs ) ) {
        $query->set( 'post__in', array( 0 ) );
        return;
    }

    $query->set( 'post_type',      'instructor' );
    $query->set( 'post_name__in',  $slugs );
    $query->set( 'posts_per_page', -1 );
    $query->set( 'orderby',        'title' );
    $query->set( 'order',          'ASC' );
}
```

---

## Task 6: Register `elementor-queries.php` in Main Plugin File

**File: `arc-qb-sync/arc-qb-sync.php`**

Add a `require_once` for `elementor-queries.php` in the includes block, alongside the other includes. Place it after the CPT and shortcode requires — it depends on nothing and nothing depends on it, but keeping it last in the block is cleanest.

```php
require_once ARC_QB_SYNC_DIR . 'includes/elementor-queries.php';
```

---

## Task 7: Bump Version to 3.5.0

**File: `arc-qb-sync/arc-qb-sync.php`**

Update both the plugin header and the version constant:

```php
 * Version:      3.5.0
```

```php
define( 'ARC_QB_SYNC_VERSION', '3.5.0' );
```

---

## Task 8: Update Documentation

**File: `docs/elementor-field-keys.md`**

In the **Event CPT** table:

- Add a new row for `_arc_event_schedule`:

  | `_arc_event_schedule` | Computed: days of week + " • " + dates. Empty if both source fields are blank. | Plain text |

  Place it adjacent to the `_arc_event_dates` and `_arc_event_days_of_week` rows.

- Remove the nine instructor slot rows (`_arc_event_instructor1/2/3_name`, `_headshot_url`, `_headshot_alt`).

**File: `docs/field-mapping-events.md`**

- Add `_arc_event_schedule` as a computed field (no FID — derived from FIDs 413 and 45).
- Mark the nine instructor slot meta keys as removed in v3.5.0.

---

## Task 9: Update CHANGELOG.md

Add a v3.5.0 entry at the top of the changelog following the existing format. Include:

- Added `_arc_event_schedule` computed meta field (days_of_week + ' • ' + dates) and `[event_schedule]` shortcode
- Added `elementor-queries.php` with `arc_event_instructors` Loop Grid query hook (query ID: `arc_event_instructors`)
- Removed instructor slot fields (instructor1/2/3 name/headshot/alt) from sync and shortcodes — never deployed, cleanly cut

---

## Verification Checklist

After all changes are complete, confirm each of the following:

1. Plugin version in `arc-qb-sync.php` header and constant both read `3.5.0`
2. `elementor-queries.php` exists in `includes/` and is required in `arc-qb-sync.php`
3. `sync-events.php` no longer contains any reference to `instructor1`, `instructor2`, or `instructor3`
4. `shortcodes-events-cpt.php` no longer contains any of the nine instructor slot callbacks or registrations
5. `sync-events.php` contains a single `arc_qb_get_course_field( $record, 413 )` call (not two) — the variable is reused for both `update_post_meta` and the schedule concat
6. `sync-events.php` contains a single `arc_qb_get_course_field( $record, 45 )` call — same reason
7. `_arc_event_schedule` is stored via `update_post_meta` in `sync-events.php`
8. `[event_schedule]` is registered in `shortcodes-events-cpt.php`
9. `arc_qb_elementor_query_arc_event_instructors()` is registered on `elementor/query/arc_event_instructors`
10. `_arc_event_instructors_legacy` and `_arc_event_instructor_slugs_legacy` are still present in `sync-events.php` — do not remove these
11. `docs/elementor-field-keys.md` contains `_arc_event_schedule` and does not contain instructor1/2/3 rows
12. `CHANGELOG.md` has a v3.5.0 entry

---

## Notes

**wp-config.php cleanup (optional, outside plugin scope):** The v3.0.0 spec added nine `ARC_QB_EVENT_INSTRUCTOR1/2/3_*` constants to `wp-config.php`. These are not used by the plugin code (FIDs were hardcoded inline) and can be removed from `wp-config.php` as a housekeeping step. This is not required for v3.5.0 to function.

**Loop Grid setup in Elementor:** After deploying, set up the Loop Grid on the event single template as follows:
- Query → Source: Custom
- Query → Query ID: `arc_event_instructors`
- The Loop Item template should target the `instructor` CPT and use `[instructor_name]`, `[instructor_headshot_url]`, etc.
