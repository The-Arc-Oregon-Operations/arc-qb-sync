---
status: ready-for-code
started: 2026-06-07
owner: Alan Lytle
target_version: 3.10.0
---

# CLAUDE_CODE_PROMPT_v3.10.0.md — Public-catalog gatekeeper migration: add FID 438 alongside FID 137

## What You Are Doing and Why

The public catalog gatekeeper in `arc-qb-sync` currently consults Events FID 137 `Show Public` as the sole filter. A new field, Events FID 438 `Hide from Public Event Listings`, was uplifted by Alan in the 2026-06-08 Cowork session as the canonical "unlisted-concept" handle — the way to mark an event as registerable but not browseable in the public catalog (the "hard-to-find open door" pattern for private group registrations). This ship migrates `arc-qb-sync` from the single-gate model to a joint-gate model: an event syncs to WordPress only when **FID 137 = true AND FID 438 = false**.

Semantic model after this ship: FID 137 remains the absolute registration gate (also still enforced independently by `arc-event-reg`'s `class-event-lookup.php`); FID 438 layers on as catalog visibility only. A retired event has FID 137 = false (and FID 438 doesn't matter). An active public event has FID 137 = true, FID 438 = false. An unlisted event (registerable via direct URL, hidden from catalog browse) has FID 137 = true, FID 438 = true. The gate change is settled in `event-management/sessions/2026-06-07_free-in-person-workflow/README.md` — Cluster 3, including the joint-gate decision (Cluster-1 q1) and the demote-to-draft symmetry decision (Cluster-2 q1) settled 2026-06-07. The arc-event-reg side requires no code change — Cluster 3 already verified FID 137 reg-gating works in `class-event-lookup.php`.

## Prerequisites — confirm before starting

- Current plugin version reads `3.9.0` in both `arc-qb-sync/arc-qb-sync.php` header `Version:` line and `ARC_QB_SYNC_VERSION` constant.
- QB Events table FID 438 `Hide from Public Event Listings` exists, is a Checkbox type, and defaults to false on new and existing rows. (Uplifted from `otac-pre-2024` use by Alan 2026-06-08; added to the Events form this session.)
- No outstanding `claude/*` worktrees blocking branch creation. Run the standard Windows + OneDrive pre-flight from `CLAUDE.md` § *Windows + OneDrive — stale locks*. A leftover `.claude/worktrees/busy-cannon/` was flagged during the 2026-06-07 Cowork pass; clear it as part of the pre-flight if it is still present.

## Read These Files First

1. `event-management/sessions/2026-06-07_free-in-person-workflow/README.md` — Cluster 3 resolution and the joint-gate decision blocks dated 2026-06-07.
2. `arc-qb-sync/arc-qb-sync/includes/sync-events.php` — the only PHP file touched. The gatekeeper lives here in two places: the bulk-fetch `where` clause (line ~94) and the per-record `arc_qb_upsert_event()` non-public branch (lines ~148–161).
3. `arc-qb-sync/CHANGELOG.md` — top entry confirms `3.9.0` is current.

## Target File Structure Changes

```
arc-qb-sync/
├── arc-qb-sync.php                                ← update: Version + ARC_QB_SYNC_VERSION → 3.10.0
├── arc-qb-sync/
│   └── includes/
│       └── sync-events.php                        ← update: gate semantic — add FID 438 to select; AND-in to where; add to $is_public calc; refresh docblocks + admin text
└── CHANGELOG.md                                    ← NEW entry: [3.10.0] — 2026-06-07
```

No other files change. `docs/field-mapping-events.md` is a shortcode/meta-key reference (it does not document gating fields today) and is left untouched. `README.md` carries no "Show Public" language and is left untouched.

## Numbered Tasks

### Task 1 — `arc-qb-sync.php`: version bump

Header (line 5):
```php
 * Version:      3.9.0
```
becomes:
```php
 * Version:      3.10.0
```

Constant (line 13):
```php
define( 'ARC_QB_SYNC_VERSION', '3.9.0' );
```
becomes:
```php
define( 'ARC_QB_SYNC_VERSION', '3.10.0' );
```

Both must match.

