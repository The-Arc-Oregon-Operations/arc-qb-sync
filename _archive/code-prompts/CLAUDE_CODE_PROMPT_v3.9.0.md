---
status: shipped
started: 2026-05-24
owner: Alan Lytle
target_version: 3.9.0
shipped: 2026-05-24
shipped_version: 3.9.0
---

# CLAUDE_CODE_PROMPT_v3.9.0.md — Library Phase 2 consumer migration

_Shipped 2026-05-24; paths and concepts in this document reflect the state at ship time. Refer to CLAUDE.md for current state._

## What You Are Doing and Why

Phase 2 of the Events display field library. Phase 1 (2026-05-22) built ten library entries on the Events table — bare-default rich-text formula fields meant to be the canonical display surface for instructors, dates, status, etc. This ship migrates `arc-qb-sync` from the legacy consumers (FID 271 *Instructor(s) - legacy*, the PHP-side `_arc_event_schedule` concat) to the library entries (FID 422 *Event Instructor(s)*) and refreshes stale labels and comments left over from Phase 1's field renames.

Four cutovers, bundled into a single v3.9.0 minor ship: (1) instructor display swaps FID 271 → FID 422 with a meta-key + shortcode-name rename, (2) the dormant `[event_schedule]` shortcode + `_arc_event_schedule` meta retire, (3) doc-only refresh for the FID 458 *Event Mode* → *Event Delivery Mode* rename and value-format shift, (4) inline-comment hygiene on `Length Num` → `Length (number)` references. All four cutovers and design choices were settled in the 2026-05-24 Cowork walk and are recorded as Confirmed (2026-05-24 · Alan Lytle) blocks in `event-management/sessions/2026-05-21_event-display-field-library/phase-2-cutover-plan.md`.

## Prerequisites — confirm before starting

- Current plugin version reads `3.8.6` in both `arc-qb-sync/arc-qb-sync.php` header `Version:` line and `ARC_QB_SYNC_VERSION` constant.
- QB Events table FID 422 *Event Instructor(s)* is populated and emits Oxford-comma rich-text (`<b>Alice</b>, <b>Bob</b>, and <b>Carol</b>` format) on test records. Phase 1 backfill is complete per Confirmed 2026-05-24 — all instructors for relevant classes have been validated with the new Instructor 1/2/3 field(s).
- Alan has provided WP DB grep results for `event_schedule` and `_arc_event_schedule` references — zero hits expected from `wp_posts.post_content` and from any `wp_postmeta.meta_value` Elementor-template payloads. **If hits are present, HALT cutover #2 and ask Alan how to proceed (defer or alias). Cutovers #1, #3, #4 proceed regardless.**
- No outstanding `claude/*` worktrees blocking branch creation. Run the standard Windows + OneDrive pre-flight from `CLAUDE.md` § *Windows + OneDrive — stale locks*.

## Read These Files First

1. `event-management/sessions/2026-05-21_event-display-field-library/phase-2-cutover-plan.md` — the canonical plan with all Confirmed (2026-05-24) blocks settling the four cutovers.
2. `arc-qb-sync/docs/field-mapping-events.md` — current FID/shortcode/meta-key map.
3. `arc-qb-sync/arc-qb-sync/includes/sync-events.php` — primary file touched (select list + meta writes + docblock).
4. `arc-qb-sync/arc-qb-sync/includes/shortcodes-events.php` — QB-direct `[instructors]` shortcode (line 86–90).
5. `arc-qb-sync/arc-qb-sync/includes/shortcodes-events-cpt.php` — CPT-side shortcodes + register block (line 313+).
6. `arc-qb-sync/arc-qb-sync/includes/events.php` — legacy `arc_td_get_current_record` select list (line 70–95).
7. `arc-qb-sync/arc-qb-sync/includes/elementor-dynamic-tags.php` — Elementor dynamic-tag picker map (line 90–107).
8. `arc-qb-sync/CHANGELOG.md` — top entry confirms `3.8.6` current.

## Target File Structure Changes

