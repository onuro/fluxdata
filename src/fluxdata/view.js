/**
 * FluxData Block Frontend JavaScript
 * Handles async data fetching for better performance
 */

document.addEventListener( 'DOMContentLoaded', function() {
	// Find all FluxData blocks on the page
	const fluxDataBlocks = document.querySelectorAll( '.fluxdata-block' );
	
	if ( fluxDataBlocks.length === 0 ) {
		return;
	}
	
	// Process each block
	fluxDataBlocks.forEach( function( block ) {
		const dataType = block.getAttribute( 'data-type' );
		const humanReadable = block.getAttribute( 'data-human-readable' ) === 'true';
		const valueElement = block.querySelector( '.fluxdata-value' );
		
		if ( ! dataType || ! valueElement ) {
			return;
		}
		
		// Fetch data from REST API
		fetchFluxData( dataType, humanReadable )
			.then( function( data ) {
				// Update the display with real data
				valueElement.classList.remove( 'fluxdata-loading' );
				valueElement.innerHTML = escapeHtml( data );
			} )
			.catch( function( error ) {
				// Handle errors gracefully
				valueElement.classList.remove( 'fluxdata-loading' );
				valueElement.classList.add( 'fluxdata-error' );
				valueElement.innerHTML = 'Error loading data';
				
				// Log error for debugging (only in development)
				if ( window.console && window.console.error ) {
					console.error( 'FluxData fetch error:', error );
				}
			} );
	} );
} );

/**
 * Fetch data from FluxData REST API
 */
function fetchFluxData( type, humanReadable ) {
	const apiUrl = '/wp-json/fluxdata/v1/data/' + encodeURIComponent( type );
	const params = new URLSearchParams();
	
	if ( humanReadable ) {
		params.append( 'human_readable', 'true' );
	}
	
	const fullUrl = apiUrl + ( params.toString() ? '?' + params.toString() : '' );
	
	return fetch( fullUrl, {
		method: 'GET',
		headers: {
			'Accept': 'application/json',
		},
		credentials: 'same-origin'
	} )
	.then( function( response ) {
		if ( ! response.ok ) {
			throw new Error( 'Network response was not ok: ' + response.status );
		}
		return response.json();
	} )
	.then( function( result ) {
		if ( result.success && result.data ) {
			return result.data;
		} else {
			throw new Error( 'Invalid API response' );
		}
	} );
}

/**
 * Simple HTML escape function for security
 */
function escapeHtml( text ) {
	const div = document.createElement( 'div' );
	div.textContent = text;
	return div.innerHTML;
}
