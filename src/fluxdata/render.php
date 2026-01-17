<?php
/**
 * FluxData Block Render Template
 *
 * Server-renders cached value immediately.
 * JS checks timestamp and refreshes if stale (for cached page scenarios).
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

// Get the data type and formatting preference from block attributes
$data_type = isset( $attributes['dataType'] ) ? $attributes['dataType'] : 'nodecount';
$human_readable = isset( $attributes['humanReadable'] ) ? (bool) $attributes['humanReadable'] : false;

// Get display value from cache (always returns a value - from cache, fallback, or placeholder)
$display_value = fluxdata_get_display_value( $data_type, $human_readable );

// Get cache timestamp for JS staleness check
$cache_time = fluxdata_get_cache_timestamp();

// Generate wrapper attributes with data for JS freshness check
$wrapper_attributes = get_block_wrapper_attributes( array(
	'class'               => 'fluxdata-block',
	'data-type'           => esc_attr( $data_type ),
	'data-human-readable' => $human_readable ? 'true' : 'false',
	'data-cache-time'     => esc_attr( $cache_time ),
) );
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<span class="fluxdata-value"><?php echo esc_html( $display_value ); ?></span>
</div>
