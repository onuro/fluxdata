<?php
/**
 * Plugin Name:       FluxData
 * Description:       Display Flux network data in your WordPress site.
 * Requires at least: 6.6
 * Requires PHP:      7.2
 * Version:           0.1.6
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
 * Simple AJAX handler for FluxData
 */
function fluxdata_ajax_handler() {
	// Verify nonce for security
	if ( ! wp_verify_nonce( $_POST['nonce'], 'fluxdata_nonce' ) ) {
		wp_die( 'Security check failed' );
	}
	
	$type = sanitize_text_field( $_POST['type'] );
	$human_readable = isset( $_POST['human_readable'] ) && $_POST['human_readable'] === 'true';
	
	$data = null;
	
	switch ( $type ) {
		case 'nodecount':
			$api_data = fluxdata_get_node_count();
			if ( is_wp_error( $api_data ) ) {
				// Try to get cached data directly from options on error
				$cache_data = get_option( 'fluxdata_cache', array() );
				if ( isset( $cache_data['node_count']['data'] ) ) {
					$api_data = $cache_data['node_count']['data'];
				} else {
					wp_send_json_error( $api_data->get_error_message() );
				}
			}
			if ( isset( $api_data['data']['total'] ) && is_numeric( $api_data['data']['total'] ) ) {
				$value = $api_data['data']['total'];
				$data = $human_readable ? fluxdata_format_number( $value ) : number_format( $value );
			}
			break;
			
		case 'runningapps':
			$api_data = fluxdata_get_running_apps_count();
			if ( is_wp_error( $api_data ) ) {
				// Try to get cached data directly from options on error
				$cache_data = get_option( 'fluxdata_cache', array() );
				if ( isset( $cache_data['running_apps']['data'] ) ) {
					$api_data = $cache_data['running_apps']['data'];
				} else {
					wp_send_json_error( $api_data->get_error_message() );
				}
			}
			if ( isset( $api_data['data'] ) && is_array( $api_data['data'] ) ) {
				$value = count( $api_data['data'] );
				$data = $human_readable ? fluxdata_format_number( $value ) : number_format( $value );
			}
			break;
			
		case 'totalcores':
			$api_data = fluxdata_get_total_cores();
			if ( is_wp_error( $api_data ) ) {
				// Try to get cached data directly from options on error
				$cache_data = get_option( 'fluxdata_cache', array() );
				if ( isset( $cache_data['total_cores']['data'] ) ) {
					$api_data = $cache_data['total_cores']['data'];
				} else {
					wp_send_json_error( $api_data->get_error_message() );
				}
			}
			if ( isset( $api_data['data']['total_cores'] ) && is_numeric( $api_data['data']['total_cores'] ) ) {
				$value = $api_data['data']['total_cores'];
				$data = $human_readable ? fluxdata_format_number( $value ) : number_format( $value );
			}
			break;
			
		case 'totalram':
			$api_data = fluxdata_get_total_ram();
			if ( is_wp_error( $api_data ) ) {
				// Try to get cached data directly from options on error
				$cache_data = get_option( 'fluxdata_cache', array() );
				if ( isset( $cache_data['total_ram']['data'] ) ) {
					$api_data = $cache_data['total_ram']['data'];
				} else {
					wp_send_json_error( $api_data->get_error_message() );
				}
			}
			if ( isset( $api_data['data']['total_ram'] ) && is_numeric( $api_data['data']['total_ram'] ) ) {
				$value = $api_data['data']['total_ram'];
				$data = $human_readable ? fluxdata_format_ram( $value, true ) : number_format( $value ) . ' GB';
			}
			break;
			
		case 'totalssd':
			$api_data = fluxdata_get_total_ssd();
			if ( is_wp_error( $api_data ) ) {
				// Try to get cached data directly from options on error
				$cache_data = get_option( 'fluxdata_cache', array() );
				if ( isset( $cache_data['total_ssd']['data'] ) ) {
					$api_data = $cache_data['total_ssd']['data'];
				} else {
					wp_send_json_error( $api_data->get_error_message() );
				}
			}
			if ( isset( $api_data['data']['total_ssd'] ) && is_numeric( $api_data['data']['total_ssd'] ) ) {
				$value = $api_data['data']['total_ssd'];
				$data = $human_readable ? fluxdata_format_ssd( $value ) : number_format( $value ) . ' GB';
			}
			break;
			
		default:
			wp_send_json_error( 'Invalid data type' );
	}
	
	if ( $data === null ) {
		wp_send_json_error( 'Data not available' );
	}
	
	wp_send_json_success( $data );
}

