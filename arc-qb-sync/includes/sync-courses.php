<?php
/**
 * Course sync engine.
 *
 * Handles syncing Quickbase Course Catalog records into WordPress as `course`
 * Custom Post Type posts. Provides:
 *
 *  - arc_qb_fetch_course_record( $record_id ) — fetch one QB record by ID
 *  - arc_qb_fetch_all_course_records()        — fetch all QB course records
 *  - arc_qb_upsert_course( $record )          — create or update a WP post
 *  - arc_qb_sync_all_courses()                — full sync (all records)
 *  - WP Admin settings page with "Sync All Courses Now" button
 *  - [arc_course_filter_pills] shortcode
 *
 * QB field mapping (v2.2.0):
 *   3  → _arc_qb_record_id (sync key) + post lookup
 *   6  → post_title
 *   14 → _arc_course_length_ms (raw ms) + _arc_course_length (formatted)
 *   20 → _arc_course_hours (numeric hours)
 *   36 → post_status: publish (TRUE) / draft (FALSE)
 *   39 → _arc_course_base_rate
 *   40 → _arc_course_delivery_method
 *   43 → _arc_course_category
 *   46 → post_excerpt
 *   50 → _arc_course_target_audience
 *   56 → course_tag taxonomy terms + _course_tag_slugs (comma-separated slugs)
 *   62 → _arc_course_learning_objectives_html (primary display field)
 *   84 → _arc_course_details_url
 *   85 → _arc_course_learning_objectives (secondary)
 *   88 → _arc_course_image_url
 *   89 → _arc_course_attribution
 *        Note: FID 89 in the Events table is "Event Time" — separate table,
 *        independent FID numbering.
 *   90 → _arc_course_use_attribution (checkbox — stored as "1" or "0")
 *   92 → _arc_course_slug → also post_name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── QB fetch helpers ──────────────────────────────────────────────────────────

/**
 * Fetch a single Course Catalog record from Quickbase by its Record ID#.
 *
 * @param  int              $record_id  QB Record ID (field 3).
 * @return array|\WP_Error             Single record array on success.
 */
function arc_qb_fetch_course_record( $record_id ) {
	if ( ! defined( 'QB_COURSES_TABLE_ID' ) ) {
		return new WP_Error(
			'arc_qb_missing_config',
			'QB_COURSES_TABLE_ID is not defined in wp-config.php.'
		);
	}

	$select = array( 3, 6, 14, 20, 36, 39, 40, 43, 46, 50, 56, 62, 84, 85, 88, 89, 90, 92 );

	$select[] = 94; // Featured Image URL [lookup]
	$select[] = 96; // Hero Image URL [lookup]

	$body = array(
		'from'   => QB_COURSES_TABLE_ID,
		'select' => $select,
		'where'  => sprintf( '{3.EX.%d}', intval( $record_id ) ),
		'options' => array( 'top' => 1 ),
	);

	$records = arc_qb_request( $body );

	if ( is_wp_error( $records ) ) {
		return $records;
	}

	$record = reset( $records );

	if ( ! is_array( $record ) ) {
		return new WP_Error( 'arc_qb_no_record', sprintf( 'No QB record found for record_id %d.', $record_id ) );
	}

	return $record;
}

/**
 * Fetch publicly listed Course Catalog records from Quickbase (field 36 = TRUE only).
 *
 * QB is the strict gatekeeper. Non-public courses never enter WordPress.
 * If a course is unchecked in QB, the incremental sync demotes the existing
 * WP post to draft — it is never created here.
 *
 * Note: The QB Records Query API returns up to 10,000 records per call.
 * Pagination would be required if the catalog ever exceeds that threshold.
 *
 * @return array|\WP_Error  Array of record arrays on success.
 */
function arc_qb_fetch_all_course_records() {
	if ( ! defined( 'QB_COURSES_TABLE_ID' ) ) {
		return new WP_Error(
			'arc_qb_missing_config',
			'QB_COURSES_TABLE_ID is not defined in wp-config.php.'
		);
	}

	$select = array( 3, 6, 14, 20, 36, 39, 40, 43, 46, 50, 56, 62, 84, 85, 88, 89, 90, 92 );

	$select[] = 94; // Featured Image URL [lookup]
	$select[] = 96; // Hero Image URL [lookup]

	$body = array(
		'from'   => QB_COURSES_TABLE_ID,
		'select' => $select,
		'where'  => '{36.EX.true}',
		'sortBy' => array(
			array(
				'fieldId' => 6,
				'order'   => 'ASC',
			),
		),
	);

	return arc_qb_request( $body );
}

