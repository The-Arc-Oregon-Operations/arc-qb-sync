<?php
/**
 * Events CPT shortcodes — v3.
 *
 * All shortcodes read from WP post meta on `arc_event` CPT posts.
 * They use the `arc_event_` prefix to avoid conflicts with the existing
 * `event_` shortcodes in shortcodes-events.php (which do live QB fetches).
 *
 * Shortcodes provided:
 *   [arc_event_id]                        — _arc_qb_event_id
 *   [arc_event_title]                     — post_title (get_the_title)
 *   [arc_event_dates]                     — _arc_event_dates
 *   [arc_event_time]                      — _arc_event_time
 *   [arc_event_venue]                     — _arc_event_venue
 *   [arc_event_days_of_week]              — _arc_event_days_of_week
 *   [arc_event_mode]                      — _arc_event_mode
 *   [arc_event_length]                    — _arc_event_length
 *   [arc_event_description]               — _arc_event_description
 *   [arc_event_price]                     — _arc_event_price
 *   [arc_event_reg_url]                   — _arc_event_reg_url
 *   [arc_event_flyer_url]                 — _arc_event_flyer_url
 *   [arc_event_image_url]                 — _arc_event_image_url (legacy manual URL)
 *   [arc_event_featured_image_url]        — _arc_event_featured_image_url
 *   [arc_event_hero_image_url]            — _arc_event_hero_image_url
 *   [arc_event_instructors_legacy]        — _arc_event_instructors_legacy
 *   [arc_event_instructor_slugs_legacy]   — _arc_event_instructor_slugs_legacy
 *   [arc_event_instructor1_name]          — _arc_event_instructor1_name
 *   [arc_event_instructor1_headshot_url]  — _arc_event_instructor1_headshot_url
 *   [arc_event_instructor1_headshot_alt]  — _arc_event_instructor1_headshot_alt
 *   [arc_event_instructor2_name]          — _arc_event_instructor2_name
 *   [arc_event_instructor2_headshot_url]  — _arc_event_instructor2_headshot_url
 *   [arc_event_instructor2_headshot_alt]  — _arc_event_instructor2_headshot_alt
 *   [arc_event_instructor3_name]          — _arc_event_instructor3_name
 *   [arc_event_instructor3_headshot_url]  — _arc_event_instructor3_headshot_url
 *   [arc_event_instructor3_headshot_alt]  — _arc_event_instructor3_headshot_alt
 *   [arc_event_is_multiday]              — _arc_event_is_multiday ("1" or "0")
 *   [arc_event_is_multisession]          — _arc_event_is_multisession ("1" or "0")
 *   [arc_event_field]                    — generic meta access by key
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Shortcode callbacks ───────────────────────────────────────────────────────

function arc_qb_sc_arc_event_id() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_qb_event_id', true ) );
}

function arc_qb_sc_arc_event_title() {
	return esc_html( get_the_title() );
}

function arc_qb_sc_arc_event_dates() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_dates', true ) );
}

function arc_qb_sc_arc_event_time() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_time', true ) );
}

function arc_qb_sc_arc_event_venue() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_venue', true ) );
}

function arc_qb_sc_arc_event_days_of_week() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_days_of_week', true ) );
}

function arc_qb_sc_arc_event_mode() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_mode', true ) );
}

function arc_qb_sc_arc_event_length() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_length', true ) );
}

function arc_qb_sc_arc_event_description() {
	$value = get_post_meta( get_the_ID(), '_arc_event_description', true );
	if ( empty( $value ) ) {
		return '';
	}
	return wp_kses_post( wpautop( (string) $value ) );
}

function arc_qb_sc_arc_event_price() {
	$value = get_post_meta( get_the_ID(), '_arc_event_price', true );
	if ( empty( $value ) ) {
		return '';
	}
	$allowed = array(
		'a'      => array( 'href' => array(), 'title' => array(), 'target' => array(), 'rel' => array() ),
		'strong' => array(),
		'em'     => array(),
		'span'   => array( 'class' => array(), 'style' => array() ),
		'br'     => array(),
		'strike' => array(),
	);
	return wp_kses( (string) $value, $allowed );
}

function arc_qb_sc_arc_event_reg_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_event_reg_url', true ) );
}

function arc_qb_sc_arc_event_flyer_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_event_flyer_url', true ) );
}

function arc_qb_sc_arc_event_image_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_event_image_url', true ) );
}

function arc_qb_sc_arc_event_featured_image_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_event_featured_image_url', true ) );
}

function arc_qb_sc_arc_event_hero_image_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_event_hero_image_url', true ) );
}

function arc_qb_sc_arc_event_instructors_legacy() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_instructors_legacy', true ) );
}

function arc_qb_sc_arc_event_instructor_slugs_legacy() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_instructor_slugs_legacy', true ) );
}

function arc_qb_sc_arc_event_instructor1_name() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_instructor1_name', true ) );
}

function arc_qb_sc_arc_event_instructor1_headshot_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_event_instructor1_headshot_url', true ) );
}

function arc_qb_sc_arc_event_instructor1_headshot_alt() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_instructor1_headshot_alt', true ) );
}

function arc_qb_sc_arc_event_instructor2_name() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_instructor2_name', true ) );
}

function arc_qb_sc_arc_event_instructor2_headshot_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_event_instructor2_headshot_url', true ) );
}

function arc_qb_sc_arc_event_instructor2_headshot_alt() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_instructor2_headshot_alt', true ) );
}

function arc_qb_sc_arc_event_instructor3_name() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_instructor3_name', true ) );
}

function arc_qb_sc_arc_event_instructor3_headshot_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_event_instructor3_headshot_url', true ) );
}

function arc_qb_sc_arc_event_instructor3_headshot_alt() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_instructor3_headshot_alt', true ) );
}

function arc_qb_sc_arc_event_is_multiday() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_is_multiday', true ) );
}

function arc_qb_sc_arc_event_is_multisession() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_event_is_multisession', true ) );
}

/**
 * [arc_event_field meta="meta_key_name"] — returns any event CPT meta value.
 * [arc_event_field meta="meta_key_name" format="html"] — wp_kses_post( wpautop() )
 */
