<?php
/**
 * Event shortcodes.
 *
 * Shortcode names updated to consistent `event_` prefix in v2.2.0.
 * Old names are kept as deprecated aliases so live pages continue to work.
 *
 * Shortcodes provided:
 *   [event_id]               — QB Record ID# (FID 3)                NEW in v2.2.0
 *   [event_title]            — Event Title (FID 19)
 *   [event_dates]            — Event Date(s) (FID 45)
 *   [event_time]             — Event Time (FID 89)
 *   [event_venue]            — Venue Name (FID 29)       alias: venue_name
 *   [event_instructors]      — Instructor(s) (FID 271)   alias: instructors
 *   [event_price]            — Training Cost (FID 450)   alias: training_cost
 *   [event_description]      — Event Description (FID 440)
 *   [event_reg_url]          — Add Registration URL (FID 14)  alias: add_registration_url
 *   [event_days_of_week]     — Day(s) of Week (FID 413)
 *   [event_mode]             — Event Mode (FID 458)
 *   [event_image_url]        — Featured Image URL (FID 461)  alias: featured_image_url
 *   [event_flyer]            — Flyer URL (FID 267)       alias: flyer_url
 *   [event_instructor_slugs] — Instructor Slugs (FID 449)  alias: instructor_slugs
 *   [event_is_multiday]      — Is Multi-Day (FID 453)    alias: is_multiday
 *   [event_is_multisession]  — Is Multi-Session (FID 454)  alias: is_multisession
 *   [event_length]           — Credit Hours (FID 361)                NEW in v2.2.0
 *   [event_field]            — Generic field access       alias: arc_training_field
 *   [loop_trainer_title]     — Raw post_title for Elementor loop  alias: arc_trainer_title
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

// ── [event_id] — NEW ──────────────────────────────────────────────────────────

// [event_id] — QB Record ID# for the current event (FID 3)
function arc_td_shortcode_event_id( $atts ) {
	return esc_html( arc_td_get_field_value( 3 ) );
}
add_shortcode( 'event_id', 'arc_td_shortcode_event_id' );

// ── [event_title] ─────────────────────────────────────────────────────────────

/* Event Title (FID 19) */
function arc_td_shortcode_event_title() {
	return esc_html( arc_td_get_field_value( 19 ) );
}
add_shortcode( 'event_title', 'arc_td_shortcode_event_title' );

// ── [event_dates] ─────────────────────────────────────────────────────────────

/* Event Date(s) (FID 45) */
function arc_td_shortcode_event_dates() {
	return esc_html( arc_td_get_field_value( 45 ) );
}
add_shortcode( 'event_dates', 'arc_td_shortcode_event_dates' );

// ── [event_time] ──────────────────────────────────────────────────────────────

/* Event Time (FID 89) */
function arc_td_shortcode_event_time() {
	return esc_html( arc_td_get_field_value( 89 ) );
}
add_shortcode( 'event_time', 'arc_td_shortcode_event_time' );

// ── [event_venue] / [venue_name] ──────────────────────────────────────────────

/* Venue Name (FID 29) */
function arc_td_shortcode_event_venue() {
	return esc_html( arc_td_get_field_value( 29 ) );
}
add_shortcode( 'event_venue', 'arc_td_shortcode_event_venue' );
add_shortcode( 'venue_name',  'arc_td_shortcode_event_venue' ); // Deprecated alias — remove in future version

// ── [event_instructors] / [instructors] ───────────────────────────────────────

/* Instructor(s) (FID 271) */
function arc_td_shortcode_event_instructors() {
	return esc_html( arc_td_get_field_value( 271 ) );
}
add_shortcode( 'event_instructors', 'arc_td_shortcode_event_instructors' );
add_shortcode( 'instructors',       'arc_td_shortcode_event_instructors' ); // Deprecated alias — remove in future version

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
add_shortcode( 'event_price',   'arc_td_shortcode_event_price' );
add_shortcode( 'training_cost', 'arc_td_shortcode_event_price' ); // Deprecated alias — remove in future version

// ── [event_description] ───────────────────────────────────────────────────────

/* Event Description (FID 440) */
function arc_td_shortcode_event_description() {
	$raw = arc_td_get_field_value( 440 );
	if ( ! $raw ) {
		return '';
	}
	return wp_kses_post( wpautop( $raw ) );
}
add_shortcode( 'event_description', 'arc_td_shortcode_event_description' );

