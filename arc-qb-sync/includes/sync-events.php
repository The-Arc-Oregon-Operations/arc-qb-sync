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
 *   14  → _arc_event_reg_url
 *   19  → post_title
 *   29  → _arc_event_venue
 *   45  → _arc_event_dates
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
 *   ARC_QB_EVENT_FEATURED_IMAGE_FID (464) → _arc_event_featured_image_url
 *   ARC_QB_EVENT_HERO_IMAGE_FID     (466) → _arc_event_hero_image_url
 *   ARC_QB_EVENT_INSTRUCTOR1_NAME_FID        (482) → _arc_event_instructor1_name
 *   ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID    (483) → _arc_event_instructor1_headshot_url
 *   ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID (484) → _arc_event_instructor1_headshot_alt
 *   ARC_QB_EVENT_INSTRUCTOR2_NAME_FID        (486) → _arc_event_instructor2_name
 *   ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID    (487) → _arc_event_instructor2_headshot_url
 *   ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID (494) → _arc_event_instructor2_headshot_alt
 *   ARC_QB_EVENT_INSTRUCTOR3_NAME_FID        (491) → _arc_event_instructor3_name
 *   ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID    (492) → _arc_event_instructor3_headshot_url
 *   ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID (493) → _arc_event_instructor3_headshot_alt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	$select = array( 3, 14, 19, 29, 45, 89, 137, 267, 271, 361, 413, 440, 449, 450, 453, 454, 458, 461 );

	// Image lookup FIDs — all non-zero (constants are fully defined).
	$select[] = ARC_QB_EVENT_FEATURED_IMAGE_FID; // 464
	$select[] = ARC_QB_EVENT_HERO_IMAGE_FID;     // 466

	// Instructor slot FIDs — all non-zero (constants are fully defined).
	$select[] = ARC_QB_EVENT_INSTRUCTOR1_NAME_FID;          // 482
	$select[] = ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID;      // 483
	$select[] = ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID;  // 484
	$select[] = ARC_QB_EVENT_INSTRUCTOR2_NAME_FID;          // 486
	$select[] = ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID;      // 487
	$select[] = ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID;  // 494
	$select[] = ARC_QB_EVENT_INSTRUCTOR3_NAME_FID;          // 491
	$select[] = ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID;      // 492
	$select[] = ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID;  // 493

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

	$post_slug = sanitize_title( $title );

	$post_data = array(
		'post_type'   => 'arc_event',
		'post_title'  => sanitize_text_field( $title ),
		'post_status' => 'publish',
		'post_name'   => $post_slug,
	);

	if ( $post_id > 0 ) {
		$post_data['ID'] = $post_id;
		$result          = wp_update_post( $post_data, true );
	} else {
		$result = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$post_id = $result;

	// ── Update post meta ──────────────────────────────────────────────────────

	update_post_meta( $post_id, '_arc_qb_event_id',                  $qb_event_id );
	update_post_meta( $post_id, '_arc_event_reg_url',                esc_url_raw( arc_qb_get_course_field( $record, 14 ) ) );
	update_post_meta( $post_id, '_arc_event_venue',                  sanitize_text_field( arc_qb_get_course_field( $record, 29 ) ) );
	update_post_meta( $post_id, '_arc_event_dates',                  sanitize_text_field( arc_qb_get_course_field( $record, 45 ) ) );
	update_post_meta( $post_id, '_arc_event_time',                   sanitize_text_field( arc_qb_get_course_field( $record, 89 ) ) );
	update_post_meta( $post_id, '_arc_event_flyer_url',              esc_url_raw( arc_qb_get_course_field( $record, 267 ) ) );
	update_post_meta( $post_id, '_arc_event_instructors_legacy',     sanitize_text_field( arc_qb_get_course_field( $record, 271 ) ) );
	update_post_meta( $post_id, '_arc_event_length',                 sanitize_text_field( arc_qb_get_course_field( $record, 361 ) ) );
	update_post_meta( $post_id, '_arc_event_days_of_week',           sanitize_text_field( arc_qb_get_course_field( $record, 413 ) ) );
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

	// Image Asset lookup fields.
	update_post_meta( $post_id, '_arc_event_featured_image_url',
		esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_EVENT_FEATURED_IMAGE_FID ) ) );
	update_post_meta( $post_id, '_arc_event_hero_image_url',
		esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_EVENT_HERO_IMAGE_FID ) ) );

	// Instructor slot 1.
	update_post_meta( $post_id, '_arc_event_instructor1_name',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR1_NAME_FID ) ) );
	update_post_meta( $post_id, '_arc_event_instructor1_headshot_url',
		esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_FID ) ) );
	update_post_meta( $post_id, '_arc_event_instructor1_headshot_alt',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR1_HEADSHOT_ALT_FID ) ) );

	// Instructor slot 2.
	update_post_meta( $post_id, '_arc_event_instructor2_name',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR2_NAME_FID ) ) );
	update_post_meta( $post_id, '_arc_event_instructor2_headshot_url',
		esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_FID ) ) );
	update_post_meta( $post_id, '_arc_event_instructor2_headshot_alt',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR2_HEADSHOT_ALT_FID ) ) );

	// Instructor slot 3.
	update_post_meta( $post_id, '_arc_event_instructor3_name',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR3_NAME_FID ) ) );
	update_post_meta( $post_id, '_arc_event_instructor3_headshot_url',
		esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_FID ) ) );
	update_post_meta( $post_id, '_arc_event_instructor3_headshot_alt',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_EVENT_INSTRUCTOR3_HEADSHOT_ALT_FID ) ) );

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

// ── Admin settings page ───────────────────────────────────────────────────────

add_action( 'admin_menu', 'arc_qb_add_event_sync_settings_page' );

/**
 * Register the Arc Event Sync settings page under WP Admin → Settings.
 */
function arc_qb_add_event_sync_settings_page() {
	add_options_page(
		'Arc Event Sync',
		'Arc Event Sync',
		'manage_options',
		'arc-event-sync',
		'arc_qb_render_event_sync_page'
	);
}

/**
 * Handle the "Sync All Events Now" form POST and render the settings page.
 */
function arc_qb_render_event_sync_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$sync_result = null;

	if (
		isset( $_POST['arc_qb_sync_all_events'] ) &&
		check_admin_referer( 'arc_qb_sync_all_events', 'arc_qb_event_sync_nonce' )
	) {
		$sync_result = arc_qb_sync_all_events();
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Arc Event Sync', 'arc-qb-sync' ); ?></h1>

		<?php if ( null !== $sync_result ) : ?>
			<?php if ( 0 === $sync_result['errors'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php
						printf(
							esc_html__( 'Sync complete. %d events synced, %d drafted (removed from QB public listing).', 'arc-qb-sync' ),
							$sync_result['synced'],
							$sync_result['ghosted']
						);
					?></p>
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

		<h2><?php esc_html_e( 'Training Events Sync', 'arc-qb-sync' ); ?></h2>
		<p><?php esc_html_e( 'Run a full sync to pull all publicly listed events (Show Public = checked) from the Quickbase Training Events table into WordPress as arc_event posts. Non-public events are never imported. Use this for initial setup, after bulk changes in Quickbase, or to recover from a missed webhook.', 'arc-qb-sync' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'arc_qb_sync_all_events', 'arc_qb_event_sync_nonce' ); ?>
			<?php submit_button( __( 'Sync All Events Now', 'arc-qb-sync' ), 'primary', 'arc_qb_sync_all_events' ); ?>
		</form>
	</div>
	<?php
}
