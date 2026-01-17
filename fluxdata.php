<?php
/**
 * Plugin Name:       FluxData
 * Description:       Display Flux network data in your WordPress site.
 * Requires at least: 6.6
 * Requires PHP:      7.2
 * Version:           0.3.0
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
 * Plugin activation hook
 * Schedules the cron job and runs initial data fetch
 */
register_activation_hook( __FILE__, 'fluxdata_activate' );
function fluxdata_activate() {
	// Add custom cron schedule first
	add_filter( 'cron_schedules', 'fluxdata_cron_schedules' );

	// Schedule the cron event if not already scheduled
	if ( ! wp_next_scheduled( 'fluxdata_cron_hook' ) ) {
		wp_schedule_event( time(), 'ten_minutes', 'fluxdata_cron_hook' );
	}

	// Run initial data fetch immediately
	fluxdata_refresh_all_data();
}

/**
 * Plugin deactivation hook
 * Clears the scheduled cron job
 */
register_deactivation_hook( __FILE__, 'fluxdata_deactivate' );
function fluxdata_deactivate() {
	wp_clear_scheduled_hook( 'fluxdata_cron_hook' );
}

/**
 * Add custom cron schedule for 10 minutes
 */
add_filter( 'cron_schedules', 'fluxdata_cron_schedules' );
function fluxdata_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['ten_minutes'] ) ) {
		$schedules['ten_minutes'] = array(
			'interval' => 600, // 10 minutes in seconds
			'display'  => __( 'Every 10 Minutes', 'fluxdata' )
		);
	}
	return $schedules;
}

/**
 * Cron callback - refreshes all FluxData
 */
add_action( 'fluxdata_cron_hook', 'fluxdata_cron_refresh' );
function fluxdata_cron_refresh() {
	fluxdata_refresh_all_data();
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

/**
 * AJAX handler for refreshing stale data on cached pages
 * Returns current cached value (not live API call)
 */
add_action( 'wp_ajax_fluxdata_get', 'fluxdata_ajax_handler' );
add_action( 'wp_ajax_nopriv_fluxdata_get', 'fluxdata_ajax_handler' );

function fluxdata_ajax_handler() {
	$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
	$human_readable = isset( $_POST['human_readable'] ) && $_POST['human_readable'] === 'true';

	if ( empty( $type ) ) {
		wp_send_json_error( 'Missing type parameter' );
	}

	$value = fluxdata_get_display_value( $type, $human_readable );
	$cache_time = fluxdata_get_cache_timestamp();

	wp_send_json_success( array(
		'value'      => $value,
		'cache_time' => $cache_time,
	) );
}

/**
 * Enqueue frontend scripts with AJAX data
 */
add_action( 'wp_enqueue_scripts', 'fluxdata_enqueue_scripts' );

function fluxdata_enqueue_scripts() {
	// The script handle follows WordPress block naming: {block-namespace}-{block-name}-view-script
	wp_localize_script(
		'fluxdata-fluxinfo-view-script',
		'fluxdataAjax',
		array(
			'ajaxurl'        => admin_url( 'admin-ajax.php' ),
			'staleThreshold' => 600, // 10 minutes in seconds
		)
	);
}
