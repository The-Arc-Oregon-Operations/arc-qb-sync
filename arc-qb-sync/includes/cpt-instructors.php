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
			'supports'       => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'has_archive'    => false,
			'rewrite'        => array( 'slug' => 'instructor' ),
			'menu_icon'      => 'dashicons-groups',
		)
	);
}
