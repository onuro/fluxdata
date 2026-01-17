<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

// Get the data type and formatting preference from block attributes
$data_type = isset( $attributes['dataType'] ) ? $attributes['dataType'] : 'nodecount';
$human_readable = isset( $attributes['humanReadable'] ) ? (bool) $attributes['humanReadable'] : false;

// Try to get cached data from WordPress options
$cached_data = get_option( 'fluxdata_cache', array() );
$server_value = '';

// Map data types to cache keys
$cache_key_map = array(
	'nodecount' => 'node_count',
	'runningapps' => 'running_apps',
	'totalcores' => 'total_cores',
	'totalram' => 'total_ram',
	'totalssd' => 'total_ssd'
);

$cache_key = isset( $cache_key_map[ $data_type ] ) ? $cache_key_map[ $data_type ] : $data_type;

if ( ! empty( $cached_data ) && isset( $cached_data[ $cache_key ]['data'] ) ) {
	$api_data = $cached_data[ $cache_key ]['data'];
	
	// Extract the actual value based on data type
	$raw_value = null;
	switch ( $data_type ) {
		case 'nodecount':
			if ( isset( $api_data['data']['total'] ) && is_numeric( $api_data['data']['total'] ) ) {
				$raw_value = $api_data['data']['total'];
			}
			break;
		case 'runningapps':
			if ( isset( $api_data['data'] ) && is_array( $api_data['data'] ) ) {
				$raw_value = count( $api_data['data'] );
			}
			break;
		case 'totalcores':
			if ( isset( $api_data['data']['total_cores'] ) && is_numeric( $api_data['data']['total_cores'] ) ) {
				$raw_value = $api_data['data']['total_cores'];
			}
			break;
		case 'totalram':
			if ( isset( $api_data['data']['total_ram'] ) && is_numeric( $api_data['data']['total_ram'] ) ) {
				$raw_value = $api_data['data']['total_ram'];
			}
			break;
		case 'totalssd':
			if ( isset( $api_data['data']['total_ssd'] ) && is_numeric( $api_data['data']['total_ssd'] ) ) {
				$raw_value = $api_data['data']['total_ssd'];
			}
			break;
	}
	
	if ( $raw_value !== null ) {
		if ( $human_readable ) {
			// Apply human-readable formatting
			switch ( $data_type ) {
				case 'totalram':
					// Convert GB to bytes for consistent formatting, then back to human readable
					$bytes = $raw_value * 1024 * 1024 * 1024; // GB to bytes
					$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
					$power = $bytes > 0 ? floor( log( $bytes, 1024 ) ) : 0;
					$server_value = number_format( $bytes / pow( 1024, $power ), 2, '.', ',' ) . ' ' . $units[ $power ];
					break;
				case 'totalssd':
					// Convert GB to bytes for consistent formatting, then back to human readable
					$bytes = $raw_value * 1024 * 1024 * 1024; // GB to bytes
					$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
					$power = $bytes > 0 ? floor( log( $bytes, 1024 ) ) : 0;
					$server_value = number_format( $bytes / pow( 1024, $power ), 2, '.', ',' ) . ' ' . $units[ $power ];
					break;
				default:
					// For other types, just format numbers with commas
					$server_value = number_format( (float) $raw_value );
					break;
			}
		} else {
			// Non-human readable formatting
			switch ( $data_type ) {
				case 'totalram':
				case 'totalssd':
					$server_value = number_format( $raw_value ) . ' GB';
					break;
				default:
					$server_value = number_format( $raw_value );
					break;
			}
		}
	}
}

// Generate wrapper attributes with server value if available
$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'fluxdata-block',
	'data-type' => esc_attr( $data_type ),
	'data-human-readable' => $human_readable ? 'true' : 'false',
	'data-server-value' => ! empty( $server_value ) ? esc_attr( $server_value ) : '',
) );

// Get placeholder text based on data type
$placeholder_text = '';
switch ( $data_type ) {
	case 'nodecount':
		$placeholder_text = esc_html__( '...', 'fluxdata' );
		break;
	case 'runningapps':
		$placeholder_text = esc_html__( '...', 'fluxdata' );
		break;
	case 'totalcores':
		$placeholder_text = esc_html__( '...', 'fluxdata' );
		break;
	case 'totalram':
		$placeholder_text = esc_html__( '...', 'fluxdata' );
		break;
	case 'totalssd':
		$placeholder_text = esc_html__( '...', 'fluxdata' );
		break;
	default:
		$placeholder_text = esc_html__( 'Loading...', 'fluxdata' );
}
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! empty( $server_value ) ) : ?>
		<span class="fluxdata-value" aria-live="polite">
			<?php echo esc_html( $server_value ); ?>
		</span>
	<?php else : ?>
		<span class="fluxdata-value fluxdata-loading" aria-live="polite">
			<span class="fluxdata-spinner"></span>
			<!-- <?php echo $placeholder_text; ?> -->
		</span>
	<?php endif; ?>
</div>
