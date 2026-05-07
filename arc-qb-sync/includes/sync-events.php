<?php
/**
 * Events sync engine.
 *
 * Handles syncing Quickbase Training Events records into WordPress as `arc_event`
 * Custom Post Type posts. Provides:
 *
 *  - arc_qb_fetch_all_event_records()  — fetch all public QB event records
 *  - arc_qb_upsert_event( $record )    — create or update a WP post
 *  - arc_qb_sync_all_events()          — full sync (all records)
 *  - WP Admin settings page with "Sync All Events Now" button
 *
 * QB field mapping (v3.0.0):
 *   3   → _arc_qb_event_id (sync key)
 *   7   → _arc_event_start_date (ISO date, e.g. "2026-04-15" — used for sort order)
 *   14  → _arc_event_reg_url
 *   19  → post_title
 *   29  → _arc_event_venue
 *   45  → _arc_event_dates (formatted display text — NOT used for sorting)
 *   89  → _arc_event_time
 *   137 → post_status: publish (TRUE) / draft (FALSE)
 *   267 → _arc_event_flyer_url
 *   271 → _arc_event_instructors_legacy
 *   361 → _arc_event_length
 *   413 → _arc_event_days_of_week
 *   440 → _arc_event_description
 *   449 → _arc_event_instructor_slugs_legacy
 *   450 → _arc_event_price
 *   453 → _arc_event_is_multiday
 *   454 → _arc_event_is_multisession
 *   458 → _arc_event_mode
 *   461 → _arc_event_image_url (legacy manual field)
 *   464 → _arc_event_featured_image_url
 *   466 → _arc_event_hero_image_url
 *   computed → _arc_event_schedule (FIDs 413 + 45 concatenated with ' • ')
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── FID constants — Events image lookups ──────────────────────────────────────
// These are permanent Quickbase field IDs specific to The Arc Oregon's QB app.
// They belong in the plugin, not in wp-config.php.

define( 'ARC_QB_EVENT_FEATURED_IMAGE_FID', 464 ); // Events: Featured Image URL [lookup from Image Assets]
define( 'ARC_QB_EVENT_HERO_IMAGE_FID',     466 ); // Events: Hero Image URL [lookup from Image Assets]

// ── Events: pipeline lookup FIDs ──────────────────────────────────────────────
// Shared ARC_QB_IA_FID_* constants are defined in sync-courses.php (loaded first).
if ( ! defined( 'ARC_QB_EVENT_FEATURED_IMAGE_FK_FID' ) )         define( 'ARC_QB_EVENT_FEATURED_IMAGE_FK_FID',         463 ); // Featured Image [Ref] — FK
if ( ! defined( 'ARC_QB_EVENT_FEATURED_IMAGE_ATTACHMENT_FID' ) ) define( 'ARC_QB_EVENT_FEATURED_IMAGE_ATTACHMENT_FID', 495 ); // Featured Image - Attachment ID [lookup]
if ( ! defined( 'ARC_QB_EVENT_FEATURED_IMAGE_REVIEW_FID' ) )     define( 'ARC_QB_EVENT_FEATURED_IMAGE_REVIEW_FID',     496 ); // Featured Image - Review Status [lookup]
if ( ! defined( 'ARC_QB_EVENT_HERO_IMAGE_FK_FID' ) )             define( 'ARC_QB_EVENT_HERO_IMAGE_FK_FID',             465 ); // Hero Image [Ref] — FK
if ( ! defined( 'ARC_QB_EVENT_HERO_IMAGE_ATTACHMENT_FID' ) )     define( 'ARC_QB_EVENT_HERO_IMAGE_ATTACHMENT_FID',     497 ); // Hero Image - Attachment ID [lookup]
if ( ! defined( 'ARC_QB_EVENT_HERO_IMAGE_REVIEW_FID' ) )         define( 'ARC_QB_EVENT_HERO_IMAGE_REVIEW_FID',         498 ); // Hero Image - Review Status [lookup]

// ── QB fetch helpers ──────────────────────────────────────────────────────────

/**
 * Fetch publicly listed Training Event records from Quickbase (FID 137 = TRUE only).
 *
 * QB is the strict gatekeeper. Non-public events never enter WordPress.
 * If an event's Show Public flag is unchecked in QB, the sync demotes the
 * existing WP post to draft — it is never created here.
 *
 * @return array|\WP_Error  Array of record arrays on success.
 */