/**
 * AJAX handler for cached FluxData fallback
 */
function fluxdata_cached_ajax_handler() {
	// Verify nonce for security
	if ( ! wp_verify_nonce( $_POST['nonce'], 'fluxdata_nonce' ) ) {
		wp_die( 'Security check failed' );
	}
	
	$type = sanitize_text_field( $_POST['type'] );
	$human_readable = isset( $_POST['human_readable'] ) && $_POST['human_readable'] === 'true';
	
	// Get cached data directly from options
	$cache_data = get_option( 'fluxdata_cache', array() );
	
	$cache_key_map = array(
		'nodecount' => 'node_count',
		'runningapps' => 'running_apps',
		'totalcores' => 'total_cores',
		'totalram' => 'total_ram',
		'totalssd' => 'total_ssd'
	);
	
	if ( ! isset( $cache_key_map[ $type ] ) ) {
		wp_send_json_error( 'Invalid data type' );
	}
	
	$cache_key = $cache_key_map[ $type ];
	
	if ( ! isset( $cache_data[ $cache_key ]['data'] ) ) {
		wp_send_json_error( 'No cached data available' );
	}
	
	$api_data = $cache_data[ $cache_key ]['data'];
	$data = null;
	
	switch ( $type ) {
		case 'nodecount':
			if ( isset( $api_data['data']['total'] ) && is_numeric( $api_data['data']['total'] ) ) {
				$value = $api_data['data']['total'];
				$data = $human_readable ? fluxdata_format_number( $value ) : number_format( $value );
			}
			break;
			
		case 'runningapps':
			if ( isset( $api_data['data'] ) && is_array( $api_data['data'] ) ) {
				$value = count( $api_data['data'] );
				$data = $human_readable ? fluxdata_format_number( $value ) : number_format( $value );
			}
			break;
			
		case 'totalcores':
			if ( isset( $api_data['data']['total_cores'] ) && is_numeric( $api_data['data']['total_cores'] ) ) {
				$value = $api_data['data']['total_cores'];
				$data = $human_readable ? fluxdata_format_number( $value ) : number_format( $value );
			}
			break;
			
		case 'totalram':
			if ( isset( $api_data['data']['total_ram'] ) && is_numeric( $api_data['data']['total_ram'] ) ) {
				$value = $api_data['data']['total_ram'];
				$data = $human_readable ? fluxdata_format_ram( $value, true ) : number_format( $value ) . ' GB';
			}
			break;
			
		case 'totalssd':
			if ( isset( $api_data['data']['total_ssd'] ) && is_numeric( $api_data['data']['total_ssd'] ) ) {
				$value = $api_data['data']['total_ssd'];
				$data = $human_readable ? fluxdata_format_ssd( $value ) : number_format( $value ) . ' GB';
			}
			break;
	}
	
	if ( $data === null ) {
		wp_send_json_error( 'Cached data not available or invalid' );
	}
	
	wp_send_json_success( $data );
}

// Register AJAX handlers
add_action( 'wp_ajax_fluxdata_get', 'fluxdata_ajax_handler' );
add_action( 'wp_ajax_nopriv_fluxdata_get', 'fluxdata_ajax_handler' );
add_action( 'wp_ajax_fluxdata_get_cached', 'fluxdata_cached_ajax_handler' );
add_action( 'wp_ajax_nopriv_fluxdata_get_cached', 'fluxdata_cached_ajax_handler' );

/**
 * Enqueue scripts and localize AJAX data
 */
function fluxdata_enqueue_scripts() {
	// Only enqueue if we have FluxData blocks on the page
	if ( has_block( 'fluxdata/fluxinfo' ) ) {
		wp_localize_script( 'fluxdata-fluxinfo-view-script', 'fluxdataAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fluxdata_nonce' )
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'fluxdata_enqueue_scripts' );

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