// ── Upsert ────────────────────────────────────────────────────────────────────

/**
 * Create or update a `course` WP post from a Quickbase record array.
 *
 * QB field 36 (Public Listing) is the strict gatekeeper:
 *  - TRUE  → create or update the WP post as published.
 *  - FALSE → if a WP post already exists, demote it to draft.
 *            If no WP post exists yet, do nothing (never create a non-public post).
 *
 * Upsert key: _arc_qb_record_id post meta (QB field 3).
 *
 * @param  array     $record  A single QB record array keyed by field ID string.
 * @return int|\WP_Error      WP post ID on success, WP_Error on failure.
 *                            Returns 0 (not an error) if a non-public record has
 *                            no existing WP post — nothing to do.
 */
function arc_qb_upsert_course( array $record ) {

	// ── Extract fields ────────────────────────────────────────────────────────

	$qb_record_id  = intval( arc_qb_get_course_field( $record, 3 ) );
	$title         = arc_qb_get_course_field( $record, 6 );
	$excerpt       = arc_qb_get_course_field( $record, 46 );
	$public_raw    = arc_qb_get_course_field( $record, 36 );
	$length_ms_raw = arc_qb_get_course_field( $record, 14 );

	// FID 92 — Slug for Website drives post_name.
	$course_slug = sanitize_title( arc_qb_get_course_field( $record, 92 ) );

	// Determine whether this course is publicly listed.
	// QB booleans come back as PHP true/false after JSON decode, but may also
	// arrive as the string "true" depending on the field type in some responses.
	$is_public = ( true === $public_raw || 'true' === strtolower( (string) $public_raw ) );

	// ── Find existing post by QB record ID ────────────────────────────────────

	$existing_posts = get_posts(
		array(
			'post_type'   => 'course',
			'meta_key'    => '_arc_qb_record_id',
			'meta_value'  => $qb_record_id,
			'numberposts' => 1,
			'post_status' => 'any',
		)
	);

	$post_id = ! empty( $existing_posts ) ? $existing_posts[0]->ID : 0;

	// ── Gatekeeper: non-public courses ───────────────────────────────────────
	//
	// If Public Listing is FALSE and no WP post exists, do nothing.
	// QB is the source of truth — non-public courses never enter WordPress.
	//
	// If Public Listing is FALSE and a WP post already exists (it was previously
	// public and has now been unchecked), demote it to draft and stop there.
	// No meta update needed — content hasn't changed, only visibility.

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

	// ── Build WP post array (public courses only) ─────────────────────────────

	$post_data = array(
		'post_type'    => 'course',
		'post_title'   => sanitize_text_field( $title ),
		'post_excerpt' => wp_kses_post( $excerpt ),
		'post_status'  => 'publish',
		'post_name'    => $course_slug,
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

	update_post_meta( $post_id, '_arc_qb_record_id',                    $qb_record_id );
	update_post_meta( $post_id, '_arc_course_length_ms',                $length_ms_raw );
	update_post_meta( $post_id, '_arc_course_length',                   arc_qb_format_duration( $length_ms_raw ) );
	update_post_meta( $post_id, '_arc_course_base_rate',                sanitize_text_field( arc_qb_get_course_field( $record, 39 ) ) );
	update_post_meta( $post_id, '_arc_course_delivery_method',          sanitize_text_field( arc_qb_get_course_field( $record, 40 ) ) );
	update_post_meta( $post_id, '_arc_course_category',                 sanitize_text_field( arc_qb_get_course_field( $record, 43 ) ) );
	update_post_meta( $post_id, '_arc_course_target_audience',          wp_kses_post( arc_qb_get_course_field( $record, 50 ) ) );
	update_post_meta( $post_id, '_arc_course_learning_objectives_html', wp_kses_post( arc_qb_get_course_field( $record, 62 ) ) );
	update_post_meta( $post_id, '_arc_course_learning_objectives',      wp_kses_post( arc_qb_get_course_field( $record, 85 ) ) );
	update_post_meta( $post_id, '_arc_course_image_url',                esc_url_raw( arc_qb_get_course_field( $record, 88 ) ) );

	// Image Asset lookup FIDs — hardcoded (stable QB schema).
	update_post_meta( $post_id, '_arc_course_featured_image_url',
		esc_url_raw( arc_qb_get_course_field( $record, 94 ) ) );
	update_post_meta( $post_id, '_arc_course_hero_image_url',
		esc_url_raw( arc_qb_get_course_field( $record, 96 ) ) );

	// FID 20 — Length Num (numeric hours value)
	update_post_meta( $post_id, '_arc_course_hours', sanitize_text_field( arc_qb_get_course_field( $record, 20 ) ) );

	// FID 84 — Link to Course Overview Page
	update_post_meta( $post_id, '_arc_course_details_url', esc_url_raw( arc_qb_get_course_field( $record, 84 ) ) );

	// FID 89 — Attribution
	// Note: FID 89 in the Events table is "Event Time" — separate table, independent FID numbering.
	update_post_meta( $post_id, '_arc_course_attribution', sanitize_text_field( arc_qb_get_course_field( $record, 89 ) ) );

	// FID 90 — Use Attribution (checkbox)
	$use_attribution = arc_qb_get_course_field( $record, 90 );
	update_post_meta( $post_id, '_arc_course_use_attribution', ( $use_attribution && 'false' !== strtolower( (string) $use_attribution ) ) ? '1' : '0' );

	// FID 92 — Slug for Website (also drives post_name — see $post_data above)
	update_post_meta( $post_id, '_arc_course_slug', $course_slug );

	// ── Sync tags ─────────────────────────────────────────────────────────────

	$raw_tags  = arc_qb_get_course_field( $record, 56 );
	$tag_names = arc_qb_parse_tags( $raw_tags );

	// Sync to course_tag taxonomy terms.
	wp_set_object_terms( $post_id, $tag_names, 'course_tag' );

	// Build comma-separated slug string for Elementor data-tags attribute.
	$tag_slugs = implode( ',', array_map( 'sanitize_title', $tag_names ) );
	update_post_meta( $post_id, '_course_tag_slugs', $tag_slugs );

	return $post_id;
}

// ── Full sync ─────────────────────────────────────────────────────────────────

/**
 * Fetch all QB Course Catalog records and upsert each one into WordPress.
 *
 * After upserting, runs a ghost-removal pass: any published course post whose
 * QB record ID was not returned by the sync query is demoted to draft. This
 * handles courses that were deleted or unchecked in QB between syncs.
 * Posts are never deleted — only drafted.
 *
 * Returns an associative array with 'synced', 'errors', 'ghosted', and
 * 'messages' keys.
 *
 * @return array  Results summary.
 */
function arc_qb_sync_all_courses() {
	$records = arc_qb_fetch_all_course_records();

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
		// Collect synced QB record IDs for ghost-removal pass below.
		$qb_id = intval( arc_qb_get_course_field( $record, 3 ) );
		if ( $qb_id > 0 ) {
			$synced_ids[] = $qb_id;
		}

		$result = arc_qb_upsert_course( $record );
		if ( is_wp_error( $result ) ) {
			$errors++;
			$messages[] = $result->get_error_message();
		} else {
			$synced++;
		}
	}

	// ── Ghost removal ─────────────────────────────────────────────────────────
	// Draft any published course posts not returned by this sync.
	// These are courses that no longer appear in QB's public listing
	// (deleted or unpublished). We draft rather than delete to preserve
	// content history.
	$ghosted = 0;
	$published_courses = get_posts( array(
		'post_type'      => 'course',
		'post_status'    => 'publish',
		'numberposts'    => -1,
		'meta_key'       => '_arc_qb_record_id',
		'fields'         => 'ids',
	) );

	foreach ( $published_courses as $wp_post_id ) {
		$post_qb_id = intval( get_post_meta( $wp_post_id, '_arc_qb_record_id', true ) );
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

add_action( 'admin_menu', 'arc_qb_add_sync_settings_page' );

/**
 * Register the Arc QB Sync settings page under WP Admin → Settings.
 */
function arc_qb_add_sync_settings_page() {
	add_options_page(
		'Arc QB Sync',
		'Arc QB Sync',
		'manage_options',
		'arc-qb-sync',
		'arc_qb_render_sync_settings_page'
	);
}

/**
 * Handle the "Sync All Courses Now" form POST and render the settings page.
 */
function arc_qb_render_sync_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$sync_result = null;

	// Handle sync form submission.
	if (
		isset( $_POST['arc_qb_sync_all'] ) &&
		check_admin_referer( 'arc_qb_sync_all_courses', 'arc_qb_sync_nonce' )
	) {
		$sync_result = arc_qb_sync_all_courses();
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Arc QB Sync', 'arc-qb-sync' ); ?></h1>

		<?php if ( null !== $sync_result ) : ?>
			<?php if ( 0 === $sync_result['errors'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php
						printf(
							esc_html__( 'Sync complete. %d courses synced, %d drafted (removed from QB public listing).', 'arc-qb-sync' ),
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

		<h2><?php esc_html_e( 'Course Catalog Sync', 'arc-qb-sync' ); ?></h2>
		<p><?php esc_html_e( 'Run a full sync to pull all publicly listed courses (Public Listing = checked) from the Quickbase Course Catalog into WordPress. Non-public courses are never imported. Use this for initial setup, after bulk changes in Quickbase, or to recover from a missed webhook. Incremental syncs happen automatically via Zapier.', 'arc-qb-sync' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'arc_qb_sync_all_courses', 'arc_qb_sync_nonce' ); ?>
			<?php submit_button( __( 'Sync All Courses Now', 'arc-qb-sync' ), 'primary', 'arc_qb_sync_all' ); ?>
		</form>
	</div>
	<?php
}

// ── [arc_course_filter_pills] shortcode ───────────────────────────────────────

add_action( 'init', 'arc_qb_register_filter_pills_shortcode' );

/**
 * Register the [arc_course_filter_pills] shortcode.
 */
function arc_qb_register_filter_pills_shortcode() {
	add_shortcode( 'arc_course_filter_pills', 'arc_qb_sc_course_filter_pills' );
}

/**
 * [arc_course_filter_pills] shortcode callback.
 *
 * Queries all published `course` posts, collects their course_tag terms,
 * deduplicates and sorts alphabetically, and outputs filter pill buttons.
 * An "All" pill is always first.
 *
 * Pill structure matches v1:
 *   <button class="arc-filter-pill" data-filter="[slug]">[label]</button>
 *
 * Place an Elementor Shortcode widget containing [arc_course_filter_pills]
 * in the section above the Loop Grid on the /training page.
 *
 * @return string  HTML output.
 */
function arc_qb_sc_course_filter_pills() {

	// Get IDs of all published course posts.
	$post_ids = get_posts(
		array(
			'post_type'   => 'course',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);

	if ( empty( $post_ids ) ) {
		return '';
	}

	// Collect course_tag terms for those posts.
	$terms = wp_get_object_terms(
		$post_ids,
		'course_tag',
		array(
			'orderby' => 'name',
			'order'   => 'ASC',
			'fields'  => 'all',
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	// Deduplicate by term_id (wp_get_object_terms can return duplicates when
	// multiple post IDs share the same term).
	$seen  = array();
	$pills = array();
	foreach ( $terms as $term ) {
		if ( ! isset( $seen[ $term->term_id ] ) ) {
			$seen[ $term->term_id ] = true;
			$pills[]                = $term;
		}
	}

	// Enqueue the filter script so the pills are wired up.
	wp_enqueue_script(
		'arc-course-catalog',
		ARC_QB_SYNC_URL . 'assets/js/course-catalog.js',
		array(),
		ARC_QB_SYNC_VERSION,
		true
	);

	// Build output.
	$html  = '<div class="arc-catalog-filters" role="group" aria-label="Filter courses by topic">';
	$html .= '<button class="arc-filter-pill is-active" data-filter="all">All</button>';

	foreach ( $pills as $term ) {
		$html .= sprintf(
			'<button class="arc-filter-pill" data-filter="%s">%s</button>',
			esc_attr( $term->slug ),
			esc_html( $term->name )
		);
	}

	$html .= '</div>';

	return $html;
}
