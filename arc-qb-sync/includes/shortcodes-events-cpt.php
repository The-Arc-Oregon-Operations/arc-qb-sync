<?php
/**
 * Events CPT shortcodes — v3.2.
 *
 * All shortcodes read from WP post meta on `arc_event` CPT posts.
 * Primary prefix is `event_*`; `arc_event_*` names remain as deprecated aliases.
 *
 * Context resolution order:
 *  1. If the current post is an `arc_event` CPT, use its ID.
 *  2. If ?event-id=nnnn is in the URL, look up the `arc_event` post by
 *     _arc_qb_event_id meta (temporary bridge for legacy ?event-id= URLs during
 *     CPT template development; remove once the legacy redirect is enabled in
 *     cpt-events.php).
 *  3. Return false if no event context is found.
 *
 * Shortcodes provided (primary → deprecated alias):
 *   [event_id]                        / [arc_event_id]                       — _arc_qb_event_id
 *   [event_title]                     / [arc_event_title]                    — post_title
 *   [event_dates]                     / [arc_event_dates]                    — _arc_event_dates
 *   [event_time]                      / [arc_event_time]                     — _arc_event_time
 *   [event_venue]                     / [arc_event_venue]                    — _arc_event_venue
 *   [event_days_of_week]              / [arc_event_days_of_week]             — _arc_event_days_of_week
 *   [event_mode]                      / [arc_event_mode]                     — _arc_event_mode
 *   [event_length]                    / [arc_event_length]                   — _arc_event_length
 *   [event_description]               / [arc_event_description]              — _arc_event_description
 *   [event_price]                     / [arc_event_price]                    — _arc_event_price
 *   [event_reg_url]                   / [arc_event_reg_url]                  — _arc_event_reg_url
 *   [event_flyer_url]                 / [arc_event_flyer_url]                — _arc_event_flyer_url
 *   [event_image_url]                 / [arc_event_image_url]                — _arc_event_image_url (legacy manual URL)
 *   [event_featured_image_url]        / [arc_event_featured_image_url]       — _arc_event_featured_image_url
 *   [event_hero_image_url]            / [arc_event_hero_image_url]           — _arc_event_hero_image_url
 *   [event_instructors_legacy]        / [arc_event_instructors_legacy]       — _arc_event_instructors_legacy
 *   [event_instructor_slugs_legacy]   / [arc_event_instructor_slugs_legacy]  — _arc_event_instructor_slugs_legacy
 *   [event_instructor1_name]          / [arc_event_instructor1_name]         — _arc_event_instructor1_name
 *   [event_instructor1_headshot_url]  / [arc_event_instructor1_headshot_url] — _arc_event_instructor1_headshot_url
 *   [event_instructor1_headshot_alt]  / [arc_event_instructor1_headshot_alt] — _arc_event_instructor1_headshot_alt
 *   [event_instructor2_name]          / [arc_event_instructor2_name]         — _arc_event_instructor2_name
 *   [event_instructor2_headshot_url]  / [arc_event_instructor2_headshot_url] — _arc_event_instructor2_headshot_url
 *   [event_instructor2_headshot_alt]  / [arc_event_instructor2_headshot_alt] — _arc_event_instructor2_headshot_alt
 *   [event_instructor3_name]          / [arc_event_instructor3_name]         — _arc_event_instructor3_name
 *   [event_instructor3_headshot_url]  / [arc_event_instructor3_headshot_url] — _arc_event_instructor3_headshot_url
 *   [event_instructor3_headshot_alt]  / [arc_event_instructor3_headshot_alt] — _arc_event_instructor3_headshot_alt
 *   [event_is_multiday]               / [arc_event_is_multiday]              — _arc_event_is_multiday ("1" or "0")
 *   [event_is_multisession]           / [arc_event_is_multisession]          — _arc_event_is_multisession ("1" or "0")
 *   [event_field]                     / [arc_event_field]                    — generic meta access by key
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Context helper ────────────────────────────────────────────────────────────

/**
 * Resolve the WP post ID for the current event context.
 *
 * Uses a static cache so all shortcodes on the same page hit the database
 * at most twice (once for CPT check, once for meta lookup).
 *
 * @return int|false  WP post ID, or false if no event context is found.
 */
function arc_qb_get_event_post_id() {
	static $resolved_id = null;

	if ( null !== $resolved_id ) {
		return $resolved_id;
	}

	// 1. Current post is an arc_event CPT.
	$current_id = get_the_ID();
	if ( $current_id && 'arc_event' === get_post_type( $current_id ) ) {
		$resolved_id = $current_id;
		return $resolved_id;
	}

	// 2. Legacy ?event-id= fallback (temporary bridge during CPT template development).
	//    Remove this block once the legacy redirect is enabled in cpt-events.php.
	if ( isset( $_GET['event-id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$record_id = intval( $_GET['event-id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $record_id > 0 ) {
			$posts = get_posts(
				array(
					'post_type'   => 'arc_event',
					'meta_key'    => '_arc_qb_event_id',
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

function arc_qb_sc_event_id() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_qb_event_id', true ) );
}

function arc_qb_sc_event_title() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( get_the_title( $post_id ) );
}

function arc_qb_sc_event_dates() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_dates', true ) );
}

function arc_qb_sc_event_time() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_time', true ) );
}

function arc_qb_sc_event_venue() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_venue', true ) );
}

function arc_qb_sc_event_days_of_week() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_days_of_week', true ) );
}

