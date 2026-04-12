<?php
/**
 * Legacy event shortcodes — live QB fetch on page load.
 *
 * These functions fetch directly from the Quickbase API using the ?event-id=
 * URL parameter. They serve existing /training-details/?event-id=NNNN pages
 * during the transition to the arc_event CPT model.
 *
 * As of v3.2.0, the `event_*` shortcode names are owned by shortcodes-events-cpt.php
 * (CPT branch, reads from WP meta). The `event_*` add_shortcode() calls have been
 * removed from this file. The legacy functions remain defined here in case anything
 * references them directly, but they are no longer registered as shortcodes.
 *
 * Still active from this file:
 *   [loop_trainer_title] / [arc_trainer_title] — Elementor trainer loop title
 *   [venue_name]         — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [instructors]        — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [training_cost]      — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [add_registration_url] — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [featured_image_url] — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [flyer_url]          — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [instructor_slugs]   — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [is_multiday]        — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [is_multisession]    — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   [arc_training_field] — deprecated alias (pre-v2.2.0), points to legacy QB fetch
 *   elementor/query/trainers — Elementor custom query hook
 *
 * TODO: When legacy ?event-id= pages are retired, remove this entire file
 * (except loop_trainer_title / arc_trainer_title and the Elementor query hook,
 * which should be moved to shortcodes-events-cpt.php at that point).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── [loop_trainer_title] / [arc_trainer_title] ────────────────────────────────

/**
 * [loop_trainer_title]
 * Returns the raw post_title for the current loop item (no filters),
 * so Elementor can show trainer names even if the_title is filtered.
 */
function arc_td_shortcode_loop_trainer_title() {
	global $post;

	if ( ! $post ) {
		return '';
	}

	return esc_html( get_post_field( 'post_title', $post->ID ) );
}
add_shortcode( 'loop_trainer_title', 'arc_td_shortcode_loop_trainer_title' );
add_shortcode( 'arc_trainer_title',  'arc_td_shortcode_loop_trainer_title' ); // Deprecated alias — remove in future version

// ── Legacy QB-fetch functions (no longer registered as shortcodes) ────────────
// The event_* shortcode names are now owned by shortcodes-events-cpt.php.
// Functions preserved here for reference during transition.

// QB Record ID# (FID 3)
function arc_td_shortcode_event_id( $atts ) {
	return esc_html( arc_td_get_field_value( 3 ) );
}

// Event Title (FID 19)
function arc_td_shortcode_event_title() {
	return esc_html( arc_td_get_field_value( 19 ) );
}

// Event Date(s) (FID 45)
function arc_td_shortcode_event_dates() {
	return esc_html( arc_td_get_field_value( 45 ) );
}

// Event Time (FID 89)
function arc_td_shortcode_event_time() {
	return esc_html( arc_td_get_field_value( 89 ) );
}

