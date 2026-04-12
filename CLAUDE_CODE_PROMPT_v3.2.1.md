# Claude Code Task: QA Cleanup (v3.2.1)

## What you are doing and why

This prompt addresses all actionable issues identified in `docs/qa-review-2026-04-12.md`. Read that file before starting — it is the authoritative reference for why each change is being made.

The issues fall into four categories:
1. **Blockers** — unresolved git merge conflicts that prevent the plugin from loading
2. **Functional bugs** — code that produces wrong output in specific scenarios
3. **Code quality** — naming and logic issues that aren't bugs today but will cause confusion
4. **Docs** — stale or broken documentation

Read this entire prompt before making any changes. Do the work in the order listed. Version bump and CHANGELOG entry come last.

---

## 1. Resolve merge conflicts — `sync-courses.php`

There are two unresolved conflict blocks. In both cases, take the `Stashed changes` side (uses named constants rather than hardcoded literals). Remove all conflict markers.

**Block 1** is in `arc_qb_fetch_course_record()`. Replace the entire conflict block with:

```php
$select = array( 3, 6, 14, 20, 36, 39, 40, 43, 46, 50, 56, 62, 84, 85, 88, 89, 90, 92,
	ARC_QB_COURSE_FEATURED_IMAGE_FID, // 94
	ARC_QB_COURSE_HERO_IMAGE_FID,     // 96
);
```

**Block 2** is in `arc_qb_upsert_course()`. Replace the entire conflict block with:

```php
// Image Asset lookup fields
update_post_meta( $post_id, '_arc_course_featured_image_url',
	esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_COURSE_FEATURED_IMAGE_FID ) ) ); // 94
update_post_meta( $post_id, '_arc_course_hero_image_url',
	esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_COURSE_HERO_IMAGE_FID ) ) );     // 96
```

---

## 2. Resolve merge conflict — `sync-instructors.php`

There is one unresolved conflict block for the `ARC_QB_INSTRUCTOR_PROFILE_FID` constant. Take the `Stashed changes` side:

```php
if ( ! defined( 'ARC_QB_INSTRUCTOR_PROFILE_FID' ) ) define( 'ARC_QB_INSTRUCTOR_PROFILE_FID', 15 ); // Headshot URL [lookup from Image Assets]
```

Then update the `$select` array in `arc_qb_fetch_all_instructor_records()`: replace the bare `15,` literal with `ARC_QB_INSTRUCTOR_PROFILE_FID,` so the constant is actually used. Update the inline comment from `// FID 15 — Headshot URL lookup` to `// ARC_QB_INSTRUCTOR_PROFILE_FID`.

Also update `arc_qb_upsert_instructor()`: replace `arc_qb_get_course_field( $record, 15 )` with `arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_PROFILE_FID )`.

---

## 3. Fix `arc_qb_request()` — empty QB response treated as error

**File:** `includes/qb-api.php`

The current condition:
```php
if ( 200 !== $status || empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
```

Replace with:
```php
if ( 200 !== $status || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
```

This allows a valid 200 response with zero records (`"data": []`) to return an empty array rather than a `WP_Error`. The sync loops already handle empty arrays gracefully.

---

## 4. Fix `[course_request_url]` shortcode context resolver

**File:** `includes/shortcodes-courses.php`

In `arc_qb_shortcode_course_request_url()`, replace:

```php
$post_id = get_the_ID();
if ( ! $post_id ) {
    return '';
}
$record_id = intval( get_post_meta( $post_id, '_arc_qb_record_id', true ) );
```

With:

```php
$post_id = arc_qb_get_course_post_id();
if ( ! $post_id ) {
    return '';
}
$record_id = intval( get_post_meta( $post_id, '_arc_qb_record_id', true ) );
```

This ensures the shortcode works on legacy `?course-id=NNNN` pages, consistent with all other `[course_*]` shortcodes.

---

