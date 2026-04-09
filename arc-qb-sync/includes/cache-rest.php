<?php
/**
 * REST endpoint for cache invalidation.
 *
 * Route: POST /wp-json/arc-qb-sync/v1/bust-cache
 *
 * Called by Zapier when a Course Catalog record is saved in Quickbase.
 * Requires Authorization: Bearer [ARC_QB_CACHE_BUST_TOKEN] header.
 *
 * See docs/webhook-zapier.md for setup instructions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'arc_qb_register_cache_bust_route' );

/**
 * Register the cache bust REST route.
 */
function arc_qb_register_cache_bust_route() {
	register_rest_route(
		'arc-qb-sync/v1',
		'/bust-cache',
		array(
			'methods'             => 'POST',
			'callback'            => 'arc_qb_handle_cache_bust',
			'permission_callback' => 'arc_qb_cache_bust_permission',
		)
	);
}

/**
 * Permission callback: validate Bearer token in Authorization header.
 *
 * @param \WP_REST_Request $request
 * @return true|\WP_Error
 */
function arc_qb_cache_bust_permission( $request ) {
	if ( ! defined( 'ARC_QB_CACHE_BUST_TOKEN' ) ) {
		return new WP_Error(
			'arc_qb_auth_not_configured',
			'Cache bust endpoint is not configured.',
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
 * Callback: delete the course catalog transient and return success.
 *
 * @return \WP_REST_Response
 */
function arc_qb_handle_cache_bust() {
	delete_transient( 'arc_qb_public_courses' );

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'Cache cleared.',
		)
	);
}
