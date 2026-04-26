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

	if ( 200 !== $status || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
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
 * Set the WP featured image for a CPT post — Option A pipeline hybrid.
 *
 * Fast path: if $args['attachment_id'] is non-zero, call set_post_thumbnail()
 * directly. No HTTP, no lookup.
 *
 * Miss path: if attachment_id is empty and review_status is 'Approved':
 *   1. Try QB file download (via i_file attachment field on Image Assets record).
 *   2. If no file: fall back to sideload from $args['image_url'].
 *   3. On success: write attachment ID back to QB via arc_qb_write_image_attachment_id().
 *
 * If review_status is not 'Approved', skip sideload and log gate reason.
 * If all paths fail, clear any existing thumbnail (stale prevention).
 *
 * @param int   $post_id   WP post ID.
 * @param array $args {
 *   @type int    $attachment_id  Stored WP attachment ID (0 if not yet sideloaded).
 *   @type string $review_status  Value of i_review_status on Image Assets record.
 *   @type string $image_url      Fallback public URL (i_url lookup, FID 6).
 *   @type int    $ia_record_id   Image Assets Record ID# (FK from child record).
 *                                Required for QB file download and writeback.
 *   @type string $ia_filename    Optional. Filename hint for QB file download.
 *   @type string $context_label  Log label — e.g. 'Course 123 featured'.
 * }
 * @return void
 */
function arc_qb_sync_set_featured_image( $post_id, array $args ) {

	$attachment_id = intval( $args['attachment_id'] ?? 0 );
	$review_status = sanitize_text_field( $args['review_status'] ?? '' );
	$image_url     = esc_url_raw( $args['image_url'] ?? '' );
	$ia_record_id  = intval( $args['ia_record_id'] ?? 0 );
	$ia_filename   = sanitize_file_name( $args['ia_filename'] ?? 'image' );
	$label         = sanitize_text_field( $args['context_label'] ?? 'unknown' );

	// ── Fast path: attachment ID already stored ───────────────────────────────
	if ( $attachment_id > 0 ) {
		set_post_thumbnail( $post_id, $attachment_id );
		return;
	}

	// ── Miss path: need to sideload ───────────────────────────────────────────
	if ( 'Approved' !== $review_status ) {
		if ( $review_status ) {
			error_log( "[arc-qb-sync] {$label}: review gate ({$review_status}) — sideload skipped." );
		}
		delete_post_thumbnail( $post_id );
		return;
	}

	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$new_attachment_id = 0;
	$public_url        = '';

	// ── Attempt 1: QB file attachment download ────────────────────────────────
	if ( $ia_record_id > 0 && defined( 'ARC_QB_IA_FID_FILE' ) && ARC_QB_IA_FID_FILE > 0 ) {
		$file = arc_qb_download_image_file(
			QB_IMAGE_ASSETS_TABLE_ID,
			$ia_record_id,
			ARC_QB_IA_FID_FILE,
			$ia_filename
		);

		if ( ! is_wp_error( $file ) ) {
			$overrides = array( 'test_form' => false );
			$moved     = wp_handle_sideload( $file, $overrides );

			if ( empty( $moved['error'] ) ) {
				$attachment = array(
					'post_mime_type' => $moved['type'],
					'post_title'     => sanitize_file_name( pathinfo( $moved['file'], PATHINFO_FILENAME ) ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				);
				$att_id = wp_insert_attachment( $attachment, $moved['file'], $post_id );
				if ( ! is_wp_error( $att_id ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$metadata = wp_generate_attachment_metadata( $att_id, $moved['file'] );
					wp_update_attachment_metadata( $att_id, $metadata );
					$new_attachment_id = $att_id;
					$public_url        = wp_get_attachment_url( $att_id );
				}
			} else {
				error_log( "[arc-qb-sync] {$label}: QB file sideload failed — {$moved['error']}" );
			}

			// Clean up temp file if it still exists.
			if ( file_exists( $file['tmp_name'] ) ) {
				@unlink( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		} else {
			error_log( "[arc-qb-sync] {$label}: QB file download error — " . $file->get_error_message() );
		}
	}

	// ── Attempt 2: URL sideload fallback ──────────────────────────────────────
	if ( 0 === $new_attachment_id && $image_url ) {
		$result = media_sideload_image( $image_url, $post_id, null, 'id' );
		if ( ! is_wp_error( $result ) ) {
			$new_attachment_id = $result;
			$public_url        = wp_get_attachment_url( $new_attachment_id );
		} else {
			error_log( "[arc-qb-sync] {$label}: URL sideload failed — " . $result->get_error_message() );
		}
	}

	// ── Set thumbnail and write back to QB ────────────────────────────────────
	if ( $new_attachment_id > 0 ) {
		set_post_thumbnail( $post_id, $new_attachment_id );

		if ( $ia_record_id > 0 ) {
			$wb = arc_qb_write_image_attachment_id( $ia_record_id, $new_attachment_id, $public_url );
			if ( is_wp_error( $wb ) ) {
				error_log( "[arc-qb-sync] {$label}: writeback failed — " . $wb->get_error_message() );
			} else {
				error_log( "[arc-qb-sync] {$label}: sideloaded and written back to QB (att ID {$new_attachment_id})." );
			}
		}
	} else {
		// All paths exhausted — clear any stale thumbnail.
		delete_post_thumbnail( $post_id );
		error_log( "[arc-qb-sync] {$label}: no image source available; featured image cleared." );
	}
}

/**
 * Upsert one or more records to a Quickbase table.
 *
 * Posts to /v1/records. Include FID 3 (Record ID#) in each record's data
 * array to update an existing record; omit it to create a new one.
 *
 * @param string $table_id  Quickbase table ID (e.g. 'bvx88yiv2').
 * @param array  $data      Array of record data arrays. Each inner array is
 *                          keyed by FID (int) → array( 'value' => $value ).
 * @return array|\WP_Error  QB API response body on success, WP_Error on failure.
 */
function arc_qb_upsert_record( $table_id, array $data ) {

	if ( ! defined( 'QB_REALM_HOST' ) || ! defined( 'QB_USER_TOKEN' ) ) {
		return new WP_Error(
			'arc_qb_missing_config',
			'Quickbase configuration missing: QB_REALM_HOST and QB_USER_TOKEN must be defined in wp-config.php.'
		);
	}

	$url  = 'https://api.quickbase.com/v1/records';
	$body = array(
		'to'   => $table_id,
		'data' => $data,
	);

	$args = array(
		'headers' => array(
			'QB-Realm-Hostname' => QB_REALM_HOST,
			'User-Agent'        => 'WordPress-ArcOregon-QBSync',
			'Authorization'     => 'QB-USER-TOKEN ' . QB_USER_TOKEN,
			'Content-Type'      => 'application/json',
		),
		'body'        => wp_json_encode( $body ),
		'method'      => 'POST',
		'timeout'     => 15,
		'data_format' => 'body',
	);

	$response = wp_remote_post( $url, $args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status      = wp_remote_retrieve_response_code( $response );
	$parsed_body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $status < 200 || $status >= 300 ) {
		return new WP_Error(
			'arc_qb_upsert_failed',
			sprintf( 'QB upsert failed (HTTP %d).', $status )
		);
	}

	return $parsed_body;
}

/**
 * Download a file from a Quickbase File Attachment field.
 *
 * Uses the QB Files API: GET /v1/files/{tableId}/{recordId}/{fieldId}/{version}
 * Version 0 is the original upload. If the file has been replaced in QB,
 * clear i_attachment_id and re-approve to trigger a fresh sideload.
 *
 * @param string $table_id  QB table ID containing the file attachment field.
 * @param int    $record_id QB Record ID# of the record with the attachment.
 * @param int    $field_id  FID of the File Attachment field.
 * @param string $filename  Suggested filename for the temp file.
 * @return array|\WP_Error  Array with keys 'tmp_name', 'name', 'size', 'type'
 *                          on success (compatible with wp_handle_sideload()).
 *                          WP_Error on failure.
 */
function arc_qb_download_image_file( $table_id, $record_id, $field_id, $filename = 'image' ) {

	if ( ! defined( 'QB_REALM_HOST' ) || ! defined( 'QB_USER_TOKEN' ) ) {
		return new WP_Error( 'arc_qb_missing_config', 'QB_REALM_HOST and QB_USER_TOKEN must be defined.' );
	}

	$url = sprintf(
		'https://api.quickbase.com/v1/files/%s/%d/%d/0',
		rawurlencode( $table_id ),
		intval( $record_id ),
		intval( $field_id )
	);

	$response = wp_remote_get( $url, array(
		'headers' => array(
			'QB-Realm-Hostname' => QB_REALM_HOST,
			'Authorization'     => 'QB-USER-TOKEN ' . QB_USER_TOKEN,
			'User-Agent'        => 'WordPress-ArcOregon-QBSync',
		),
		'timeout' => 30,
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		return new WP_Error(
			'arc_qb_file_download_failed',
			sprintf( 'QB file download returned HTTP %d for record %d field %d.', $status, $record_id, $field_id )
		);
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		return new WP_Error( 'arc_qb_file_empty', 'QB file download returned empty body.' );
	}

	// QB Files API returns the file content base64-encoded in the response body.
	// Decode before writing to disk so WP receives real binary, not base64 text.
	$decoded = base64_decode( $body, true ); // strict=true: returns false on invalid base64
	if ( false !== $decoded && '' !== $decoded ) {
		$body = $decoded;
	}

	// Detect content type from response headers.
	$content_type = wp_remote_retrieve_header( $response, 'content-type' );
	$content_type = $content_type ?: 'application/octet-stream';

	// ── Resolve final filename with extension ─────────────────────────────────
	// Priority 1: original filename from Content-Disposition (QB sends this).
	// Priority 2: extension derived from content-type.
	// Priority 3: passed $filename as-is (no extension — least preferred).
	$final_name = '';

	$content_disposition = wp_remote_retrieve_header( $response, 'content-disposition' );
	if ( $content_disposition ) {
		// Matches both filename="foo.png" and filename*=UTF-8''foo.png forms.
		if ( preg_match( '/filename\*?=["\']?(?:UTF-\d+\'\')?([^\'";\s]+)["\']?/i', $content_disposition, $m ) ) {
			$final_name = sanitize_file_name( rawurldecode( $m[1] ) );
		}
	}

	if ( ! $final_name ) {
		$base = pathinfo( sanitize_file_name( $filename ), PATHINFO_FILENAME );
		$base = $base ?: 'image';

		$mime_ext = array(
			'image/jpeg'   => 'jpg',
			'image/jpg'    => 'jpg',
			'image/png'    => 'png',
			'image/gif'    => 'gif',
			'image/webp'   => 'webp',
			'image/svg+xml' => 'svg',
		);
		$base_mime = strtolower( trim( explode( ';', $content_type )[0] ) );
		$ext       = $mime_ext[ $base_mime ] ?? '';

		$final_name = $ext ? "{$base}.{$ext}" : sanitize_file_name( $filename );
	}

	// Write to a temp file in WP's upload tmp dir.
	$tmp_dir  = get_temp_dir();
	$tmp_file = tempnam( $tmp_dir, 'arc_qb_img_' );
	if ( false === $tmp_file ) {
		return new WP_Error( 'arc_qb_tmpfile_failed', 'Could not create temp file for QB image download.' );
	}

	file_put_contents( $tmp_file, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

	return array(
		'tmp_name' => $tmp_file,
		'name'     => $final_name,
		'size'     => strlen( $body ),
		'type'     => $content_type,
	);
}

/**
 * Write a WP attachment ID back to the Image Assets record in QB (Option A).
 *
 * Called only on successful first-touch sideload — not on every sync.
 * On subsequent syncs, i_attachment_id will be non-zero and the sideload
 * path is skipped entirely.
 *
 * @param int    $ia_record_id  Image Assets Record ID# (from the FK field on
 *                              the parent — e.g. Courses, Events, Instructors).
 * @param int    $attachment_id WP Media Library attachment ID to write.
 * @param string $public_url    Optional. WP public media URL to write to i_url (FID 6).
 *                              Pass empty string to skip.
 * @return array|\WP_Error
 */
function arc_qb_write_image_attachment_id( $ia_record_id, $attachment_id, $public_url = '' ) {

	if ( ! defined( 'QB_IMAGE_ASSETS_TABLE_ID' ) || '' === QB_IMAGE_ASSETS_TABLE_ID ) {
		return new WP_Error(
			'arc_qb_missing_config',
			'QB_IMAGE_ASSETS_TABLE_ID is not defined in wp-config.php.'
		);
	}

	// FID 3 = Record ID# (upsert target). FID values from FID Log.
	$record = array(
		3                             => array( 'value' => intval( $ia_record_id ) ),
		ARC_QB_IA_FID_ATTACHMENT_ID   => array( 'value' => intval( $attachment_id ) ),
		ARC_QB_IA_FID_PROC_STATUS     => array( 'value' => 'Processed' ),
	);

	if ( $public_url ) {
		$record[ ARC_QB_IA_FID_URL ] = array( 'value' => esc_url_raw( $public_url ) );
	}

	return arc_qb_upsert_record( QB_IMAGE_ASSETS_TABLE_ID, array( $record ) );
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
