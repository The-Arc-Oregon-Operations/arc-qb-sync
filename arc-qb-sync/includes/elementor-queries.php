<?php
/**
 * Elementor custom query hooks for arc-qb-sync CPTs.
 *
 * Registers named query hooks consumed by Elementor Pro Loop Grid widgets.
 * Each hook modifies a WP_Query instance based on the current post context.
 *
 * Query IDs (enter in Loop Grid → Query → Query ID field):
 *   arc_event_instructors — filters instructor CPT posts linked to the current arc_event
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query ID: arc_event_instructors
 *
 * Filters a Loop Grid query to show only `instructor` CPT posts whose
 * post_name (slug) appears in the current event's instructor slugs field.
 *
 * Source: _arc_event_instructor_slugs_legacy (pipe-separated, e.g. "nkaasa|ldutton").
 * Returns no posts if the field is empty or no matching slugs are found.
 *
 * Usage: Set Loop Grid → Query → Source to "Custom" and Query ID to "arc_event_instructors".
 * This hook only modifies the query when the current post is an arc_event — it is safe
 * to leave the Loop Grid set to this query ID on any single template.
 */
add_action( 'elementor/query/arc_event_instructors', 'arc_qb_elementor_query_arc_event_instructors' );

function arc_qb_elementor_query_arc_event_instructors( $query ) {
	$post_id = get_the_ID();

	if ( ! $post_id || 'arc_event' !== get_post_type( $post_id ) ) {
		return;
	}

	$slugs_raw = get_post_meta( $post_id, '_arc_event_instructor_slugs_legacy', true );

	if ( empty( $slugs_raw ) ) {
		// No instructors on this event — return an empty result set.
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	$slugs = array_values(
		array_filter(
			array_map( 'trim', explode( '|', (string) $slugs_raw ) )
		)
	);

	if ( empty( $slugs ) ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	$query->set( 'post_type',      'instructor' );
	$query->set( 'post_name__in',  $slugs );
	$query->set( 'posts_per_page', -1 );
	$query->set( 'orderby',        'title' );
	$query->set( 'order',          'ASC' );
}