function arc_qb_fetch_all_event_records() {
	if ( ! defined( 'QB_TABLE_ID' ) ) {
		return new WP_Error(
			'arc_qb_missing_config',
			'QB_TABLE_ID is not defined in wp-config.php.'
		);
	}

	// Base fields — always included.
	// FID 7 = Start Date (Date type, returns ISO 8601 — used for sort order via _arc_event_start_date).
	$select = array( 3, 7, 14, 19, 29, 45, 89, 137, 267, 271, 361, 413, 440, 449, 450, 453, 454, 458, 461 );

	// Image lookup FIDs — hardcoded (stable QB schema, not wp-config).
	$select = array_merge( $select, array( 464, 466 ) );

	// Add pipeline FIDs (non-zero only — safe to deploy before FID log is finalized).
	if ( ARC_QB_EVENT_FEATURED_IMAGE_FK_FID > 0 )         $select[] = ARC_QB_EVENT_FEATURED_IMAGE_FK_FID;
	if ( ARC_QB_EVENT_FEATURED_IMAGE_ATTACHMENT_FID > 0 ) $select[] = ARC_QB_EVENT_FEATURED_IMAGE_ATTACHMENT_FID;
	if ( ARC_QB_EVENT_FEATURED_IMAGE_REVIEW_FID > 0 )     $select[] = ARC_QB_EVENT_FEATURED_IMAGE_REVIEW_FID;
	if ( ARC_QB_EVENT_HERO_IMAGE_FK_FID > 0 )             $select[] = ARC_QB_EVENT_HERO_IMAGE_FK_FID;
	if ( ARC_QB_EVENT_HERO_IMAGE_ATTACHMENT_FID > 0 )     $select[] = ARC_QB_EVENT_HERO_IMAGE_ATTACHMENT_FID;
	if ( ARC_QB_EVENT_HERO_IMAGE_REVIEW_FID > 0 )         $select[] = ARC_QB_EVENT_HERO_IMAGE_REVIEW_FID;

	$body = array(
		'from'   => QB_TABLE_ID,
		'select' => $select,
		'where'  => '{137.EX.true}',
		'sortBy' => array(
			array(
				'fieldId' => 19,
				'order'   => 'ASC',
			),
		),
	);

	return arc_qb_request( $body );
}

// ── Upsert ────────────────────────────────────────────────────────────────────

/**
 * Create or update an `arc_event` WP post from a Quickbase record array.
 *
 * QB field 137 (Show Public) is the strict gatekeeper:
 *  - TRUE  → create or update the WP post as published.
 *  - FALSE → if a WP post already exists, demote it to draft.
 *            If no WP post exists yet, do nothing.
 *
 * Upsert key: _arc_qb_event_id post meta (QB field 3).
 *
 * @param  array     $record  A single QB record array keyed by field ID string.
 * @return int|\WP_Error      WP post ID on success, WP_Error on failure.
 *                            Returns 0 (not an error) if a non-public record has
 *                            no existing WP post — nothing to do.
 */
