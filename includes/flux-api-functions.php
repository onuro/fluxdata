<?php
/**
 * Flux API Functions
 *
 * Contains all functions for fetching and formatting Flux network data.
 *
 * @package FluxData
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format SSD in GB to PB, TB, or GB for human readable display
 *
 * @param int|string $ssd_gb SSD in gigabytes
 * @return string Formatted SSD
 */
function fluxdata_format_ssd( $ssd_gb ) {
	$ssd_gb = (int) $ssd_gb;
	
	// Convert GB to PB (1 PB = 1,000,000 GB - decimal)
	$ssd_pb = $ssd_gb / 1000000;
	
	if ( $ssd_pb >= 1 ) {
		$formatted = round( $ssd_pb, 1 );
		return ( $formatted == (int) $formatted ) ? (int) $formatted . 'PB' : $formatted . 'PB';
	}
	
	// Convert GB to TB (1 TB = 1,000 GB - decimal)
	$ssd_tb = $ssd_gb / 1000;
	
	if ( $ssd_tb >= 1 ) {
		$formatted = round( $ssd_tb, 1 );
		return ( $formatted == (int) $formatted ) ? (int) $formatted . 'TB' : $formatted . 'TB';
	}
	
	return number_format( $ssd_gb ) . ' GB';
}

/**
 * Get total SSD from Flux network
 * Uses the same logic as the official Flux Resources.vue component
 *
 * @return array|WP_Error
 */
