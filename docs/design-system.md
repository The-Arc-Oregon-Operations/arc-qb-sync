---
status: in-development
started: 2026-04-11
owner: Alan Lytle
notes: Shortcode reference updated 2026-04-11 from source. Event card unblocked — event_* is current. Instructor prefix stays arc_instructor_* (not yet migrated). Pick up with Elementor build.
---

# QB Sync — Design System Plan

Reference for the Elementor styleguide build. A draft WordPress page titled "QB Sync — Design System" (post ID 38284) exists on thearcoregon.org as a starting canvas. No content has been built on it yet.

---

## Design Foundations

**Primary color for this project:** The Arc Blue (`#005E85`) — intentionally featured more prominently here than the brand's usual orange-dominant approach. This is a deliberate section-level differentiation for the training/QB Sync area.

**Full palette:**
| Name | Hex |
|---|---|
| The Arc Blue | `#005E85` |
| The Arc Orange | `#EA7125` |
| The Arc Yellow | `#FECB00` |
| The Arc Gray | `#484847` |
| The Arc Purple | `#825AA4` |

**Typography:** Poppins (already loaded on site). Georgia as a secondary accent font, used sparingly.

**Size floor:** Absolute minimum is roughly 14pt equivalent. Scale up from there. Nothing smaller than this anywhere on any template.

**Button style:** Primary = blue fill. Outlined buttons are open for exploration but not a standard. No secondary outlined buttons as a rule yet — to be decided during styleguide build.

---

## Elementor Build Approach

Each "thing" gets its own Elementor container, styled completely, then saved as a global widget or global template for reuse across course, event, and instructor templates.

Poppins is confirmed active on the site. Build directly — no font configuration needed.

---

## Containers to Build (in order)

### 1. Color Accessibility Blocks
One wide section per brand color. Each section shows:
- White text at multiple weights/sizes against that background
- The Arc Gray (`#484847`) text at multiple weights/sizes against that background
- Goal: determine which combinations are approved for use (e.g., white on orange is usually OK, better with heavier weights)
- Label each section by brand name ("The Arc Orange"), not hex code
- No hex codes visible on page — these are design decisions, not a developer color chart

### 2. Typography Scale
H1 through H4, body text, caption — all in Poppins on white background. Shows weights, sizes, and color hierarchy (blue for headings, gray for body, etc.).

### 3. Hero Header Container
Full-width section, The Arc Blue background, white H1, optional subtitle line in white or light weight. This becomes the reusable header pattern for course catalog, course detail, event detail, and instructor pages.

### 4. Section Divider / Label Pattern
How a named content section begins within a page — H2 with blue treatment (left border, underline, or color). To be explored during build.

### 5. Filter Pills
`[arc_course_filter_pills]` shortcode styled. Active pill vs. inactive pill states.

### 6. Button Set
Primary (blue fill), outlined (exploration only), text link. Decide during build which are approved for the standard.

### 7. Course Card
Fields to include:
- `[course_featured_image_url]` or `[course_image_url]`
- `[course_title]`
- `[course_category]`
- `[course_delivery_method]`
- `[course_hours]`
- `[course_short_description]`
- `[course_tags]`
- `[course_request_url]` (CTA)
- `[course_details_url]` (secondary link)

### 8. Instructor Card
Fields to include:
- `[arc_instructor_headshot_url]`
- `[arc_instructor_name]`
- `[arc_instructor_title]`
- `[arc_instructor_organization]`
- `[arc_instructor_credentials]`
- `[arc_instructor_bio]`
- `[arc_instructor_contact_url]`

### 9. Event Card
Fields to include:
- `[event_image_url]` (featured image)
- `[event_title]`
- `[event_dates]`
- `[event_time]`
- `[event_venue]`
- `[event_mode]`
- `[event_length]`
- `[event_price]`
- `[event_description]` (short/truncated)
- `[event_instructors]` (text list)
- `[event_reg_url]` (CTA)

---

## Shortcode Reference — Confirmed Current

Source: verified from plugin PHP files 2026-04-11. These are the shortcodes to use for all new template and styleguide work.

---

### Courses (`course_*` prefix — CPT-based, reads WP post meta)

