<?php
/**
 * Plugin Name:       FluxData
 * Description:       Display Flux network data in your WordPress site.
 * Requires at least: 6.6
 * Requires PHP:      7.2
 * Version:           0.1.0
 * Author:            Onur Oztaskiran
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fluxdata
 *
 * @package FluxData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Include Flux API functions
require_once plugin_dir_path( __FILE__ ) . 'includes/flux-api-functions.php';

/**
 * Register REST API endpoint for async data fetching
 */
function fluxdata_register_rest_routes() {
	register_rest_route( 'fluxdata/v1', '/data/(?P<type>[a-zA-Z0-9_-]+)', array(
		'methods'             => 'GET',
		'callback'            => 'fluxdata_rest_get_data',
		'permission_callback' => '__return_true', // Public endpoint
		'args'                => array(
			'type' => array(
				'required'          => true,
				'validate_callback' => function( $param ) {
					return in_array( $param, array( 'nodecount', 'runningapps', 'totalcores', 'totalram', 'totalssd' ) );
				},
			),
			'human_readable' => array(
				'required'          => false,
				'default'           => false,
				'validate_callback' => function( $param ) {
					return is_bool( $param ) || in_array( $param, array( 'true', 'false', '1', '0' ) );
				},
				'sanitize_callback' => function( $param ) {
					return filter_var( $param, FILTER_VALIDATE_BOOLEAN );
				},
			),
		),
	) );
}
add_action( 'rest_api_init', 'fluxdata_register_rest_routes' );

/**
 * REST API callback to get Flux data
 */
function fluxdata_rest_get_data( $request ) {
	$type = $request->get_param( 'type' );
	$human_readable = $request->get_param( 'human_readable' );
	
	$data = null;
	$error = null;
	
	try {
		switch ( $type ) {
			case 'nodecount':
				$api_data = fluxdata_get_node_count();
				if ( is_wp_error( $api_data ) ) {
					return $api_data;
				}
				if ( isset( $api_data['data']['total'] ) && is_numeric( $api_data['data']['total'] ) ) {
					$data = $api_data['data']['total'];
					if ( $human_readable ) {
						$data = fluxdata_format_number( $data );
					} else {
						$data = number_format( $data );
					}
				}
				break;
				
			case 'runningapps':
				$api_data = fluxdata_get_running_apps_count();
				if ( is_wp_error( $api_data ) ) {
					return $api_data;
				}
				if ( isset( $api_data['data'] ) && is_array( $api_data['data'] ) ) {
					$data = count( $api_data['data'] );
					if ( $human_readable ) {
						$data = fluxdata_format_number( $data );
					} else {
						$data = number_format( $data );
					}
				}
				break;
				
			case 'totalcores':
				$api_data = fluxdata_get_total_cores();
				if ( is_wp_error( $api_data ) ) {
					return $api_data;
				}
				if ( isset( $api_data['data']['total_cores'] ) && is_numeric( $api_data['data']['total_cores'] ) ) {
					$data = $api_data['data']['total_cores'];
					if ( $human_readable ) {
						$data = fluxdata_format_number( $data );
					} else {
						$data = number_format( $data );
					}
				}
				break;
				
			case 'totalram':
				$api_data = fluxdata_get_total_ram();
				if ( is_wp_error( $api_data ) ) {
					return $api_data;
				}
				if ( isset( $api_data['data']['total_ram'] ) && is_numeric( $api_data['data']['total_ram'] ) ) {
					$ram_value = $api_data['data']['total_ram'];
					if ( $human_readable ) {
						$data = fluxdata_format_ram( $ram_value, true );
					} else {
						$data = number_format( $ram_value ) . ' GB';
					}
				}
				break;
				
			case 'totalssd':
				$api_data = fluxdata_get_total_ssd();
				if ( is_wp_error( $api_data ) ) {
					return $api_data;
				}
				if ( isset( $api_data['data']['total_ssd'] ) && is_numeric( $api_data['data']['total_ssd'] ) ) {
					$ssd_value = $api_data['data']['total_ssd'];
					if ( $human_readable ) {
						$data = fluxdata_format_ssd( $ssd_value );
					} else {
						$data = number_format( $ssd_value ) . ' GB';
					}
				}
				break;
				
			default:
				return new WP_Error( 'invalid_type', 'Invalid data type requested', array( 'status' => 400 ) );
		}
		
		if ( $data === null || $data === false ) {
			return new WP_Error( 'data_unavailable', 'Data temporarily unavailable', array( 'status' => 503 ) );
		}
		
		return rest_ensure_response( array(
			'success' => true,
			'data'    => $data,
			'type'    => $type,
			'human_readable' => $human_readable,
		) );
		
	} catch ( Exception $e ) {
		return new WP_Error( 'server_error', 'Internal server error', array( 'status' => 500 ) );
	}
}

/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 * Behind the scenes, it also registers all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function fluxdata_fluxdata_block_init() {
	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block metadata.
	 * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 */
	if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
		wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
		return;
	}

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
		wp_register_block_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
	}
	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		register_block_type( __DIR__ . "/build/{$block_type}" );
	}
}
add_action( 'init', 'fluxdata_fluxdata_block_init' );