### Task 2 — `arc-qb-sync/includes/sync-events.php`: file-top docblock

In the comment block at lines 13–35, the FID 137 line currently reads:
```
 *   137 → post_status: publish (TRUE) / draft (FALSE)
```

Replace with two lines (137 retains its post_status role; 438 is a catalog-gate-only field with no meta write):
```
 *   137 → post_status: publish (TRUE) / draft (FALSE)  — joint gatekeeper (with FID 438)
 *   438 → catalog visibility only (no WP meta write) — TRUE hides from public listing — joint gatekeeper (with FID 137)
```

### Task 3 — `arc-qb-sync/includes/sync-events.php`: `arc_qb_fetch_all_event_records()` docblock + select + where

**3a. Docblock at lines 59–67.** Replace:
```
/**
 * Fetch publicly listed Training Event records from Quickbase (FID 137 = TRUE only).
 *
 * QB is the strict gatekeeper. Non-public events never enter WordPress.
 * If an event's Show Public flag is unchecked in QB, the sync demotes the
 * existing WP post to draft — it is never created here.
 *
 * @return array|\WP_Error  Array of record arrays on success.
 */
```
with:
```
/**
 * Fetch publicly listed Training Event records from Quickbase.
 *
 * Joint gatekeeper (v3.10.0+):
 *   - FID 137 `Show Public` = TRUE         (event is publicly registerable)
 *   - FID 438 `Hide from Public Event Listings` = FALSE  (event is browseable in catalog)
 *
 * Both conditions must hold for an event to enter WordPress. FID 137 alone
 * was the gate through v3.9.0; FID 438 layers on as catalog visibility for
 * the unlisted-concept ("hard-to-find open door") pattern — events that are
 * registerable via direct URL but hidden from public browse. arc-event-reg
 * still gates registration on FID 137 only; FID 438 is a catalog-side concept
 * and is intentionally not consulted by the registration plugin.
 *
 * If either gate flips against an event mid-life, the ghost-removal pass in
 * arc_qb_sync_all_events() demotes the existing WP post to draft.
 *
 * @return array|\WP_Error  Array of record arrays on success.
 */
```

**3b. Select list at line 78.** Replace:
```php
$select = array( 3, 7, 14, 19, 29, 45, 89, 137, 267, 361, 413, 422, 440, 449, 450, 453, 454, 458, 461 );
```
with (FID 438 added in sorted position):
```php
$select = array( 3, 7, 14, 19, 29, 45, 89, 137, 267, 361, 413, 422, 438, 440, 449, 450, 453, 454, 458, 461 );
```

**3c. Where clause at line 94.** Replace:
```php
'where'  => '{137.EX.true}',
```
with:
```php
'where'  => '{137.EX.true}AND{438.EX.false}',
```

### Task 4 — `arc-qb-sync/includes/sync-events.php`: `arc_qb_upsert_event()` docblock + per-record gate

**4a. Docblock at lines 108–122.** Replace:
```
/**
 * Create or update an `arc_event` WP post from a Quickbase record array.
 *
 * QB field 137 (Show Public) is the strict gatekeeper:
 *  - TRUE  → create or update the WP post as published.
 *  - FALSE → if a WP post already exists, demote it to draft.
 *            If no WP post exists yet, do nothing.
 *
 * Upsert key: _arc_qb_event_id post meta (QB field 3).
 *
 * @param  array     $record  A single QB record array keyed by field ID string.
 * @return int|\WP_Error      WP post ID on success, WP_Error on failure.
 *                            Returns 0 (not an error) if a non-public record has
 *                            no existing WP post — nothing to do.
 */
```
with:
```
/**
 * Create or update an `arc_event` WP post from a Quickbase record array.
 *
 * Joint gatekeeper (v3.10.0+): an event is treated as publicly listed when
 *   - FID 137 `Show Public` = TRUE, AND
 *   - FID 438 `Hide from Public Event Listings` = FALSE
 *
 * Both conditions must hold:
 *  - Both TRUE/FALSE accordingly → create or update the WP post as published.
 *  - Either gate against → if a WP post already exists, demote it to draft.
 *                          If no WP post exists yet, do nothing.
 *
 * In normal bulk-sync flow, records that fail either gate are filtered out by
 * the `where` clause in arc_qb_fetch_all_event_records() and never reach this
 * function; the ghost-removal pass in arc_qb_sync_all_events() handles existing
 * posts whose gates have flipped. The defensive branch below remains in place
 * so that any future per-record caller (e.g. a webhook handler) inherits the
 * same gate semantics.
 *
 * Upsert key: _arc_qb_event_id post meta (QB field 3).
 *
 * @param  array     $record  A single QB record array keyed by field ID string.
 * @return int|\WP_Error      WP post ID on success, WP_Error on failure.
 *                            Returns 0 (not an error) if a non-listed record has
 *                            no existing WP post — nothing to do.
 */
```

