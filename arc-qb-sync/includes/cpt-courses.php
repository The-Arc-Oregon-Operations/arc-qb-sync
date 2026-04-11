<?php
/**
 * Course CPT support: taxonomy registration and legacy URL redirect.
 *
 * The `course` Custom Post Type itself is registered by ACF — this file does
 * not re-register it. Two responsibilities live here:
 *
 *  1. Register the `course_tag` taxonomy (unless you have already done this
 *     via ACF's "Create taxonomy" UI — don't register it in both places).
 *
 *  2. Redirect legacy `/course-catalog/?course-id=nnnn` URLs to the correct
 *     CPT permalink via a 301, preserving any bookmarked or shared links.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── 1. Register course_tag taxonomy ──────────────────────────────────────────

add_action( 'init', 'arc_qb_register_course_tag_taxonomy' );

/**
 * Register the course_tag taxonomy on the `course` CPT.
 *
 * Skip this if you registered the taxonomy via ACF's UI — registering the
 * same taxonomy key twice will cause a PHP notice.
 */
function arc_qb_register_course_tag_taxonomy() {
	register_taxonomy(
		'course_tag',
		'course',
		array(
			'label'        => 'Course Tags',
			'public'       => true,
			'hierarchical' => false,
			'rewrite'      => false, // tag archive pages are not needed
			'show_in_rest' => true,
		)
	);
}

// ── 2. Legacy ?course-id= redirect ───────────────────────────────────────────

add_action( 'template_redirect', 'arc_qb_redirect_legacy_course_url' );

/**
 * If a request includes ?course-id=nnnn, look up the matching `course` CPT
 * post by its _arc_qb_record_id meta value and issue a 301 redirect to its
 * permalink. Silently passes through if no match is found.
 */
function arc_qb_redirect_legacy_course_url() {
	if ( ! isset( $_GET['course-id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$record_id = intval( $_GET['course-id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $record_id <= 0 ) {
		return;
	}

	$posts = get_posts(
		array(
			'post_type'   => 'course',
			'meta_key'    => '_arc_qb_record_id',
			'meta_value'  => $record_id,
			'numberposts' => 1,
		)
	);

	if ( ! empty( $posts ) ) {
		wp_redirect( get_permalink( $posts[0]->ID ), 301 );
		exit;
	}
}