function arc_qb_upsert_event( array $record ) {

	// ── Extract fields ────────────────────────────────────────────────────────

	$qb_event_id = intval( arc_qb_get_course_field( $record, 3 ) );
	$title       = arc_qb_get_course_field( $record, 19 );
	$public_raw  = arc_qb_get_course_field( $record, 137 );

	// Determine whether this event is publicly listed.
	$is_public = ( true === $public_raw || 'true' === strtolower( (string) $public_raw ) );

	// ── Find existing post by QB event ID ─────────────────────────────────────

	$existing_posts = get_posts(
		array(
			'post_type'   => 'arc_event',
			'meta_key'    => '_arc_qb_event_id',
			'meta_value'  => $qb_event_id,
			'numberposts' => 1,
			'post_status' => 'any',
		)
	);

	$post_id = ! empty( $existing_posts ) ? $existing_posts[0]->ID : 0;

	// ── Gatekeeper: non-public events ─────────────────────────────────────────

	if ( ! $is_public ) {
		if ( $post_id > 0 ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
			return $post_id;
		}
		return 0; // Nothing to do — non-public, no existing post.
	}

	// ── Build WP post array (public events only) ──────────────────────────────

	$post_data = array(
		'post_type'   => 'arc_event',
		'post_title'  => sanitize_text_field( $title ),
		'post_status' => 'publish',
	);

	if ( $post_id > 0 ) {
		$post_data['ID'] = $post_id;
		// post_name is intentionally omitted on update — slug is set once on insert
		// and never changed, so existing URLs remain stable even if the title changes in QB.
		$result = wp_update_post( $post_data, true );
	} else {
		$post_data['post_name'] = sanitize_title( $title );
		$result = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$post_id = $result;

	// ── Update post meta ──────────────────────────────────────────────────────

	update_post_meta( $post_id, '_arc_qb_event_id',                  $qb_event_id );

	// FID 7 — Start Date (QB Date field, returns ISO 8601 e.g. "2026-04-15").
	// Stored as-is for reliable chronological meta_value sort in pre_get_posts.
	update_post_meta( $post_id, '_arc_event_start_date',             sanitize_text_field( arc_qb_get_course_field( $record, 7 ) ) );

	update_post_meta( $post_id, '_arc_event_reg_url',                esc_url_raw( arc_qb_get_course_field( $record, 14 ) ) );
	update_post_meta( $post_id, '_arc_event_venue',                  sanitize_text_field( arc_qb_get_course_field( $record, 29 ) ) );
	$event_dates    = sanitize_text_field( arc_qb_get_course_field( $record, 45 ) );
	$days_of_week   = sanitize_text_field( arc_qb_get_course_field( $record, 413 ) );

	update_post_meta( $post_id, '_arc_event_dates',        $event_dates );
	update_post_meta( $post_id, '_arc_event_days_of_week', $days_of_week );

	// Concatenated schedule display string.
	$parts = array_filter( array( $days_of_week, $event_dates ) );
	update_post_meta( $post_id, '_arc_event_schedule', implode( ' • ', $parts ) );

	update_post_meta( $post_id, '_arc_event_time',                   sanitize_text_field( arc_qb_get_course_field( $record, 89 ) ) );
	update_post_meta( $post_id, '_arc_event_flyer_url',              esc_url_raw( arc_qb_get_course_field( $record, 267 ) ) );
	update_post_meta( $post_id, '_arc_event_instructors_legacy',     sanitize_text_field( arc_qb_get_course_field( $record, 271 ) ) );
	update_post_meta( $post_id, '_arc_event_length',                 sanitize_text_field( arc_qb_get_course_field( $record, 361 ) ) );
	update_post_meta( $post_id, '_arc_event_description',            wp_kses_post( arc_qb_get_course_field( $record, 440 ) ) );
	update_post_meta( $post_id, '_arc_event_instructor_slugs_legacy', sanitize_text_field( arc_qb_get_course_field( $record, 449 ) ) );
	update_post_meta( $post_id, '_arc_event_mode',                   sanitize_text_field( arc_qb_get_course_field( $record, 458 ) ) );
	update_post_meta( $post_id, '_arc_event_image_url',              esc_url_raw( arc_qb_get_course_field( $record, 461 ) ) );

	// Training Cost — allow limited HTML (links, formatting, strike-through).
	$price_allowed = array(
		'a'      => array( 'href' => array(), 'title' => array(), 'target' => array(), 'rel' => array() ),
		'strong' => array(),
		'em'     => array(),
		'span'   => array( 'class' => array(), 'style' => array() ),
		'br'     => array(),
		'strike' => array(),
	);
	update_post_meta( $post_id, '_arc_event_price', wp_kses( arc_qb_get_course_field( $record, 450 ), $price_allowed ) );

	// Checkbox fields — store "1" or "0".
	$is_multiday = arc_qb_get_course_field( $record, 453 );
	update_post_meta( $post_id, '_arc_event_is_multiday', ( $is_multiday && 'false' !== strtolower( (string) $is_multiday ) ) ? '1' : '0' );

	$is_multisession = arc_qb_get_course_field( $record, 454 );
	update_post_meta( $post_id, '_arc_event_is_multisession', ( $is_multisession && 'false' !== strtolower( (string) $is_multisession ) ) ? '1' : '0' );

	// Image Asset lookup fields — hardcoded FIDs (stable QB schema).
	$event_featured_image_url = esc_url_raw( arc_qb_get_course_field( $record, 464 ) );
	update_post_meta( $post_id, '_arc_event_featured_image_url', $event_featured_image_url );
	update_post_meta( $post_id, '_arc_event_hero_image_url',
		esc_url_raw( arc_qb_get_course_field( $record, 466 ) ) );

	// Store event slug for use as filename hint in image sideloads.
	update_post_meta( $post_id, '_arc_event_slug', get_post_field( 'post_name', $post_id ) );

	// ── Featured image — Option A ─────────────────────────────────────────────
	arc_qb_sync_set_featured_image( $post_id, array(
		'attachment_id' => intval( arc_qb_get_course_field( $record, ARC_QB_EVENT_FEATURED_IMAGE_ATTACHMENT_FID ) ),
		'review_status' => sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_EVENT_FEATURED_IMAGE_REVIEW_FID ) ),
		'image_url'     => $event_featured_image_url, // existing FID 464 URL lookup (fallback)
		'ia_record_id'  => intval( arc_qb_get_course_field( $record, ARC_QB_EVENT_FEATURED_IMAGE_FK_FID ) ),
		'ia_filename'   => sanitize_file_name( get_post_meta( $post_id, '_arc_event_slug', true ) . '-featured' ),
		'context_label' => 'Event ' . $qb_event_id . ' featured',
	) );

	// ── Hero image — Option A writeback only (not set_post_thumbnail) ─────────
	$hero_attachment_id = intval( arc_qb_get_course_field( $record, ARC_QB_EVENT_HERO_IMAGE_ATTACHMENT_FID ) );
	$hero_review_status = sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_EVENT_HERO_IMAGE_REVIEW_FID ) );
	$hero_ia_record_id  = intval( arc_qb_get_course_field( $record, ARC_QB_EVENT_HERO_IMAGE_FK_FID ) );
	$hero_image_url     = esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_EVENT_HERO_IMAGE_FID ) );

	if ( 0 === $hero_attachment_id && 'Approved' === $hero_review_status && $hero_ia_record_id > 0 ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$hero_att_id  = 0;
		$hero_pub_url = '';

		// Try QB file first.
		if ( defined( 'ARC_QB_IA_FID_FILE' ) && ARC_QB_IA_FID_FILE > 0 ) {
			$hero_slug = sanitize_file_name( get_post_meta( $post_id, '_arc_event_slug', true ) . '-hero' );
			$file = arc_qb_download_image_file( QB_IMAGE_ASSETS_TABLE_ID, $hero_ia_record_id, ARC_QB_IA_FID_FILE, $hero_slug );
			if ( ! is_wp_error( $file ) ) {
				$moved = wp_handle_sideload( $file, array( 'test_form' => false ) );
				if ( empty( $moved['error'] ) ) {
					$att = wp_insert_attachment( array(
						'post_mime_type' => $moved['type'],
						'post_title'     => sanitize_file_name( pathinfo( $moved['file'], PATHINFO_FILENAME ) ),
						'post_content'   => '',
						'post_status'    => 'inherit',
					), $moved['file'], $post_id );
					if ( ! is_wp_error( $att ) ) {
						wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $moved['file'] ) );
						$hero_att_id  = $att;
						$hero_pub_url = wp_get_attachment_url( $att );
					}
				}
				if ( file_exists( $file['tmp_name'] ) ) { @unlink( $file['tmp_name'] ); } // phpcs:ignore
			}
		}
		// URL fallback.
		if ( 0 === $hero_att_id && $hero_image_url ) {
			$result = media_sideload_image( $hero_image_url, $post_id, null, 'id' );
			if ( ! is_wp_error( $result ) ) {
				$hero_att_id  = $result;
				$hero_pub_url = wp_get_attachment_url( $hero_att_id );
			}
		}
		// Writeback.
		if ( $hero_att_id > 0 ) {
			arc_qb_write_image_attachment_id( $hero_ia_record_id, $hero_att_id, $hero_pub_url );
		}
	}

	return $post_id;
}

