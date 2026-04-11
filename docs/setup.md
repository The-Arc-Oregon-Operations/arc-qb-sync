# Arc QB Sync — Setup Guide

## wp-config.php Constants

Add these constants to `wp-config.php` on the WordPress server **before** activating the plugin.

### Existing constants (already present for arc-training-details v0.4.0)

| Constant | Description | Where to find the value |
|---|---|---|
| `QB_REALM_HOST` | Quickbase realm hostname | Your QB subdomain, e.g. `otac.quickbase.com` |
| `QB_TABLE_ID` | Events table ID (Training Events) | QB App → Tables → Training Events → table ID in URL, e.g. `bc7mmze9k` |
| `QB_USER_TOKEN` | Quickbase user token for API auth | QB Profile → My User Information → Manage User Tokens |

### New constants (required for Courses module)

| Constant | Description | Where to find the value |
|---|---|---|
| `QB_COURSES_TABLE_ID` | Course Catalog table ID | QB App → Tables → Course Catalog → table ID in URL, e.g. `bc7mmze9m` |
| `ARC_QB_CACHE_BUST_TOKEN` | Shared secret for Zapier → WP cache invalidation | Generate a strong random string (e.g. `openssl rand -hex 32`); set the same value in both wp-config.php and the Zapier Zap |

### New constants (required for v3.0.0 features)

#### Instructors table

| Constant | Description | Where to find the value |
|---|---|---|
| `QB_INSTRUCTORS_TABLE_ID` | Instructors table ID | QB App → Tables → Instructors → table ID in URL |

#### Image Asset lookup FIDs — Courses

These constants point to lookup fields added to the Courses table by QB Build Spec v3.0. If the QB build has not been applied yet, you can temporarily set these to `0` — the plugin will skip writing those meta keys until they are populated.

| Constant | FID | Description |
|---|---|---|
| `ARC_QB_COURSE_FEATURED_IMAGE_FID` | 94 | Courses: Featured Image URL [lookup from Image Assets] |
| `ARC_QB_COURSE_HERO_IMAGE_FID` | 96 | Courses: Hero Image URL [lookup from Image Assets] |

#### Image Asset lookup FIDs — Events

| Constant | FID | Description |
|---|---|---|
| `ARC_QB_EVENT_FEATURED_IMAGE_FID` | 464 | Events: Featured Image URL [lookup from Image Assets] |
| `ARC_QB_EVENT_HERO_IMAGE_FID` | 466 | Events: Hero Image URL [lookup from Image Assets] |

#### Instructor lookup FIDs on Events (three slots)

| Constant | FID | Description |
|---|---|---|
| `ARC_QB_EVENT_INSTRUCTOR1_NAME_FID` | 482 | Instructor 1 - Name |
| `ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID` | 483 | Instructor 1 - Headshot URL |
| `ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID` | 484 | Instructor 1 - Headshot - Alt Text |
| `ARC_QB_EVENT_INSTRUCTOR2_NAME_FID` | 486 | Instructor 2 - Name |
| `ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID` | 487 | Instructor 2 - Headshot URL |
| `ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID` | 494 | Instructor 2 - Headshot - Alt Text |
| `ARC_QB_EVENT_INSTRUCTOR3_NAME_FID` | 491 | Instructor 3 - Name |
| `ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID` | 492 | Instructor 3 - Headshot URL |
| `ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID` | 493 | Instructor 3 - Headshot - Alt Text |

#### Instructor profile image (on Instructors table)

| Constant | FID | Description |
|---|---|---|
| `ARC_QB_INSTRUCTOR_PROFILE_FID` | 15 | Instructors: Headshot URL [lookup from Image Assets] |

> **Note:** No header image FID for instructors in this version — headshot only.

### Example wp-config.php block

