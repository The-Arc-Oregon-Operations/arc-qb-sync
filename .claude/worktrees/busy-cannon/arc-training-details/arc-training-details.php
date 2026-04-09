<?php
/**
 * Plugin Name:  Quickbase Event Management Sync for The Arc Oregon
 * Description:  Pulls event details from Quickbase and exposes them as shortcodes for Elementor.
 * Version:      0.4.0
 * Author:       Alan Lytle at The Arc Oregon
 *
 * Shortcodes provided:
 *   [event_title]                  - Event Title
 *   [event_dates]                  - Event Date(s)
 *   [event_time]                   - Event Time
 *   [venue_name]                   - Venue Name
 *   [instructors]                  - Instructor(s)
 *   [training_cost]                - Training Cost (HTML allowed)
 *   [event_description]            - Event Description (long text, HTML)
 *   [add_registration_url]         - Add Registration (URL)
 *   [event_days_of_week]           - Day(s) of Week
 *   [event_mode]                   - Event Mode (Online, In-person, etc.)
 *   [featured_image_url]           - Featured Image URL
 *   [flyer_url]                    - Flyer URL
 *   [instructor_slugs]             - Pipe-separated trainer slugs
 *   [is_multiday]                  - "1" if multi-day, "0" otherwise
 *   [is_multisession]              - "1" if multi-session, "0" otherwise
 *
 * Expected URL pattern:
 *   /training-details/?event-id=1234
 *
 * Quickbase configuration:
 *   Assumes these constants are defined in wp-config.php:
 *     QB_REALM_HOST
 *     QB_TABLE_ID
 *     QB_USER_TOKEN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure Quickbase constants exist.
 */
function arc_td_has_quickbase_config() {
	return defined( 'QB_REALM_HOST' ) && defined( 'QB_TABLE_ID' ) && defined( 'QB_USER_TOKEN' );
}

/**
 * Fetch a single Quickbase record by event-id (Record ID#).
 *
 * Caches the record in a static variable per request so multiple shortcodes
 * don't trigger multiple HTTP calls.
 *
 * @return array|\WP_Error
 */
