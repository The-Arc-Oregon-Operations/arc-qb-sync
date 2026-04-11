<?php
/**
 * Instructor CPT shortcodes — v3.
 *
 * All shortcodes read from WP post meta on `instructor` CPT posts.
 * They use the `arc_instructor_` prefix.
 *
 * Shortcodes provided:
 *   [arc_instructor_id]           — _arc_qb_instructor_id
 *   [arc_instructor_name]         — post_title (get_the_title)
 *   [arc_instructor_first_name]   — _arc_instructor_first_name
 *   [arc_instructor_last_name]    — _arc_instructor_last_name
 *   [arc_instructor_title]        — _arc_instructor_title
 *   [arc_instructor_organization] — _arc_instructor_organization
 *   [arc_instructor_credentials]  — _arc_instructor_credentials
 *   [arc_instructor_slug]         — _arc_instructor_slug
 *   [arc_instructor_bio]          — _arc_instructor_bio
 *   [arc_instructor_contact_url]  — _arc_instructor_contact_url
 *   [arc_instructor_headshot_url] — _arc_instructor_headshot_url
 *   [arc_instructor_field]        — generic meta access by key
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Shortcode callbacks ───────────────────────────────────────────────────────

function arc_qb_sc_arc_instructor_id() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_qb_instructor_id', true ) );
}

function arc_qb_sc_arc_instructor_name() {
	return esc_html( get_the_title() );
}

function arc_qb_sc_arc_instructor_first_name() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_instructor_first_name', true ) );
}

function arc_qb_sc_arc_instructor_last_name() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_instructor_last_name', true ) );
}

function arc_qb_sc_arc_instructor_title() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_instructor_title', true ) );
}

function arc_qb_sc_arc_instructor_organization() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_instructor_organization', true ) );
}

function arc_qb_sc_arc_instructor_credentials() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_instructor_credentials', true ) );
}

function arc_qb_sc_arc_instructor_slug() {
	return esc_html( (string) get_post_meta( get_the_ID(), '_arc_instructor_slug', true ) );
}

function arc_qb_sc_arc_instructor_bio() {
	$value = get_post_meta( get_the_ID(), '_arc_instructor_bio', true );
	if ( empty( $value ) ) {
		return '';
	}
	return wp_kses_post( wpautop( (string) $value ) );
}

function arc_qb_sc_arc_instructor_contact_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_instructor_contact_url', true ) );
}

function arc_qb_sc_arc_instructor_headshot_url() {
	return esc_url( (string) get_post_meta( get_the_ID(), '_arc_instructor_headshot_url', true ) );
}

/**
 * [arc_instructor_field meta="meta_key_name"] — returns any instructor CPT meta value.
 * [arc_instructor_field meta="meta_key_name" format="html"] — wp_kses_post( wpautop() )
 */
function arc_qb_shortcode_arc_instructor_field_generic( $atts ) {
	$atts = shortcode_atts( array(
		'meta'   => '',
		'format' => 'text',
	), $atts, 'arc_instructor_field' );

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

add_action( 'init', 'arc_qb_register_arc_instructor_shortcodes' );

function arc_qb_register_arc_instructor_shortcodes() {
	add_shortcode( 'arc_instructor_id',           'arc_qb_sc_arc_instructor_id' );
	add_shortcode( 'arc_instructor_name',         'arc_qb_sc_arc_instructor_name' );
	add_shortcode( 'arc_instructor_first_name',   'arc_qb_sc_arc_instructor_first_name' );
	add_shortcode( 'arc_instructor_last_name',    'arc_qb_sc_arc_instructor_last_name' );
	add_shortcode( 'arc_instructor_title',        'arc_qb_sc_arc_instructor_title' );
	add_shortcode( 'arc_instructor_organization', 'arc_qb_sc_arc_instructor_organization' );
	add_shortcode( 'arc_instructor_credentials',  'arc_qb_sc_arc_instructor_credentials' );
	add_shortcode( 'arc_instructor_slug',         'arc_qb_sc_arc_instructor_slug' );
	add_shortcode( 'arc_instructor_bio',          'arc_qb_sc_arc_instructor_bio' );
	add_shortcode( 'arc_instructor_contact_url',  'arc_qb_sc_arc_instructor_contact_url' );
	add_shortcode( 'arc_instructor_headshot_url', 'arc_qb_sc_arc_instructor_headshot_url' );
	add_shortcode( 'arc_instructor_field',        'arc_qb_shortcode_arc_instructor_field_generic' );
}
