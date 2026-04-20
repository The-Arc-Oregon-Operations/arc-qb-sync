<?php
/**
 * Arc Oregon QB Sync — Elementor Dynamic Tags
 *
 * Registers three custom Elementor dynamic tags — "Event Meta", "Course Meta",
 * and "Instructor Meta" — whose Field dropdowns are populated with labeled
 * options for every user-facing display field on the respective CPT.
 *
 * Why this exists: Elementor Pro's built-in "Post Custom Field" dynamic tag
 * populates its Key dropdown from get_post_custom_keys() against the current
 * post, which is unreliable in Theme Builder template context (empty dropdown
 * even with a real preview post and populated meta). This file replaces that
 * picker with three plugin-authored tags so template builders see a stable,
 * labeled list of options rather than having to type underscore-prefixed meta
 * keys into the Custom Key fallback field.
 *
 * Render strategy: each labeled option maps to an existing shortcode in
 * shortcodes-events-cpt.php / shortcodes-courses.php / shortcodes-instructors.php
 * so escaping, formatting, and post-type gating remain centralized. No direct
 * get_post_meta() fallbacks are needed here — all fields have a shortcode.
 *
 * Method signatures: get_name/get_title/get_group/get_categories/
 * register_controls/render carry typed return declarations to match
 * Elementor's current documented base-class signatures. get_group() returns
 * an array, not a string — Elementor expects an array of group names.
 * These signatures were QA'd in the summit-qb-sync v1.8.0 implementation
 * (2026-04-19) and are confirmed correct for Elementor 3.5+.
 *
 * Graceful degradation: the file returns early when Elementor is not loaded,
 * mirroring the pattern in elementor-queries.php. Deactivating Elementor or
 * Elementor Pro is a no-op.
 *
 * Fields excluded from dropdowns (by design):
 *   - Internal / QB ID fields (_arc_qb_*_id, slug)
 *   - Flag fields (is_multiday, is_multisession, offers_online, offers_inperson)
 *   - Legacy bridge fields (instructors_legacy, instructor_slugs_legacy)
 *   - Superseded image fields (image_url — use featured_image_url or hero_image_url)
 *
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Elementor\\Plugin' ) ) {
	return;
}

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

// ── Event Meta tag ────────────────────────────────────────────────────────────

/**
 * Dynamic tag: Event Meta.
 *
 * Appears in the "Post" group of Elementor's dynamic tag picker. The
 * Field dropdown exposes a curated, labeled list of arc_event display
 * fields. Render output is produced by calling the matching [event_*]
 * shortcode, which handles post-type gating and escaping.
 *
 * Fields marked "(HTML)" output formatted markup — place them in an HTML
 * widget or a Text Editor widget with raw HTML mode enabled.
 * Fields marked "(URL)" output a bare URL — wire to a button link or
 * image src attribute rather than a plain text widget.
 */
