<?php
/**
 * Shared Quickbase API request function.
 *
 * Both events.php and courses.php call arc_qb_request() rather than
 * making their own wp_remote_post calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send a query to the Quickbase Records API.
 *
 * @param array $body  The request body (from, select, where, options, sortBy, etc.)
 * @return array|\WP_Error  Parsed 'data' array on success, WP_Error on failure.
 */
function arc_qb_request( array $body ) {

	if ( ! defined( 'QB_REALM_HOST' ) || ! defined( 'QB_USER_TOKEN' ) ) {
		return new WP_Error(
			'arc_qb_missing_config',
			'Quickbase configuration missing: QB_REALM_HOST and QB_USER_TOKEN must be defined in wp-config.php.'
		);
	}

	$url = 'https://api.quickbase.com/v1/records/query';

	$args = array(
		'headers' => array(
			'QB-Realm-Hostname' => QB_REALM_HOST,
			'User-Agent'        => 'WordPress-ArcOregon-QBSync',
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
		return new WP_Error(
			'arc_qb_bad_response',
			sprintf( 'Unexpected response from Quickbase (HTTP %d).', $status )
		);
	}

	return $data['data'];
}

// ── Course field helpers ───────────────────────────────────────────────────────
// Moved here from courses.php in v2.2.0. sync-courses.php and
// shortcodes-courses.php both call these; qb-api.php is loaded first.

/**
 * Extract a single field's value from a course record array.
 *
 * @param array $record    A single QB record array (keyed by field ID string).
 * @param int   $field_id  Quickbase field ID.
 * @return string
 */
function arc_qb_get_course_field( $record, $field_id ) {
	$key = (string) $field_id;
	if ( isset( $record[ $key ]['value'] ) ) {
		return $record[ $key ]['value'];
	}
	return '';
}

/**
 * Parse the Keywords / Tags field value into a clean array of tag strings.
 *
 * QB field 56 (Keywords / Tags) returns a PHP array via the REST API.
 * Falls back to newline-splitting for plain string values.
 *
 * @param mixed $raw  Raw value from QB field 56 (array or string).
 * @return string[]   Array of trimmed, non-empty tag strings.
 */
function arc_qb_parse_tags( $raw ) {
	if ( is_array( $raw ) ) {
		return array_values( array_filter( array_map( 'trim', $raw ) ) );
	}
	$tags = array_filter(
		array_map( 'trim', explode( "\n", (string) $raw ) )
	);
	return array_values( $tags );
}

/**
 * Format a Quickbase Duration field value (milliseconds) as a human-readable
 * hours string, e.g. 23400000 → "6.5 hours".
 *
 * @param mixed $ms  Raw QB duration value in milliseconds.
 * @return string    Formatted string, e.g. "6.5 hours" or "2 hours".
 */
function arc_qb_format_duration( $ms ) {
	if ( ! is_numeric( $ms ) || intval( $ms ) <= 0 ) {
		return esc_html( (string) $ms );
	}
	$hours = intval( $ms ) / 3600000;
	if ( $hours === floor( $hours ) ) {
		$formatted = number_format( (int) $hours ) . ' hours';
	} else {
		$formatted = rtrim( rtrim( number_format( $hours, 1 ), '0' ), '.' ) . ' hours';
	}
	return $formatted;
}