function arc_qb_sc_event_mode() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_mode', true ) );
}

function arc_qb_sc_event_length() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_length', true ) );
}

function arc_qb_sc_event_description() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	$value = get_post_meta( $post_id, '_arc_event_description', true );
	if ( empty( $value ) ) {
		return '';
	}
	return wp_kses_post( wpautop( (string) $value ) );
}

function arc_qb_sc_event_price() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	$value = get_post_meta( $post_id, '_arc_event_price', true );
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

function arc_qb_sc_event_reg_url() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_url( (string) get_post_meta( $post_id, '_arc_event_reg_url', true ) );
}

function arc_qb_sc_event_flyer_url() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_url( (string) get_post_meta( $post_id, '_arc_event_flyer_url', true ) );
}

function arc_qb_sc_event_image_url() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_url( (string) get_post_meta( $post_id, '_arc_event_image_url', true ) );
}

function arc_qb_sc_event_featured_image_url() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_url( (string) get_post_meta( $post_id, '_arc_event_featured_image_url', true ) );
}

function arc_qb_sc_event_hero_image_url() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_url( (string) get_post_meta( $post_id, '_arc_event_hero_image_url', true ) );
}

function arc_qb_sc_event_instructors_legacy() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructors_legacy', true ) );
}

function arc_qb_sc_event_instructor_slugs_legacy() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructor_slugs_legacy', true ) );
}

function arc_qb_sc_event_instructor1_name() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructor1_name', true ) );
}

function arc_qb_sc_event_instructor1_headshot_url() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_url( (string) get_post_meta( $post_id, '_arc_event_instructor1_headshot_url', true ) );
}

function arc_qb_sc_event_instructor1_headshot_alt() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructor1_headshot_alt', true ) );
}

function arc_qb_sc_event_instructor2_name() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructor2_name', true ) );
}

function arc_qb_sc_event_instructor2_headshot_url() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_url( (string) get_post_meta( $post_id, '_arc_event_instructor2_headshot_url', true ) );
}

function arc_qb_sc_event_instructor2_headshot_alt() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructor2_headshot_alt', true ) );
}

function arc_qb_sc_event_instructor3_name() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructor3_name', true ) );
}

function arc_qb_sc_event_instructor3_headshot_url() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_url( (string) get_post_meta( $post_id, '_arc_event_instructor3_headshot_url', true ) );
}

function arc_qb_sc_event_instructor3_headshot_alt() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_instructor3_headshot_alt', true ) );
}

function arc_qb_sc_event_is_multiday() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_is_multiday', true ) );
}

function arc_qb_sc_event_is_multisession() {
	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}
	return esc_html( (string) get_post_meta( $post_id, '_arc_event_is_multisession', true ) );
}

/**
 * [event_field meta="meta_key_name"] — returns any event CPT meta value.
 * [event_field meta="meta_key_name" format="html"] — wp_kses_post( wpautop() )
 */
function arc_qb_sc_event_field_generic( $atts ) {
	$atts = shortcode_atts(
		array(
			'meta'   => '',
			'format' => 'text',
		),
		$atts,
		'event_field'
	);

	if ( empty( $atts['meta'] ) ) {
		return '';
	}

	$post_id = arc_qb_get_event_post_id();
	if ( ! $post_id ) {
		return '';
	}

	$value = get_post_meta( $post_id, sanitize_key( $atts['meta'] ), true );

	if ( 'html' === $atts['format'] ) {
		return wp_kses_post( wpautop( (string) $value ) );
	}

	return esc_html( (string) $value );
}

// ── Register all shortcodes on init ───────────────────────────────────────────

add_action( 'init', 'arc_qb_register_event_shortcodes' );

