<?php
/**
 * Plugin Name:  Arc Oregon QB Sync
 * Description:  Integrates Quickbase with WordPress for The Arc Oregon. Syncs Course Catalog records as Custom Post Types and provides shortcodes for event detail pages.
 * Version:      2.1.0
 * Author:       Alan Lytle at The Arc Oregon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARC_QB_SYNC_VERSION', '2.1.0' );
define( 'ARC_QB_SYNC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARC_QB_SYNC_URL', plugin_dir_url( __FILE__ ) );

// ── Core ──────────────────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/qb-api.php';

// ── Courses — v2 ─────────────────────────────────────────────────────────────

// Deprecated — kept so any external callers don't fatal-error, and because
// arc_qb_get_course_field(), arc_qb_parse_tags(), and arc_qb_format_duration()
// are still used by sync-courses.php. Remove in v2.1 after moving those
// helpers to qb-api.php or a dedicated utilities file.
require_once ARC_QB_SYNC_DIR . 'includes/courses.php';

require_once ARC_QB_SYNC_DIR . 'includes/cpt-courses.php';        // course_tag taxonomy + legacy redirect
require_once ARC_QB_SYNC_DIR . 'includes/sync-courses.php';       // upsert engine, full sync, admin UI, filter pills
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-courses.php'; // [course_*] shortcodes (read from WP meta)
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-catalog.php'; // [course_catalog] deprecated no-op

// ── Events — unchanged ────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/events.php';
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-events.php';

// ── REST endpoint ─────────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/cache-rest.php';     // POST /sync-course (incremental upsert)
