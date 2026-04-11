<?php
/**
 * Course Catalog grid shortcode — DEPRECATED in v2.
 *
 * [course_catalog] is deprecated. In v2 the catalog grid is built entirely in
 * Elementor using a Loop Grid widget querying the `course` CPT. This file
 * keeps the shortcode registered so any pages where it was placed do not
 * produce a visible error or raw shortcode text, but it outputs nothing.
 *
 * Remove this file and the require_once in arc-qb-sync.php in v2.1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the (deprecated) catalog shortcode on init.
 */
function arc_qb_register_catalog_shortcode() {
	add_shortcode( 'course_catalog', 'arc_qb_sc_course_catalog' );
}
add_action( 'init', 'arc_qb_register_catalog_shortcode' );

/**
 * [course_catalog] shortcode callback — no-op in v2.
 *
 * Outputs an HTML comment so developers can see the shortcode is present
 * during debugging, but nothing is rendered to visitors.
 *
 * @return string
 */
function arc_qb_sc_course_catalog() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return '<!-- [course_catalog] is deprecated in v2. Replace with an Elementor Loop Grid widget querying the "course" post type. -->';
}
