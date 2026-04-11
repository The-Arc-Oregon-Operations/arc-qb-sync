/**
 * Arc Oregon Course Catalog — client-side tag filter.
 *
 * No jQuery dependency. Wires up the filter pill buttons to show/hide
 * Loop tiles based on their data-tags attribute.
 *
 * How it works:
 *  - Filter pills are rendered by [arc_course_filter_pills] shortcode (v2)
 *    and carry a data-filter="[slug]" attribute.
 *  - Each Loop tile has:
 *      class="arc-catalog-tile"         (set via Elementor Advanced → CSS Classes)
 *      data-tags="slug-a,slug-b,slug-c" (set via Elementor Advanced → Custom Attributes,
 *                                        sourced from the _course_tag_slugs post meta)
 *  - Filtering toggles the CSS class `arc-tile--hidden` on each tile.
 *
 * v2 changes from v1:
 *  - The embedded JSON script block (#arc-catalog-data) is no longer required.
 *    The filter reads data-tags directly from the DOM. If the element happens
 *    to be present (e.g., during a transitional period), it is parsed silently
 *    but is not used for filtering.
 *  - Bail condition no longer requires the JSON element — only pills and tiles
 *    must be present.
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		// ── Locate required DOM elements ──────────────────────────────────────

		var pills = document.querySelectorAll( '.arc-filter-pill' );
		var tiles = document.querySelectorAll( '.arc-catalog-tile' );

		if ( ! pills.length || ! tiles.length ) {
			return; // Nothing to do — filter UI or tile grid not present on this page.
		}

		// Parse the embedded JSON if present (legacy / transitional support).
		// Not used for filtering; retained for any future JS-side enhancements.
		var dataEl     = document.getElementById( 'arc-catalog-data' );
		var catalogData = []; // eslint-disable-line no-unused-vars
		if ( dataEl ) {
			try {
				catalogData = JSON.parse( dataEl.textContent );
			} catch ( e ) {
				// Non-fatal.
			}
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
