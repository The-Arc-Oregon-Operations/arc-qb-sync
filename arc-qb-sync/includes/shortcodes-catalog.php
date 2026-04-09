<?php
/**
 * Course Catalog grid shortcode.
 *
 * Usage: [course_catalog]
 *
 * Renders a filterable course grid for the /training page.
 * Fetches all publicly listed courses (field 36 = true) from Quickbase
 * via arc_qb_get_public_courses() (15-minute transient cache).
 *
 * Client-side filtering is handled by assets/js/course-catalog.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the catalog shortcode on init.
 */
function arc_qb_register_catalog_shortcode() {
	add_shortcode( 'course_catalog', 'arc_qb_sc_course_catalog' );
}
add_action( 'init', 'arc_qb_register_catalog_shortcode' );

/**
 * [course_catalog] shortcode callback.
 *
 * @return string  HTML output.
 */
function arc_qb_sc_course_catalog() {

	$courses = arc_qb_get_public_courses();

	if ( is_wp_error( $courses ) || empty( $courses ) ) {
		return '<p class="arc-catalog-empty">No courses are currently available. Please check back soon.</p>';
	}

	// ── Enqueue assets ───────────────────────────────────────────────────────

	wp_enqueue_style(
		'arc-course-catalog',
		ARC_QB_SYNC_URL . 'assets/css/course-catalog.css',
		array(),
		ARC_QB_SYNC_VERSION
	);

	wp_enqueue_script(
		'arc-course-catalog',
		ARC_QB_SYNC_URL . 'assets/js/course-catalog.js',
		array(),
		ARC_QB_SYNC_VERSION,
		true // load in footer
	);

	// ── Collect all unique tags across every course ───────────────────────────

	$all_tags = array(); // tag label => sanitized slug

	foreach ( $courses as $course ) {
		$raw  = arc_qb_get_course_field( $course, 56 );
		$tags = arc_qb_parse_tags( $raw );
		foreach ( $tags as $tag ) {
			$slug = sanitize_title( $tag );
			if ( ! isset( $all_tags[ $slug ] ) ) {
				$all_tags[ $slug ] = $tag;
			}
		}
	}

	// Sort by label alphabetically.
	asort( $all_tags );

	// ── Filter pills ─────────────────────────────────────────────────────────

	$html  = '<div class="arc-catalog-filters" role="group" aria-label="Filter courses by topic">';
	$html .= '<button class="arc-filter-pill is-active" data-filter="all">All</button>';

	foreach ( $all_tags as $slug => $label ) {
		$html .= sprintf(
			'<button class="arc-filter-pill" data-filter="%s">%s</button>',
			esc_attr( $slug ),
			esc_html( $label )
		);
	}

	$html .= '</div>';

	// ── Course grid ───────────────────────────────────────────────────────────

	$html .= '<div class="arc-catalog-grid">';

	foreach ( $courses as $course ) {
		$id          = intval( arc_qb_get_course_field( $course, 3 ) );
		$title       = arc_qb_get_course_field( $course, 6 );
		$length      = arc_qb_get_course_field( $course, 14 );
		$image_url   = arc_qb_get_course_field( $course, 88 );
		$raw_tags    = arc_qb_get_course_field( $course, 56 );
		$tags        = arc_qb_parse_tags( $raw_tags );

		// Build comma-separated slug list for data-tags attribute.
		$tag_slugs = implode( ',', array_map( 'sanitize_title', $tags ) );

		$html .= '<div class="arc-catalog-tile" data-tags="' . esc_attr( $tag_slugs ) . '">';

		// Image.
		$html .= '<div class="arc-catalog-tile__image">';
		if ( ! empty( $image_url ) ) {
			$html .= sprintf(
				'<img src="%s" alt="%s" loading="lazy">',
				esc_url( $image_url ),
				esc_attr( $title )
			);
		}
		$html .= '</div>';

		// Body.
		$html .= '<div class="arc-catalog-tile__body">';
		$html .= '<h3 class="arc-catalog-tile__title">' . esc_html( $title ) . '</h3>';

		if ( ! empty( $length ) ) {
			$html .= '<p class="arc-catalog-tile__length">' . esc_html( $length ) . '</p>';
		}

		// Tag pills.
		if ( ! empty( $tags ) ) {
			$html .= '<div class="arc-catalog-tile__tags">';
			foreach ( $tags as $tag ) {
				$html .= '<span class="arc-tag">' . esc_html( $tag ) . '</span>';
			}
			$html .= '</div>';
		}

		// CTA link — course ID must be valid.
		if ( $id > 0 ) {
			$html .= sprintf(
				'<a href="/course-catalog/?course-id=%d" class="arc-catalog-tile__cta">Learn More</a>',
				$id
			);
		}

		$html .= '</div>'; // .arc-catalog-tile__body
		$html .= '</div>'; // .arc-catalog-tile
	}

	$html .= '</div>'; // .arc-catalog-grid

	// ── Embedded JSON for JS filter ───────────────────────────────────────────

	// Build a lean array with only the fields needed by the JS.
	$catalog_data = array();
	foreach ( $courses as $course ) {
		$catalog_data[] = array(
			'id'    => intval( arc_qb_get_course_field( $course, 3 ) ),
			'title' => arc_qb_get_course_field( $course, 6 ),
			'length' => arc_qb_get_course_field( $course, 14 ),
			'short_description' => arc_qb_get_course_field( $course, 46 ),
			'tags'  => arc_qb_get_course_field( $course, 56 ),
			'image' => arc_qb_get_course_field( $course, 88 ),
		);
	}

	$html .= '<script id="arc-catalog-data" type="application/json">';
	$html .= wp_json_encode( $catalog_data );
	$html .= '</script>';

	return $html;
}
