# Arc QB Sync — Setup Guide

## wp-config.php Constants

As of v3.0.1, wp-config.php holds only credentials and Quickbase table IDs. All field IDs (FIDs) are hardcoded inside the plugin and do not belong here.

| Constant | Description | Where to find the value |
|---|---|---|
| `QB_REALM_HOST` | Quickbase realm hostname | Your QB subdomain, e.g. `otac.quickbase.com` |
| `QB_USER_TOKEN` | Quickbase user token for API auth | QB Profile → My User Information → Manage User Tokens |
<<<<<<< Updated upstream

### New constants (required for Courses module)

| Constant | Description | Where to find the value |
|---|---|---|
| `QB_COURSES_TABLE_ID` | Course Catalog table ID | QB App → Tables → Course Catalog → table ID in URL, e.g. `bc7mmze9m` |
| `ARC_QB_CACHE_BUST_TOKEN` | Shared secret for Zapier → WP cache invalidation | Generate a strong random string (e.g. `openssl rand -hex 32`); set the same value in both wp-config.php and the Zapier Zap |

### New constants (required for v3.0.0)

| Constant | Description | Where to find the value |
|---|---|---|
=======
| `QB_TABLE_ID` | Events table ID | QB App → Tables → Training Events → table ID in URL |
| `QB_COURSES_TABLE_ID` | Course Catalog table ID | QB App → Tables → Course Catalog → table ID in URL |
>>>>>>> Stashed changes
| `QB_INSTRUCTORS_TABLE_ID` | Instructors table ID | QB App → Tables → Instructors → table ID in URL |
| `ARC_QB_CACHE_BUST_TOKEN` | Shared secret for Zapier → WP webhook auth | Generate with `openssl rand -hex 32`; same value goes in Zapier |

<<<<<<< Updated upstream
### Example wp-config.php block
=======
### wp-config.php block

Replace any earlier Arc QB Sync entries with this clean block:
>>>>>>> Stashed changes

```php
// Arc QB Sync — Quickbase integration
define( 'QB_REALM_HOST',           'otac.quickbase.com' );
<<<<<<< Updated upstream
define( 'QB_TABLE_ID',             'bc7mmze9k' );   // Training Events table
=======
>>>>>>> Stashed changes
define( 'QB_USER_TOKEN',           'your-token-here' );
define( 'QB_TABLE_ID',             'bc7mmze9k' );  // Training Events table
define( 'QB_COURSES_TABLE_ID',     'bc7mmze9m' );  // Course Catalog table
define( 'QB_INSTRUCTORS_TABLE_ID', 'bvx9ae9x8' );  // Instructors table
define( 'ARC_QB_CACHE_BUST_TOKEN', 'your-random-secret-here' );
<<<<<<< Updated upstream
define( 'QB_INSTRUCTORS_TABLE_ID', 'bvx9ae9x8' );   // Instructors table
=======
>>>>>>> Stashed changes
```

Field IDs (FIDs) are hardcoded in the plugin. No FID constants are required in wp-config.php.

---

## v3.0.0 — Course CPT migration from ACF

In v3.0.0, the `course` Custom Post Type is registered by the plugin (`cpt-courses.php`) instead of ACF. Before activating v3.0.0 or later:

1. In WP Admin → ACF → Post Types, delete or disable the `course` post type definition.
2. Activate (or update) the plugin. The plugin now owns CPT registration.

> **Warning:** Registering the same post type twice (plugin + ACF) will cause PHP notices and may break ACF field group assignments. Remove the ACF registration first.

---

## Deployment Steps (v3.0.1+)

1. **Build the zip:** From the repo root, run `./build.sh`. This creates `arc-qb-sync.zip`.
2. **Confirm wp-config.php constants** are present on the live server (see block above).
3. **ACF — remove `course` CPT** (first deployment only): WP Admin → ACF → Post Types → delete the `course` entry.
4. **Upload and activate:** WP Admin → Plugins → Add New → Upload Plugin → choose `arc-qb-sync.zip` → Install Now → Activate.
5. **Flush permalinks:** Settings → Permalinks → Save Changes. Required after any activation that registers new CPTs.
6. **Run initial syncs:** Settings → Arc Event Sync → Sync All Events Now. Then Settings → Arc Instructor Sync → Sync All Instructors Now.

---

## Verifying the Events Module

Load any existing event shortcode page:

```
https://thearcoregon.org/training-details/?event-id=NNNN
```

All shortcode fields (`[event_title]`, `[event_dates]`, `[event_venue]`, etc.) should render as before. The Elementor trainer loop should also still work.

---

## Verifying the Courses Module

1. In Quickbase, find a Course Catalog record with **Public Listing** (field 36) set to `true` and note its Record ID.
2. Load:
   ```
   https://thearcoregon.org/course-catalog/?course-id=NNNN
   ```
   Shortcodes like `[course_title]`, `[course_short_description]`, `[course_tags]` should render.
3. Load the `/training` page. Publicly listed courses should appear in the filterable grid.

---

## Webhook / Zapier

For the Zapier → WP incremental course sync endpoint, see `docs/webhook-zapier.md`.