function arc_qb_shortcode_arc_event_field_generic( $atts ) {
	$atts = shortcode_atts( array(
		'meta'   => '',
		'format' => 'text',
	), $atts, 'arc_event_field' );

	if ( empty( $atts['meta'] ) ) {
		return '';
	}

	$value = get_post_meta( get_the_ID(), sanitize_key( $atts['meta'] ), true );

	if ( 'html' === $atts['format'] ) {
		return wp_kses_post( wpautop( (string) $value ) );
	}

	return esc_html( (string) $value );
}

// ── Register all shortcodes on init ───────────────────────────────────────────

add_action( 'init', 'arc_qb_register_arc_event_shortcodes' );

function arc_qb_register_arc_event_shortcodes() {
	add_shortcode( 'arc_event_id',                       'arc_qb_sc_arc_event_id' );
	add_shortcode( 'arc_event_title',                    'arc_qb_sc_arc_event_title' );
	add_shortcode( 'arc_event_dates',                    'arc_qb_sc_arc_event_dates' );
	add_shortcode( 'arc_event_time',                     'arc_qb_sc_arc_event_time' );
	add_shortcode( 'arc_event_venue',                    'arc_qb_sc_arc_event_venue' );
	add_shortcode( 'arc_event_days_of_week',             'arc_qb_sc_arc_event_days_of_week' );
	add_shortcode( 'arc_event_mode',                     'arc_qb_sc_arc_event_mode' );
	add_shortcode( 'arc_event_length',                   'arc_qb_sc_arc_event_length' );
	add_shortcode( 'arc_event_description',              'arc_qb_sc_arc_event_description' );
	add_shortcode( 'arc_event_price',                    'arc_qb_sc_arc_event_price' );
	add_shortcode( 'arc_event_reg_url',                  'arc_qb_sc_arc_event_reg_url' );
	add_shortcode( 'arc_event_flyer_url',                'arc_qb_sc_arc_event_flyer_url' );
	add_shortcode( 'arc_event_image_url',                'arc_qb_sc_arc_event_image_url' );
	add_shortcode( 'arc_event_featured_image_url',       'arc_qb_sc_arc_event_featured_image_url' );
	add_shortcode( 'arc_event_hero_image_url',           'arc_qb_sc_arc_event_hero_image_url' );
	add_shortcode( 'arc_event_instructors_legacy',       'arc_qb_sc_arc_event_instructors_legacy' );
	add_shortcode( 'arc_event_instructor_slugs_legacy',  'arc_qb_sc_arc_event_instructor_slugs_legacy' );
	add_shortcode( 'arc_event_instructor1_name',         'arc_qb_sc_arc_event_instructor1_name' );
	add_shortcode( 'arc_event_instructor1_headshot_url', 'arc_qb_sc_arc_event_instructor1_headshot_url' );
	add_shortcode( 'arc_event_instructor1_headshot_alt', 'arc_qb_sc_arc_event_instructor1_headshot_alt' );
	add_shortcode( 'arc_event_instructor2_name',         'arc_qb_sc_arc_event_instructor2_name' );
	add_shortcode( 'arc_event_instructor2_headshot_url', 'arc_qb_sc_arc_event_instructor2_headshot_url' );
	add_shortcode( 'arc_event_instructor2_headshot_alt', 'arc_qb_sc_arc_event_instructor2_headshot_alt' );
	add_shortcode( 'arc_event_instructor3_name',         'arc_qb_sc_arc_event_instructor3_name' );
	add_shortcode( 'arc_event_instructor3_headshot_url', 'arc_qb_sc_arc_event_instructor3_headshot_url' );
	add_shortcode( 'arc_event_instructor3_headshot_alt', 'arc_qb_sc_arc_event_instructor3_headshot_alt' );
	add_shortcode( 'arc_event_is_multiday',              'arc_qb_sc_arc_event_is_multiday' );
	add_shortcode( 'arc_event_is_multisession',          'arc_qb_sc_arc_event_is_multisession' );
	add_shortcode( 'arc_event_field',                    'arc_qb_shortcode_arc_event_field_generic' );
}
