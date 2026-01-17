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
			return ( formatted === Math.floor( formatted ) ) ? Math.floor( formatted ) + 'TB' : formatted + 'TB';
		}

		return ramGb.toLocaleString() + 'GB';
	};

	const formatSsd = ( ssdGb, isHumanReadable ) => {
		if ( ! isHumanReadable ) {
			return ssdGb.toLocaleString() + ' GB';
		}

		// Convert GB to PB (1 PB = 1000 * 1000 GB) - using decimal conversion for storage
		const ssdPb = ssdGb / ( 1000 * 1000 );

		if ( ssdPb >= 1 ) {
			const formatted = Math.round( ssdPb * 10 ) / 10; // Round to 1 decimal place
			return ( formatted === Math.floor( formatted ) ) ? Math.floor( formatted ) + 'PB' : formatted + 'PB';
		}

		// Convert GB to TB (1 TB = 1000 GB) - using decimal conversion for storage
		const ssdTb = ssdGb / 1000;

		if ( ssdTb >= 1 ) {
			const formatted = Math.round( ssdTb * 10 ) / 10; // Round to 1 decimal place
			return ( formatted === Math.floor( formatted ) ) ? Math.floor( formatted ) + 'TB' : formatted + 'TB';
		}

		return ssdGb.toLocaleString() + 'GB';
	};

	const getPreviewText = () => {
		// Preview values based on tier defaults calculation:
		// CUMULUS: ~4800 nodes × (4 cores, 8 GB RAM, 220 GB SSD)
		// NIMBUS: ~1800 nodes × (8 cores, 32 GB RAM, 440 GB SSD)
		// STRATUS: ~1600 nodes × (16 cores, 64 GB RAM, 880 GB SSD)
		switch ( dataType ) {
			case 'nodecount':
				const nodeCount = 8200; // ~4800 + 1800 + 1600
				return formatNumber( nodeCount, humanReadable );
			case 'runningapps':
				const appsCount = 150;
				return formatNumber( appsCount, humanReadable );
			case 'totalcores':
				const totalCores = 59200; // (4800×4) + (1800×8) + (1600×16)
				return formatNumber( totalCores, humanReadable );
			case 'totalram':
				const totalRam = 198400; // (4800×8) + (1800×32) + (1600×64) GB
				return formatRam( totalRam, humanReadable );
			case 'totalssd':
				const totalSsd = 3256000; // (4800×220) + (1800×440) + (1600×880) GB
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
