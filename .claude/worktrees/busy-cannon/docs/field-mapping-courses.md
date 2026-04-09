# Courses Module — Field Mapping Reference

**Quickbase App ID:** `bc7mmze9h`  
**Realm:** `otac.quickbase.com`  
**Table ID:** `bc7mmze9m` (set via `QB_COURSES_TABLE_ID` constant)  
**URL pattern:** `/course-catalog/?course-id=NNNN`

---

## Detail Page Fields

| QB Field ID | QB Label | QB Type | Shortcode | Output Treatment | Notes |
|---|---|---|---|---|---|
| 3 | Record ID# | Record ID | *(filter key)* | — | Used in `{3.EX.[course-id]}` query; used as tile CTA link param |
| 6 | Course Title | Text | `[course_title]` | `esc_html()` | Also used as `<img alt>` in catalog tiles |
| 7 | Description + Learning Objectives | Text | *(fallback)* | `wp_kses_post( wpautop() )` | Used by `[course_description]` only when field 85 is empty |
| 14 | Hours of Instruction | Duration | `[course_length]` | `esc_html()` | QB returns as-is; shown as tile "Length" label |
| 36 | Public Listing | Checkbox | *(visibility flag)* | — | `true` = show on website; used as catalog query filter `{36.EX.true}` |
| 39 | Payment | Currency | `[course_payment]` | `esc_html()` | QB returns numeric; display as-is |
| 40 | Delivery Method | Text (multiple choice) | `[course_delivery_method]` | `esc_html()` | |
| 43 | Category | Text (multiple choice) | `[course_category]` | `esc_html()` | |
| 46 | Description, Short | Text (multi-line) | `[course_short_description]` | `wp_kses_post( wpautop() )` | Tile preview text + detail page intro |
| 50 | Target Audience - English | Text (multi-line) | `[course_target_audience]` | `wp_kses_post( wpautop() )` | |
| 56 | Keywords / Tags | Multi-line Text | `[course_tags]` | Tag `<span>` pills | Parsed by `arc_qb_parse_tags()`. ⚠️ Delimiter assumed `\n` — verify against live API |
| 62 | Learning Objectives | Text (multi-line) | *(fallback)* | `wp_kses_post( wpautop() )` | Used by `[course_learning_objectives]` when field 85 is empty |
| 85 | Learning Objectives - HTML | Rich Text | `[course_description]` / `[course_learning_objectives]` | `wp_kses_post( wpautop() )` | Preferred source; falls back to field 7 (description) or 62 (objectives) |
| 88 | Featured Image URL | URL | `[course_image_url]` | `esc_url()` | Tile image + detail page hero |

---

## Catalog Tile Fields (fetched by `arc_qb_get_public_courses()`)

The catalog fetch selects only the fields needed for tiles — not the full detail set — to keep payloads small:

| Field ID | Label | Used for |
|---|---|---|
| 3 | Record ID# | CTA link `?course-id=` param |
| 6 | Course Title | Tile heading |
| 14 | Hours of Instruction | Tile "Length" line |
| 46 | Description, Short | Embedded in JSON for potential JS use |
| 56 | Keywords / Tags | Filter pills; tile tag pills |
| 88 | Featured Image URL | Tile image |

---

## Generic Shortcode

Any Course Catalog field can be accessed by ID:

```
[arc_qb_course_field id="NNNN"]
[arc_qb_course_field id="85" format="html"]
```

- `format="text"` (default) — `esc_html()`
- `format="html"` — `wp_kses_post( wpautop() )`

---

## Tags Field Note

Field 56 is a Multi-line Text field in QB. The `arc_qb_parse_tags()` function splits on `\n` (newline). If the live QB API returns tags with a different delimiter (e.g. comma, pipe), update the `$delimiter` variable in `includes/courses.php`.
