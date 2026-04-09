# Events Module — Field Mapping Reference

**Quickbase App ID:** `bc7mmze9h`  
**Realm:** `otac.quickbase.com`  
**Table ID:** `bc7mmze9k` (set via `QB_TABLE_ID` constant)  
**URL pattern:** `/training-details/?event-id=NNNN`

---

| QB Field ID | Field Label | Shortcode | Notes |
|---|---|---|---|
| 3 | Record ID# | *(filter key)* | Used in `{3.EX.[event-id]}` query; not exposed as a shortcode |
| 14 | Add Registration (URL) | `[add_registration_url]` | Returns `esc_url()` output; also accessible via `[arc_training_field id="14" format="html"]` for full button markup |
| 19 | Event Title | `[event_title]` | `esc_html()` |
| 29 | Venue Name | `[venue_name]` | `esc_html()` |
| 45 | Event Date(s) | `[event_dates]` | `esc_html()` |
| 89 | Event Time | `[event_time]` | `esc_html()` |
| 267 | Flyer URL | `[flyer_url]` | `esc_url()` |
| 271 | Instructor(s) | `[instructors]` | `esc_html()` |
| 361 | Credit Hours | *(see note)* | Exposed via `[arc_training_field id="361"]` — no dedicated shortcode by this name; `arc_td_sc_credit_hours()` is registered but not listed in original shortcode header |
| 413 | Day(s) of Week | `[event_days_of_week]` | `esc_html()` |
| 440 | Event Description | `[event_description]` | `wp_kses_post( wpautop() )` — long text / HTML |
| 449 | Instructor Slugs | `[instructor_slugs]` | Pipe-separated, e.g. `nkaasa|ldutton`; also stored in `arc_trainer_slugs` query var for Elementor trainer loop |
| 450 | Training Cost | `[training_cost]` | `wp_kses()` with allowlist for `<a>`, `<strong>`, `<em>`, `<span>`, `<br>`, `<strike>` |
| 453 | Is Multi-Day | `[is_multiday]` | Returns `"1"` if truthy, `"0"` otherwise |
| 454 | Is Multi-Session | `[is_multisession]` | Returns `"1"` if truthy, `"0"` otherwise |
| 458 | Event Mode | `[event_mode]` | `esc_html()` — e.g. "Online", "In-person" |
| 461 | Featured Image URL | `[featured_image_url]` | `esc_url()` |

---

## Generic Shortcode

Any field can be accessed by ID:

```
[arc_training_field id="NNNN"]
[arc_training_field id="440" format="html"]
```

- `format="text"` (default) — `esc_html()`
- `format="html"` — `wp_kses_post( wpautop() )` (field 14 skips `wpautop`)

---

## Elementor Trainer Loop

The Elementor custom query hook `elementor/query/trainers` reads `[instructor_slugs]` (field 449) and sets `post_name__in` on the WP_Query to filter trainer CPT posts. Custom Query ID in Elementor must be set to `trainers`.

`[arc_trainer_title]` returns the raw `post_title` for the current loop post, bypassing any filters.