// Venue Name (FID 29)
function arc_td_shortcode_event_venue() {
	return esc_html( arc_td_get_field_value( 29 ) );
}
add_shortcode( 'venue_name', 'arc_td_shortcode_event_venue' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// Instructor(s) plain text (FID 271)
function arc_td_shortcode_event_instructors() {
	return esc_html( arc_td_get_field_value( 271 ) );
}
add_shortcode( 'instructors', 'arc_td_shortcode_event_instructors' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// ── [event_price] / [training_cost] ───────────────────────────────────────────

/* Training Cost (FID 450) — HTML allowed */
function arc_td_shortcode_event_price( $atts ) {
	$raw = arc_td_get_field_value( 450 );

	if ( empty( $raw ) ) {
		return '';
	}

	// Allow only safe formatting tags used in cost fields.
	$allowed = array(
		'a'      => array(
			'href'   => array(),
			'title'  => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'strong' => array(),
		'em'     => array(),
		'span'   => array(
			'class' => array(),
			'style' => array(),
		),
		'br'     => array(),
		'strike' => array(),
	);

	return wp_kses( $raw, $allowed );
}
add_shortcode( 'training_cost', 'arc_td_shortcode_event_price' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// Event Description (FID 440)
function arc_td_shortcode_event_description() {
	$raw = arc_td_get_field_value( 440 );
	if ( ! $raw ) {
		return '';
	}
	return wp_kses_post( wpautop( $raw ) );
}

// ── [event_reg_url] / [add_registration_url] ─────────────────────────────────

/* Add Registration URL (FID 14) */
function arc_td_shortcode_event_reg_url() {
	$value = arc_td_get_field_value( 14 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_url( $value );
}
add_shortcode( 'add_registration_url', 'arc_td_shortcode_event_reg_url' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// Day(s) of Week (FID 413)
function arc_td_shortcode_event_days_of_week() {
	return esc_html( arc_td_get_field_value( 413 ) );
}

// Event Mode (FID 458)
function arc_td_shortcode_event_mode() {
	$value = arc_td_get_field_value( 458 );
	if ( $value === '' || $value === null ) {
		return '';
	}
	return esc_html( $value );
}

// ── [event_image_url] / [featured_image_url] ─────────────────────────────────

/* Featured Image URL (FID 461) */
function arc_td_shortcode_event_image_url() {
	$value = arc_td_get_field_value( 461 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_url( $value );
}
add_shortcode( 'featured_image_url', 'arc_td_shortcode_event_image_url' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// Flyer URL (FID 267)
function arc_td_shortcode_event_flyer() {
	$value = arc_td_get_field_value( 267 );
	if ( empty( $value ) ) {
		return '';
	}
	return esc_url( $value );
}
add_shortcode( 'flyer_url', 'arc_td_shortcode_event_flyer' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// ── [event_instructor_slugs] / [instructor_slugs] ────────────────────────────

/* Instructor Slugs (FID 449) — pipe-separated */
function arc_td_shortcode_event_instructor_slugs() {
	$value = arc_td_get_field_value( 449 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_html( $value );
}
add_shortcode( 'instructor_slugs', 'arc_td_shortcode_event_instructor_slugs' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// Is Multi-Day (FID 453) — returns "1" or "0"
function arc_td_shortcode_event_is_multiday() {
	$value = arc_td_get_field_value( 453 );
	return ! empty( $value ) ? '1' : '0';
}
add_shortcode( 'is_multiday', 'arc_td_shortcode_event_is_multiday' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// Is Multi-Session (FID 454) — returns "1" or "0"
function arc_td_shortcode_event_is_multisession() {
	$value = arc_td_get_field_value( 454 );
	return ! empty( $value ) ? '1' : '0';
}
add_shortcode( 'is_multisession', 'arc_td_shortcode_event_is_multisession' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// Credit Hours (FID 361)
function arc_td_shortcode_event_length( $atts ) {
	return esc_html( arc_td_get_field_value( 361 ) );
}

// ── [event_field] / [arc_training_field] ─────────────────────────────────────

/**
 * Generic field shortcode:
 *   [event_field id="45"]
 * Optional: [event_field id="440" format="html"]
 *
 * - id:     Quickbase field ID (required)
 * - format: "text" (default, escaped) or "html" (wpautop + wp_kses_post)
 */
function arc_td_shortcode_event_field( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'     => 0,
			'format' => 'text',
		),
		$atts,
		'event_field'
	);

	$field_id = intval( $atts['id'] );
	if ( $field_id <= 0 ) {
		return '';
	}

	$value = arc_td_get_field_value( $field_id );

	// Normalize to string & trim whitespace.
	if ( is_string( $value ) ) {
		$value = trim( $value );
	}

	// Treat empty, null, or literal "null" as no output.
	if ( $value === '' || $value === null || strtolower( (string) $value ) === 'null' ) {
		return '';
	}

	// HTML mode: allow safe HTML through.
	if ( strtolower( $atts['format'] ) === 'html' ) {

		// Special-case: field 14 usually contains full button/link markup.
		// We do NOT wrap it in <p> tags so it stays clean inside Elementor.
		if ( $field_id === 14 ) {
			return wp_kses_post( $value );
		}

		return wp_kses_post( wpautop( $value ) );
	}

	// Default: escape as plain text.
	return esc_html( (string) $value );
}
add_shortcode( 'arc_training_field', 'arc_td_shortcode_event_field' ); // Pre-v2.2.0 alias — remove when legacy pages are retired

// ── Elementor custom query ────────────────────────────────────────────────────

/**
 * Elementor custom query: limit trainer loop items to the slugs passed from QB.
 *
 * Used by Loop Grid with Custom Query ID: "trainers".
 */
add_action( 'elementor/query/trainers', function( $query ) {

	// This will load from Quickbase once per request (cached via static).
	$record = arc_td_get_current_record();

	// If we failed to load a record, return no posts.
	if ( is_wp_error( $record ) || empty( $record ) ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	// Read Trainer WP Slugs (FID 449) from the Quickbase record.
	if ( empty( $record['449']['value'] ) ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	$raw_slugs = $record['449']['value']; // e.g. "nkaasa|ldutton"

	$slugs = array_filter(
		array_map( 'trim', explode( '|', $raw_slugs ) )
	);

	if ( empty( $slugs ) ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	// Filter by slug (post_name) and preserve order of $slugs.
	$query->set( 'post_name__in', $slugs );
	$query->set( 'orderby', 'post_name__in' );
	$query->set( 'order', 'ASC' );
} );
