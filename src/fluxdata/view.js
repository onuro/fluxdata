/**
 * FluxData Block Frontend JavaScript
 * Uses WordPress AJAX with PHP formatting functions
 */

document.addEventListener( 'DOMContentLoaded', function() {
	// Find all FluxData blocks on the page
	const fluxDataBlocks = document.querySelectorAll( '.fluxdata-block' );
	
	if ( fluxDataBlocks.length === 0 ) {
		return;
	}

	// Cache to avoid duplicate AJAX calls
	const dataCache = {};
	
	// Process each block
	fluxDataBlocks.forEach( function( block ) {
		const dataType = block.getAttribute( 'data-type' );
		const humanReadable = block.getAttribute( 'data-human-readable' ) === 'true';
		const valueElement = block.querySelector( '.fluxdata-value' );
		
		if ( ! dataType || ! valueElement ) {
			return;
		}
		
		// Check cache first
		const cacheKey = dataType + '_' + humanReadable;
		if ( dataCache[cacheKey] ) {
			valueElement.classList.remove( 'fluxdata-loading' );
			valueElement.innerHTML = escapeHtml( dataCache[cacheKey] );
			return;
		}
		
		// Fetch data via WordPress AJAX
		fetchFluxDataAjax( dataType, humanReadable )
			.then( function( data ) {
				// Cache the result
				dataCache[cacheKey] = data;
				
				// Update all blocks with same data type
				document.querySelectorAll( '.fluxdata-block[data-type="' + dataType + '"][data-human-readable="' + humanReadable + '"]' ).forEach( function( sameTypeBlock ) {
					const sameValueElement = sameTypeBlock.querySelector( '.fluxdata-value' );
					if ( sameValueElement ) {
						sameValueElement.classList.remove( 'fluxdata-loading' );
						sameValueElement.innerHTML = escapeHtml( data );
					}
				} );
			} )
			.catch( function( error ) {
				// Handle errors gracefully
				valueElement.classList.remove( 'fluxdata-loading' );
				valueElement.classList.add( 'fluxdata-error' );
				valueElement.innerHTML = 'Error loading data';
				
				// Log error for debugging
				if ( window.console && window.console.error ) {
					console.error( 'FluxData fetch error:', error );
				}
			} );
	} );
} );

/**
 * Fetch data via WordPress AJAX
 */
function fetchFluxDataAjax( type, humanReadable ) {
	const formData = new FormData();
	formData.append( 'action', 'fluxdata_get' );
	formData.append( 'type', type );
	formData.append( 'human_readable', humanReadable ? 'true' : 'false' );
	formData.append( 'nonce', fluxdataAjax.nonce );
	
	return fetch( fluxdataAjax.ajaxurl, {
		method: 'POST',
		body: formData,
		credentials: 'same-origin'
	} )
	.then( function( response ) {
		if ( ! response.ok ) {
			throw new Error( 'Network response was not ok: ' + response.status );
		}
		return response.json();
	} )
	.then( function( result ) {
		if ( ! result.success ) {
			throw new Error( result.data || 'Unknown error' );
		}
		return result.data;
	} );
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml( text ) {
	const div = document.createElement( 'div' );
	div.textContent = text;
	return div.innerHTML;
}
