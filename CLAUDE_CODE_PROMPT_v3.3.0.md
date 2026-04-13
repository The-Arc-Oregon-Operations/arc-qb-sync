# Claude Code Task: New Instructor and Course Fields (v3.3.0)

## What you are doing and why

Three new Quickbase fields need to be threaded through the full sync pipeline: QB query → post meta → shortcode. Two are in the Instructors table (Pronouns, Trainer Role(s)); two are in the Course Catalog table (Offer delivery Online, Offer delivery In-Person). A fifth fix — removing a spurious `wpautop()` call on the already-formatted bio field — is included because it was discovered during the same analysis.

Read this entire prompt before making any changes. Do the work in the order listed. Version bump and CHANGELOG come last.

---

## Context and field behavior

**Instructor Name (FID 6):** The QB field is a fully concatenated plain-text string in the format `[First Name] [Last Name], [Credentials] (Pronouns)` — the credentials and pronouns are conditionally appended in QB. This is what syncs to `post_title`. Do not change how this field is handled.

**Pronouns (FID 29):** Plain text. Already includes surrounding parentheses as entered in QB — e.g., `(she/her)`. No transformation needed; `esc_html()` on output.

**Trainer Role(s) (FID 31):** Multi-line text field. Content comes from QB pre-formatted with HTML markup (`<p>`, `<ul>`, `<li>` tags). Use `wp_kses_post()` without `wpautop()` — both for storage and output. Do not apply `wpautop()`.

**Bio (FID 11):** Same as Trainer Role(s) — pre-formatted HTML from QB. The current shortcode incorrectly applies `wpautop()` on top of already-formatted content. This is fixed in step 1 below.

**Offer delivery Online (FID 109) / In-Person (FID 110):** Checkbox fields. Store as `"1"` or `"0"`, following the same pattern as FID 90 (Use Attribution Line).

---

## 1. Fix `[instructor_bio]` — remove spurious `wpautop()`

**File:** `includes/shortcodes-instructors.php`

In `arc_qb_sc_arc_instructor_bio()`, replace:

```php
return wp_kses_post( wpautop( (string) $value ) );
```

With:

```php
return wp_kses_post( (string) $value );
```

The bio content arrives from QB already formatted with `<p>` and other block-level HTML. Applying `wpautop()` double-wraps it. Also update the generic shortcode handler `arc_qb_shortcode_arc_instructor_field_generic()`: when `format="html"`, replace `wp_kses_post( wpautop( (string) $value ) )` with `wp_kses_post( (string) $value )`.

---

## 2. Add Pronouns (FID 29) — Instructors

### 2a. `includes/sync-instructors.php`

Add the constant after the existing `ARC_QB_INSTRUCTOR_FID_ACTIVE` definition:

```php
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_PRONOUNS' ) ) define( 'ARC_QB_INSTRUCTOR_FID_PRONOUNS', 29 ); // Pronouns — plain text, stored with parens e.g. (she/her)
```

Update the header comment block to add:

```
 *   ARC_QB_INSTRUCTOR_FID_PRONOUNS     (29) → _arc_instructor_pronouns
```

In `arc_qb_fetch_all_instructor_records()`, add to the `$select` array:

```php
ARC_QB_INSTRUCTOR_FID_PRONOUNS,     // 29
```

In `arc_qb_upsert_instructor()`, add after the existing `update_post_meta` calls:

```php
update_post_meta( $post_id, '_arc_instructor_pronouns',
    esc_html( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_PRONOUNS ) ) );
```

### 2b. `includes/shortcodes-instructors.php`

Add the function after `arc_qb_sc_arc_instructor_bio()`:

```php
/**
 * [instructor_pronouns] — Instructor pronouns (plain text, includes parens from QB).
 *
 * @return string esc_html() output.
 */
function arc_qb_sc_arc_instructor_pronouns() {
	$value = get_post_meta( get_the_ID(), '_arc_instructor_pronouns', true );
	if ( '' === $value ) {
		return '';
	}
	return esc_html( (string) $value );
}
```

Register the shortcode in `arc_qb_register_arc_instructor_shortcodes()`:

```php
add_shortcode( 'instructor_pronouns',     'arc_qb_sc_arc_instructor_pronouns' );
add_shortcode( 'arc_instructor_pronouns', 'arc_qb_sc_arc_instructor_pronouns' ); // Deprecated alias — remove in future version
```

Update the header comment block to include:

```
 * [instructor_pronouns]     / [arc_instructor_pronouns]     — _arc_instructor_pronouns
```

---

## 3. Add Trainer Role(s) (FID 31) — Instructors

### 3a. `includes/sync-instructors.php`

Add the constant after `ARC_QB_INSTRUCTOR_FID_PRONOUNS`:

```php
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_TRAINER_ROLES' ) ) define( 'ARC_QB_INSTRUCTOR_FID_TRAINER_ROLES', 31 ); // Trainer Role(s) — pre-formatted HTML (p, ul, li)
```

Update the header comment block:

```
 *   ARC_QB_INSTRUCTOR_FID_TRAINER_ROLES (31) → _arc_instructor_trainer_roles
```

In `arc_qb_fetch_all_instructor_records()`, add to `$select`:

```php
ARC_QB_INSTRUCTOR_FID_TRAINER_ROLES, // 31
```

In `arc_qb_upsert_instructor()`, add:

```php
update_post_meta( $post_id, '_arc_instructor_trainer_roles',
    wp_kses_post( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_TRAINER_ROLES ) ) );
// Note: no wpautop() — content arrives from QB pre-formatted with <p>/<ul> markup.
```

### 3b. `includes/shortcodes-instructors.php`

Add the function after `arc_qb_sc_arc_instructor_pronouns()`:

```php
/**
 * [instructor_trainer_roles] — Instructor trainer role(s) (pre-formatted HTML from QB).
 *
 * Content arrives with <p>, <ul>, <li> markup already applied.
 * wp_kses_post() sanitizes on output; wpautop() is intentionally NOT applied.
 *
 * @return string wp_kses_post() output.
 */
function arc_qb_sc_arc_instructor_trainer_roles() {
	$value = get_post_meta( get_the_ID(), '_arc_instructor_trainer_roles', true );
	if ( '' === $value ) {
		return '';
	}
	return wp_kses_post( (string) $value );
}
```

Register:

```php
add_shortcode( 'instructor_trainer_roles',     'arc_qb_sc_arc_instructor_trainer_roles' );
add_shortcode( 'arc_instructor_trainer_roles', 'arc_qb_sc_arc_instructor_trainer_roles' ); // Deprecated alias — remove in future version
```

Update the header comment block:

```
 * [instructor_trainer_roles] / [arc_instructor_trainer_roles] — _arc_instructor_trainer_roles
```

---

## 4. Add Offer delivery Online (FID 109) and Offer delivery In-Person (FID 110) — Courses

### 4a. `includes/sync-courses.php`

Add constants after the existing course image FID definitions:

```php
define( 'ARC_QB_COURSE_OFFERS_ONLINE_FID',   109 ); // Offer delivery Online — Checkbox → "1" / "0"
define( 'ARC_QB_COURSE_OFFERS_INPERSON_FID', 110 ); // Offer delivery In-Person — Checkbox → "1" / "0"
```

There are two `$select` arrays in this file — one in `arc_qb_fetch_course_record()` and one in `arc_qb_sync_all_courses()`. Add both FIDs to both arrays:

```php
ARC_QB_COURSE_OFFERS_ONLINE_FID,   // 109
ARC_QB_COURSE_OFFERS_INPERSON_FID, // 110
```

In `arc_qb_upsert_course()`, add after the Use Attribution block (FID 90), following the same checkbox pattern:

```php
// FID 109 — Offer delivery Online (checkbox)
$offers_online_raw = arc_qb_get_course_field( $record, ARC_QB_COURSE_OFFERS_ONLINE_FID );
update_post_meta( $post_id, '_arc_course_offers_online',
    ( true === $offers_online_raw || 'true' === strtolower( (string) $offers_online_raw ) ) ? '1' : '0' );

// FID 110 — Offer delivery In-Person (checkbox)
$offers_inperson_raw = arc_qb_get_course_field( $record, ARC_QB_COURSE_OFFERS_INPERSON_FID );
update_post_meta( $post_id, '_arc_course_offers_inperson',
    ( true === $offers_inperson_raw || 'true' === strtolower( (string) $offers_inperson_raw ) ) ? '1' : '0' );
```