```php
// Arc QB Sync — Quickbase integration
define( 'QB_REALM_HOST',           'otac.quickbase.com' );
define( 'QB_TABLE_ID',             'bc7mmze9k' );   // Events table
define( 'QB_USER_TOKEN',           'your-token-here' );
define( 'QB_COURSES_TABLE_ID',     'bc7mmze9m' );   // Course Catalog table
define( 'ARC_QB_CACHE_BUST_TOKEN', 'your-random-secret-here' );

// v3.0.0 — Instructors table
define( 'QB_INSTRUCTORS_TABLE_ID', 'bvx9ae9x8' );

// v3.0.0 — Image Asset lookup FIDs (Courses)
define( 'ARC_QB_COURSE_FEATURED_IMAGE_FID', 94  ); // Courses: Featured Image URL [lookup]
define( 'ARC_QB_COURSE_HERO_IMAGE_FID',     96  ); // Courses: Hero Image URL [lookup]

// v3.0.0 — Image Asset lookup FIDs (Events)
define( 'ARC_QB_EVENT_FEATURED_IMAGE_FID',  464 ); // Events: Featured Image URL [lookup]
define( 'ARC_QB_EVENT_HERO_IMAGE_FID',      466 ); // Events: Hero Image URL [lookup]

// v3.0.0 — Instructor lookup FIDs on Events (three slots)
define( 'ARC_QB_EVENT_INSTRUCTOR1_NAME_FID',         482 );
define( 'ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID',     483 );
define( 'ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID', 484 );
define( 'ARC_QB_EVENT_INSTRUCTOR2_NAME_FID',         486 );
define( 'ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID',     487 );
define( 'ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID', 494 );
define( 'ARC_QB_EVENT_INSTRUCTOR3_NAME_FID',         491 );
define( 'ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID',     492 );
define( 'ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID', 493 );

// v3.0.0 — Instructor profile image (Instructors table)
define( 'ARC_QB_INSTRUCTOR_PROFILE_FID',    15  ); // Instructors: Headshot URL [lookup]
```

---

## v3.0.0 — Course CPT migration from ACF

In v3.0.0, the `course` Custom Post Type is registered by the plugin (`cpt-courses.php`) instead of ACF. Before activating v3.0.0:

1. In WP Admin → ACF → Post Types, delete or disable the `course` post type definition.
2. Activate (or update) the plugin. The plugin now owns CPT registration.

> **Warning:** Registering the same post type twice (plugin + ACF) will cause PHP notices and may break ACF field group assignments. Remove the ACF registration first.

---

## First Install Steps

1. **Build the zip:** From the repo root, run `./build.sh`. This creates `arc-qb-sync.zip`.
2. **Add wp-config.php constants:** Add all 5 constants above to `wp-config.php` on the live server before activating.
3. **Deactivate the old plugin:** In WP Admin → Plugins, deactivate *Quickbase Event Management Sync for The Arc Oregon* (`arc-training-details`).
4. **Upload and activate the new plugin:** WP Admin → Plugins → Add New → Upload Plugin → choose `arc-qb-sync.zip` → Install Now → Activate.
5. **Do not delete the old plugin yet** — keep it deactivated until the new plugin is verified.

---

## Verifying the Events Module

Load any existing training-details URL that was working before:

```
https://thearcoregon.org/training-details/?event-id=NNNN
```

All shortcode fields (`[event_title]`, `[event_dates]`, `[venue_name]`, etc.) should render as before. The Elementor trainer loop should also still work.

---

## Verifying the Courses Module

1. In Quickbase, find a Course Catalog record with **Public Listing** (field 36) set to `true` and note its Record ID.
2. Load:
   ```
   https://thearcoregon.org/course-catalog/?course-id=NNNN
   ```
   Shortcodes like `[course_title]`, `[course_short_description]`, `[course_tags]` should render.
3. Load the `/training` page (which should contain `[course_catalog]`). Publicly listed courses should appear in the filterable grid.

---

## Cache Invalidation

The course catalog is cached for 15 minutes via WP Transients. To manually clear the cache:

```bash
curl -X POST https://thearcoregon.org/wp-json/arc-qb-sync/v1/bust-cache \
  -H "Authorization: Bearer YOUR_CACHE_BUST_TOKEN"
```

For automated cache clearing via Quickbase webhook + Zapier, see `docs/webhook-zapier.md`.
