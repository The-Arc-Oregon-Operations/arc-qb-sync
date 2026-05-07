<?php
/**
 * Instructor CPT registration.
 *
 * Registers the `instructor` Custom Post Type for instructor profiles synced
 * from Quickbase. This CPT runs alongside the existing `trainer` CPT — it does
 * NOT replace it. Elementor loops on existing pages that use the `trainer` CPT
 * continue to work unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'arc_qb_register_instructor_cpt' );
add_action( 'pre_get_posts', 'arc_qb_default_instructor_order' );

/**
 * Default sort order for `instructor` CPT queries on the front end.
 *
 * Primary:   menu_order ASC  — set to a negative integer (e.g. -10) to pin
 *            an instructor above the alphabetical list (e.g. featured speakers).
 * Secondary: post title ASC  — alphabetical fallback within the same menu_order.
 *
 * Only fires when orderby has not already been set on the query.
 */
function arc_qb_default_instructor_order( WP_Query $query ) {
	if ( is_admin() ) {
		return;
	}
	if ( 'instructor' !== $query->get( 'post_type' ) ) {
		return;
	}
	$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
}

function arc_qb_register_instructor_cpt() {
	register_post_type(
		'instructor',
		array(
			'label'          => 'Instructors',
			'labels'         => array(
				'name'               => 'Instructors',
				'singular_name'      => 'Instructor',
				'add_new_item'       => 'Add New Instructor',
				'edit_item'          => 'Edit Instructor',
				'view_item'          => 'View Instructor',
				'search_items'       => 'Search Instructors',
				'not_found'          => 'No instructors found.',
				'not_found_in_trash' => 'No instructors found in trash.',
			),
			'public'         => true,
			'show_in_rest'   => true,
			'supports'       => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes' ),
			'has_archive'    => false,
			'rewrite'        => array( 'slug' => 'instructor' ),
			'menu_icon'      => 'dashicons-groups',
		)
	);
}
