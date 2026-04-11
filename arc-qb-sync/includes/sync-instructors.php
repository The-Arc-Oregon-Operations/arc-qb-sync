<?php
/**
 * Instructors sync engine.
 *
 * Handles syncing Quickbase Instructors table records into WordPress as
 * `instructor` Custom Post Type posts. Provides:
 *
 *  - arc_qb_fetch_all_instructor_records() — fetch all active QB instructor records
 *  - arc_qb_upsert_instructor( $record )   — create or update a WP post
 *  - arc_qb_sync_all_instructors()         — full sync (all records)
 *  - WP Admin settings page with "Sync All Instructors Now" button
 *
 * All functions check that QB_INSTRUCTORS_TABLE_ID is defined before making
 * any API calls.
 *
 * QB field mapping (v3.0.0):
 *   ARC_QB_INSTRUCTOR_FID_RECORD_ID    (3)  → _arc_qb_instructor_id (sync key)
 *   ARC_QB_INSTRUCTOR_FID_NAME         (6)  → post_title
 *   ARC_QB_INSTRUCTOR_FID_FIRST_NAME   (7)  → _arc_instructor_first_name
 *   ARC_QB_INSTRUCTOR_FID_LAST_NAME    (8)  → _arc_instructor_last_name
 *   ARC_QB_INSTRUCTOR_FID_CONTACT_URL  (9)  → _arc_instructor_contact_url
 *   ARC_QB_INSTRUCTOR_FID_CREDENTIALS  (10) → _arc_instructor_credentials
 *   ARC_QB_INSTRUCTOR_FID_BIO          (11) → _arc_instructor_bio
 *   ARC_QB_INSTRUCTOR_FID_TITLE        (12) → _arc_instructor_title
 *   ARC_QB_INSTRUCTOR_FID_ORGANIZATION (13) → _arc_instructor_organization
 *   ARC_QB_INSTRUCTOR_PROFILE_FID      (15) → _arc_instructor_headshot_url
 *   ARC_QB_INSTRUCTOR_FID_SLUG         (27) → _arc_instructor_slug + post_name
 *   ARC_QB_INSTRUCTOR_FID_ACTIVE       (28) → post_status: publish (TRUE) / draft (FALSE)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Instructors table field ID constants ──────────────────────────────────────
// Values confirmed from QB export 2026-04-11.

if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_RECORD_ID' ) )    define( 'ARC_QB_INSTRUCTOR_FID_RECORD_ID',    3  ); // Record ID#
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_NAME' ) )         define( 'ARC_QB_INSTRUCTOR_FID_NAME',         6  ); // Instructor Name
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_FIRST_NAME' ) )   define( 'ARC_QB_INSTRUCTOR_FID_FIRST_NAME',   7  ); // First Name
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_LAST_NAME' ) )    define( 'ARC_QB_INSTRUCTOR_FID_LAST_NAME',    8  ); // Last Name
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_CONTACT_URL' ) )  define( 'ARC_QB_INSTRUCTOR_FID_CONTACT_URL',  9  ); // Contact Me URL
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_CREDENTIALS' ) )  define( 'ARC_QB_INSTRUCTOR_FID_CREDENTIALS',  10 ); // Credentials
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_BIO' ) )          define( 'ARC_QB_INSTRUCTOR_FID_BIO',          11 ); // Bio (multi-line text)
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_TITLE' ) )        define( 'ARC_QB_INSTRUCTOR_FID_TITLE',        12 ); // Title/Position
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_ORGANIZATION' ) ) define( 'ARC_QB_INSTRUCTOR_FID_ORGANIZATION', 13 ); // Organization
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_SLUG' ) )         define( 'ARC_QB_INSTRUCTOR_FID_SLUG',         27 ); // slug (WP post slug)
if ( ! defined( 'ARC_QB_INSTRUCTOR_FID_ACTIVE' ) )       define( 'ARC_QB_INSTRUCTOR_FID_ACTIVE',       28 ); // Active (checkbox — publish gate)
// ARC_QB_INSTRUCTOR_PROFILE_FID (15) — Headshot URL lookup — defined in wp-config.php.

// ── QB fetch helpers ──────────────────────────────────────────────────────────

/**
 * Fetch active Instructor records from Quickbase (Active field = TRUE only).
 *
 * @return array|\WP_Error  Array of record arrays on success.
 */