// ── [event_reg_url] / [add_registration_url] ─────────────────────────────────

/* Add Registration URL (FID 14) */
function arc_td_shortcode_event_reg_url() {
	$value = arc_td_get_field_value( 14 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_url( $value );
}
add_shortcode( 'event_reg_url',        'arc_td_shortcode_event_reg_url' );
add_shortcode( 'add_registration_url', 'arc_td_shortcode_event_reg_url' ); // Deprecated alias — remove in future version

// ── [event_days_of_week] ──────────────────────────────────────────────────────

/* Day(s) of Week (FID 413) */
function arc_td_shortcode_event_days_of_week() {
	return esc_html( arc_td_get_field_value( 413 ) );
}
add_shortcode( 'event_days_of_week', 'arc_td_shortcode_event_days_of_week' );

// ── [event_mode] ──────────────────────────────────────────────────────────────

/* Event Mode (FID 458) */
function arc_td_shortcode_event_mode() {
	$value = arc_td_get_field_value( 458 );

	if ( $value === '' || $value === null ) {
		return '';
	}

	return esc_html( $value );
}
add_shortcode( 'event_mode', 'arc_td_shortcode_event_mode' );

// ── [event_image_url] / [featured_image_url] ─────────────────────────────────

/* Featured Image URL (FID 461) */
function arc_td_shortcode_event_image_url() {
	$value = arc_td_get_field_value( 461 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_url( $value );
}
add_shortcode( 'event_image_url',    'arc_td_shortcode_event_image_url' );
add_shortcode( 'featured_image_url', 'arc_td_shortcode_event_image_url' ); // Deprecated alias — remove in future version

// ── [event_flyer] / [flyer_url] ───────────────────────────────────────────────

/* Flyer URL (FID 267) */
function arc_td_shortcode_event_flyer() {
	$value = arc_td_get_field_value( 267 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_url( $value );
}
add_shortcode( 'event_flyer', 'arc_td_shortcode_event_flyer' );
add_shortcode( 'flyer_url',   'arc_td_shortcode_event_flyer' ); // Deprecated alias — remove in future version

// ── [event_instructor_slugs] / [instructor_slugs] ────────────────────────────

/* Instructor Slugs (FID 449) — pipe-separated */
function arc_td_shortcode_event_instructor_slugs() {
	$value = arc_td_get_field_value( 449 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_html( $value );
}
add_shortcode( 'event_instructor_slugs', 'arc_td_shortcode_event_instructor_slugs' );
add_shortcode( 'instructor_slugs',       'arc_td_shortcode_event_instructor_slugs' ); // Deprecated alias — remove in future version

// ── [event_is_multiday] / [is_multiday] ──────────────────────────────────────

/* Is Multi-Day (FID 453) — returns "1" or "0" */
function arc_td_shortcode_event_is_multiday() {
	$value = arc_td_get_field_value( 453 );

	return ! empty( $value ) ? '1' : '0';
}
add_shortcode( 'event_is_multiday', 'arc_td_shortcode_event_is_multiday' );
add_shortcode( 'is_multiday',       'arc_td_shortcode_event_is_multiday' ); // Deprecated alias — remove in future version

// ── [event_is_multisession] / [is_multisession] ──────────────────────────────

/* Is Multi-Session (FID 454) — returns "1" or "0" */
function arc_td_shortcode_event_is_multisession() {
	$value = arc_td_get_field_value( 454 );

	return ! empty( $value ) ? '1' : '0';
}
add_shortcode( 'event_is_multisession', 'arc_td_shortcode_event_is_multisession' );
add_shortcode( 'is_multisession',       'arc_td_shortcode_event_is_multisession' ); // Deprecated alias — remove in future version

// ── [event_length] — NEW ──────────────────────────────────────────────────────

// [event_length] — Credit Hours for the current event (FID 361)
function arc_td_shortcode_event_length( $atts ) {
	return esc_html( arc_td_get_field_value( 361 ) );
}
add_shortcode( 'event_length', 'arc_td_shortcode_event_length' );

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
add_shortcode( 'event_field',        'arc_td_shortcode_event_field' );
add_shortcode( 'arc_training_field', 'arc_td_shortcode_event_field' ); // Deprecated alias — remove in future version

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