// ── Full sync ─────────────────────────────────────────────────────────────────

/**
 * Fetch all QB Training Event records and upsert each one into WordPress.
 *
 * After upserting, runs a ghost-removal pass: any published arc_event post whose
 * QB event ID was not returned by the sync query is demoted to draft. This
 * handles events that were removed or unchecked in QB between syncs.
 * Posts are never deleted — only drafted.
 *
 * @return array  Results summary with 'synced', 'errors', 'ghosted', 'messages'.
 */
function arc_qb_sync_all_events() {
	$records = arc_qb_fetch_all_event_records();

	if ( is_wp_error( $records ) ) {
		return array(
			'synced'   => 0,
			'errors'   => 1,
			'ghosted'  => 0,
			'messages' => array( $records->get_error_message() ),
		);
	}

	$synced     = 0;
	$errors     = 0;
	$messages   = array();
	$synced_ids = array();

	foreach ( $records as $record ) {
		$qb_id = intval( arc_qb_get_course_field( $record, 3 ) );
		if ( $qb_id > 0 ) {
			$synced_ids[] = $qb_id;
		}

		$result = arc_qb_upsert_event( $record );
		if ( is_wp_error( $result ) ) {
			$errors++;
			$messages[] = $result->get_error_message();
			error_log( '[arc-qb-sync] Event upsert failed for QB record ' . $qb_id . ': ' . $result->get_error_message() );
		} else {
			$synced++;
		}
	}

	// ── Ghost removal ─────────────────────────────────────────────────────────
	$ghosted          = 0;
	$published_events = get_posts( array(
		'post_type'   => 'arc_event',
		'post_status' => 'publish',
		'numberposts' => -1,
		'meta_key'    => '_arc_qb_event_id',
		'fields'      => 'ids',
	) );

	foreach ( $published_events as $wp_post_id ) {
		$post_qb_id = intval( get_post_meta( $wp_post_id, '_arc_qb_event_id', true ) );
		if ( $post_qb_id > 0 && ! in_array( $post_qb_id, $synced_ids, true ) ) {
			wp_update_post( array(
				'ID'          => $wp_post_id,
				'post_status' => 'draft',
			) );
			$ghosted++;
		}
	}

	return array(
		'synced'   => $synced,
		'errors'   => $errors,
		'ghosted'  => $ghosted,
		'messages' => $messages,
	);
}

