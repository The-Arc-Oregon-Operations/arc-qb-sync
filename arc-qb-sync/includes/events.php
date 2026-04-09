<?php
/**
 * Events module.
 *
 * Fetches a single event record from the Quickbase Events table
 * (QB_TABLE_ID) using the ?event-id= URL parameter.
 *
 * This is a direct migration of the logic from arc-training-details v0.4.0.
 * Behavior and field IDs are unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure Quickbase constants exist for the Events module.
 *
 * @return bool
 */
function arc_td_has_quickbase_config() {
	return defined( 'QB_REALM_HOST' ) && defined( 'QB_TABLE_ID' ) && defined( 'QB_USER_TOKEN' );
}

/**
 * Fetch a single Quickbase event record by event-id (Record ID#).
 *
 * Caches the record in a static variable per request so multiple shortcodes
 * on the same page don't trigger multiple HTTP calls.
 *
 * Field IDs fetched:
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

	$records = arc_qb_request( $body );

	if ( is_wp_error( $records ) ) {
		return $records;
	}

	$record = reset( $records );

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
 * Get a single field's value from the current Quickbase event record.
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
