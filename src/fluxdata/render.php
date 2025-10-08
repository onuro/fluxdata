<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

// Get the data type and formatting preference from block attributes
$data_type = isset( $attributes['dataType'] ) ? $attributes['dataType'] : 'nodecount';
$human_readable = isset( $attributes['humanReadable'] ) ? (bool) $attributes['humanReadable'] : false;

// Initialize variables
$display_value = '';
$error_message = '';

// Fetch data based on selected type
if ( 'nodecount' === $data_type ) {
	$node_data = fluxdata_get_node_count();
	
	if ( is_wp_error( $node_data ) ) {
		$error_message = esc_html( $node_data->get_error_message() );
	} elseif ( isset( $node_data['data']['total'] ) && is_numeric( $node_data['data']['total'] ) ) {
		$display_value = fluxdata_format_number( $node_data['data']['total'], $human_readable );
	} else {
		$error_message = esc_html__( 'Unable to retrieve node count data', 'fluxdata' );
	}
} elseif ( 'runningapps' === $data_type ) {
	$apps_data = fluxdata_get_running_apps_count();
	
	if ( is_wp_error( $apps_data ) ) {
		$error_message = esc_html( $apps_data->get_error_message() );
	} elseif ( isset( $apps_data['data'] ) && is_array( $apps_data['data'] ) ) {
		$display_value = fluxdata_format_number( count( $apps_data['data'] ), $human_readable );
	} else {
		$error_message = esc_html__( 'Unable to retrieve running apps data', 'fluxdata' );
	}
} elseif ( 'totalcores' === $data_type ) {
	$cores_data = fluxdata_get_total_cores();
	
	if ( is_wp_error( $cores_data ) ) {
		$error_message = esc_html( $cores_data->get_error_message() );
	} elseif ( isset( $cores_data['data']['total_cores'] ) && is_numeric( $cores_data['data']['total_cores'] ) ) {
		$display_value = fluxdata_format_number( $cores_data['data']['total_cores'], $human_readable );
	} else {
		$error_message = esc_html__( 'Unable to retrieve total cores data', 'fluxdata' );
	}
} elseif ( 'totalram' === $data_type ) {
	$ram_data = fluxdata_get_total_ram();
	
	if ( is_wp_error( $ram_data ) ) {
		$error_message = esc_html( $ram_data->get_error_message() );
	} elseif ( isset( $ram_data['data']['total_ram'] ) && is_numeric( $ram_data['data']['total_ram'] ) ) {
		$display_value = fluxdata_format_ram( $ram_data['data']['total_ram'], $human_readable );
	} else {
		$error_message = esc_html__( 'Unable to retrieve total RAM data', 'fluxdata' );
	}
} elseif ( 'totalssd' === $data_type ) {
	$ssd_data = fluxdata_get_total_ssd();
	
	if ( is_wp_error( $ssd_data ) ) {
		$error_message = esc_html( $ssd_data->get_error_message() );
	} elseif ( isset( $ssd_data['data']['total_ssd'] ) && is_numeric( $ssd_data['data']['total_ssd'] ) ) {
		$display_value = fluxdata_format_ssd( $ssd_data['data']['total_ssd'], $human_readable );
	} else {
		$error_message = esc_html__( 'Unable to retrieve total SSD data', 'fluxdata' );
	}
}
// Generate wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! empty( $error_message ) ) : ?>
		<div class="fluxdata-error">
			<?php echo esc_html__( 'Error: ', 'fluxdata' ) . $error_message; ?>
		</div>
	<?php elseif ( ! empty( $display_value ) ) : ?>
		<span class="fluxdata-value">
			<?php echo esc_html( $display_value ); ?>
	</span>
	<?php else : ?>
		<div class="fluxdata-loading">
			<?php echo esc_html__( 'Loading...', 'fluxdata' ); ?>
		</div>
	<?php endif; ?>
</div>
