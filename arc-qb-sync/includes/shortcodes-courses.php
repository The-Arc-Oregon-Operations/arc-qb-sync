<?php
/**
 * Course detail page shortcodes — v2.
 *
 * In v2, all shortcodes read from WP post meta rather than calling the QB API
 * directly. These shortcodes are kept for backward compatibility with any
 * existing pages that use them. New detail pages should use Elementor's
 * Theme Builder single template with Post Custom Field dynamic tags instead.
 *
 * Context resolution order:
 *  1. If the current post is a `course` CPT, use its ID.
 *  2. If ?course-id=nnnn is in the URL, look up the `course` post by
 *     _arc_qb_record_id meta (legacy fallback — the template_redirect in
 *     cpt-courses.php should have already redirected, but guard just in case).
 *  3. Return '' (empty) if no course context is found.
 *
 * Shortcodes provided:
 *   [course_title]               - post_title
 *   [course_short_description]   - post_excerpt
 *   [course_description]         - _arc_course_learning_objectives_html (fallback: _arc_course_description_fallback)
 *   [course_length]              - _arc_course_length
 *   [course_tags]                - course_tag terms as <span> pills
 *   [course_image_url]           - _arc_course_image_url
 *   [course_delivery_method]     - _arc_course_delivery_method
 *   [course_target_audience]     - _arc_course_target_audience
 *   [course_category]            - _arc_course_category
 *   [course_payment]             - _arc_course_payment
 *   [course_learning_objectives] - _arc_course_learning_objectives_html (fallback: _arc_course_learning_objectives)
 *   [arc_qb_course_field]        - generic field access by QB field ID meta key convention
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Context helper ────────────────────────────────────────────────────────────

/**
 * Resolve the WP post ID for the current course context.
 *
 * Uses a static cache so all shortcodes on the same page hit the database
 * at most twice (once for CPT check, once for meta lookup).
 *
 * @return int|false  WP post ID, or false if no course context is found.
 */
function arc_qb_get_course_post_id() {
	static $resolved_id = null;

	if ( null !== $resolved_id ) {
		return $resolved_id;
	}

	// 1. Current post is a course CPT.
	$current_id = get_the_ID();
	if ( $current_id && 'course' === get_post_type( $current_id ) ) {
		$resolved_id = $current_id;
		return $resolved_id;
	}

	// 2. Legacy ?course-id= fallback.
	if ( isset( $_GET['course-id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$record_id = intval( $_GET['course-id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $record_id > 0 ) {
			$posts = get_posts(
				array(
					'post_type'   => 'course',
					'meta_key'    => '_arc_qb_record_id',
					'meta_value'  => $record_id,
					'numberposts' => 1,
					'post_status' => 'any',
				)
			);
			if ( ! empty( $posts ) ) {
				$resolved_id = $posts[0]->ID;
				return $resolved_id;
			}
		}
	}

	$resolved_id = false;
	return false;
}

// ── Shortcode callbacks ───────────────────────────────────────────────────────

/* Course Title */
function arc_qb_sc_course_title() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( get_the_title( $post_id ) );
}

/* Short Description */
function arc_qb_sc_course_short_description() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	$post = get_post( $post_id );
	if ( empty( $post->post_excerpt ) ) {
		return '';
	}
	return wp_kses_post( wpautop( $post->post_excerpt ) );
}

/* Full Description — preferred: _arc_course_learning_objectives_html, fallback: _arc_course_description_fallback */
function arc_qb_sc_course_description() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	$html = get_post_meta( $post_id, '_arc_course_learning_objectives_html', true );
	if ( empty( $html ) ) {
		$html = get_post_meta( $post_id, '_arc_course_description_fallback', true );
	}
	if ( empty( $html ) ) {
		return '';
	}
	return wp_kses_post( wpautop( $html ) );
}

/* Hours of Instruction */
function arc_qb_sc_course_length() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( get_post_meta( $post_id, '_arc_course_length', true ) );
}

/* Tags — rendered as <span class="arc-tag"> pills */
function arc_qb_sc_course_tags() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}

	$terms = get_the_terms( $post_id, 'course_tag' );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	$output = '<div class="arc-catalog-tile__tags">';
	foreach ( $terms as $term ) {
		$output .= '<span class="arc-tag">' . esc_html( $term->name ) . '</span>';
	}
	$output .= '</div>';

	return $output;
}

/* Featured Image URL */
function arc_qb_sc_course_image_url() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	$url = get_post_meta( $post_id, '_arc_course_image_url', true );
	if ( empty( $url ) ) {
		return '';
	}
	return esc_url( $url );
}

/* Delivery Method */
function arc_qb_sc_course_delivery_method() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( get_post_meta( $post_id, '_arc_course_delivery_method', true ) );
}

