<?php
/**
 * Plugin Name:  Arc Oregon QB Sync
 * Description:  Integrates Quickbase Event Management with WordPress. Provides shortcodes for event detail pages and a public course catalog for The Arc Oregon.
 * Version:      1.0.0
 * Author:       Alan Lytle at The Arc Oregon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARC_QB_SYNC_VERSION', '1.0.0' );
define( 'ARC_QB_SYNC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARC_QB_SYNC_URL', plugin_dir_url( __FILE__ ) );

require_once ARC_QB_SYNC_DIR . 'includes/qb-api.php';
require_once ARC_QB_SYNC_DIR . 'includes/events.php';
require_once ARC_QB_SYNC_DIR . 'includes/courses.php';
require_once ARC_QB_SYNC_DIR . 'includes/cache-rest.php';
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-events.php';
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-courses.php';
require_once ARC_QB_SYNC_DIR . 'includes/shortcodes-catalog.php';