| Shortcode | What it outputs |
|---|---|
| `[course_id]` | QB Record ID |
| `[course_title]` | Course title |
| `[course_short_description]` | Short description (post excerpt) |
| `[course_description]` | Learning objectives HTML (FID 62) |
| `[course_learning_objectives]` | Same as above, with plain-text fallback |
| `[course_learning_objectives2]` | Secondary learning objectives (FID 85) |
| `[course_length]` | Formatted duration string (e.g. "6.5 hours") |
| `[course_hours]` | Numeric hours only (FID 20) |
| `[course_category]` | Category |
| `[course_delivery_method]` | Delivery method |
| `[course_target_audience]` | Target audience (HTML) |
| `[course_base_rate]` | Price / base rate |
| `[course_tags]` | Tag pills (`<span class="arc-tag">`) |
| `[course_featured_image_url]` | Featured image URL (from Image Assets) |
| `[course_hero_image_url]` | Hero image URL (from Image Assets) |
| `[course_details_url]` | Link to course overview page |
| `[course_request_url]` | Org training request CTA URL (constructed) |
| `[course_attribution]` | Attribution text |
| `[course_use_attribution]` | Attribution flag ("1" or "0") |
| `[course_field id="N"]` | Any course field by QB FID |

> `[course_image_url]` exists in the code but is a legacy manual field (FID 88). Use `[course_featured_image_url]` or `[course_hero_image_url]` for new work.

**Catalog:**
| Shortcode | What it outputs |
|---|---|
| `[arc_course_filter_pills]` | Filter pill buttons for the catalog |

> `[course_catalog]` appears in some planning docs but is not registered in the main plugin branch as of 2026-04-11. May be in a worktree. Verify before using.

---

### Events (`event_*` prefix — live QB fetch)

**Confirmed current.** `event_*` is the preferred system for all new template work. `arc_event_*` (CPT-based) is held for backward compatibility only — do not use in new styleguide or template builds.

| Shortcode | What it outputs |
|---|---|
| `[event_id]` | QB Record ID (FID 3) |
| `[event_title]` | Event title (FID 19) |
| `[event_dates]` | Date(s) (FID 45) |
| `[event_time]` | Time (FID 89) |
| `[event_venue]` | Venue name (FID 29) |
| `[event_mode]` | Event mode / modality (FID 458) |
| `[event_days_of_week]` | Day(s) of week (FID 413) |
| `[event_length]` | Credit hours (FID 361) |
| `[event_price]` | Training cost (FID 450) |
| `[event_description]` | Event description (FID 440, HTML) |
| `[event_instructors]` | Instructor name(s) text (FID 271) |
| `[event_instructor_slugs]` | Instructor WP slugs, pipe-separated (FID 449) |
| `[event_image_url]` | Featured image URL (FID 461) |
| `[event_flyer]` | Flyer URL (FID 267) |
| `[event_reg_url]` | Registration URL (FID 14) |
| `[event_is_multiday]` | "1" or "0" (FID 453) |
| `[event_is_multisession]` | "1" or "0" (FID 454) |
| `[event_field id="N"]` | Any event field by QB FID |
| `[loop_trainer_title]` | Raw post_title for Elementor loop (trainer CPT) |

---

### Instructors (`arc_instructor_*` prefix — CPT-based, reads WP post meta)

> **Naming note:** The goal is a clean `instructor_*` prefix to match `course_*` and `event_*`, but the migration hasn't happened yet. The only registered shortcodes use `arc_instructor_*`. Use these until the prefix is updated.

| Shortcode | What it outputs |
|---|---|
| `[arc_instructor_id]` | QB Record ID |
| `[arc_instructor_name]` | Full name (post title) |
| `[arc_instructor_first_name]` | First name |
| `[arc_instructor_last_name]` | Last name |
| `[arc_instructor_title]` | Title / position |
| `[arc_instructor_organization]` | Organization |
| `[arc_instructor_credentials]` | Credentials |
| `[arc_instructor_bio]` | Bio (HTML) |
| `[arc_instructor_headshot_url]` | Headshot image URL |
| `[arc_instructor_contact_url]` | Contact URL |
| `[arc_instructor_slug]` | WP post slug |
| `[arc_instructor_field meta="key_name"]` | Any instructor meta field by key |

---

## Page Layout Patterns (after cards are right)
- Course detail page
- Event detail page (pending shortcode resolution)
- Instructor profile page
