<?php
/**
 * Course CPT support: CPT registration, taxonomy registration, and legacy URL redirect.
 *
 * Three responsibilities live here:
 *
 *  1. Register the `course` Custom Post Type.
 *     NOTE: If ACF is currently registering this CPT via its post types UI,
 *     you must remove the ACF registration before activating this code.
 *
 *  2. Register the `course_tag` taxonomy (unless you have already done this
 *     via ACF's "Create taxonomy" UI — don't register it in both places).
 *
 *  3. Redirect legacy `/course-catalog/?course-id=nnnn` URLs to the correct
 *     CPT permalink via a 301, preserving any bookmarked or shared links.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── 1. Register course CPT ────────────────────────────────────────────────────

add_action( 'init', 'arc_qb_register_course_cpt' );

/**
 * Register the `course` Custom Post Type.
 *
 * NOTE: If ACF is currently registering this CPT via its post types UI,
 * you must remove the ACF registration before activating this code.
 * Registering the same post type twice will cause a PHP notice and
 * unpredictable behavior.
 */
function arc_qb_register_course_cpt() {
	register_post_type(
		'course',
		array(
			'label'               => 'Courses',
			'labels'              => array(
				'name'               => 'Courses',
				'singular_name'      => 'Course',
				'add_new_item'       => 'Add New Course',
				'edit_item'          => 'Edit Course',
				'view_item'          => 'View Course',
				'search_items'       => 'Search Courses',
				'not_found'          => 'No courses found.',
				'not_found_in_trash' => 'No courses found in trash.',
			),
			'public'              => true,
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes' ),
			'has_archive'         => false,
			'rewrite'             => array( 'slug' => 'courses' ),
			'menu_icon'           => 'dashicons-welcome-learn-more',
		)
	);
}

// ── 2. Default sort order ─────────────────────────────────────────────────────

add_action( 'pre_get_posts', 'arc_qb_default_course_order' );

/**
 * Default sort order for `course` CPT queries on the front end.
 *
 * Primary:   menu_order ASC  — set any post to a negative integer (e.g. -10)
 *            to pin it above the normal alphabetical list. Posts at the same
 *            menu_order value sort alphabetically among themselves.
 * Secondary: post title ASC  — alphabetical fallback within the same menu_order.
 *
 * Only fires when orderby has not already been set on the query, so Elementor
 * Loop Grid custom Query IDs and explicit WP_Query args are unaffected.
 *
 * Convention:
 *   menu_order = 0   → normal pool (default for all posts; sorts alphabetically)
 *   menu_order = -10 → featured tier (floats to top; multiple items sort alpha)
 *   menu_order = -5  → second tier if needed
 */
function arc_qb_default_course_order( WP_Query $query ) {
	if ( is_admin() || $query->get( 'orderby' ) ) {
		return;
	}
	if ( 'course' !== $query->get( 'post_type' ) ) {
		return;
	}
	$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
}

// ── 3. Register course_tag taxonomy ──────────────────────────────────────────

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

// ── 3. Backward-compat redirect: /course/[slug]/ → /courses/[slug]/ ──────────

add_action( 'template_redirect', 'arc_qb_redirect_old_course_cpt_urls' );

/**
 * Redirect old /course/[slug]/ URLs to /courses/[slug]/ after the CPT rewrite
 * slug was changed from 'course' to 'courses' in v3.6.0.
 *
 * The slug was changed because the CPT rewrite slug 'course' conflicted with
 * any WordPress page whose slug starts with 'course' (e.g. course-catalog),
 * preventing those pages from being nested under a parent page correctly.
 */
function arc_qb_redirect_old_course_cpt_urls() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! preg_match( '#^/course/([^/?]+)/?$#', $uri, $matches ) ) {
		return;
	}
	wp_redirect( home_url( '/courses/' . $matches[1] . '/' ), 301 );
	exit;
}

// ── 4. Legacy ?course-id= redirect ───────────────────────────────────────────

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
