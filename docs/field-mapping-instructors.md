# Instructors Module — Field Mapping Reference

**Quickbase App ID:** `bc7mmze9h`  
**Realm:** `otac.quickbase.com`  
**Table ID:** set via `QB_INSTRUCTORS_TABLE_ID` constant in `wp-config.php` (e.g. `bvx9ae9x8`)  
**WordPress CPT:** `instructor`  
**Admin sync page:** WP Admin → Settings → Arc Instructor Sync

---

## Field Mapping

| QB FID Constant | FID | QB Label | WP Meta Key | Shortcode | Output |
|---|---|---|---|---|---|
| `ARC_QB_INSTRUCTOR_FID_RECORD_ID` | 3 | Record ID# | `_arc_qb_instructor_id` | `[arc_instructor_id]` | `esc_html()` — sync key |
| `ARC_QB_INSTRUCTOR_FID_NAME` | 6 | Instructor Name | *(post_title)* | `[arc_instructor_name]` | `esc_html()` via `get_the_title()` — fully concatenated QB formula field; see note below |
| `ARC_QB_INSTRUCTOR_FID_FIRST_NAME` | 7 | First Name | `_arc_instructor_first_name` | `[arc_instructor_first_name]` | `esc_html()` |
| `ARC_QB_INSTRUCTOR_FID_LAST_NAME` | 8 | Last Name | `_arc_instructor_last_name` | `[arc_instructor_last_name]` | `esc_html()` |
| `ARC_QB_INSTRUCTOR_FID_CONTACT_URL` | 9 | Contact Me URL | `_arc_instructor_contact_url` | `[arc_instructor_contact_url]` | `esc_url()` |
| `ARC_QB_INSTRUCTOR_FID_CREDENTIALS` | 10 | Credentials | `_arc_instructor_credentials` | `[arc_instructor_credentials]` | `esc_html()` |
| `ARC_QB_INSTRUCTOR_FID_BIO` | 11 | Bio | `_arc_instructor_bio` | `[arc_instructor_bio]` | `wp_kses_post()` — pre-formatted HTML, no `wpautop()` |
| `ARC_QB_INSTRUCTOR_FID_TITLE` | 12 | Title/Position | `_arc_instructor_title` | `[arc_instructor_title]` | `esc_html()` |
| `ARC_QB_INSTRUCTOR_FID_ORGANIZATION` | 13 | Organization | `_arc_instructor_organization` | `[arc_instructor_organization]` | `esc_html()` |
| 15 | 15 | Headshot URL [lookup] | `_arc_instructor_headshot_url` | `[arc_instructor_headshot_url]` | `esc_url()` — from Image Assets table |
| `ARC_QB_INSTRUCTOR_FID_SLUG` | 27 | slug | `_arc_instructor_slug` + `post_name` | `[arc_instructor_slug]` | `esc_html()` — also drives WP post slug |
| `ARC_QB_INSTRUCTOR_FID_ACTIVE` | 28 | Active | → `post_status` | *(visibility flag)* | `true` = publish; `false` = draft; sync query filter |
| `ARC_QB_INSTRUCTOR_FID_PRONOUNS` | 29 | Pronouns | `_arc_instructor_pronouns` | `[instructor_pronouns]` | `esc_html()` — stored with parens, e.g. (she/her) |
| `ARC_QB_INSTRUCTOR_FID_TRAINER_ROLES` | 31 | Trainer Role(s) | `_arc_instructor_trainer_roles` | `[instructor_trainer_roles]` | `wp_kses_post()` — pre-formatted HTML, no `wpautop()` |

> **Note:** All `ARC_QB_INSTRUCTOR_FID_*` constants are defined at the top of `sync-instructors.php`. FIDs are hardcoded in the plugin — none are required in `wp-config.php`.

> **Note on pre-formatted HTML fields:** FID 11 (Bio) and FID 31 (Trainer Role(s)) arrive from QB with `<p>`, `<ul>`, and `<li>` markup already applied. Neither the sync nor the shortcode applies `wpautop()` to these fields.

> **Note on Instructor Name (FID 6):** This is a fully concatenated QB formula field: `[First Name] [Last Name][, Credentials] (Pronouns)`. It syncs to `post_title` as-is and is the authoritative display name. Credentials and pronouns are conditionally appended in QB — do not re-derive this string from the individual component fields in WP.

> **Note:** No header image FID for instructors in this version — headshot only.

---

## Sync Behavior

- **Sync flag:** `ARC_QB_INSTRUCTOR_FID_ACTIVE` (FID 28) — only records where Active = TRUE are fetched and published.
- **Upsert key:** `_arc_qb_instructor_id` on `post_type = instructor`.
- **Post slug:** derived from QB Slug field (FID 27); falls back to `sanitize_title( name )` if empty.
- **Ghost removal:** after each full sync, any published `instructor` post whose QB ID was not returned by the sync query is demoted to draft. Posts are never deleted.

---

## Generic Shortcode

Any instructor meta value can be accessed by key:

```
[arc_instructor_field meta="_arc_instructor_credentials"]
[arc_instructor_field meta="_arc_instructor_bio" format="html"]
```

- `format="text"` (default) — `esc_html()`
- `format="html"` — `wp_kses_post()` (no `wpautop()` — content arrives pre-formatted from QB)

---

## Relationship to `trainer` CPT

The `instructor` CPT is a new parallel post type. The existing `trainer` CPT is **not** affected. Elementor loops using the `trainer` CPT and `[loop_trainer_title]` / `[arc_trainer_title]` shortcodes continue to work unchanged.
