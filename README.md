# Arc Oregon QB Sync

WordPress plugin that integrates Quickbase with thearcoregon.org. Provides shortcodes for training event detail pages and a filterable public course catalog.

---

## Shortcodes

### Events Module

Used on `/training-details/?event-id=NNNN` pages.

| Shortcode | Field | Output |
|---|---|---|
| `[event_title]` | Event Title | Escaped text |
| `[event_dates]` | Event Date(s) | Escaped text |
| `[event_time]` | Event Time | Escaped text |
| `[venue_name]` | Venue Name | Escaped text |
| `[instructors]` | Instructor(s) | Escaped text |
| `[training_cost]` | Training Cost | Safe HTML (links, bold, etc.) |
| `[event_description]` | Event Description | HTML via `wp_kses_post` |
| `[add_registration_url]` | Registration URL | Escaped URL |
| `[event_days_of_week]` | Day(s) of Week | Escaped text |
| `[event_mode]` | Event Mode | Escaped text |
| `[featured_image_url]` | Featured Image URL | Escaped URL |
| `[flyer_url]` | Flyer URL | Escaped URL |
| `[instructor_slugs]` | Instructor Slugs (pipe-sep.) | Escaped text |
| `[is_multiday]` | Is Multi-Day | `"1"` or `"0"` |
| `[is_multisession]` | Is Multi-Session | `"1"` or `"0"` |
| `[arc_training_field id="N"]` | Any field by ID | Escaped text |
| `[arc_training_field id="N" format="html"]` | Any field by ID | HTML |
| `[arc_trainer_title]` | Trainer post title | Escaped text (Elementor loop) |

### Courses Module

Used on `/course-catalog/?course-id=NNNN` detail pages.

| Shortcode | Field | Output |
|---|---|---|
| `[course_title]` | Course Title (6) | Escaped text |
| `[course_short_description]` | Short Description (46) | HTML |
| `[course_description]` | Learning Obj. HTML (85), fallback field 7 | HTML |
| `[course_length]` | Hours of Instruction (14) | Escaped text |
| `[course_tags]` | Keywords / Tags (56) | `<span class="arc-tag">` pills |
| `[course_image_url]` | Featured Image URL (88) | Escaped URL |
| `[course_delivery_method]` | Delivery Method (40) | Escaped text |
| `[course_target_audience]` | Target Audience (50) | HTML |
| `[course_category]` | Category (43) | Escaped text |
| `[course_payment]` | Payment (39) | Escaped text |
| `[course_learning_objectives]` | Learning Obj. HTML (85), fallback field 62 | HTML |
| `[arc_qb_course_field id="N"]` | Any field by ID | Escaped text |
| `[arc_qb_course_field id="N" format="html"]` | Any field by ID | HTML |

### Course Catalog Grid

```
[course_catalog]
```

Renders a filterable grid of all publicly listed courses (QB field 36 = true). Place on the `/training` page. Enqueues `course-catalog.js` and `course-catalog.css` automatically. Catalog data is cached for 15 minutes via WP Transients.

---

## Build

From the repo root:

```bash
./build.sh
```

Creates `arc-qb-sync.zip` ready for WordPress upload.

---

## Deploy

1. Run `./build.sh`
2. WP Admin → Plugins → Add New → Upload Plugin → choose `arc-qb-sync.zip`
3. Install Now → Activate

See `docs/setup.md` for first-install steps, including wp-config.php constants that must be set before activation.

---

## wp-config.php Constants

See [`docs/setup.md`](docs/setup.md) for full reference. Five constants are required:

- `QB_REALM_HOST` — Quickbase realm hostname
- `QB_TABLE_ID` — Events table ID
- `QB_USER_TOKEN` — Quickbase API user token
- `QB_COURSES_TABLE_ID` — Course Catalog table ID *(new in 1.0.0)*
- `ARC_QB_CACHE_BUST_TOKEN` — Shared secret for Zapier cache invalidation *(new in 1.0.0)*

---

## Cache Invalidation

The course catalog supports automated cache clearing via Quickbase webhook + Zapier. See [`docs/webhook-zapier.md`](docs/webhook-zapier.md) for setup instructions.

REST endpoint: `POST /wp-json/arc-qb-sync/v1/bust-cache`  
Header required: `Authorization: Bearer [ARC_QB_CACHE_BUST_TOKEN]`
