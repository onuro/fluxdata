/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

/**
 * WordPress components for the block editor.
 */
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object} props               The block props.
 * @param {Object} props.attributes    The block attributes.
 * @param {Function} props.setAttributes The function to update block attributes.
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { dataType, humanReadable } = attributes;

	const dataTypeOptions = [
		{
			label: __( 'Flux Node Count', 'fluxdata' ),
			value: 'nodecount'
		},
		{
			label: __( 'Running Apps Count', 'fluxdata' ),
			value: 'runningapps'
		},
		{
			label: __( 'Total CPU Cores', 'fluxdata' ),
			value: 'totalcores'
		},
		{
			label: __( 'Total RAM', 'fluxdata' ),
			value: 'totalram'
		},
		{
			label: __( 'Total SSD', 'fluxdata' ),
			value: 'totalssd'
		}
	];

	const formatNumber = ( number, isHumanReadable ) => {
		if ( ! isHumanReadable ) {
			return number.toLocaleString();
		}
		
		if ( number >= 1000000 ) {
			return ( number / 1000000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'M';
		}
		if ( number >= 1000 ) {
			return ( number / 1000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'K';
		}
		return number.toString();
	};

	const formatRam = ( ramGb, isHumanReadable ) => {
		if ( ! isHumanReadable ) {
			return ramGb.toLocaleString() + ' GB';
		}
		
		// Convert GB to TB (1 TB = 1024 GB)
		const ramTb = ramGb / 1024;
		
		if ( ramTb >= 1 ) {
			const formatted = Math.round( ramTb * 10 ) / 10; // Round to 1 decimal place
			return ( formatted === Math.floor( formatted ) ) ? Math.floor( formatted ) + ' TB' : formatted + ' TB';
		}
		
		return ramGb.toLocaleString() + ' GB';
	};

	const formatSsd = ( ssdGb, isHumanReadable ) => {
		if ( ! isHumanReadable ) {
			return ssdGb.toLocaleString() + ' GB';
		}
		
		// Convert GB to PB (1 PB = 1000 * 1000 GB) - using decimal conversion for storage
		const ssdPb = ssdGb / ( 1000 * 1000 );
		
		if ( ssdPb >= 1 ) {
			const formatted = Math.round( ssdPb * 10 ) / 10; // Round to 1 decimal place
			return ( formatted === Math.floor( formatted ) ) ? Math.floor( formatted ) + ' PB' : formatted + ' PB';
		}
		
		// Convert GB to TB (1 TB = 1000 GB) - using decimal conversion for storage
		const ssdTb = ssdGb / 1000;
		
		if ( ssdTb >= 1 ) {
			const formatted = Math.round( ssdTb * 10 ) / 10; // Round to 1 decimal place
			return ( formatted === Math.floor( formatted ) ) ? Math.floor( formatted ) + ' TB' : formatted + ' TB';
		}
		
		return ssdGb.toLocaleString() + ' GB';
	};

	const getPreviewText = () => {
		switch ( dataType ) {
			case 'nodecount':
				const nodeCount = 8491; // Updated to current realistic count
				return formatNumber( nodeCount, humanReadable );
			case 'runningapps':
				const appsCount = 1234;
				return formatNumber( appsCount, humanReadable );
			case 'totalcores':
				const totalCores = 91370; // Based on Flux documentation
				return formatNumber( totalCores, humanReadable );
			case 'totalram':
				const totalRam = 732160; // Estimated total RAM in GB based on Flux network
				return formatRam( totalRam, humanReadable );
			case 'totalssd':
				const totalSsd = 7321600; // Estimated total SSD in GB based on Flux network (10x RAM estimate)
				return formatSsd( totalSsd, humanReadable );
			default:
				return __( 'Data', 'fluxdata' );
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Flux Data Settings', 'fluxdata' ) }>
					<SelectControl
						label={ __( 'Data Type', 'fluxdata' ) }
						value={ dataType }
						options={ dataTypeOptions }
						onChange={ ( value ) => setAttributes( { dataType: value } ) }
						help={ __( 'Choose which Flux network data to display.', 'fluxdata' ) }
					/>
					<ToggleControl
						label={ __( 'Human Readable Format', 'fluxdata' ) }
						checked={ humanReadable }
						onChange={ ( value ) => setAttributes( { humanReadable: value } ) }
						help={ __( 'Display numbers in human readable format (e.g., 8.4K instead of 8,491).', 'fluxdata' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
					{ getPreviewText() }
			</div>
		</>
	);
}