```
arc-qb-sync/
├── arc-qb-sync.php                                ← update: Version + ARC_QB_SYNC_VERSION → 3.9.0
├── arc-qb-sync/
│   └── includes/
│       ├── sync-events.php                         ← update: cutover #1 (FID 271→422; meta-key rename) + cutover #2 (remove _arc_event_schedule)
│       ├── shortcodes-events.php                   ← update: cutover #1 ([instructors] swap to FID 422; switch esc_html → wp_kses for <b> tags)
│       ├── shortcodes-events-cpt.php               ← update: cutover #1 (add [event_instructors] + alias the legacy names to the new function) + cutover #2 (remove [event_schedule] function + registration)
│       ├── events.php                              ← update: cutover #1 (select list 271→422 + docblock)
│       ├── elementor-dynamic-tags.php              ← update: cutover #2 (remove 'schedule' entry from get_fields_map)
│       ├── sync-courses.php                        ← update: cutover #4 (Length Num → Length (number) in inline comments)
│       └── shortcodes-courses.php                  ← update: cutover #4 (same comment refresh)
├── docs/
│   ├── field-mapping-events.md                     ← update: cutover #1 (swap FID 271 row → FID 422; rename `_arc_event_instructors_legacy` → `_arc_event_instructors`; deprecation note on alias) + cutover #2 (remove [event_schedule] row) + cutover #3 (FID 458 label + value-format updates)
│   ├── elementor-field-keys.md                     ← update: cutover #3 (FID 458 value format + label)
│   └── design-system.md                            ← update: cutover #3 (FID 458 label)
├── CHANGELOG.md                                    ← NEW entry: [3.9.0] — Date
└── README.md                                       ← update: shortcode table (remove [event_schedule] row; update [instructors] output column; rename [event_mode] label)
```

## Numbered Tasks

### Task 1 — `arc-qb-sync.php`: version bump

Header (line 5):
```php
 * Version:      3.8.6
```
becomes:
```php
 * Version:      3.9.0
```

Constant (line 13):
```php
define( 'ARC_QB_SYNC_VERSION', '3.8.6' );
```
becomes:
```php
define( 'ARC_QB_SYNC_VERSION', '3.9.0' );
```

Both must match.

### Task 2 — `arc-qb-sync/includes/sync-events.php`: Cutover #1 (instructor swap + meta-key rename)

**2a. Docblock update at top of file.**

In the comment block at lines 13–36, replace:
```
 *   271 → _arc_event_instructors_legacy
```
with:
```
 *   422 → _arc_event_instructors (rich-text; Oxford-comma chain over Instructor 1/2/3)
```

