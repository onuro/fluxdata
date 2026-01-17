/**
 * FluxData Block Frontend JavaScript
 *
 * Checks if server-rendered data is stale (for cached pages).
 * Only fetches fresh data from WordPress cache if needed.
 */

document.addEventListener( 'DOMContentLoaded', function() {
	const blocks = document.querySelectorAll( '.fluxdata-block' );

	if ( blocks.length === 0 || typeof fluxdataAjax === 'undefined' ) {
		return;
	}

	blocks.forEach( function( block ) {
		const cacheTime = parseInt( block.getAttribute( 'data-cache-time' ), 10 );
		const now = Math.floor( Date.now() / 1000 );
		const age = now - cacheTime;

		// Only fetch if data is stale (older than threshold)
		if ( age > fluxdataAjax.staleThreshold ) {
			refreshBlock( block );
		}
	} );
} );

/**
 * Refresh a single block with fresh data from WordPress cache
 */
function refreshBlock( block ) {
	const dataType = block.getAttribute( 'data-type' );
	const humanReadable = block.getAttribute( 'data-human-readable' );

	const formData = new FormData();
	formData.append( 'action', 'fluxdata_get' );
	formData.append( 'type', dataType );
	formData.append( 'human_readable', humanReadable );

	fetch( fluxdataAjax.ajaxurl, {
		method: 'POST',
		body: formData,
		credentials: 'same-origin'
	} )
	.then( function( response ) {
		return response.json();
	} )
	.then( function( result ) {
		if ( result.success && result.data && result.data.value ) {
			const valueEl = block.querySelector( '.fluxdata-value' );
			if ( valueEl ) {
				valueEl.textContent = result.data.value;
			}
			// Update cache time so we don't refetch on SPA navigation
			block.setAttribute( 'data-cache-time', result.data.cache_time );
		}
	} )
	.catch( function( error ) {
		// Silently fail - server-rendered value remains visible
		if ( window.console && window.console.error ) {
			console.error( 'FluxData refresh error:', error );
		}
	} );
}
