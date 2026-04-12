<?php
/**
 * REST endpoint for incremental course sync.
 *
 * Route: POST /wp-json/arc-qb-sync/v1/sync-course
 *
 * Called by Zapier when a Course Catalog record is saved in Quickbase.
 * Requires Authorization: Bearer [ARC_QB_CACHE_BUST_TOKEN] header.
 * Body: {"record_id": 12345}
 *
 * The endpoint fetches that single record from QB and upserts it into WP.
 *
 * Zapier action (Webhooks by Zapier — POST):
 *   URL:     https://thearcoregon.org/wp-json/arc-qb-sync/v1/sync-course
 *   Headers: Authorization: Bearer [ARC_QB_CACHE_BUST_TOKEN value]
 *   Body:    {"record_id": "{{3}}"}
 *
 * Note: Confirm that the QB webhook payload includes field values and that
 * Record ID# (field 3) is accessible as {{3}} in Zapier's data mapper.
 * If not, add an intermediate QB lookup step before the WP POST action.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'arc_qb_register_sync_course_route' );

/**
 * Register the sync-course REST route.
 */
function arc_qb_register_sync_course_route() {
	register_rest_route(
		'arc-qb-sync/v1',
		'/sync-course',
		array(
			'methods'             => 'POST',
			'callback'            => 'arc_qb_handle_sync_course',
			'permission_callback' => 'arc_qb_sync_course_permission',
		)
	);
}

/**
 * Permission callback: validate Bearer token in Authorization header.
 *
 * @param  \WP_REST_Request $request
 * @return true|\WP_Error
 */
function arc_qb_sync_course_permission( $request ) {
	if ( ! defined( 'ARC_QB_CACHE_BUST_TOKEN' ) ) {
		return new WP_Error(
			'arc_qb_auth_not_configured',
			'Sync endpoint is not configured.',
			array( 'status' => 401 )
		);
	}

	$auth_header = $request->get_header( 'authorization' );
	$expected    = 'Bearer ' . ARC_QB_CACHE_BUST_TOKEN;

	if ( $auth_header !== $expected ) {
		return new WP_Error(
			'arc_qb_unauthorized',
			'Invalid or missing Authorization token.',
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Callback: fetch the specified QB record and upsert it into WordPress.
 *
 * @param  \WP_REST_Request $request
 * @return \WP_REST_Response|\WP_Error
 */
function arc_qb_handle_sync_course( $request ) {
	$body      = $request->get_json_params();
	$record_id = isset( $body['record_id'] ) ? intval( $body['record_id'] ) : 0;

	if ( $record_id <= 0 ) {
		return new WP_Error(
			'arc_qb_missing_record_id',
			'Missing or invalid record_id in request body.',
			array( 'status' => 400 )
		);
	}

	// Fetch the single QB record.
	$record = arc_qb_fetch_course_record( $record_id );

	if ( is_wp_error( $record ) ) {
		error_log( '[arc-qb-sync] Webhook fetch failed for record_id ' . $record_id . ': ' . $record->get_error_message() );
		return new WP_Error(
			'arc_qb_fetch_failed',
			$record->get_error_message(),
			array( 'status' => 502 )
		);
	}

	// Upsert into WordPress.
	$post_id = arc_qb_upsert_course( $record );

	if ( is_wp_error( $post_id ) ) {
		error_log( '[arc-qb-sync] Webhook upsert failed for record_id ' . $record_id . ': ' . $post_id->get_error_message() );
		return new WP_Error(
			'arc_qb_upsert_failed',
			$post_id->get_error_message(),
			array( 'status' => 500 )
		);
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'post_id' => $post_id,
		)
	);
}