class Arc_QB_Event_Meta_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name(): string {
		return 'arc-qb-event-meta';
	}

	public function get_title(): string {
		return __( 'Event Meta', 'arc-qb-sync' );
	}

	public function get_group(): array {
		return array( 'post' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * Option key → [translated label, shortcode].
	 */
	private function get_fields_map(): array {
		return array(
			'title'              => array( __( 'Event title', 'arc-qb-sync' ),                '[event_title]' ),
			'dates'              => array( __( 'Dates', 'arc-qb-sync' ),                      '[event_dates]' ),
			'time'               => array( __( 'Time', 'arc-qb-sync' ),                       '[event_time]' ),
			'schedule'           => array( __( 'Schedule (days • dates)', 'arc-qb-sync' ),    '[event_schedule]' ),
			'days_of_week'       => array( __( 'Days of week', 'arc-qb-sync' ),               '[event_days_of_week]' ),
			'venue'              => array( __( 'Venue / location', 'arc-qb-sync' ),           '[event_venue]' ),
			'mode'               => array( __( 'Delivery mode', 'arc-qb-sync' ),              '[event_mode]' ),
			'length'             => array( __( 'Length', 'arc-qb-sync' ),                     '[event_length]' ),
			'description'        => array( __( 'Description (HTML)', 'arc-qb-sync' ),         '[event_description]' ),
			'price'              => array( __( 'Price / pricing info (HTML)', 'arc-qb-sync' ),'[event_price]' ),
			'reg_url'            => array( __( 'Registration URL', 'arc-qb-sync' ),           '[event_reg_url]' ),
			'flyer_url'          => array( __( 'Flyer URL', 'arc-qb-sync' ),                  '[event_flyer_url]' ),
			'featured_image_url' => array( __( 'Featured image URL', 'arc-qb-sync' ),         '[event_featured_image_url]' ),
			'hero_image_url'     => array( __( 'Hero image URL', 'arc-qb-sync' ),             '[event_hero_image_url]' ),
		);
	}

	protected function register_controls(): void {
		$fields  = $this->get_fields_map();
		$options = array( '' => __( 'Select…', 'arc-qb-sync' ) );
		foreach ( $fields as $k => $row ) {
			$options[ $k ] = $row[0];
		}

		$this->add_control(
			'key',
			array(
				'label'   => __( 'Field', 'arc-qb-sync' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);
	}

	public function render(): void {
		$key = $this->get_settings( 'key' );
		if ( ! $key ) {
			return;
		}

		$fields = $this->get_fields_map();
		if ( ! isset( $fields[ $key ] ) ) {
			return;
		}

		// Escaping and post-type gating are handled inside each shortcode.
		echo do_shortcode( $fields[ $key ][1] );
	}
}

// ── Course Meta tag ───────────────────────────────────────────────────────────

/**
 * Dynamic tag: Course Meta.
 *
 * Appears in the "Post" group. The Field dropdown exposes a curated,
 * labeled list of course CPT display fields. Render output is produced
 * by calling the matching [course_*] shortcode.
 *
 * Fields marked "(HTML)" output formatted markup.
 * Fields marked "(URL)" output a bare URL.
 * "Tags (HTML)" outputs <span class="arc-tag"> pill elements — place
 * in an HTML widget.
 */
class Arc_QB_Course_Meta_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name(): string {
		return 'arc-qb-course-meta';
	}

	public function get_title(): string {
		return __( 'Course Meta', 'arc-qb-sync' );
	}

	public function get_group(): array {
		return array( 'post' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * Option key → [translated label, shortcode].
	 */
	private function get_fields_map(): array {
		return array(
			'title'               => array( __( 'Course title', 'arc-qb-sync' ),                                  '[course_title]' ),
			'short_description'   => array( __( 'Short description (HTML)', 'arc-qb-sync' ),                      '[course_short_description]' ),
			'learning_objectives' => array( __( 'Learning objectives / full description (HTML)', 'arc-qb-sync' ), '[course_learning_objectives]' ),
			'length'              => array( __( 'Length (formatted, e.g. "6.5 hours")', 'arc-qb-sync' ),          '[course_length]' ),
			'hours'               => array( __( 'Hours (numeric)', 'arc-qb-sync' ),                               '[course_hours]' ),
			'delivery_method'     => array( __( 'Delivery method', 'arc-qb-sync' ),                               '[course_delivery_method]' ),
			'target_audience'     => array( __( 'Target audience (HTML)', 'arc-qb-sync' ),                        '[course_target_audience]' ),
			'category'            => array( __( 'Category', 'arc-qb-sync' ),                                      '[course_category]' ),
			'base_rate'           => array( __( 'Base rate', 'arc-qb-sync' ),                                     '[course_base_rate]' ),
			'details_url'         => array( __( 'Course details URL', 'arc-qb-sync' ),                            '[course_details_url]' ),
			'request_url'         => array( __( 'Org training request URL', 'arc-qb-sync' ),                      '[course_request_url]' ),
			'attribution'         => array( __( 'Attribution', 'arc-qb-sync' ),                                   '[course_attribution]' ),
			'featured_image_url'  => array( __( 'Featured image URL', 'arc-qb-sync' ),                            '[course_featured_image_url]' ),
			'hero_image_url'      => array( __( 'Hero image URL', 'arc-qb-sync' ),                                '[course_hero_image_url]' ),
			'tags'                => array( __( 'Tags (HTML pill spans)', 'arc-qb-sync' ),                         '[course_tags]' ),
		);
	}

	protected function register_controls(): void {
		$fields  = $this->get_fields_map();
		$options = array( '' => __( 'Select…', 'arc-qb-sync' ) );
		foreach ( $fields as $k => $row ) {
			$options[ $k ] = $row[0];
		}

		$this->add_control(
			'key',
			array(
				'label'   => __( 'Field', 'arc-qb-sync' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);
	}

	public function render(): void {
		$key = $this->get_settings( 'key' );
		if ( ! $key ) {
			return;
		}

		$fields = $this->get_fields_map();
		if ( ! isset( $fields[ $key ] ) ) {
			return;
		}

		// Escaping and post-type gating are handled inside each shortcode.
		echo do_shortcode( $fields[ $key ][1] );
	}
}

// ── Instructor Meta tag ───────────────────────────────────────────────────────

/**
 * Dynamic tag: Instructor Meta.
 *
 * Appears in the "Post" group. The Field dropdown exposes a curated,
 * labeled list of instructor CPT display fields. Render output is
 * produced by calling the matching [instructor_*] shortcode.
 *
 * Fields marked "(HTML)" output formatted markup — bio arrives from QB
 * pre-sanitized; trainer_roles arrives with <ul><li> markup already applied.
 * Fields marked "(URL)" output a bare URL.
 */
class Arc_QB_Instructor_Meta_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name(): string {
		return 'arc-qb-instructor-meta';
	}

	public function get_title(): string {
		return __( 'Instructor Meta', 'arc-qb-sync' );
	}

	public function get_group(): array {
		return array( 'post' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * Option key → [translated label, shortcode].
	 */
	private function get_fields_map(): array {
		return array(
			'name'          => array( __( 'Full name', 'arc-qb-sync' ),              '[instructor_name]' ),
			'first_name'    => array( __( 'First name', 'arc-qb-sync' ),             '[instructor_first_name]' ),
			'last_name'     => array( __( 'Last name', 'arc-qb-sync' ),              '[instructor_last_name]' ),
			'pronouns'      => array( __( 'Pronouns', 'arc-qb-sync' ),               '[instructor_pronouns]' ),
			'title'         => array( __( 'Title / position', 'arc-qb-sync' ),       '[instructor_title]' ),
			'organization'  => array( __( 'Organization', 'arc-qb-sync' ),           '[instructor_organization]' ),
			'credentials'   => array( __( 'Credentials', 'arc-qb-sync' ),            '[instructor_credentials]' ),
			'bio'           => array( __( 'Bio (HTML)', 'arc-qb-sync' ),             '[instructor_bio]' ),
			'trainer_roles' => array( __( 'Trainer role(s) (HTML)', 'arc-qb-sync' ), '[instructor_trainer_roles]' ),
			'headshot_url'  => array( __( 'Headshot image URL', 'arc-qb-sync' ),     '[instructor_headshot_url]' ),
			'contact_url'   => array( __( 'Contact URL', 'arc-qb-sync' ),            '[instructor_contact_url]' ),
		);
	}

	protected function register_controls(): void {
		$fields  = $this->get_fields_map();
		$options = array( '' => __( 'Select…', 'arc-qb-sync' ) );
		foreach ( $fields as $k => $row ) {
			$options[ $k ] = $row[0];
		}

		$this->add_control(
			'key',
			array(
				'label'   => __( 'Field', 'arc-qb-sync' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);
	}

	public function render(): void {
		$key = $this->get_settings( 'key' );
		if ( ! $key ) {
			return;
		}

		$fields = $this->get_fields_map();
		if ( ! isset( $fields[ $key ] ) ) {
			return;
		}

		// Escaping and post-type gating are handled inside each shortcode.
		echo do_shortcode( $fields[ $key ][1] );
	}
}

// ── Registration ──────────────────────────────────────────────────────────────

/**
 * Register all three tags with Elementor.
 *
 * Uses the elementor/dynamic_tags/register hook (Elementor 3.5+). The
 * legacy elementor/dynamic_tags/register_tags hook is not supported.
 */
add_action( 'elementor/dynamic_tags/register', function ( $dynamic_tags_manager ) {
	$dynamic_tags_manager->register( new Arc_QB_Event_Meta_Tag() );
	$dynamic_tags_manager->register( new Arc_QB_Course_Meta_Tag() );
	$dynamic_tags_manager->register( new Arc_QB_Instructor_Meta_Tag() );
} );