// ── Admin page ────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'arc_qb_add_event_sync_page' );

/**
 * Register the QB Event Sync submenu under the Events CPT admin menu.
 */
function arc_qb_add_event_sync_page() {
	add_submenu_page(
		'edit.php?post_type=arc_event',
		'QB Event Sync',
		'QB Event Sync',
		'manage_options',
		'arc-qb-event-sync',
		'arc_qb_render_event_sync_page'
	);
}

/**
 * Preview what a full event sync would do — reads QB and WP, writes nothing.
 *
 * @return array|WP_Error  Keys: total, new, update, ghost. WP_Error on QB failure.
 */
function arc_qb_preview_event_sync() {
	$records = arc_qb_fetch_all_event_records();
	if ( is_wp_error( $records ) ) {
		return $records;
	}

	$qb_ids = array();
	foreach ( $records as $record ) {
		$id = intval( arc_qb_get_course_field( $record, 3 ) );
		if ( $id > 0 ) {
			$qb_ids[] = $id;
		}
	}

	// Map existing WP arc_event posts by QB event ID.
	$existing_posts = get_posts( array(
		'post_type'   => 'arc_event',
		'post_status' => array( 'publish', 'draft' ),
		'numberposts' => -1,
		'meta_key'    => '_arc_qb_event_id',
		'fields'      => 'ids',
	) );

	$existing_qb_ids = array();
	foreach ( $existing_posts as $post_id ) {
		$qb_id = intval( get_post_meta( $post_id, '_arc_qb_event_id', true ) );
		if ( $qb_id > 0 ) {
			$existing_qb_ids[] = $qb_id;
		}
	}

	$new    = count( array_diff( $qb_ids, $existing_qb_ids ) );
	$update = count( array_intersect( $qb_ids, $existing_qb_ids ) );

	// Ghost: published posts whose QB ID is not in this sync result.
	$published_posts = get_posts( array(
		'post_type'   => 'arc_event',
		'post_status' => 'publish',
		'numberposts' => -1,
		'meta_key'    => '_arc_qb_event_id',
		'fields'      => 'ids',
	) );

	$ghost = 0;
	foreach ( $published_posts as $post_id ) {
		$qb_id = intval( get_post_meta( $post_id, '_arc_qb_event_id', true ) );
		if ( $qb_id > 0 && ! in_array( $qb_id, $qb_ids, true ) ) {
			$ghost++;
		}
	}

	return array(
		'total'  => count( $records ),
		'new'    => $new,
		'update' => $update,
		'ghost'  => $ghost,
	);
}

