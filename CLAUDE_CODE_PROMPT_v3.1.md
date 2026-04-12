# Claude Code Task: Instructor Shortcode Prefix Migration (v3.1.0)

## What you are doing and why

The `arc-qb-sync` plugin uses a consistent `course_*` and `event_*` shortcode naming convention. Instructor shortcodes were added in v3.0.0 using an `arc_instructor_*` prefix, which doesn't match that pattern and creates confusion during Elementor template work.

This task migrates instructor shortcodes to the clean `instructor_*` prefix, following the exact same pattern used when `event_*` was standardized in v2.2.0. The `arc_instructor_*` shortcodes remain registered as deprecated aliases — no live pages break.

**No other files, behaviors, sync logic, CPTs, or admin pages are touched.**

---

## File to modify: `arc-qb-sync/includes/shortcodes-instructors.php`

### Pattern to follow

Look at how `shortcodes-events.php` handles deprecated aliases:

```php
add_shortcode( 'event_venue', 'arc_td_shortcode_event_venue' );
add_shortcode( 'venue_name',  'arc_td_shortcode_event_venue' ); // Deprecated alias — remove in future version
```

Apply the same pattern here: register `instructor_*` as primary, `arc_instructor_*` as the deprecated alias.

### What to change in `shortcodes-instructors.php`

1. **Update the file header docblock** — change "Shortcodes provided" list to show `instructor_*` names as primary, `arc_instructor_*` as deprecated aliases.

2. **Update `arc_qb_register_arc_instructor_shortcodes()`** — for every shortcode, register the `instructor_*` name first, then the `arc_instructor_*` name as a deprecated alias on the next line. Example:

```php
add_shortcode( 'instructor_id',           'arc_qb_sc_arc_instructor_id' );
add_shortcode( 'arc_instructor_id',       'arc_qb_sc_arc_instructor_id' ); // Deprecated alias — remove in future version
```

   Apply this pattern to all 12 shortcodes:
   - `instructor_id` / `arc_instructor_id`
   - `instructor_name` / `arc_instructor_name`
   - `instructor_first_name` / `arc_instructor_first_name`
   - `instructor_last_name` / `arc_instructor_last_name`
   - `instructor_title` / `arc_instructor_title`
   - `instructor_organization` / `arc_instructor_organization`
   - `instructor_credentials` / `arc_instructor_credentials`
   - `instructor_slug` / `arc_instructor_slug`
   - `instructor_bio` / `arc_instructor_bio`
   - `instructor_headshot_url` / `arc_instructor_headshot_url`
   - `instructor_contact_url` / `arc_instructor_contact_url`
   - `instructor_field` / `arc_instructor_field`

3. **Do not rename any PHP functions.** The callback functions (`arc_qb_sc_arc_instructor_*`) are internal and don't need to change — only the shortcode names registered with WordPress.

4. **Do not change any logic, meta key reads, or output formatting.** The shortcode callbacks are correct. Only the `add_shortcode()` registration lines change.

---

## File to modify: `arc-qb-sync/arc-qb-sync.php`

Bump plugin version header from `3.0.2` to `3.1.0`.

---

## File to modify: `CHANGELOG.md`

Add a new entry at the top:

```markdown
## [3.1.0] — 2026-04-11

### Changed
- Instructor shortcodes migrated to clean `instructor_*` prefix to match `course_*` and `event_*` conventions
- `arc_instructor_*` shortcodes remain registered as deprecated aliases — no breaking change for live pages

### Deprecated
- `[arc_instructor_id]`, `[arc_instructor_name]`, `[arc_instructor_first_name]`, `[arc_instructor_last_name]`, `[arc_instructor_title]`, `[arc_instructor_organization]`, `[arc_instructor_credentials]`, `[arc_instructor_slug]`, `[arc_instructor_bio]`, `[arc_instructor_headshot_url]`, `[arc_instructor_contact_url]`, `[arc_instructor_field]` — use `instructor_*` equivalents for all new work
```

---

## Verification

After making changes, confirm:

1. `shortcodes-instructors.php` registers exactly 24 shortcodes total (12 primary `instructor_*` + 12 deprecated `arc_instructor_*` aliases).
2. No PHP function names were changed.
3. Plugin version in `arc-qb-sync.php` reads `3.1.0`.
4. CHANGELOG entry is at the top of the file.
5. No other files were modified.

---

## What NOT to do

- Do not touch `sync-instructors.php`, `cpt-instructors.php`, or any admin page files.
- Do not touch `shortcodes-events.php`, `shortcodes-courses.php`, or `shortcodes-events-cpt.php`.
- Do not rename any PHP callback functions.
- Do not change meta key names or any data logic.
- Do not address `course_catalog` or any other shortcode gaps — this task is instructor prefix only.
