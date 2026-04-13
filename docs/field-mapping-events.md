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

## Events CPT Shortcodes (`arc_event_*`) — v3.0.0

These shortcodes read from WP post meta on `arc_event` CPT posts. They require the Events CPT sync (`sync-events.php`) to be active and a full sync to have been run. They use the `arc_event_` prefix to avoid conflicts with the QB-direct `event_*` shortcodes above.

| Shortcode | WP Meta Key | Source FID | Notes |
|---|---|---|---|
| `[arc_event_id]` | `_arc_qb_event_id` | 3 | `esc_html()` |
| `[arc_event_title]` | *(post_title)* | 19 | `esc_html()` via `get_the_title()` |
| `[arc_event_dates]` | `_arc_event_dates` | 45 | `esc_html()` |
| `[arc_event_time]` | `_arc_event_time` | 89 | `esc_html()` |
| `[arc_event_venue]` | `_arc_event_venue` | 29 | `esc_html()` |
| `[arc_event_days_of_week]` | `_arc_event_days_of_week` | 413 | `esc_html()` |
| `[event_schedule]` | `_arc_event_schedule` | computed | Computed from FIDs 413 + 45, separated by ` • `. `esc_html()` |
| `[arc_event_mode]` | `_arc_event_mode` | 458 | `esc_html()` |
| `[arc_event_length]` | `_arc_event_length` | 361 | `esc_html()` |
| `[arc_event_description]` | `_arc_event_description` | 440 | `wp_kses_post( wpautop() )` |
| `[arc_event_price]` | `_arc_event_price` | 450 | `wp_kses()` with link/strong/em/span/br/strike |
| `[arc_event_reg_url]` | `_arc_event_reg_url` | 14 | `esc_url()` |
| `[arc_event_flyer_url]` | `_arc_event_flyer_url` | 267 | `esc_url()` |
| `[arc_event_image_url]` | `_arc_event_image_url` | 461 | `esc_url()` — legacy manual field |
| `[arc_event_featured_image_url]` | `_arc_event_featured_image_url` | 464 | `esc_url()` — Image Assets lookup |
| `[arc_event_hero_image_url]` | `_arc_event_hero_image_url` | 466 | `esc_url()` — Image Assets lookup |
| `[arc_event_instructors_legacy]` | `_arc_event_instructors_legacy` | 271 | `esc_html()` — legacy manual field |
| `[arc_event_instructor_slugs_legacy]` | `_arc_event_instructor_slugs_legacy` | 449 | `esc_html()` — pipe-separated, legacy |
| *(removed v3.5.0)* | `_arc_event_instructor1_name` | 482 | Removed — never deployed in live templates |
| *(removed v3.5.0)* | `_arc_event_instructor1_headshot_url` | 483 | Removed — never deployed in live templates |
| *(removed v3.5.0)* | `_arc_event_instructor1_headshot_alt` | 484 | Removed — never deployed in live templates |
| *(removed v3.5.0)* | `_arc_event_instructor2_name` | 486 | Removed — never deployed in live templates |
| *(removed v3.5.0)* | `_arc_event_instructor2_headshot_url` | 487 | Removed — never deployed in live templates |
| *(removed v3.5.0)* | `_arc_event_instructor2_headshot_alt` | 494 | Removed — never deployed in live templates |
| *(removed v3.5.0)* | `_arc_event_instructor3_name` | 491 | Removed — never deployed in live templates |
| *(removed v3.5.0)* | `_arc_event_instructor3_headshot_url` | 492 | Removed — never deployed in live templates |
| *(removed v3.5.0)* | `_arc_event_instructor3_headshot_alt` | 493 | Removed — never deployed in live templates |
| `[arc_event_is_multiday]` | `_arc_event_is_multiday` | 453 | Returns `"1"` or `"0"` |
| `[arc_event_is_multisession]` | `_arc_event_is_multisession` | 454 | Returns `"1"` or `"0"` |

Generic escape hatch:

```
[arc_event_field meta="_arc_event_dates"]
[arc_event_field meta="_arc_event_description" format="html"]
```

- `format="text"` (default) — `esc_html()`
- `format="html"` — `wp_kses_post( wpautop() )`

---

## Generic Shortcode (QB-direct)

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