function fluxdata_get_total_ssd() {
	// Increase memory limit for processing large API response
	ini_set( 'memory_limit', '512M' );
	
	$cache_key = 'fluxdata_total_ssd';
	$cached_data = get_transient( $cache_key );
	
	if ( false !== $cached_data ) {
		return $cached_data;
	}
	
	$response = wp_remote_get( 'https://stats.runonflux.io/fluxinfo', array(
		'timeout' => 30,
		'headers' => array(
			'Accept' => 'application/json',
		),
	) );
	
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 503 ) );
	}
	
	$http_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $http_code ) {
		return new WP_Error( 'api_error', sprintf( __( 'HTTP error: %d', 'fluxdata' ), $http_code ), array( 'status' => 503 ) );
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		return new WP_Error( 'api_error', __( 'Invalid JSON response', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	if ( ! $data || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
		return new WP_Error( 'api_error', __( 'Failed to fetch total SSD data', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
		return new WP_Error( 'api_error', __( 'Invalid total SSD data format', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	// Initialize counters for each tier
	$cumulus_ssd_value = 0;
	$nimbus_ssd_value = 0;
	$stratus_ssd_value = 0;
	
	// Process each node using the same logic as Resources.vue
	foreach ( $data['data'] as $node ) {
		if ( $node['tier'] === 'CUMULUS' && isset( $node['benchmark']['bench'] ) ) {
			$ssd = $node['benchmark']['bench']['ssd'];
			$cumulus_ssd_value += ( $ssd < 220 ) ? 220 : round( $ssd );
		} elseif ( $node['tier'] === 'CUMULUS' ) {
			$cumulus_ssd_value += 220;
		} elseif ( $node['tier'] === 'NIMBUS' && isset( $node['benchmark']['bench'] ) ) {
			$ssd = $node['benchmark']['bench']['ssd'];
			$nimbus_ssd_value += ( $ssd < 440 ) ? 440 : round( $ssd );
		} elseif ( $node['tier'] === 'NIMBUS' ) {
			$nimbus_ssd_value += 440;
		} elseif ( $node['tier'] === 'STRATUS' && isset( $node['benchmark']['bench'] ) ) {
			$ssd = $node['benchmark']['bench']['ssd'];
			$stratus_ssd_value += ( $ssd < 880 ) ? 880 : round( $ssd );
		} elseif ( $node['tier'] === 'STRATUS' ) {
			$stratus_ssd_value += 880;
		}
	}
	
	// Calculate total SSD in GB
	$total_ssd = $cumulus_ssd_value + $nimbus_ssd_value + $stratus_ssd_value;
	
	$result = array(
		'status' => 'success',
		'data' => array(
			'total_ssd' => $total_ssd,
			'cumulus_ssd' => $cumulus_ssd_value,
			'nimbus_ssd' => $nimbus_ssd_value,
			'stratus_ssd' => $stratus_ssd_value,
		),
	);
	
	// Cache for 10 minutes
	set_transient( $cache_key, $result, 1 * MINUTE_IN_SECONDS );
	
	return $result;
}

/**
 * Format number with K, M, B suffixes
 *
 * @param int|string $number The number to format
 * @return string Formatted number
 */
function fluxdata_format_number( $number ) {
	$number = (int) $number;
	
	if ( $number >= 1000000000 ) {
		$formatted = round( $number / 1000000000, 1 );
		return ( $formatted == (int) $formatted ) ? (int) $formatted . 'B' : $formatted . 'B';
	}
	
	if ( $number >= 1000000 ) {
		$formatted = round( $number / 1000000, 1 );
		return ( $formatted == (int) $formatted ) ? (int) $formatted . 'M' : $formatted . 'M';
	}
	
	if ( $number >= 1000 ) {
		$formatted = round( $number / 1000, 1 );
		return ( $formatted == (int) $formatted ) ? (int) $formatted . 'K' : $formatted . 'K';
	}
	
	return (string) $number;
}

/**
 * Format RAM in GB to TB for human readable display
 *
 * @param int|string $ram_gb RAM in gigabytes
 * @param bool $human_readable Whether to use human readable format
 * @return string Formatted RAM
 */
function fluxdata_format_ram( $ram_gb, $human_readable = false ) {
	$ram_gb = (int) $ram_gb;
	
	if ( ! $human_readable ) {
		return number_format( $ram_gb ) . ' GB';
	}
	
	// Convert GB to TB (1 TB = 1024 GB)
	$ram_tb = $ram_gb / 1024;
	
	if ( $ram_tb >= 1 ) {
		$formatted = round( $ram_tb );
		return $formatted . ' TB';
	}
	
	return number_format( $ram_gb ) . ' GB';
}

/**
 * Get Flux node count from API
 *
 * @return array|WP_Error
 */
function fluxdata_get_node_count() {
	$cache_key = 'fluxdata_node_count';
	$cached_data = get_transient( $cache_key );
	
	if ( false !== $cached_data ) {
		return $cached_data;
	}
	
	$response = wp_remote_get( 'https://api.runonflux.io/daemon/getfluxnodecount', array(
		'timeout' => 15,
		'headers' => array(
			'Accept' => 'application/json',
		),
	) );
	
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 503 ) );
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( ! $data || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
		return new WP_Error( 'api_error', __( 'Failed to fetch node count data', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	if ( ! isset( $data['data']['total'] ) || ! is_numeric( $data['data']['total'] ) ) {
		return new WP_Error( 'api_error', __( 'Invalid node count data format', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	// Cache for 5 minutes
	set_transient( $cache_key, $data, 1 * MINUTE_IN_SECONDS );
	
	return $data;
}

/**
 * Get running apps count from API
 *
 * @return array|WP_Error
 */
function fluxdata_get_running_apps_count() {
	$cache_key = 'fluxdata_running_apps';
	$cached_data = get_transient( $cache_key );
	
	if ( false !== $cached_data ) {
		return $cached_data;
	}
	
	$response = wp_remote_get( 'https://api.runonflux.io/apps/listrunningapps', array(
		'timeout' => 15,
		'headers' => array(
			'Accept' => 'application/json',
		),
	) );
	
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 503 ) );
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( ! $data || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
		return new WP_Error( 'api_error', __( 'Failed to fetch running apps data', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
		return new WP_Error( 'api_error', __( 'Invalid running apps data format', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	// Cache for 5 minutes
	set_transient( $cache_key, $data, 1 * MINUTE_IN_SECONDS );
	
	return $data;
}

/**
 * Get total cores count from Flux network
 * Uses the same logic as the official Flux Resources.vue component
 *
 * @return array|WP_Error
 */
function fluxdata_get_total_cores() {
	// Increase memory limit for processing large API response
	ini_set( 'memory_limit', '512M' );
	
	$cache_key = 'fluxdata_total_cores';
	$cached_data = get_transient( $cache_key );
	
	if ( false !== $cached_data ) {
		return $cached_data;
	}
	
	$response = wp_remote_get( 'https://stats.runonflux.io/fluxinfo', array(
		'timeout' => 30,
		'headers' => array(
			'Accept' => 'application/json',
		),
	) );
	
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 503 ) );
	}
	
	$http_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $http_code ) {
		return new WP_Error( 'api_error', sprintf( __( 'HTTP error: %d', 'fluxdata' ), $http_code ), array( 'status' => 503 ) );
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		return new WP_Error( 'api_error', __( 'Invalid JSON response', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	if ( ! $data || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
		return new WP_Error( 'api_error', __( 'Failed to fetch total cores data', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
		return new WP_Error( 'api_error', __( 'Invalid total cores data format', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	// Initialize counters for each tier
	$cumulus_cpu_value = 0;
	$nimbus_cpu_value = 0;
	$stratus_cpu_value = 0;
	
	// Process each node using the same logic as Resources.vue
	foreach ( $data['data'] as $node ) {
		if ( $node['tier'] === 'CUMULUS' && isset( $node['benchmark']['bench'] ) ) {
			$cores = $node['benchmark']['bench']['cores'];
			$cumulus_cpu_value += ( $cores === 0 ) ? 4 : $cores;
		} elseif ( $node['tier'] === 'CUMULUS' ) {
			$cumulus_cpu_value += 4;
		} elseif ( $node['tier'] === 'NIMBUS' && isset( $node['benchmark']['bench'] ) ) {
			$cores = $node['benchmark']['bench']['cores'];
			$nimbus_cpu_value += ( $cores === 0 ) ? 8 : $cores;
		} elseif ( $node['tier'] === 'NIMBUS' ) {
			$nimbus_cpu_value += 8;
		} elseif ( $node['tier'] === 'STRATUS' && isset( $node['benchmark']['bench'] ) ) {
			$cores = $node['benchmark']['bench']['cores'];
			$stratus_cpu_value += ( $cores === 0 ) ? 16 : $cores;
		} elseif ( $node['tier'] === 'STRATUS' ) {
			$stratus_cpu_value += 16;
		}
	}
	
	// Calculate total cores
	$total_cores = $cumulus_cpu_value + $nimbus_cpu_value + $stratus_cpu_value;
	
	$result = array(
		'status' => 'success',
		'data' => array(
			'total_cores' => $total_cores,
			'cumulus_cores' => $cumulus_cpu_value,
			'nimbus_cores' => $nimbus_cpu_value,
			'stratus_cores' => $stratus_cpu_value,
		),
	);
	
	// Cache for 10 minutes
	set_transient( $cache_key, $result, 1 * MINUTE_IN_SECONDS );
	
	return $result;
}

/**
 * Get total RAM from Flux network
 * Uses the same logic as the official Flux Resources.vue component
 *
 * @return array|WP_Error
 */
function fluxdata_get_total_ram() {
	// Increase memory limit for processing large API response
	ini_set( 'memory_limit', '512M' );
	
	$cache_key = 'fluxdata_total_ram';
	$cached_data = get_transient( $cache_key );
	
	if ( false !== $cached_data ) {
		return $cached_data;
	}
	
	$response = wp_remote_get( 'https://stats.runonflux.io/fluxinfo', array(
		'timeout' => 30,
		'headers' => array(
			'Accept' => 'application/json',
		),
	) );
	
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 503 ) );
	}
	
	$http_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $http_code ) {
		return new WP_Error( 'api_error', sprintf( __( 'HTTP error: %d', 'fluxdata' ), $http_code ), array( 'status' => 503 ) );
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		return new WP_Error( 'api_error', __( 'Invalid JSON response', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	if ( ! $data || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
		return new WP_Error( 'api_error', __( 'Failed to fetch total RAM data', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
		return new WP_Error( 'api_error', __( 'Invalid total RAM data format', 'fluxdata' ), array( 'status' => 503 ) );
	}
	
	// Initialize counters for each tier
	$cumulus_ram_value = 0;
	$nimbus_ram_value = 0;
	$stratus_ram_value = 0;
	
	// Process each node using the same logic as Resources.vue
	foreach ( $data['data'] as $node ) {
		if ( $node['tier'] === 'CUMULUS' && isset( $node['benchmark']['bench'] ) ) {
			$ram = $node['benchmark']['bench']['ram'];
			$cumulus_ram_value += ( $ram < 8 ) ? 8 : round( $ram );
		} elseif ( $node['tier'] === 'CUMULUS' ) {
			$cumulus_ram_value += 8;
		} elseif ( $node['tier'] === 'NIMBUS' && isset( $node['benchmark']['bench'] ) ) {
			$ram = $node['benchmark']['bench']['ram'];
			$nimbus_ram_value += ( $ram < 32 ) ? 32 : round( $ram );
		} elseif ( $node['tier'] === 'NIMBUS' ) {
			$nimbus_ram_value += 32;
		} elseif ( $node['tier'] === 'STRATUS' && isset( $node['benchmark']['bench'] ) ) {
			$ram = $node['benchmark']['bench']['ram'];
			$stratus_ram_value += ( $ram < 64 ) ? 64 : round( $ram );
		} elseif ( $node['tier'] === 'STRATUS' ) {
			$stratus_ram_value += 64;
		}
	}
	
	// Calculate total RAM in GB
	$total_ram = $cumulus_ram_value + $nimbus_ram_value + $stratus_ram_value;
	
	$result = array(
		'status' => 'success',
		'data' => array(
			'total_ram' => $total_ram,
			'cumulus_ram' => $cumulus_ram_value,
			'nimbus_ram' => $nimbus_ram_value,
			'stratus_ram' => $stratus_ram_value,
		),
	);
	
	// Cache for 10 minutes
	set_transient( $cache_key, $result, 1 * MINUTE_IN_SECONDS );
	
	return $result;
}