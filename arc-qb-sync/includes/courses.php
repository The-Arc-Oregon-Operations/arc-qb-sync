<?php
/**
 * Courses module.
 *
 * Fetches course records from the Quickbase Course Catalog table
 * (QB_COURSES_TABLE_ID). Provides single-course lookup for detail pages
 * and a full catalog fetch (with transient caching) for the grid shortcode.
 *
 * Expected URL pattern for detail pages: /course-catalog/?course-id=nnnn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse the Keywords / Tags field value into a clean array of tag strings.
 *
 * ⚠️ Delimiter TBD — assumes newline (\n). Change $delimiter if the live
 * QB API response for field 56 uses a different separator (e.g. comma, pipe).
 *
 * @param string $raw  Raw value from QB field 56.
 * @return string[]    Array of trimmed, non-empty tag strings.
 */
function arc_qb_parse_tags( $raw ) {
	$delimiter = "\n"; // TODO: verify against live QB API response for field 56
	$tags = array_filter(
		array_map( 'trim', explode( $delimiter, (string) $raw ) )
	);
	return array_values( $tags );
}

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
 * Fetch a single Course Catalog record by course-id (Record ID#).
 *
 * Caches the record in a static variable per request so multiple shortcodes
 * on the same page don't trigger multiple HTTP calls.
 *
 * Fields fetched:
 *   3  = Record ID#
 *   6  = Course Title
 *   7  = Description + Learning Objectives (fallback)
 *   14 = Hours of Instruction
 *   36 = Public Listing
 *   39 = Payment
 *   40 = Delivery Method
 *   43 = Category
 *   46 = Description, Short
 *   50 = Target Audience - English
 *   56 = Keywords / Tags
 *   62 = Learning Objectives
 *   85 = Learning Objectives - HTML (preferred)
 *   88 = Featured Image URL
 *
 * @return array|\WP_Error
 */
function arc_qb_get_course() {
	static $cached_record = null;

	if ( ! is_null( $cached_record ) ) {
		return $cached_record;
	}

	if ( ! defined( 'QB_REALM_HOST' ) || ! defined( 'QB_USER_TOKEN' ) || ! defined( 'QB_COURSES_TABLE_ID' ) ) {
		return new WP_Error(
			'arc_qb_courses_missing_config',
			'Quickbase Courses configuration missing. Ensure QB_REALM_HOST, QB_USER_TOKEN, and QB_COURSES_TABLE_ID are defined in wp-config.php.'
		);
	}

	// Get course-id from query string.
	$course_id = isset( $_GET['course-id'] ) ? intval( $_GET['course-id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $course_id <= 0 ) {
		return new WP_Error( 'arc_qb_missing_course_id', 'Missing or invalid course-id parameter.' );
	}

	$body = array(
		'from'   => QB_COURSES_TABLE_ID,
		'select' => array( 3, 6, 7, 14, 36, 39, 40, 43, 46, 50, 56, 62, 85, 88 ),
		'where'  => "{3.EX.$course_id}",
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
		return new WP_Error( 'arc_qb_no_course_record', 'No course record returned from Quickbase.' );
	}

	$cached_record = $record;

	return $record;
}

/**
 * Fetch all publicly listed courses from the Course Catalog table.
 *
 * Uses a WP transient (15-minute TTL) to avoid repeated API calls.
 * Fetches only the fields needed for catalog tiles.
 *
 * Fields fetched:
 *   3  = Record ID#
 *   6  = Course Title
 *   14 = Hours of Instruction
 *   46 = Description, Short
 *   56 = Keywords / Tags
 *   88 = Featured Image URL
 *
 * @return array|\WP_Error  Array of course records, empty array if none, WP_Error on failure.
 */
function arc_qb_get_public_courses() {
	$transient_key = 'arc_qb_public_courses';

	$cached = get_transient( $transient_key );
	if ( false !== $cached ) {
		return $cached;
	}

	if ( ! defined( 'QB_REALM_HOST' ) || ! defined( 'QB_USER_TOKEN' ) || ! defined( 'QB_COURSES_TABLE_ID' ) ) {
		return new WP_Error(
			'arc_qb_courses_missing_config',
			'Quickbase Courses configuration missing. Ensure QB_REALM_HOST, QB_USER_TOKEN, and QB_COURSES_TABLE_ID are defined in wp-config.php.'
		);
	}

	$body = array(
		'from'   => QB_COURSES_TABLE_ID,
		'select' => array( 3, 6, 14, 46, 56, 88 ),
		'where'  => '{36.EX.true}',
		'sortBy' => array(
			array(
				'fieldId' => 6,
				'order'   => 'ASC',
			),
		),
	);

	$records = arc_qb_request( $body );

	if ( is_wp_error( $records ) ) {
		return $records;
	}

	// arc_qb_request returns WP_Error on empty data, so $records is a non-empty array here.
	// Normalize: if for any reason it came back non-array, return empty.
	if ( ! is_array( $records ) ) {
		$records = array();
	}

	set_transient( $transient_key, $records, 15 * MINUTE_IN_SECONDS );

	return $records;
}
