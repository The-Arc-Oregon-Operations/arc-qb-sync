<?php
/**
 * Events CPT registration and legacy URL redirect.
 *
 * Registers the `arc_event` Custom Post Type for events synced from Quickbase.
 * Also redirects legacy ?event-id=NNNN URLs to the correct CPT permalink.
 *
 * Note: The existing shortcode-based event detail pages (/training-details/?event-id=NNNN)
 * continue to function via shortcodes-events.php. This CPT is a parallel system —
 * existing pages are NOT replaced until manually migrated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── CPT registration ──────────────────────────────────────────────────────────

add_action( 'init', 'arc_qb_register_event_cpt' );

function arc_qb_register_event_cpt() {
	register_post_type(
		'arc_event',
		array(
			'label'          => 'Events',
			'labels'         => array(
				'name'               => 'Events',
				'singular_name'      => 'Event',
				'add_new_item'       => 'Add New Event',
				'edit_item'          => 'Edit Event',
				'view_item'          => 'View Event',
				'search_items'       => 'Search Events',
				'not_found'          => 'No events found.',
				'not_found_in_trash' => 'No events found in trash.',
			),
			'public'         => true,
			'show_in_rest'   => true,
			'supports'       => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'has_archive'    => false,
			'rewrite'        => array( 'slug' => 'training' ),
			'menu_icon'      => 'dashicons-calendar-alt',
		)
	);
}

// ── Legacy ?event-id= redirect ────────────────────────────────────────────────

add_action( 'template_redirect', 'arc_qb_redirect_legacy_event_url' );

/**
 * Redirect legacy /training-details/?event-id=NNNN URLs to the CPT permalink.
 * Silently passes through if no matching CPT post is found.
 */
function arc_qb_redirect_legacy_event_url() {
	if ( ! isset( $_GET['event-id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$record_id = intval( $_GET['event-id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $record_id <= 0 ) {
		return;
	}

	$posts = get_posts(
		array(
			'post_type'   => 'arc_event',
			'meta_key'    => '_arc_qb_event_id',
			'meta_value'  => $record_id,
			'numberposts' => 1,
		)
	);

	if ( ! empty( $posts ) ) {
		wp_redirect( get_permalink( $posts[0]->ID ), 301 );
		exit;
	}
}