## 5. Update `[course_field]` generic shortcode field map

**File:** `includes/shortcodes-courses.php`

In `arc_qb_sc_course_field_generic()`, add the following entries to `$field_map`:

```php
92 => '_arc_course_slug',
94 => '_arc_course_featured_image_url',
96 => '_arc_course_hero_image_url',
```

These were added in v2.2.0+ but never added to the generic shortcode map. FID 56 (tags) is deliberately excluded — it is taxonomy-based and not accessible as a simple meta string; leave a comment noting this.

---

## 6. Fix event post slug — only set `post_name` on insert

**File:** `includes/sync-events.php`

In `arc_qb_upsert_event()`, the `$post_data` array always includes `'post_name' => $post_slug`. This means a title change in QB silently changes the event's URL on the next sync.

Fix: only include `post_name` when creating a new post, not when updating an existing one.

Replace the `$post_data` block and the insert/update logic with:

```php
$post_data = array(
	'post_type'   => 'arc_event',
	'post_title'  => sanitize_text_field( $title ),
	'post_status' => 'publish',
);

if ( $post_id > 0 ) {
	$post_data['ID'] = $post_id;
	// post_name is intentionally omitted on update — slug is set once on insert
	// and never changed, so existing URLs remain stable even if the title changes in QB.
	$result = wp_update_post( $post_data, true );
} else {
	$post_data['post_name'] = sanitize_title( $title );
	$result = wp_insert_post( $post_data, true );
}
```

---

## 7. Add `error_log()` calls at failure points

Add `error_log()` calls so that sync failures leave a trail in `WP_DEBUG_LOG` even when no admin is watching. Do this in four places:

**`includes/sync-courses.php` — `arc_qb_sync_all_courses()`:**
In the `foreach` loop, after `$messages[] = $result->get_error_message();` add:
```php
error_log( '[arc-qb-sync] Course upsert failed for QB record ' . $qb_id . ': ' . $result->get_error_message() );
```

**`includes/sync-events.php` — `arc_qb_sync_all_events()`:**
Same pattern, after the event upsert error message:
```php
error_log( '[arc-qb-sync] Event upsert failed for QB record ' . $qb_id . ': ' . $result->get_error_message() );
```

**`includes/sync-instructors.php` — `arc_qb_sync_all_instructors()`:**
Same pattern for instructors:
```php
error_log( '[arc-qb-sync] Instructor upsert failed for QB record ' . $qb_id . ': ' . $result->get_error_message() );
```

**`includes/cache-rest.php` — `arc_qb_handle_sync_course()`:**
After the QB fetch failure check:
```php
error_log( '[arc-qb-sync] Webhook fetch failed for record_id ' . $record_id . ': ' . $record->get_error_message() );
```
And after the upsert failure check:
```php
error_log( '[arc-qb-sync] Webhook upsert failed for record_id ' . $record_id . ': ' . $post_id->get_error_message() );
```

---

## 8. Fix comment numbering in `cpt-courses.php`

**File:** `includes/cpt-courses.php`

The third section header reads `// ── 2. Legacy ?course-id= redirect`. Change `2.` to `3.`.

---

## 9. Resolve merge conflicts and update `docs/setup.md`

The file has several unresolved conflict blocks. Merge them manually into a single clean document. The correct final state is:

- Intro paragraph: keep the v3.0.1 wording ("As of v3.0.1, wp-config.php holds only credentials and Quickbase table IDs...")
- Constants table: one clean table with all six constants — `QB_REALM_HOST`, `QB_USER_TOKEN`, `QB_TABLE_ID`, `QB_COURSES_TABLE_ID`, `QB_INSTRUCTORS_TABLE_ID`, `ARC_QB_CACHE_BUST_TOKEN`
- Example block: the clean six-constant block (already present in both sides of the conflict)
- No duplicate `QB_TABLE_ID` or `QB_INSTRUCTORS_TABLE_ID` entries