function arc_qb_fetch_all_instructor_records() {
	if ( ! defined( 'QB_INSTRUCTORS_TABLE_ID' ) || '' === QB_INSTRUCTORS_TABLE_ID ) {
		return new WP_Error(
			'arc_qb_missing_config',
			'QB_INSTRUCTORS_TABLE_ID is not defined in wp-config.php.'
		);
	}

	// All FIDs are confirmed non-zero — no need to filter.
	$select = array(
		ARC_QB_INSTRUCTOR_FID_RECORD_ID,    // 3
		ARC_QB_INSTRUCTOR_FID_NAME,         // 6
		ARC_QB_INSTRUCTOR_FID_FIRST_NAME,   // 7
		ARC_QB_INSTRUCTOR_FID_LAST_NAME,    // 8
		ARC_QB_INSTRUCTOR_FID_CONTACT_URL,  // 9
		ARC_QB_INSTRUCTOR_FID_CREDENTIALS,  // 10
		ARC_QB_INSTRUCTOR_FID_BIO,          // 11
		ARC_QB_INSTRUCTOR_FID_TITLE,        // 12
		ARC_QB_INSTRUCTOR_FID_ORGANIZATION, // 13
		ARC_QB_INSTRUCTOR_PROFILE_FID,      // 15 — Headshot URL lookup
		ARC_QB_INSTRUCTOR_FID_SLUG,         // 27
		ARC_QB_INSTRUCTOR_FID_ACTIVE,       // 28
	);

	$body = array(
		'from'   => QB_INSTRUCTORS_TABLE_ID,
		'select' => $select,
		'where'  => sprintf( '{%d.EX.true}', ARC_QB_INSTRUCTOR_FID_ACTIVE ),
		'sortBy' => array(
			array(
				'fieldId' => ARC_QB_INSTRUCTOR_FID_NAME,
				'order'   => 'ASC',
			),
		),
	);

	return arc_qb_request( $body );
}

// ── Upsert ────────────────────────────────────────────────────────────────────

/**
 * Create or update an `instructor` WP post from a Quickbase record array.
 *
 * QB Active field (FID 28) is the strict gatekeeper:
 *  - TRUE  → create or update the WP post as published.
 *  - FALSE → if a WP post already exists, demote it to draft.
 *            If no WP post exists yet, do nothing.
 *
 * Upsert key: _arc_qb_instructor_id post meta (QB field 3).
 *
 * @param  array     $record  A single QB record array keyed by field ID string.
 * @return int|\WP_Error      WP post ID on success, WP_Error on failure.
 *                            Returns 0 if an inactive record has no existing WP post.
 */
function arc_qb_upsert_instructor( array $record ) {
	if ( ! defined( 'QB_INSTRUCTORS_TABLE_ID' ) || '' === QB_INSTRUCTORS_TABLE_ID ) {
		return new WP_Error(
			'arc_qb_missing_config',
			'QB_INSTRUCTORS_TABLE_ID is not defined in wp-config.php.'
		);
	}

	// ── Extract fields ────────────────────────────────────────────────────────

	$qb_instructor_id = intval( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_RECORD_ID ) );
	$name             = arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_NAME );
	$active_raw       = arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_ACTIVE );
	$slug_raw         = arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_SLUG );

	$is_active = ( true === $active_raw || 'true' === strtolower( (string) $active_raw ) );

	// Derive post_name: use QB slug field if set; fall back to title derivation.
	$post_name = ! empty( $slug_raw ) ? sanitize_title( $slug_raw ) : sanitize_title( $name );

	// ── Find existing post by QB instructor ID ────────────────────────────────

	$existing_posts = get_posts(
		array(
			'post_type'   => 'instructor',
			'meta_key'    => '_arc_qb_instructor_id',
			'meta_value'  => $qb_instructor_id,
			'numberposts' => 1,
			'post_status' => 'any',
		)
	);

	$post_id = ! empty( $existing_posts ) ? $existing_posts[0]->ID : 0;

	// ── Gatekeeper: inactive instructors ─────────────────────────────────────

	if ( ! $is_active ) {
		if ( $post_id > 0 ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
			return $post_id;
		}
		return 0; // Nothing to do — inactive, no existing post.
	}

	// ── Build WP post array (active instructors only) ─────────────────────────

	$post_data = array(
		'post_type'   => 'instructor',
		'post_title'  => sanitize_text_field( $name ),
		'post_status' => 'publish',
		'post_name'   => $post_name,
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

	update_post_meta( $post_id, '_arc_qb_instructor_id',    $qb_instructor_id );
	update_post_meta( $post_id, '_arc_instructor_first_name',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_FIRST_NAME ) ) );
	update_post_meta( $post_id, '_arc_instructor_last_name',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_LAST_NAME ) ) );
	update_post_meta( $post_id, '_arc_instructor_contact_url',
		esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_CONTACT_URL ) ) );
	update_post_meta( $post_id, '_arc_instructor_credentials',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_CREDENTIALS ) ) );
	update_post_meta( $post_id, '_arc_instructor_bio',
		wp_kses_post( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_BIO ) ) );
	update_post_meta( $post_id, '_arc_instructor_title',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_TITLE ) ) );
	update_post_meta( $post_id, '_arc_instructor_organization',
		sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_ORGANIZATION ) ) );
	update_post_meta( $post_id, '_arc_instructor_headshot_url',
		esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_PROFILE_FID ) ) );
	update_post_meta( $post_id, '_arc_instructor_slug', $post_name );

	return $post_id;
}

