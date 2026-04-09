<?php
/**
 * Course detail page shortcodes.
 *
 * Each shortcode reads from arc_qb_get_course() and extracts fields via
 * arc_qb_get_course_field(). These shortcodes are intended for use on
 * a /course-catalog/?course-id=nnnn detail page in Elementor.
 *
 * Shortcodes provided:
 *   [course_title]               - Course Title (field 6)
 *   [course_short_description]   - Short description / intro (field 46)
 *   [course_description]         - Full description HTML (field 85, fallback 7)
 *   [course_length]              - Hours of Instruction (field 14)
 *   [course_tags]                - Tag pills (field 56)
 *   [course_image_url]           - Featured image URL (field 88)
 *   [course_delivery_method]     - Delivery Method (field 40)
 *   [course_target_audience]     - Target Audience (field 50)
 *   [course_category]            - Category (field 43)
 *   [course_payment]             - Price / Payment (field 39)
 *   [course_learning_objectives] - Learning objectives HTML (field 85, fallback 62)
 *   [arc_qb_course_field]        - Generic field access by QB field ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Course Title */
function arc_qb_sc_course_title() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}
	return esc_html( arc_qb_get_course_field( $record, 6 ) );
}

/* Short Description */
function arc_qb_sc_course_short_description() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}
	$value = arc_qb_get_course_field( $record, 46 );
	if ( empty( $value ) ) {
		return '';
	}
	return wp_kses_post( wpautop( $value ) );
}

/* Full Description — field 85 (HTML), fallback to field 7 */
function arc_qb_sc_course_description() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}

	$html = arc_qb_get_course_field( $record, 85 );
	if ( empty( $html ) ) {
		$html = arc_qb_get_course_field( $record, 7 );
	}
	if ( empty( $html ) ) {
		return '';
	}
	return wp_kses_post( wpautop( $html ) );
}

/* Hours of Instruction */
function arc_qb_sc_course_length() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}
	return esc_html( arc_qb_get_course_field( $record, 14 ) );
}

/* Tags — rendered as <span class="arc-tag"> pills */
function arc_qb_sc_course_tags() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}

	$raw  = arc_qb_get_course_field( $record, 56 );
	$tags = arc_qb_parse_tags( $raw );

	if ( empty( $tags ) ) {
		return '';
	}

	$output = '<div class="arc-catalog-tile__tags">';
	foreach ( $tags as $tag ) {
		$output .= '<span class="arc-tag">' . esc_html( $tag ) . '</span>';
	}
	$output .= '</div>';

	return $output;
}

/* Featured Image URL */
function arc_qb_sc_course_image_url() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}
	$value = arc_qb_get_course_field( $record, 88 );
	if ( empty( $value ) ) {
		return '';
	}
	return esc_url( $value );
}

/* Delivery Method */
function arc_qb_sc_course_delivery_method() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}
	return esc_html( arc_qb_get_course_field( $record, 40 ) );
}

/* Target Audience */
function arc_qb_sc_course_target_audience() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}
	$value = arc_qb_get_course_field( $record, 50 );
	if ( empty( $value ) ) {
		return '';
	}
	return wp_kses_post( wpautop( $value ) );
}

/* Category */
function arc_qb_sc_course_category() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}
	return esc_html( arc_qb_get_course_field( $record, 43 ) );
}

/* Payment / Price — QB Currency type returns a numeric value */
function arc_qb_sc_course_payment() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}
	return esc_html( arc_qb_get_course_field( $record, 39 ) );
}

/* Learning Objectives — field 85 (HTML), fallback to field 62 */
function arc_qb_sc_course_learning_objectives() {
	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}

	$html = arc_qb_get_course_field( $record, 85 );
	if ( empty( $html ) ) {
		$html = arc_qb_get_course_field( $record, 62 );
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
 * - id:     Quickbase field ID (required)
 * - format: "text" (default, escaped) or "html" (wpautop + wp_kses_post)
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

	$record = arc_qb_get_course();
	if ( is_wp_error( $record ) ) {
		return '';
	}

	$value = arc_qb_get_course_field( $record, $field_id );

	if ( is_string( $value ) ) {
		$value = trim( $value );
	}

	if ( $value === '' || $value === null || strtolower( (string) $value ) === 'null' ) {
		return '';
	}

	if ( strtolower( $atts['format'] ) === 'html' ) {
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
