# Courses Module — Field Mapping Reference

**Quickbase App ID:** `bc7mmze9h`
**Realm:** `otac.quickbase.com`
**Table ID:** `bc7mmze9m` (set via `QB_COURSES_TABLE_ID` constant)

**Synced FIDs:** 3, 6, 14, 20, 36, 39, 40, 43, 46, 50, 56, 62, 84, 85, 88, 89, 90, 92, 94, 96

---

## Field Map

| QB FID | QB Label | Meta Key / WP Field | Shortcode | Notes |
|--------|----------|---------------------|-----------|-------|
| 3 | Record ID# | `_arc_qb_record_id` | `[course_id]` | Sync key; used in `{3.EX.NNNN}` QB query |
| 6 | Course Title | `post_title` | `[course_title]` | Also drives `<img alt>` in catalog tiles |
| 14 | Hours of Instruction | `_arc_course_length_ms` (raw ms) + `_arc_course_length` (formatted) | `[course_length]` | Formatted by `arc_qb_format_duration()`, e.g. "6.5 hours" |
| 20 | Length Num | `_arc_course_hours` | `[course_hours]` | Numeric hours value |
| 36 | Public Listing | `post_status` (publish / draft) | *(visibility flag)* | `true` = publish; `false` = draft existing post; never create non-public posts |
| 39 | Base Rate | `_arc_course_base_rate` | `[course_base_rate]` | Renamed from `course_payment` in v2.2.0 |
| 40 | Delivery Method | `_arc_course_delivery_method` | `[course_delivery_method]` | |
| 43 | Category | `_arc_course_category` | `[course_category]` | |
| 46 | Description, Short | `post_excerpt` | `[course_short_description]` | Tile preview text + detail page intro |
| 50 | Target Audience - English | `_arc_course_target_audience` | `[course_target_audience]` | Stored and output as HTML (`wp_kses_post`) |
| 56 | Keywords / Tags | `course_tag` taxonomy + `_course_tag_slugs` | `[course_tags]` | Parsed by `arc_qb_parse_tags()`; `_course_tag_slugs` is a comma-separated slug string for Elementor `data-tags`; **not accessible via `[course_field]`** (taxonomy-based — use `[course_tags]` instead) |
| 62 | Learning Objectives | `_arc_course_learning_objectives_html` | `[course_description]` / `[course_learning_objectives]` | Primary display field; stored as HTML |
| 84 | Link to Course Overview Page | `_arc_course_details_url` | `[course_details_url]` | Stored via `esc_url_raw()` |
| 85 | Learning Objectives (secondary) | `_arc_course_learning_objectives` | `[course_learning_objectives2]` | Secondary field; `[course_learning_objectives]` falls back to FID 62 if this is empty |
| 88 | Featured Image URL (legacy) | `_arc_course_image_url` | `[course_image_url]` | Legacy manual URL field |
| 89 | Attribution | `_arc_course_attribution` | `[course_attribution]` | Note: FID 89 in the Events table is "Event Time" — separate table, independent FID numbering |
| 90 | Use Attribution | `_arc_course_use_attribution` | `[course_use_attribution]` | Checkbox; stored as `"1"` or `"0"` |
| 92 | Slug for Website | `_arc_course_slug` + `post_name` | `[course_field id="92"]` | Drives `post_name` on insert and update; QB-managed stable URL slug |
| 94 | Featured Image URL | `_arc_course_featured_image_url` | `[course_featured_image_url]` / `[course_field id="94"]` | Lookup from Image Assets table; preferred over FID 88 for new templates |
| 96 | Hero Image URL | `_arc_course_hero_image_url` | `[course_hero_image_url]` / `[course_field id="96"]` | Lookup from Image Assets table |

---

## Constructed Shortcode

| Shortcode | Description |
|-----------|-------------|
| `[course_request_url]` | Builds the organization training request CTA URL from `_arc_qb_record_id`. No QB fetch required. Works on both CPT pages and legacy `?course-id=NNNN` pages. |

---

## Generic Shortcode

Any synced field can be accessed by QB field ID:

```
[course_field id="NNNN"]
[course_field id="85" format="html"]
```

- `format="text"` (default) — `esc_html()`
- `format="html"` — `wp_kses_post( wpautop() )`

**Supported IDs:** 3, 6, 14, 20, 39, 40, 43, 46, 50, 62, 84, 85, 88, 89, 90, 92, 94, 96

**Not supported via `[course_field]`:** FID 56 (tags) — taxonomy-based, use `[course_tags]` instead.

---

## URL Field Convention

Fields that store URLs use the `_url` suffix in their meta key and have dedicated shortcodes ending in `_url`:

| Meta Key | Shortcode |
|----------|-----------|
| `_arc_course_image_url` | `[course_image_url]` |
| `_arc_course_details_url` | `[course_details_url]` |
| `_arc_course_featured_image_url` | `[course_featured_image_url]` |
| `_arc_course_hero_image_url` | `[course_hero_image_url]` |

---

## Context Resolution

All `[course_*]` shortcodes use `arc_qb_get_course_post_id()` to resolve context:

1. If the current post is a `course` CPT, use its ID.
2. If `?course-id=NNNN` is in the URL, look up the `course` post by `_arc_qb_record_id` meta.
3. Return empty if no course context is found.

This means all shortcodes work on both native CPT permalinks (`/course/[slug]/`) and legacy `?course-id=NNNN` pages without template changes.

---

*See also: `docs/field-mapping-events.md`, `docs/field-mapping-instructors.md`*