// ── Full sync ─────────────────────────────────────────────────────────────────

/**
 * Fetch all active QB Instructor records and upsert each one into WordPress.
 *
 * After upserting, runs a ghost-removal pass: any published instructor post whose
 * QB instructor ID was not returned by the sync query is demoted to draft.
 * Posts are never deleted — only drafted.
 *
 * @return array  Results summary with 'synced', 'errors', 'ghosted', 'messages'.
 */
function arc_qb_sync_all_instructors() {
	$records = arc_qb_fetch_all_instructor_records();

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
		$qb_id = intval( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_RECORD_ID ) );
		if ( $qb_id > 0 ) {
			$synced_ids[] = $qb_id;
		}

		$result = arc_qb_upsert_instructor( $record );
		if ( is_wp_error( $result ) ) {
			$errors++;
			$messages[] = $result->get_error_message();
		} else {
			$synced++;
		}
	}

	// ── Ghost removal ─────────────────────────────────────────────────────────
	$ghosted               = 0;
	$published_instructors = get_posts( array(
		'post_type'   => 'instructor',
		'post_status' => 'publish',
		'numberposts' => -1,
		'meta_key'    => '_arc_qb_instructor_id',
		'fields'      => 'ids',
	) );

	foreach ( $published_instructors as $wp_post_id ) {
		$post_qb_id = intval( get_post_meta( $wp_post_id, '_arc_qb_instructor_id', true ) );
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

add_action( 'admin_menu', 'arc_qb_add_instructor_sync_settings_page' );

/**
 * Register the Arc Instructor Sync settings page under WP Admin → Settings.
 */
function arc_qb_add_instructor_sync_settings_page() {
	add_options_page(
		'Arc Instructor Sync',
		'Arc Instructor Sync',
		'manage_options',
		'arc-instructor-sync',
		'arc_qb_render_instructor_sync_page'
	);
}

/**
 * Handle the "Sync All Instructors Now" form POST and render the settings page.
 */
function arc_qb_render_instructor_sync_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$sync_result = null;

	if (
		isset( $_POST['arc_qb_sync_all_instructors'] ) &&
		check_admin_referer( 'arc_qb_sync_all_instructors', 'arc_qb_instructor_sync_nonce' )
	) {
		$sync_result = arc_qb_sync_all_instructors();
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Arc Instructor Sync', 'arc-qb-sync' ); ?></h1>

		<?php if ( null !== $sync_result ) : ?>
			<?php if ( 0 === $sync_result['errors'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php
						printf(
							esc_html__( 'Sync complete. %d instructors synced, %d drafted (deactivated in QB).', 'arc-qb-sync' ),
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

		<h2><?php esc_html_e( 'Instructor Profiles Sync', 'arc-qb-sync' ); ?></h2>
		<p><?php esc_html_e( 'Run a full sync to pull all active instructors (Active = checked) from the Quickbase Instructors table into WordPress as instructor posts. Inactive instructors are never imported. Use this for initial setup or after bulk changes in Quickbase.', 'arc-qb-sync' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'arc_qb_sync_all_instructors', 'arc_qb_instructor_sync_nonce' ); ?>
			<?php submit_button( __( 'Sync All Instructors Now', 'arc-qb-sync' ), 'primary', 'arc_qb_sync_all_instructors' ); ?>
		</form>
	</div>
	<?php
}