/* Target Audience */
function arc_qb_sc_course_target_audience() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	$value = get_post_meta( $post_id, '_arc_course_target_audience', true );
	if ( empty( $value ) ) {
		return '';
	}
	return wp_kses_post( wpautop( $value ) );
}

/* Category */
function arc_qb_sc_course_category() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( get_post_meta( $post_id, '_arc_course_category', true ) );
}

/* Payment / Price */
function arc_qb_sc_course_payment() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( get_post_meta( $post_id, '_arc_course_payment', true ) );
}

/* Learning Objectives — preferred: _arc_course_learning_objectives_html, fallback: _arc_course_learning_objectives */
function arc_qb_sc_course_learning_objectives() {
	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}
	$html = get_post_meta( $post_id, '_arc_course_learning_objectives_html', true );
	if ( empty( $html ) ) {
		$html = get_post_meta( $post_id, '_arc_course_learning_objectives', true );
	}
	if ( empty( $html ) ) {
		return '';
	}
	return wp_kses_post( wpautop( $html ) );
}

/**
 * Generic course field shortcode:
 *   [arc_qb_course_field id="6"]
 * Optional: [arc_qb_course_field id="85" format="html"]
 *
 * In v2, field IDs map to post meta keys per the convention:
 *   3  → _arc_qb_record_id
 *   6  → post_title (read via get_the_title)
 *   7  → _arc_course_description_fallback
 *   14 → _arc_course_length_ms
 *   39 → _arc_course_payment
 *   40 → _arc_course_delivery_method
 *   43 → _arc_course_category
 *   46 → post_excerpt
 *   50 → _arc_course_target_audience
 *   62 → _arc_course_learning_objectives_html (primary)
 *   85 → _arc_course_learning_objectives (secondary)
 *   88 → _arc_course_image_url
 *
 * @param array $atts  Shortcode attributes: id (required), format (text|html).
 * @return string
 */
function arc_qb_sc_course_field( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'     => 0,
			'format' => 'text',
		),
		$atts,
		'arc_qb_course_field'
	);

	$field_id = intval( $atts['id'] );
	if ( $field_id <= 0 ) {
		return '';
	}

	$post_id = arc_qb_get_course_post_id();
	if ( ! $post_id ) {
		return '';
	}

	// Map QB field IDs to WP meta keys (or special post fields).
	$field_map = array(
		3  => '_arc_qb_record_id',
		6  => '__post_title__',
		7  => '_arc_course_description_fallback',
		14 => '_arc_course_length_ms',
		39 => '_arc_course_payment',
		40 => '_arc_course_delivery_method',
		43 => '_arc_course_category',
		46 => '__post_excerpt__',
		50 => '_arc_course_target_audience',
		62 => '_arc_course_learning_objectives_html',
		85 => '_arc_course_learning_objectives',
		88 => '_arc_course_image_url',
	);

	if ( ! isset( $field_map[ $field_id ] ) ) {
		return '';
	}

	$meta_key = $field_map[ $field_id ];

	// Handle special post fields.
	if ( '__post_title__' === $meta_key ) {
		$value = get_the_title( $post_id );
	} elseif ( '__post_excerpt__' === $meta_key ) {
		$post  = get_post( $post_id );
		$value = $post ? $post->post_excerpt : '';
	} else {
		$value = get_post_meta( $post_id, $meta_key, true );
	}

	if ( '' === $value || null === $value ) {
		return '';
	}

	if ( 'html' === strtolower( $atts['format'] ) ) {
		return wp_kses_post( wpautop( $value ) );
	}

	return esc_html( (string) $value );
}

/**
 * Register all course shortcodes on init.
 */
function arc_qb_register_course_shortcodes() {
	add_shortcode( 'course_title',               'arc_qb_sc_course_title' );
	add_shortcode( 'course_short_description',   'arc_qb_sc_course_short_description' );
	add_shortcode( 'course_description',         'arc_qb_sc_course_description' );
	add_shortcode( 'course_length',              'arc_qb_sc_course_length' );
	add_shortcode( 'course_tags',                'arc_qb_sc_course_tags' );
	add_shortcode( 'course_image_url',           'arc_qb_sc_course_image_url' );
	add_shortcode( 'course_delivery_method',     'arc_qb_sc_course_delivery_method' );
	add_shortcode( 'course_target_audience',     'arc_qb_sc_course_target_audience' );
	add_shortcode( 'course_category',            'arc_qb_sc_course_category' );
	add_shortcode( 'course_payment',             'arc_qb_sc_course_payment' );
	add_shortcode( 'course_learning_objectives', 'arc_qb_sc_course_learning_objectives' );
	add_shortcode( 'arc_qb_course_field',        'arc_qb_sc_course_field' );
}
add_action( 'init', 'arc_qb_register_course_shortcodes' );