### 4b. `includes/shortcodes-courses.php`

Add two functions after `arc_qb_sc_course_use_attribution()`:

```php
/**
 * [course_offers_online] — Whether the course offers online delivery.
 *
 * Returns "1" if checked in QB, "0" otherwise.
 *
 * @return string "1" or "0".
 */
function arc_qb_sc_course_offers_online() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '0';
	}
	return esc_html( get_post_meta( $post_id, '_arc_course_offers_online', true ) ?: '0' );
}

/**
 * [course_offers_inperson] — Whether the course offers in-person delivery.
 *
 * Returns "1" if checked in QB, "0" otherwise.
 *
 * @return string "1" or "0".
 */
function arc_qb_sc_course_offers_inperson() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '0';
	}
	return esc_html( get_post_meta( $post_id, '_arc_course_offers_inperson', true ) ?: '0' );
}
```

Register in the shortcode init function:

```php
add_shortcode( 'course_offers_online',   'arc_qb_sc_course_offers_online' );
add_shortcode( 'course_offers_inperson', 'arc_qb_sc_course_offers_inperson' );
```

---

## 5. Update `docs/field-mapping-instructors.md`

Add the following rows to the Field Mapping table:

| QB FID Constant | FID | QB Label | WP Meta Key | Shortcode | Output |
|---|---|---|---|---|---|
| `ARC_QB_INSTRUCTOR_FID_PRONOUNS` | 29 | Pronouns | `_arc_instructor_pronouns` | `[instructor_pronouns]` | `esc_html()` — stored with parens, e.g. (she/her) |
| `ARC_QB_INSTRUCTOR_FID_TRAINER_ROLES` | 31 | Trainer Role(s) | `_arc_instructor_trainer_roles` | `[instructor_trainer_roles]` | `wp_kses_post()` — pre-formatted HTML, no `wpautop()` |

Add a note under the field table:

> **Note on pre-formatted HTML fields:** FID 11 (Bio) and FID 31 (Trainer Role(s)) arrive from QB with `<p>`, `<ul>`, and `<li>` markup already applied. Neither the sync nor the shortcode applies `wpautop()` to these fields.

Add a note on the Instructor Name field (FID 6):

> **Note on Instructor Name (FID 6):** This is a fully concatenated QB formula field: `[First Name] [Last Name][, Credentials] (Pronouns)`. It syncs to `post_title` as-is and is the authoritative display name. Credentials and pronouns are conditionally appended in QB — do not re-derive this string from the individual component fields in WP.

---

## 6. Update `docs/field-mapping-courses.md`

Add the following rows to the Field Map table:

| QB FID | QB Label | Meta Key / WP Field | Shortcode | Notes |
|--------|----------|---------------------|-----------|-------|
| 109 | Offer delivery Online | `_arc_course_offers_online` | `[course_offers_online]` | Checkbox → `"1"` or `"0"` |
| 110 | Offer delivery In-Person | `_arc_course_offers_inperson` | `[course_offers_inperson]` | Checkbox → `"1"` or `"0"` |

Update the **Synced FIDs** line at the top of the document to include `109, 110`.

Update the **Supported IDs** line in the Generic Shortcode section to include `109, 110`. Also add both FIDs to the `$field_map` array in `arc_qb_sc_course_field_generic()`:

```php
109 => '_arc_course_offers_online',
110 => '_arc_course_offers_inperson',
```

---

## 7. Version bump and CHANGELOG entry

- Bump version to `3.3.0` in `arc-qb-sync/arc-qb-sync.php` (`ARC_QB_SYNC_VERSION` constant and plugin header)
- Add to `CHANGELOG.md`:

```markdown
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
```

---

## What NOT to change

- Do not touch the Instructor Name (FID 6) sync — it already maps to `post_title` correctly as a QB-concatenated string
- Do not apply `wpautop()` to Trainer Role(s) or Bio anywhere in this change set
- Do not modify Events sync or shortcode files
- Do not add `[course_offers_online]` or `[course_offers_inperson]` to the existing `[course_field]` generic shortcode's FID list unless you also add them to the `$field_map` array in `arc_qb_sc_course_field_generic()` (step 6 covers this)