**4b. Per-record extraction at lines 127–132.** Replace:
```php
$qb_event_id = intval( arc_qb_get_course_field( $record, 3 ) );
$title       = arc_qb_get_course_field( $record, 19 );
$public_raw  = arc_qb_get_course_field( $record, 137 );

// Determine whether this event is publicly listed.
$is_public = ( true === $public_raw || 'true' === strtolower( (string) $public_raw ) );
```
with:
```php
$qb_event_id = intval( arc_qb_get_course_field( $record, 3 ) );
$title       = arc_qb_get_course_field( $record, 19 );
$public_raw  = arc_qb_get_course_field( $record, 137 );
$hidden_raw  = arc_qb_get_course_field( $record, 438 );

// Determine whether this event is publicly listed.
// Joint gate (v3.10.0+): FID 137 = TRUE AND FID 438 = FALSE.
$show_public = ( true === $public_raw || 'true' === strtolower( (string) $public_raw ) );
$is_hidden   = ( true === $hidden_raw || 'true' === strtolower( (string) $hidden_raw ) );
$is_public   = ( $show_public && ! $is_hidden );
```

The same truthy-string handling pattern is preserved (QB sometimes returns `'true'` / `'false'` as strings depending on the call path; matches the existing FID 137 logic).

**4c. Comment at line 148.** Replace:
```php
// ── Gatekeeper: non-public events ─────────────────────────────────────────
```
with:
```php
// ── Gatekeeper: non-listed events (FID 137 = false OR FID 438 = true) ─────
```

No code change in the branch body (lines 150–161) — the demote-to-draft / no-op semantics are identical for the new joint gate.

### Task 5 — `arc-qb-sync/includes/sync-events.php`: admin page description text

Line 483, replace:
```php
<p><?php esc_html_e( 'Pulls all publicly listed events (Show Public = checked) from the Quickbase Training Events table into WordPress as event posts. Non-public events are never imported. Use this for initial setup, after bulk changes in Quickbase, or to recover from a missed webhook.', 'arc-qb-sync' ); ?></p>
```
with:
```php
<p><?php esc_html_e( 'Pulls all publicly listed events (Show Public = checked AND Hide from Public Event Listings = unchecked) from the Quickbase Training Events table into WordPress as event posts. Non-listed events are never imported, and existing event posts whose gates have flipped are demoted to draft. Use this for initial setup, after bulk changes in Quickbase, or to recover from a missed webhook.', 'arc-qb-sync' ); ?></p>
```

### Task 6 — `arc-qb-sync/CHANGELOG.md`: new top entry

Prepend a new section above the current `## [3.9.0] — 2026-05-24` entry:

```markdown
## [3.10.0] — 2026-06-07

### Changed
- Public catalog gatekeeper is now a joint gate on Events FID 137 `Show Public` AND Events FID 438 `Hide from Public Event Listings` (v3.9.0 and earlier: FID 137 only). An event syncs to WordPress when FID 137 = TRUE **and** FID 438 = FALSE. Both the bulk-fetch `where` clause and the per-record `arc_qb_upsert_event()` defensive branch reflect the new gate. Existing event posts whose gates flip against them are demoted to draft via the existing ghost-removal pass.
- Admin page description text on the QB Event Sync screen updated to describe both gate fields.

### Added
- FID 438 added to the bulk select list (read-only; no WP meta write — catalog visibility only).

### Notes
- arc-event-reg is intentionally not changed by this ship. The registration plugin continues to gate registration on FID 137 only (`class-event-lookup.php`). FID 438 is a catalog-side concept; an event marked FID 438 = TRUE remains registerable via direct URL but does not appear in the public catalog browse. This is the "hard-to-find open door" pattern for unlisted private-group registrations.
- Field uplifted from `otac-pre-2024` use 2026-06-08 (Alan); added to the Events form the same session. Decision blocks live in `event-management/sessions/2026-06-07_free-in-person-workflow/README.md`.
```