function arc_td_get_current_record() {
	static $cached_record = null;

	if ( ! is_null( $cached_record ) ) {
		return $cached_record;
	}

	if ( ! arc_td_has_quickbase_config() ) {
		return new WP_Error( 'arc_td_missing_config', 'Quickbase configuration missing.' );
	}

	// Get event-id from query string.
	$record_id = isset( $_GET['event-id'] ) ? intval( $_GET['event-id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $record_id <= 0 ) {
		return new WP_Error( 'arc_td_missing_id', 'Missing or invalid event-id parameter.' );
	}

	// Quickbase REST API endpoint.
	$url = 'https://api.quickbase.com/v1/records/query';

	/**
	 * "from" is your Training table ID (QB_TABLE_ID).
	 * "select" is the list of field IDs to return.
	 *
	 * Field IDs used here:
	 *   3   = Record ID#
	 *   45  = Event Date(s)
	 *   89  = Event Time
	 *   19  = Event Title
	 *   29  = Venue Name
	 *   271 = Instructor(s)
	 *   361 = Credit Hours
	 *   440 = Event Description (long text)
	 *   450 = Training Cost
	 *   14  = Add Registration (URL)
	 *   413 = Day(s) of Week
	 *   458 = Event Mode
	 *   461 = Featured Image URL
	 *   267 = Flyer URL
	 *   449 = Instructor slugs (e.g. "nkaasa|ldutton")
	 *   453 = Is Multi-Day
	 *   454 = Is Multi-Session
	 */
	$body = array(
		'from'   => QB_TABLE_ID,
		'select' => array(
			3,   // Record ID#
			45,  // Event Date(s)
			89,  // Event Time
			19,  // Event Title
			29,  // Venue Name
			271, // Instructor(s)
			361, // Credit Hours
			440, // Event Description (long)
			450, // Training Cost
			14,  // Add Registration (URL)
			413, // Day(s) of Week
			458, // Event Mode
			461, // Featured Image URL
			267, // Flyer URL
			449, // Instructor slugs (e.g. "nkaasa|ldutton")
			453, // Is Multi-Day
			454, // Is Multi-Session
		),
		'where'   => "{3.EX.$record_id}",
		'options' => array(
			'top' => 1,
		),
	);

	$args = array(
		'headers' => array(
			'QB-Realm-Hostname' => QB_REALM_HOST,
			'User-Agent'        => 'WordPress-ARC-Training-Details',
			'Authorization'     => 'QB-USER-TOKEN ' . QB_USER_TOKEN,
			'Content-Type'      => 'application/json',
		),
		'body'        => wp_json_encode( $body ),
		'method'      => 'POST',
		'timeout'     => 10,
		'data_format' => 'body',
	);

	$response = wp_remote_post( $url, $args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $status || empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
		return new WP_Error( 'arc_td_bad_response', 'Unexpected response from Quickbase.' );
	}

	$record = reset( $data['data'] );

	if ( ! is_array( $record ) ) {
		return new WP_Error( 'arc_td_no_record', 'No record returned from Quickbase.' );
	}

	// Cache for subsequent calls during this request.
	$cached_record = $record;

	// Also store trainer slugs in a query var to reuse in Elementor custom query.
	if ( ! empty( $record['449']['value'] ) ) {
		$raw_slugs = $record['449']['value']; // e.g. "nkaasa|ldutton".

		$slugs = array_filter(
			array_map( 'trim', explode( '|', $raw_slugs ) )
		);

		set_query_var( 'arc_trainer_slugs', $slugs );
	}

	return $record;
}

/**
 * Get a single field's value from the current Quickbase record.
 *
 * @param int $field_id Quickbase field ID.
 * @return string
 */
function arc_td_get_field_value( $field_id ) {
	$record = arc_td_get_current_record();

	if ( is_wp_error( $record ) || empty( $record ) ) {
		return '';
	}

	$key = (string) $field_id;
	if ( ! isset( $record[ $key ]['value'] ) ) {
		return '';
	}

	return $record[ $key ]['value'];
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
add_shortcode( 'arc_trainer_title', 'arc_td_sc_trainer_title' );

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

	// TEMP: show exactly what we’re getting.
//	return '<code>' . esc_html( $value ) . '</code>';

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
 * Register shortcodes on init.
 */
function arc_td_register_shortcodes() {

	// New field shortcodes (preferred names).
	add_shortcode( 'event_title',           'arc_td_sc_title' );
	add_shortcode( 'event_dates',           'arc_td_sc_dates' );
	add_shortcode( 'event_time',            'arc_td_sc_time' );
	add_shortcode( 'venue_name',            'arc_td_sc_city' );
	add_shortcode( 'instructors',           'arc_td_sc_instructors' );
	add_shortcode( 'training_cost',         'arc_td_sc_cost' );
	add_shortcode( 'event_description',     'arc_td_sc_description' );
	add_shortcode( 'add_registration_url',  'arc_td_sc_add_registration_url' );
	add_shortcode( 'event_days_of_week',    'arc_td_sc_event_days_of_week' );
	add_shortcode( 'event_mode',            'arc_td_sc_event_mode' );
	add_shortcode( 'featured_image_url',    'arc_td_sc_featured_image_url' );
	add_shortcode( 'flyer_url',             'arc_td_sc_flyer_url' );
	add_shortcode( 'instructor_slugs',      'arc_td_sc_instructor_slugs' );
	add_shortcode( 'is_multiday',           'arc_td_sc_is_multiday' );
	add_shortcode( 'is_multisession',       'arc_td_sc_is_multisession' );

	// Generic field access by Quickbase FID.
	add_shortcode( 'arc_training_field',    'arc_td_sc_field' );
}
add_action( 'init', 'arc_td_register_shortcodes' );


/**
 * Elementor custom query: limit trainer loop items to the slugs passed from QB.
 *
 * Used by Loop Grid with Custom Query ID: "trainers".
 */
add_action( 'elementor/query/trainers', function( $query ) {

	// Make sure our helper exists.
	if ( ! function_exists( 'arc_td_get_current_record' ) ) {
		return;
	}

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