Also fix the stale admin path in the **Deployment Steps** section (step 6):

Change:
```
Settings → Arc Event Sync → Sync All Events Now. Then Settings → Arc Instructor Sync → Sync All Instructors Now.
```
To:
```
Tools → QB Event Sync → Sync All Events Now. Then Tools → QB Instructor Sync → Sync All Instructors Now.
```

---

## 10. Rewrite `docs/field-mapping-courses.md`

This file predates v2.2.0 and has not been updated. Rewrite it to reflect the current state of `sync-courses.php` and `shortcodes-courses.php`. Use the field mapping comment block at the top of `sync-courses.php` as the authoritative source for FIDs and meta keys.

The rewritten doc should:
- List all synced FIDs (3, 6, 14, 20, 36, 39, 40, 43, 46, 50, 56, 62, 84, 85, 88, 89, 90, 92, 94, 96) with their QB labels, meta keys, and corresponding shortcodes
- Use current shortcode names: `[course_base_rate]` (not `[course_payment]`), `[course_field]` (not `[arc_qb_course_field]`)
- Remove all references to `arc_qb_get_public_courses()` and the JSON embed approach
- Note that `[course_field id="56"]` (tags) is not supported via the generic shortcode (taxonomy-based)
- Note the `_url` suffix convention for URL fields
- Keep the format consistent with `docs/field-mapping-events.md` and `docs/field-mapping-instructors.md`

---

## 11. Resolve merge conflicts in `CHANGELOG.md`

The file has unresolved conflict markers from lines 3 through ~100 (covering v2.2.0 through v3.1.2 history). Merge them into a single clean changelog with entries in reverse chronological order:

3.2.0 → 3.1.2 → 3.1.1 → 3.1.0 → 3.0.2 → 3.0.1 → 3.0.0 → 2.2.0 → (earlier entries)

Both conflict branches contain real content that must be preserved — neither side is a duplicate. The v3.2.0 entry is already at the top of the file (above the conflict markers) and should remain there.

---

## 12. Version bump and CHANGELOG entry

After all changes above are complete:

- Bump version to `3.2.1` in `arc-qb-sync/arc-qb-sync.php` (`ARC_QB_SYNC_VERSION` constant and plugin header)
- Add a `[3.2.1]` entry to `CHANGELOG.md`:

```markdown
## [3.2.1] — 2026-04-12

### Fixed
- Resolved all unresolved git merge conflicts in `sync-courses.php`, `sync-instructors.php`, `CHANGELOG.md`, and `docs/setup.md`
- `arc_qb_request()` no longer treats a valid empty QB response as an error
- `[course_request_url]` shortcode now uses the shared context resolver — works correctly on legacy `?course-id=` pages
- `[event_field]` generic shortcode field map updated with FIDs 92, 94, 96
- Event `post_name` is now set only on insert, not on update — prevents URL changes when an event title is corrected in QB
- `ARC_QB_INSTRUCTOR_PROFILE_FID` constant now used consistently in `sync-instructors.php`
- Section comment numbering corrected in `cpt-courses.php`
- Admin page path updated in `docs/setup.md` (Settings → Tools)

### Changed
- `error_log()` calls added at all sync failure points for WP_DEBUG_LOG visibility

### Docs
- `docs/field-mapping-courses.md` rewritten to reflect current field set and shortcode names
- `docs/setup.md` merge conflicts resolved; clean six-constant wp-config block
```

---

## What NOT to change

- Do not rename `arc_qb_get_course_field()` to a generic name — this is a medium refactor flagged in the QA report but deferred
- Do not remove `arc_qb_get_public_courses()` from `courses.php` without first confirming nothing on the live site calls it — this is flagged in the QA report (Issue S6) but requires human verification
- Do not touch `shortcodes-events.php`, `shortcodes-events-cpt.php`, or `sync-events.php` beyond the `post_name` fix in step 6 — those files were just updated in v3.2.0
