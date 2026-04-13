# Elementor Custom Field Keys — arc-qb-sync CPTs

When using Elementor Pro's **Post Custom Field** dynamic tag, enter the meta key directly in the **Custom Key** field. These keys are hidden fields (underscore-prefixed) and will not appear in the dropdown — type them in manually.

For fields that come from the post itself (title, excerpt), use the corresponding Elementor built-in dynamic tag instead (Post Title, Post Excerpt), as noted below.

Fields that output HTML should be placed in an **HTML** widget, not a plain Text Editor widget.

---

## Instructor CPT (`instructor`)

| Elementor custom field key | What it contains | Output type |
|---|---|---|
| *(use Post Title tag)* | Full name | Post Title dynamic tag |
| `_arc_instructor_first_name` | First name | Plain text |
| `_arc_instructor_last_name` | Last name | Plain text |
| `_arc_instructor_pronouns` | Pronouns — arrives with parens, e.g. `(she/her)` | Plain text |
| `_arc_instructor_title` | Job title | Plain text |
| `_arc_instructor_organization` | Organization / employer | Plain text |
| `_arc_instructor_credentials` | Credentials | Plain text |
| `_arc_instructor_bio` | Full bio | HTML |
| `_arc_instructor_trainer_roles` | Trainer role(s) — pre-formatted `<ul><li>` from QB | HTML |
| `_arc_instructor_headshot_url` | Headshot image URL | URL — wire to image src |
| `_arc_instructor_contact_url` | Contact URL | URL — wire to button/link href |
| `_arc_instructor_slug` | QB slug | Plain text (internal use) |
| `_arc_qb_instructor_id` | QB record ID | Plain text (internal use) |

**Generic escape hatch:** `[instructor_field meta="_arc_instructor_bio" format="html"]`

---

## Course CPT (`course`)

| Elementor custom field key | What it contains | Output type |
|---|---|---|
| *(use Post Title tag)* | Course title | Post Title dynamic tag |
| *(use Post Excerpt tag)* | Short description | Post Excerpt dynamic tag |
| `_arc_course_learning_objectives_html` | Full description / learning objectives (HTML version) | HTML |
| `_arc_course_learning_objectives` | Learning objectives (plain text fallback, FID 85) | Plain text |
| `_arc_course_length` | Formatted length string, e.g. "6.5 hours" | Plain text |
| `_arc_course_hours` | Numeric hours value | Plain text |
| `_arc_course_delivery_method` | Delivery method | Plain text |
| `_arc_course_target_audience` | Target audience description | HTML |
| `_arc_course_category` | Course category | Plain text |
| `_arc_course_base_rate` | Base rate / price | Plain text |
| `_arc_course_details_url` | Link to course overview page | URL — wire to button/link href |
| `_arc_course_request_url` | Org training request form URL pre-filled with course ID (`?course=XX`) | URL — wire to button/link href |
| `_arc_course_attribution` | Attribution text | Plain text |
| `_arc_course_use_attribution` | Attribution checkbox — `"1"` or `"0"` | Plain text (flag) |
| `_arc_course_offers_online` | Online delivery available — `"1"` or `"0"` | Plain text (flag) |
| `_arc_course_offers_inperson` | In-person delivery available — `"1"` or `"0"` | Plain text (flag) |
| `_arc_course_featured_image_url` | Featured image URL (primary) | URL — wire to image src |
| `_arc_course_hero_image_url` | Hero / banner image URL | URL — wire to image src |
| `_arc_course_image_url` | Legacy manual image URL (FID 88) | URL — wire to image src |
| `_arc_course_slug` | QB slug | Plain text (internal use) |
| `_arc_qb_record_id` | QB record ID | Plain text (internal use) |

**Note:** Course tags are taxonomy-based (`course_tag`) and cannot be pulled via Post Custom Field. Use the `[course_tags]` shortcode instead.

**Generic escape hatch:** `[course_field id="85" format="html"]` (uses QB field ID, not meta key)

---

## Event CPT (`arc_event`)

| Elementor custom field key | What it contains | Output type |
|---|---|---|
| *(use Post Title tag)* | Event title | Post Title dynamic tag |
| `_arc_event_dates` | Date string(s) | Plain text |
| `_arc_event_time` | Time string | Plain text |
| `_arc_event_days_of_week` | Days of week | Plain text |
| `_arc_event_venue` | Venue name / location | Plain text |
| `_arc_event_mode` | Delivery mode (Online / In-Person / Hybrid) | Plain text |
| `_arc_event_length` | Length description | Plain text |
| `_arc_event_description` | Event description | HTML |
| `_arc_event_price` | Price / pricing info (may contain links) | HTML |
| `_arc_event_reg_url` | Registration URL | URL — wire to button/link href |
| `_arc_event_flyer_url` | Flyer PDF URL | URL — wire to button/link href |
| `_arc_event_featured_image_url` | Featured image URL (primary) | URL — wire to image src |
| `_arc_event_hero_image_url` | Hero / banner image URL | URL — wire to image src |
| `_arc_event_image_url` | Legacy manual image URL | URL — wire to image src |
| `_arc_event_is_multiday` | Multi-day flag — `"1"` or `"0"` | Plain text (flag) |
| `_arc_event_is_multisession` | Multi-session flag — `"1"` or `"0"` | Plain text (flag) |
| `_arc_event_instructor1_name` | Instructor 1 full name | Plain text |
| `_arc_event_instructor1_headshot_url` | Instructor 1 headshot URL | URL — wire to image src |
| `_arc_event_instructor1_headshot_alt` | Instructor 1 headshot alt text | Plain text |
| `_arc_event_instructor2_name` | Instructor 2 full name | Plain text |
| `_arc_event_instructor2_headshot_url` | Instructor 2 headshot URL | URL — wire to image src |
| `_arc_event_instructor2_headshot_alt` | Instructor 2 headshot alt text | Plain text |
| `_arc_event_instructor3_name` | Instructor 3 full name | Plain text |
| `_arc_event_instructor3_headshot_url` | Instructor 3 headshot URL | URL — wire to image src |
| `_arc_event_instructor3_headshot_alt` | Instructor 3 headshot alt text | Plain text |
| `_arc_event_instructors_legacy` | Legacy instructor name string (pre-CPT) | Plain text |
| `_arc_event_instructor_slugs_legacy` | Legacy instructor slug string (pre-CPT) | Plain text |
| `_arc_qb_event_id` | QB event record ID | Plain text (internal use) |

**Generic escape hatch:** `[event_field meta="_arc_event_description" format="html"]`

---

## Usage notes

**Why keys start with `_`:** The underscore prefix marks these as hidden custom fields in WordPress. Elementor's dropdown will not list them — always use the Custom Key field and type the key directly.

**HTML fields in Elementor:** Fields marked "HTML" must be placed in an HTML widget or a Text Editor widget with raw HTML mode. Placing them in a plain text widget will render raw tags.

**URL fields:** Fields marked "URL" return a bare URL string. Wire them to the appropriate attribute on an Image widget (`src`) or Button widget (`link`), not to a text display widget.

**Flag fields (`"1"` or `"0"`):** Useful for Elementor conditional display logic. A value of `"1"` means true/checked in QB; `"0"` or empty means false.