/**
 * Handle form POSTs and render the QB Event Sync page.
 */
function arc_qb_render_event_sync_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$sync_result    = null;
	$preview_result = null;

	if ( isset( $_POST['arc_qb_preview_events'] ) &&
		check_admin_referer( 'arc_qb_preview_events', 'arc_qb_event_preview_nonce' ) ) {
		$preview_result = arc_qb_preview_event_sync();
	}

	if ( isset( $_POST['arc_qb_sync_all_events'] ) &&
		check_admin_referer( 'arc_qb_sync_all_events', 'arc_qb_event_sync_nonce' ) ) {
		$sync_result = arc_qb_sync_all_events();
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'QB Event Sync', 'arc-qb-sync' ); ?></h1>
		<p><?php esc_html_e( 'Pulls all publicly listed events (Show Public = checked) from the Quickbase Training Events table into WordPress as event posts. Non-public events are never imported. Use this for initial setup, after bulk changes in Quickbase, or to recover from a missed webhook.', 'arc-qb-sync' ); ?></p>

		<?php if ( null !== $preview_result ) : ?>
			<?php if ( is_wp_error( $preview_result ) ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $preview_result->get_error_message() ); ?></p>
				</div>
			<?php else : ?>
				<div class="notice notice-info is-dismissible">
					<p><strong><?php esc_html_e( 'Preview — no changes made.', 'arc-qb-sync' ); ?></strong></p>
					<ul>
						<li><?php printf( esc_html__( 'QB records returned: %d', 'arc-qb-sync' ), $preview_result['total'] ); ?></li>
						<li><?php printf( esc_html__( 'New posts to create: %d', 'arc-qb-sync' ), $preview_result['new'] ); ?></li>
						<li><?php printf( esc_html__( 'Existing posts to update: %d', 'arc-qb-sync' ), $preview_result['update'] ); ?></li>
						<li><?php printf( esc_html__( 'Published posts to draft (removed from QB): %d', 'arc-qb-sync' ), $preview_result['ghost'] ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( null !== $sync_result ) : ?>
			<?php if ( 0 === $sync_result['errors'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php printf(
						esc_html__( 'Sync complete. %d events synced, %d drafted (removed from QB public listing).', 'arc-qb-sync' ),
						$sync_result['synced'],
						$sync_result['ghosted']
					); ?></p>
				</div>
			<?php else : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php printf( esc_html__( 'Sync finished with errors. %d synced, %d errors, %d drafted.', 'arc-qb-sync' ), $sync_result['synced'], $sync_result['errors'], $sync_result['ghosted'] ); ?></p>
					<?php if ( ! empty( $sync_result['messages'] ) ) : ?>
						<ul>
							<?php foreach ( $sync_result['messages'] as $msg ) : ?>
								<li><?php echo esc_html( $msg ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<form method="post" action="" style="display:inline-block; margin-right: 8px;">
			<?php wp_nonce_field( 'arc_qb_preview_events', 'arc_qb_event_preview_nonce' ); ?>
			<?php submit_button( __( 'Preview Sync', 'arc-qb-sync' ), 'secondary', 'arc_qb_preview_events', false ); ?>
		</form>

		<form method="post" action="" style="display:inline-block;">
			<?php wp_nonce_field( 'arc_qb_sync_all_events', 'arc_qb_event_sync_nonce' ); ?>
			<?php submit_button( __( 'Sync All Events Now', 'arc-qb-sync' ), 'primary', 'arc_qb_sync_all_events', false ); ?>
		</form>
	</div>
	<?php
}