## Verification Checklist

1. `grep "Version:" arc-qb-sync/arc-qb-sync.php` returns `3.10.0`.
2. `grep ARC_QB_SYNC_VERSION arc-qb-sync/arc-qb-sync.php` returns `'3.10.0'`.
3. `grep -n "438" arc-qb-sync/arc-qb-sync/includes/sync-events.php` returns hits in the file-top docblock, the function docblock, the select array, the where clause, and the per-record `$hidden_raw` / `$is_hidden` extraction.
4. `grep -n "137.EX.true" arc-qb-sync/arc-qb-sync/includes/sync-events.php` shows the new joint form `{137.EX.true}AND{438.EX.false}` — no surviving solo-form occurrence.
5. `grep -rn "Show Public = checked'" arc-qb-sync/arc-qb-sync/includes/sync-events.php` returns zero hits (admin text updated to mention both fields).
6. `php -l arc-qb-sync/arc-qb-sync/includes/sync-events.php` returns no syntax errors.
7. `php -l arc-qb-sync/arc-qb-sync.php` returns no syntax errors.
8. CHANGELOG top entry reads `[3.10.0] — 2026-06-07`.
9. No other files modified beyond `arc-qb-sync.php`, `arc-qb-sync/includes/sync-events.php`, and `CHANGELOG.md`.

## What NOT to Change

- **`arc-event-reg` and any cross-repo paths.** This ship is `arc-qb-sync` only. The arc-event-reg side received its (docs-only) update during the 2026-06-07 Cowork session — no code change is needed there. Do not touch `arc-event-reg/*` from this Code session.
- **The ghost-removal pass in `arc_qb_sync_all_events()` (lines ~348–367).** Its existing "any published post whose QB ID is not in `synced_ids` → demote to draft" logic naturally extends to the new joint gate. No edits.
- **`arc_qb_preview_event_sync()`.** Inherits the new gate via `arc_qb_fetch_all_event_records()`. No edits.
- **`docs/field-mapping-events.md` and `README.md`.** Neither documents gating fields today; no in-scope updates. Adding a "Gating fields" section is plausible future work, but explicitly out of scope here.
- **The legacy `arc-training-details/` folder at the repo root.** Out of scope.
- **All other shortcodes, image-pipeline code, and CPT logic.** Out of scope.

## Manual tests

Run after deploy on the live WP site. Use one event in each of the three gate states.

| # | Pre-condition | Action | Expected |
|---|---|---|---|
| 1 | A test event with FID 137 = TRUE, FID 438 = FALSE (normal public event) | Trigger a full event sync via WP Admin → QB Event Sync → Sync All Events Now | Event syncs as a published `arc_event` post. |
| 2 | A test event with FID 137 = TRUE, FID 438 = TRUE (unlisted) | Trigger a full sync | Event does NOT enter WordPress as a published post. If it already exists as a published post, it is demoted to draft by the ghost-removal pass. The registration URL (`?event-id=NNNN`) should still work end-to-end against `arc-event-reg` (verify by opening the registration page directly — out-of-band confirmation of the "hard-to-find open door" pattern). |
| 3 | A test event with FID 137 = FALSE (retired event; FID 438 doesn't matter) | Trigger a full sync | Event does NOT enter WordPress. Existing post (if any) is demoted to draft. arc-event-reg blocks registration via FID 137 (independent of this plugin). |
| 4 | Existing published post for an event; flip its FID 438 to TRUE in QB; trigger a sync | Trigger a full sync | The post is demoted to draft on this sync run (ghost-removal pass). |
| 5 | Same post as #4; flip FID 438 back to FALSE; trigger a sync | Trigger a full sync | The post is re-published. |
