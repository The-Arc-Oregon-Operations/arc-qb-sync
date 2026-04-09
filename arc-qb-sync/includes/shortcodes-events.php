<?php
/**
 * Event shortcodes.
 *
 * All shortcode names and behavior are unchanged from arc-training-details v0.4.0.
 * Functions have been moved here as-is from the original single-file plugin.
 *
 * Shortcodes provided:
 *   [event_title]            - Event Title
 *   [event_dates]            - Event Date(s)
 *   [event_time]             - Event Time
 *   [venue_name]             - Venue Name
 *   [instructors]            - Instructor(s)
 *   [training_cost]          - Training Cost (HTML allowed)
 *   [event_description]      - Event Description (long text, HTML)
 *   [add_registration_url]   - Add Registration (URL)
 *   [event_days_of_week]     - Day(s) of Week
 *   [event_mode]             - Event Mode (Online, In-person, etc.)
 *   [featured_image_url]     - Featured Image URL
 *   [flyer_url]              - Flyer URL
 *   [instructor_slugs]       - Pipe-separated trainer slugs
 *   [is_multiday]            - "1" if multi-day, "0" otherwise
 *   [is_multisession]        - "1" if multi-session, "0" otherwise
 *   [arc_training_field]     - Generic field access by QB field ID
 *   [arc_trainer_title]      - Raw post_title for current Elementor loop item
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode: [arc_trainer_title]
 * Returns the raw post_title for the current loop item (no filters),
 * so Elementor can show trainer names even if the_title is filtered.
 */
function arc_td_sc_trainer_title() {
	global $post;

	if ( ! $post ) {
		return '';
	}

	$title = get_post_field( 'post_title', $post->ID ); // unfiltered

	return esc_html( $title );
}

/* Event Title */
function arc_td_sc_title() {
	return esc_html( arc_td_get_field_value( 19 ) );
}

/* Event Date(s) */
function arc_td_sc_dates() {
	return esc_html( arc_td_get_field_value( 45 ) );
}

/* Event Time */
function arc_td_sc_time() {
	return esc_html( arc_td_get_field_value( 89 ) );
}

/* Venue Name */
function arc_td_sc_city() {
	return esc_html( arc_td_get_field_value( 29 ) );
}

/* Instructor(s) */
function arc_td_sc_instructors() {
	return esc_html( arc_td_get_field_value( 271 ) );
}

/* Credit Hours */
function arc_td_sc_credit_hours() {
	return esc_html( arc_td_get_field_value( 361 ) );
}

/* Event Cost */
function arc_td_sc_cost() {
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

/* Event Description (HTML) */
function arc_td_sc_description() {
	$raw = arc_td_get_field_value( 440 );
	if ( ! $raw ) {
		return '';
	}
	return wp_kses_post( wpautop( $raw ) );
}

/* Add Registration (URL) */
function arc_td_sc_add_registration_url() {
	$value = arc_td_get_field_value( 14 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_url( $value );
}

/* Day(s) of Week */
function arc_td_sc_event_days_of_week() {
	return esc_html( arc_td_get_field_value( 413 ) );
}

/* Event Mode */
function arc_td_sc_event_mode() {
	$value = arc_td_get_field_value( 458 );

	if ( $value === '' || $value === null ) {
		return '';
	}

	return esc_html( $value );
}

/* Featured Image URL */
function arc_td_sc_featured_image_url() {
	$value = arc_td_get_field_value( 461 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_url( $value );
}

/* Flyer URL */
function arc_td_sc_flyer_url() {
	$value = arc_td_get_field_value( 267 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_url( $value );
}

/* Instructor slugs (pipe-separated) */
function arc_td_sc_instructor_slugs() {
	$value = arc_td_get_field_value( 449 );

	if ( empty( $value ) ) {
		return '';
	}

	return esc_html( $value );
}

/* Is Multi-Day (returns "1" or "0") */
function arc_td_sc_is_multiday() {
	$value = arc_td_get_field_value( 453 );

	return ! empty( $value ) ? '1' : '0';
}

/* Is Multi-Session (returns "1" or "0") */
function arc_td_sc_is_multisession() {
	$value = arc_td_get_field_value( 454 );

	return ! empty( $value ) ? '1' : '0';
}

/**
 * Generic field shortcode:
 *   [arc_training_field id="45"]
 * Optional: [arc_training_field id="440" format="html"]
 *
 * - id:     Quickbase field ID (required)
 * - format: "text" (default, escaped) or "html" (wpautop + wp_kses_post)
 */
function arc_td_sc_field( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'     => 0,
			'format' => 'text',
		),
		$atts,
		'arc_training_field'
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

/**
 * Register all event shortcodes on init.
 */
function arc_td_register_shortcodes() {
	add_shortcode( 'event_title',          'arc_td_sc_title' );
	add_shortcode( 'event_dates',          'arc_td_sc_dates' );
	add_shortcode( 'event_time',           'arc_td_sc_time' );
	add_shortcode( 'venue_name',           'arc_td_sc_city' );
	add_shortcode( 'instructors',          'arc_td_sc_instructors' );
	add_shortcode( 'training_cost',        'arc_td_sc_cost' );
	add_shortcode( 'event_description',    'arc_td_sc_description' );
	add_shortcode( 'add_registration_url', 'arc_td_sc_add_registration_url' );
	add_shortcode( 'event_days_of_week',   'arc_td_sc_event_days_of_week' );
	add_shortcode( 'event_mode',           'arc_td_sc_event_mode' );
	add_shortcode( 'featured_image_url',   'arc_td_sc_featured_image_url' );
	add_shortcode( 'flyer_url',            'arc_td_sc_flyer_url' );
	add_shortcode( 'instructor_slugs',     'arc_td_sc_instructor_slugs' );
	add_shortcode( 'is_multiday',          'arc_td_sc_is_multiday' );
	add_shortcode( 'is_multisession',      'arc_td_sc_is_multisession' );
	add_shortcode( 'arc_training_field',   'arc_td_sc_field' );
	add_shortcode( 'arc_trainer_title',    'arc_td_sc_trainer_title' );
}
add_action( 'init', 'arc_td_register_shortcodes' );

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