function arc_qb_register_event_shortcodes() {
	// Primary names — use these for all new CPT templates.
	add_shortcode( 'event_id',                       'arc_qb_sc_event_id' );
	add_shortcode( 'event_title',                    'arc_qb_sc_event_title' );
	add_shortcode( 'event_dates',                    'arc_qb_sc_event_dates' );
	add_shortcode( 'event_time',                     'arc_qb_sc_event_time' );
	add_shortcode( 'event_venue',                    'arc_qb_sc_event_venue' );
	add_shortcode( 'event_days_of_week',             'arc_qb_sc_event_days_of_week' );
	add_shortcode( 'event_mode',                     'arc_qb_sc_event_mode' );
	add_shortcode( 'event_length',                   'arc_qb_sc_event_length' );
	add_shortcode( 'event_description',              'arc_qb_sc_event_description' );
	add_shortcode( 'event_price',                    'arc_qb_sc_event_price' );
	add_shortcode( 'event_reg_url',                  'arc_qb_sc_event_reg_url' );
	add_shortcode( 'event_flyer_url',                'arc_qb_sc_event_flyer_url' );
	add_shortcode( 'event_image_url',                'arc_qb_sc_event_image_url' );
	add_shortcode( 'event_featured_image_url',       'arc_qb_sc_event_featured_image_url' );
	add_shortcode( 'event_hero_image_url',           'arc_qb_sc_event_hero_image_url' );
	add_shortcode( 'event_instructors_legacy',       'arc_qb_sc_event_instructors_legacy' );
	add_shortcode( 'event_instructor_slugs_legacy',  'arc_qb_sc_event_instructor_slugs_legacy' );
	add_shortcode( 'event_instructor1_name',         'arc_qb_sc_event_instructor1_name' );
	add_shortcode( 'event_instructor1_headshot_url', 'arc_qb_sc_event_instructor1_headshot_url' );
	add_shortcode( 'event_instructor1_headshot_alt', 'arc_qb_sc_event_instructor1_headshot_alt' );
	add_shortcode( 'event_instructor2_name',         'arc_qb_sc_event_instructor2_name' );
	add_shortcode( 'event_instructor2_headshot_url', 'arc_qb_sc_event_instructor2_headshot_url' );
	add_shortcode( 'event_instructor2_headshot_alt', 'arc_qb_sc_event_instructor2_headshot_alt' );
	add_shortcode( 'event_instructor3_name',         'arc_qb_sc_event_instructor3_name' );
	add_shortcode( 'event_instructor3_headshot_url', 'arc_qb_sc_event_instructor3_headshot_url' );
	add_shortcode( 'event_instructor3_headshot_alt', 'arc_qb_sc_event_instructor3_headshot_alt' );
	add_shortcode( 'event_is_multiday',              'arc_qb_sc_event_is_multiday' );
	add_shortcode( 'event_is_multisession',          'arc_qb_sc_event_is_multisession' );
	add_shortcode( 'event_field',                    'arc_qb_sc_event_field_generic' );

	// Deprecated aliases — remove in a future version once arc_event_* names are retired from templates.
	add_shortcode( 'arc_event_id',                       'arc_qb_sc_event_id' );
	add_shortcode( 'arc_event_title',                    'arc_qb_sc_event_title' );
	add_shortcode( 'arc_event_dates',                    'arc_qb_sc_event_dates' );
	add_shortcode( 'arc_event_time',                     'arc_qb_sc_event_time' );
	add_shortcode( 'arc_event_venue',                    'arc_qb_sc_event_venue' );
	add_shortcode( 'arc_event_days_of_week',             'arc_qb_sc_event_days_of_week' );
	add_shortcode( 'arc_event_mode',                     'arc_qb_sc_event_mode' );
	add_shortcode( 'arc_event_length',                   'arc_qb_sc_event_length' );
	add_shortcode( 'arc_event_description',              'arc_qb_sc_event_description' );
	add_shortcode( 'arc_event_price',                    'arc_qb_sc_event_price' );
	add_shortcode( 'arc_event_reg_url',                  'arc_qb_sc_event_reg_url' );
	add_shortcode( 'arc_event_flyer_url',                'arc_qb_sc_event_flyer_url' );
	add_shortcode( 'arc_event_image_url',                'arc_qb_sc_event_image_url' );
	add_shortcode( 'arc_event_featured_image_url',       'arc_qb_sc_event_featured_image_url' );
	add_shortcode( 'arc_event_hero_image_url',           'arc_qb_sc_event_hero_image_url' );
	add_shortcode( 'arc_event_instructors_legacy',       'arc_qb_sc_event_instructors_legacy' );
	add_shortcode( 'arc_event_instructor_slugs_legacy',  'arc_qb_sc_event_instructor_slugs_legacy' );
	add_shortcode( 'arc_event_instructor1_name',         'arc_qb_sc_event_instructor1_name' );
	add_shortcode( 'arc_event_instructor1_headshot_url', 'arc_qb_sc_event_instructor1_headshot_url' );
	add_shortcode( 'arc_event_instructor1_headshot_alt', 'arc_qb_sc_event_instructor1_headshot_alt' );
	add_shortcode( 'arc_event_instructor2_name',         'arc_qb_sc_event_instructor2_name' );
	add_shortcode( 'arc_event_instructor2_headshot_url', 'arc_qb_sc_event_instructor2_headshot_url' );
	add_shortcode( 'arc_event_instructor2_headshot_alt', 'arc_qb_sc_event_instructor2_headshot_alt' );
	add_shortcode( 'arc_event_instructor3_name',         'arc_qb_sc_event_instructor3_name' );
	add_shortcode( 'arc_event_instructor3_headshot_url', 'arc_qb_sc_event_instructor3_headshot_url' );
	add_shortcode( 'arc_event_instructor3_headshot_alt', 'arc_qb_sc_event_instructor3_headshot_alt' );
	add_shortcode( 'arc_event_is_multiday',              'arc_qb_sc_event_is_multiday' );
	add_shortcode( 'arc_event_is_multisession',          'arc_qb_sc_event_is_multisession' );
	add_shortcode( 'arc_event_field',                    'arc_qb_sc_event_field_generic' );
}
