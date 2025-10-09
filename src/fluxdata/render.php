<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

// Get the data type and formatting preference from block attributes
$data_type = isset( $attributes['dataType'] ) ? $attributes['dataType'] : 'nodecount';
$human_readable = isset( $attributes['humanReadable'] ) ? (bool) $attributes['humanReadable'] : false;

// Generate wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'fluxdata-block',
	'data-type' => esc_attr( $data_type ),
	'data-human-readable' => $human_readable ? 'true' : 'false',
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
	<span class="fluxdata-value fluxdata-loading" aria-live="polite">
		<span class="fluxdata-spinner"></span>
		<!-- <?php echo $placeholder_text; ?> -->
	</span>
</div>