Also remove this line (cutover #2 also touches this block):
```
 *   computed → _arc_event_schedule (FIDs 413 + 45 concatenated with ' • ')
```

**2b. Select list at line 79.**

Replace:
```php
$select = array( 3, 7, 14, 19, 29, 45, 89, 137, 267, 271, 361, 413, 440, 449, 450, 453, 454, 458, 461 );
```
with:
```php
$select = array( 3, 7, 14, 19, 29, 45, 89, 137, 267, 361, 413, 422, 440, 449, 450, 453, 454, 458, 461 );
```
(FID 271 removed; FID 422 added in sorted position.)

**2c. Meta write at line 210.**

Replace:
```php
update_post_meta( $post_id, '_arc_event_instructors_legacy',     sanitize_text_field( arc_qb_get_course_field( $record, 271 ) ) );
```
with:
```php
update_post_meta( $post_id, '_arc_event_instructors',            wp_kses_post( arc_qb_get_course_field( $record, 422 ) ) );
```

The sanitizer swap matters — FID 422 emits `<b>...</b>` tags; `sanitize_text_field` would strip them. `wp_kses_post` preserves the standard HTML allowlist including `<b>`. Pattern matches the existing FID 440 (`_arc_event_description`) write three lines below.

### Task 3 — `arc-qb-sync/includes/sync-events.php`: Cutover #2 (remove `_arc_event_schedule`)

**3a. Removal block at lines 204–206.**

Remove these three lines entirely:
```php
// Concatenated schedule display string.
$parts = array_filter( array( $days_of_week, $event_dates ) );
update_post_meta( $post_id, '_arc_event_schedule', implode( ' • ', $parts ) );
```

Also remove the now-orphaned `$days_of_week` reference — actually, `$days_of_week` is still used by the line above:
```php
update_post_meta( $post_id, '_arc_event_days_of_week', $days_of_week );
```
Keep that line. Only delete the schedule-concat block.

### Task 4 — `arc-qb-sync/includes/shortcodes-events.php`: Cutover #1 (`[instructors]` swap)

Lines 86–90, replace:
```php
// Instructor(s) plain text (FID 271)
function arc_td_shortcode_event_instructors() {
	return esc_html( arc_td_get_field_value( 271 ) );
}
add_shortcode( 'instructors', 'arc_td_shortcode_event_instructors' ); // Pre-v2.2.0 alias — remove when legacy pages are retired
```
with:
```php
// Instructor(s) — rich-text from FID 422 Event Instructor(s) library entry (v3.9.0+; was FID 271 legacy through v3.8.6)
function arc_td_shortcode_event_instructors() {
	return wp_kses_post( arc_td_get_field_value( 422 ) );
}
add_shortcode( 'instructors', 'arc_td_shortcode_event_instructors' ); // Retained — used on legacy /training-details/?event-id=NNNN pages
```

The `esc_html` → `wp_kses_post` swap preserves the `<b>` tags FID 422 emits.

### Task 5 — `arc-qb-sync/includes/shortcodes-events-cpt.php`: Cutover #1 (new shortcode + alias old names)

**5a. Docblock at top of file (lines 16–38).**

Replace:
```
 *   [event_instructors_legacy]        / [arc_event_instructors_legacy]       — _arc_event_instructors_legacy
```
with:
```
 *   [event_instructors]               / [arc_event_instructors]              — _arc_event_instructors (FID 422, rich-text; v3.9.0+)
 *   [event_instructors_legacy]        / [arc_event_instructors_legacy]       — DEPRECATED v3.9.0 — alias to [event_instructors]; remove in a future release
```

Also remove this line as part of cutover #2:
```
 *   [event_schedule]                                                         — _arc_event_schedule (computed: days_of_week + ' • ' + dates)
```

**5b. Add new function before the existing `arc_qb_sc_event_instructors_legacy()` (around line 249).**

Insert this function above the existing legacy function:
```php
function arc_qb_sc_event_instructors() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return wp_kses_post( (string) get_post_meta( $post_id, '_arc_event_instructors', true ) );
}
```

**5c. Replace the existing `arc_qb_sc_event_instructors_legacy()` body (lines 249–255) so the legacy name becomes a thin alias reading the new meta.**

Replace:
```php
function arc_qb_sc_event_instructors_legacy() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructors_legacy', true ) );
}
```
with:
```php
// DEPRECATED v3.9.0 — alias to arc_qb_sc_event_instructors. Reads the new meta key.
// Retained one release for templates that still reference the legacy name. Remove in a future minor.
function arc_qb_sc_event_instructors_legacy() {
	return arc_qb_sc_event_instructors();
}
```

**5d. Shortcode registrations — primary names + aliases.**

In `arc_qb_register_event_shortcodes()`, add the new primary registration alongside the existing primaries (the section starting at line 319). Insert after the `event_instructor_slugs_legacy` line (or in alphabetical-ish position):

```php
add_shortcode( 'event_instructors',              'arc_qb_sc_event_instructors' );
```

In the deprecated-aliases section (starting line 342), add the new `arc_event_*` alias:

```php
add_shortcode( 'arc_event_instructors',              'arc_qb_sc_event_instructors' );
```

The existing `[event_instructors_legacy]` and `[arc_event_instructors_legacy]` registrations stay as-is — they now point at the alias function from 5c.

### Task 6 — `arc-qb-sync/includes/shortcodes-events-cpt.php`: Cutover #2 (remove `[event_schedule]`)

**6a. Remove the function at lines 153–159.**

Delete:
```php
function arc_qb_sc_event_schedule() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_schedule', true ) );
}
```

**6b. Remove the shortcode registration at line 325.**

Delete:
```php
add_shortcode( 'event_schedule',                 'arc_qb_sc_event_schedule' );
```

There is no `arc_event_schedule` alias registered — confirmed by grep. No second registration to remove.

### Task 7 — `arc-qb-sync/includes/events.php`: Cutover #1 (legacy fetch select list)

**7a. Docblock at line 37.**

Replace:
```
 *   271 = Instructor(s)
```
with:
```
 *   422 = Event Instructor(s) (rich-text; library Phase 1)
```

**7b. Select list at line 78.**

Replace:
```php
271, // Instructor(s)
```
with:
```php
422, // Event Instructor(s) (rich-text; library Phase 1)
```

### Task 8 — `arc-qb-sync/includes/elementor-dynamic-tags.php`: Cutover #2 (remove 'schedule' picker entry)

In `get_fields_map()` at line 90, remove the line:
```php
'schedule'           => array( __( 'Schedule (days • dates)', 'arc-qb-sync' ),    '[event_schedule]' ),
```

No other Elementor changes required — the 'mode' entry on line 98 already reads "Delivery mode" which matches the new field name (no rename needed).

### Task 9 — `arc-qb-sync/includes/sync-courses.php`: Cutover #4 (Length Num comments)

Line 274, replace:
```php
// Derived from FID 20 (Length Num — plain numeric hours), which is more reliable
```
with:
```php
// Derived from FID 20 (Length (number) — plain numeric hours), which is more reliable
```

Line 298, replace:
```php
// FID 20 — Length Num (numeric hours value — also used above to generate _arc_course_length)
```
with:
```php
// FID 20 — Length (number) (numeric hours value — also used above to generate _arc_course_length)
```

### Task 10 — `arc-qb-sync/includes/shortcodes-courses.php`: Cutover #4 (Length Num comment)

Line 156, replace:
```php
/* Length Num (numeric hours value — FID 20) */
```
with:
```php
/* Length (number) (numeric hours value — FID 20) */
```

### Task 11 — `arc-qb-sync/docs/field-mapping-events.md`: Cutover #1 + #2 + #3 doc updates

**11a. Cutover #1 — QB-direct table** (around line 19): replace the FID 271 row:
```
| 271 | Instructor(s) | `[instructors]` | `esc_html()` |
```
with:
```
| 422 | Event Instructor(s) | `[instructors]` | `wp_kses_post()` — rich-text; Oxford-comma chain over Instructor 1/2/3 with `<b>` wrapping per Phase 1 library design |
```

**11b. Cutover #2 — CPT shortcode table** (line 44): remove the `[event_schedule]` row entirely:
```
| `[event_schedule]` | `_arc_event_schedule` | computed | Computed from FIDs 413 + 45, separated by ` • `. `esc_html()` |
```

**11c. Cutover #1 — CPT shortcode table** (line 54): replace the legacy row:
```
| `[arc_event_instructors_legacy]` | `_arc_event_instructors_legacy` | 271 | `esc_html()` — legacy manual field |
```
with two rows:
```
| `[arc_event_instructors]` | `_arc_event_instructors` | 422 | `wp_kses_post()` — rich-text; primary v3.9.0+ |
| `[arc_event_instructors_legacy]` / `[event_instructors_legacy]` | `_arc_event_instructors` (via alias) | 422 | **DEPRECATED v3.9.0** — alias to `[event_instructors]`; remove in a future release |
```

**11d. Cutover #3 — QB-direct table** (line 27): replace the FID 458 row:
```
| 458 | Event Mode | `[event_mode]` | `esc_html()` — e.g. "Online", "In-person" |
```
with:
```
| 458 | Event Delivery Mode | `[event_mode]` | `esc_html()` — e.g. "Online (Zoom)", "In-person in [City]" (label rename + value-format updated in Phase 1, 2026-05-22) |
```

### Task 12 — `arc-qb-sync/docs/elementor-field-keys.md`: Cutover #3 doc update

Line 79, replace:
```
| `_arc_event_mode` | Delivery mode (Online / In-Person / Hybrid) | Plain text |
```
with:
```
| `_arc_event_mode` | Event Delivery Mode (e.g. "Online (Zoom)", "In-person in [City]") | Plain text |
```

(Confirm with Alan whether "Hybrid" was ever a real FID 458 value before this edit. If yes, append " / Hybrid" to the parenthetical examples. If no, the new shape above is correct.)

### Task 13 — `arc-qb-sync/docs/design-system.md`: Cutover #3 doc update

Line 159, replace:
```
| `[event_mode]` | Event mode / modality (FID 458) |
```
with:
```
| `[event_mode]` | Event Delivery Mode (FID 458) |
```

### Task 14 — `arc-qb-sync/README.md`: shortcode table updates

**14a. Remove the `[event_schedule]` row** at line 18:
```
| `[event_schedule]` | Computed: Day(s) of Week + " • " + Date(s) | Escaped text |
```

**14b. Update `[instructors]` row** at line 20. Replace:
```
| `[instructors]` | Instructor(s) | Escaped text |
```
with:
```
| `[instructors]` | Event Instructor(s) (FID 422) | Safe HTML (bold tags via `wp_kses_post`) |
```

**14c. Update `[event_mode]` row** at line 25. Replace:
```
| `[event_mode]` | Event Mode | Escaped text |
```
with:
```
| `[event_mode]` | Event Delivery Mode | Escaped text |
```

### Task 15 — `arc-qb-sync/CHANGELOG.md`: new top entry

Prepend a new section above the current `## [3.8.6] — 2026-05-11` entry:

```markdown
## [3.9.0] — 2026-05-24

### Changed
- `[instructors]` (QB-direct) and `[event_instructors]` (CPT) now read FID 422 *Event Instructor(s)* (rich-text Oxford-comma composite from Instructor 1/2/3 chain) instead of FID 271 *Instructor(s) - legacy*. Sanitizer swap from `esc_html` / `sanitize_text_field` to `wp_kses_post` to preserve the `<b>` tags FID 422 emits. Phase 2 of the Events display field library.
- WP meta key `_arc_event_instructors_legacy` renamed to `_arc_event_instructors`. Legacy meta key no longer populated by sync; consider a one-time `wp post meta delete --posts arc_event --meta-key=_arc_event_instructors_legacy --all` to clean up the dead key.
- CPT shortcode `[arc_event_instructors_legacy]` (and `[event_instructors_legacy]`) deprecated — both now alias to `[event_instructors]` and read the new meta. Slated for removal in a future minor.

### Added
- New CPT shortcode `[event_instructors]` (primary) and `[arc_event_instructors]` (alias) — preferred names going forward.

### Removed
- Computed `_arc_event_schedule` post meta + `[event_schedule]` shortcode + `'schedule'` Elementor picker entry. Confirmed dormant 2026-05-21; live-DB grep confirmed zero in-content references before removal.

### Docs
- FID 458 references updated for the Phase 1 *Event Mode* → *Event Delivery Mode* rename + value-format shift (`Online (Zoom)` / `In-person in [City]`). No code change — all FID 458 consumers passthrough or substring-match the value.
- Inline comments refreshed for the Phase 1 rename `Length Num` → `Length (number)` (FID 20 on Courses).
```

## Verification Checklist

1. `grep "Version:" arc-qb-sync/arc-qb-sync.php` returns `3.9.0`.
2. `grep ARC_QB_SYNC_VERSION arc-qb-sync/arc-qb-sync.php` returns `'3.9.0'`.
3. `grep -rn " 271" arc-qb-sync/arc-qb-sync/includes/` returns zero hits (legacy field no longer referenced in active code). Hits in vendor/comment-blocks documenting the change are acceptable if scoped.
4. `grep -rn "_arc_event_instructors_legacy" arc-qb-sync/arc-qb-sync/includes/` — should appear only in shortcode-name registrations + the deprecation-alias docblock; should NOT appear as a meta key being written.
5. `grep -rn "_arc_event_schedule" arc-qb-sync/arc-qb-sync/includes/` returns zero hits.
6. `grep -rn "event_schedule" arc-qb-sync/arc-qb-sync/includes/` returns zero hits.
7. `grep -rn "Length Num" arc-qb-sync/arc-qb-sync/includes/` returns zero hits.
8. `php -l` on every modified PHP file returns no syntax errors.
9. CHANGELOG top entry reads `[3.9.0] — 2026-05-24`.
10. `arc-qb-sync/docs/field-mapping-events.md` no longer contains `271` for the instructors row, no longer contains `[event_schedule]`, and has the new FID 422 row with `wp_kses_post()` annotation.
11. `arc-qb-sync/README.md` no longer contains the `[event_schedule]` row; `[instructors]` and `[event_mode]` rows match the new label/output text.

## What NOT to Change

- **FID 449 *Instructor Slugs - legacy* and the `[instructor_slugs]` / `[arc_event_instructor_slugs_legacy]` shortcodes** are out of scope. The new-flow slug source has not been settled. Leave both surfaces and the `_arc_event_instructor_slugs_legacy` meta untouched.
- **The legacy `/arc-training-details/` folder at the arc-qb-sync repo root** (pre-plugin reference code) is out of scope. Do not edit `arc-training-details/arc-training-details.php` even though it has stale FID 271 + Length Num references — that folder is historical.
- **Existing image-pipeline FID constants, sync logic, and image-asset shortcodes** are out of scope.
- **The `[venue_name]` / `[event_venue]` shortcodes**, FID 29 reads, and any other FID outside the four cutovers — untouched.
- **No new shortcodes or features** beyond the new `[event_instructors]` / `[arc_event_instructors]` registrations.
- **No changes to the `arc_trainer_slugs` query var pipeline** or the Elementor trainer-loop integration.

## Manual tests

Run after deploy on the live WP site. Use a known test event with at least two Instructor 1/2 records populated.

| # | Pre-condition | Action | Expected |
|---|---|---|---|
| 1 | Test event has FID 481 *Instructor 1* + FID 485 *Instructor 2* populated; FID 271 has the legacy free-text value | Trigger a full event sync via WP Admin → Settings → Arc QB Sync → Sync All Events Now | Sync completes without errors; `_arc_event_instructors` meta on the test event's `arc_event` post contains the FID 422 rich-text output (e.g. `<b>Alice</b> and <b>Bob</b>`); `_arc_event_instructors_legacy` is NOT written (key absent or untouched from prior sync) |
| 2 | Test event has FID 481 only populated | Visit the legacy `/training-details/?event-id=NNNN` page where `[instructors]` renders | Instructor name renders bold (single `<b>` wrapper visible in HTML source) — confirms QB-direct read of FID 422 + `wp_kses_post` pipeline |
| 3 | Same event | Visit the CPT page where `[event_instructors]` renders | Same bold rendering — confirms CPT-side read of `_arc_event_instructors` |
| 4 | Same event | Visit a CPT page that still uses `[arc_event_instructors_legacy]` in its template | Same bold rendering — confirms deprecation-alias works |
| 5 | Any page that previously contained `[event_schedule]` | Visit the page | The literal text `[event_schedule]` appears in the content (shortcode no longer registered). **If any page renders this literally to public users, halt and report to Alan — the verification grep missed a reference.** |
| 6 | Edit any `arc_event` post in WP admin via Elementor | Open the dynamic-tag field picker for the Arc QB Sync source | The "Schedule (days • dates)" option no longer appears in the picker; all other options remain |
| 7 | Verify in WP admin | `wp post meta list <test_event_post_id>` (CLI) or check post-meta panel | `_arc_event_instructors` is present; `_arc_event_instructors_legacy` is either absent (clean) or still carrying old data (pre-cleanup) — both states are acceptable |

## Post-deploy cleanup (optional, one-time)

After Manual Test 7 confirms `_arc_event_instructors` is populating correctly across all synced events, run one-time CLI cleanup to delete the now-dead legacy meta key:

```bash
wp post meta delete --posts arc_event --meta-key=_arc_event_instructors_legacy --all
```

Or via SQL (Plesk / phpMyAdmin):
```sql
DELETE FROM wp_postmeta WHERE meta_key = '_arc_event_instructors_legacy';
```

Either is safe — no code reads this key after v3.9.0.
