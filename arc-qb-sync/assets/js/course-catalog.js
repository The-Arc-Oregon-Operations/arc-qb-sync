/**
 * Arc Oregon Course Catalog — client-side tag filter.
 *
 * No jQuery dependency. Reads course data from the embedded JSON block
 * (#arc-catalog-data) and wires up the filter pill buttons.
 *
 * Filtering works by toggling the CSS class `arc-tile--hidden` on each
 * .arc-catalog-tile element so Elementor / theme CSS can override if needed.
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		// ── Locate required DOM elements ──────────────────────────────────────

		var dataEl   = document.getElementById( 'arc-catalog-data' );
		var pills    = document.querySelectorAll( '.arc-filter-pill' );
		var tiles    = document.querySelectorAll( '.arc-catalog-tile' );

		if ( ! dataEl || ! pills.length || ! tiles.length ) {
			return; // Nothing to do — shortcode output not present on this page.
		}

		// Parse the embedded JSON (used for potential future JS-side enhancements).
		var catalogData = [];
		try {
			catalogData = JSON.parse( dataEl.textContent );
		} catch ( e ) {
			// Non-fatal: filtering via data-tags attribute still works without it.
		}

		// ── Pill click handler ────────────────────────────────────────────────

		pills.forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {

				// Update active state.
				pills.forEach( function ( p ) {
					p.classList.remove( 'is-active' );
				} );
				pill.classList.add( 'is-active' );

				var filter = pill.getAttribute( 'data-filter' );

				// Show / hide tiles.
				tiles.forEach( function ( tile ) {
					if ( filter === 'all' ) {
						tile.classList.remove( 'arc-tile--hidden' );
						return;
					}

					// data-tags is a comma-separated list of sanitized slugs.
					var tagAttr = tile.getAttribute( 'data-tags' ) || '';
					var tagList = tagAttr.split( ',' ).map( function ( t ) {
						return t.trim();
					} );

					if ( tagList.indexOf( filter ) !== -1 ) {
						tile.classList.remove( 'arc-tile--hidden' );
					} else {
						tile.classList.add( 'arc-tile--hidden' );
					}
				} );
			} );
		} );
	} );
}() );
