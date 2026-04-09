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
