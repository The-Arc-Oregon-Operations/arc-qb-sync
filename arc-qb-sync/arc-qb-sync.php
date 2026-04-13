<?php
/**
 * Plugin Name:  Arc Oregon QB Sync
 * Description:  Integrates Quickbase with WordPress for The Arc Oregon. Syncs Course Catalog records as Custom Post Types and provides shortcodes for event detail pages.
 * Version:      3.4.1
 * Author:       Alan Lytle at The Arc Oregon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARC_QB_SYNC_VERSION', '3.4.1' );
define( 'ARC_QB_SYNC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARC_QB_SYNC_URL', plugin_dir_url( __FILE__ ) );

// ── Core ──────────────────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/qb-api.php';

// ── Courses — v2 ─────────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/courses.php'; // Legacy v1 course fetch functions — kept for backward compatibility

require_once ARC_QB_SYNC_DIR . 'includes/cpt-courses.php';        // course_tag taxonomy + legacy redirect
require_once ARC_QB_SYNC_DIR . 'includes/sync-courses.php';       // upsert engine, full sync, admin UI, filter pills
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-courses.php'; // [course_*] shortcodes (read from WP meta)

// ── Events — unchanged ────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/events.php';
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-events.php';

// ── Events CPT ───────────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/cpt-events.php';            // arc_event CPT registration + legacy redirect
require_once ARC_QB_SYNC_DIR . 'includes/sync-events.php';           // Events upsert engine, full sync, admin UI
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-events-cpt.php'; // [arc_event_*] shortcodes (WP meta)

// ── Instructors CPT ───────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/cpt-instructors.php';        // instructor CPT registration
require_once ARC_QB_SYNC_DIR . 'includes/sync-instructors.php';       // Instructors upsert engine, full sync, admin UI
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-instructors.php'; // [arc_instructor_*] shortcodes (WP meta)

// ── REST endpoint ─────────────────────────────────────────────────────────────

require_once ARC_QB_SYNC_DIR . 'includes/cache-rest.php';     // POST /sync-course (incremental upsert)
