<?php
/**
 * Flux API Functions
 *
 * Contains all functions for fetching and formatting Flux network data.
 * Uses node count API with tier defaults for fast, reliable calculations.
 *
 * @package FluxData
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define unified cache duration for all FluxData functions
if ( ! defined( 'FLUXDATA_CACHE_DURATION' ) ) {
	define( 'FLUXDATA_CACHE_DURATION', 10 * MINUTE_IN_SECONDS ); // 10 minutes
}

// Tier hardware specifications (minimum requirements per tier)
if ( ! defined( 'FLUXDATA_CUMULUS_CORES' ) ) {
	define( 'FLUXDATA_CUMULUS_CORES', 4 );
	define( 'FLUXDATA_CUMULUS_RAM', 8 );      // GB
	define( 'FLUXDATA_CUMULUS_SSD', 220 );    // GB
}
if ( ! defined( 'FLUXDATA_NIMBUS_CORES' ) ) {
	define( 'FLUXDATA_NIMBUS_CORES', 8 );
	define( 'FLUXDATA_NIMBUS_RAM', 32 );      // GB
	define( 'FLUXDATA_NIMBUS_SSD', 440 );     // GB
}
if ( ! defined( 'FLUXDATA_STRATUS_CORES' ) ) {
	define( 'FLUXDATA_STRATUS_CORES', 16 );
	define( 'FLUXDATA_STRATUS_RAM', 64 );     // GB
	define( 'FLUXDATA_STRATUS_SSD', 880 );    // GB
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
 * Calculates from node count API using tier defaults for fast, reliable results.
 *
 * @return array|WP_Error
 */
function fluxdata_get_total_ssd() {
	// Try to get cached data using Options API
	$cached_data = fluxdata_get_cache( 'total_ssd', FLUXDATA_CACHE_DURATION );
	if ( $cached_data !== false && is_array( $cached_data ) && isset( $cached_data['status'] ) ) {
		return $cached_data;
	}

	// Get node counts by tier
	$node_data = fluxdata_get_node_count();

	if ( is_wp_error( $node_data ) ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'total_ssd' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return $node_data;
	}

	// Extract tier counts
	$cumulus_count = isset( $node_data['data']['cumulus-enabled'] ) ? (int) $node_data['data']['cumulus-enabled'] : 0;
	$nimbus_count = isset( $node_data['data']['nimbus-enabled'] ) ? (int) $node_data['data']['nimbus-enabled'] : 0;
	$stratus_count = isset( $node_data['data']['stratus-enabled'] ) ? (int) $node_data['data']['stratus-enabled'] : 0;

	// Calculate SSD using tier defaults
	$cumulus_ssd = $cumulus_count * FLUXDATA_CUMULUS_SSD;
	$nimbus_ssd = $nimbus_count * FLUXDATA_NIMBUS_SSD;
	$stratus_ssd = $stratus_count * FLUXDATA_STRATUS_SSD;
	$total_ssd = $cumulus_ssd + $nimbus_ssd + $stratus_ssd;

	$result = array(
		'status' => 'success',
		'data' => array(
			'total_ssd' => $total_ssd,
			'cumulus_ssd' => $cumulus_ssd,
			'nimbus_ssd' => $nimbus_ssd,
			'stratus_ssd' => $stratus_ssd,
		),
	);

	// Cache the result
	fluxdata_set_cache( 'total_ssd', $result );

	// Update persistent "last known good" cache
	fluxdata_set_last_good( 'total_ssd', $result );

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
		return $formatted . 'TB';
	}

	return number_format( $ram_gb ) . 'GB';
}

/**
 * Get Flux node count from API
 *
 * @return array|WP_Error
 */
function fluxdata_get_node_count() {
	// Try to get cached data using Options API
	$cached_data = fluxdata_get_cache( 'node_count', FLUXDATA_CACHE_DURATION );
	if ( $cached_data !== false && is_array( $cached_data ) && isset( $cached_data['status'] ) ) {
		return $cached_data;
	}

	$response = wp_remote_get( 'https://api.runonflux.io/daemon/getfluxnodecount', array(
		'timeout' => 15,
		'headers' => array(
			'Accept' => 'application/json',
		),
	) );

	if ( is_wp_error( $response ) ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'node_count' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 503 ) );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! $data || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'node_count' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return new WP_Error( 'api_error', __( 'Failed to fetch node count data', 'fluxdata' ), array( 'status' => 503 ) );
	}

	if ( ! isset( $data['data']['total'] ) || ! is_numeric( $data['data']['total'] ) ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'node_count' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return new WP_Error( 'api_error', __( 'Invalid node count data format', 'fluxdata' ), array( 'status' => 503 ) );
	}

	// Cache the result using Options API
	fluxdata_set_cache( 'node_count', $data );

	// Update persistent "last known good" cache
	fluxdata_set_last_good( 'node_count', $data );

	return $data;
}

/**
 * Get running apps count from API
 *
 * @return array|WP_Error
 */
function fluxdata_get_running_apps_count() {
	// Try to get cached data using Options API
	$cached_data = fluxdata_get_cache( 'running_apps', FLUXDATA_CACHE_DURATION );
	if ( $cached_data !== false && is_array( $cached_data ) && isset( $cached_data['status'] ) ) {
		return $cached_data;
	}

	$response = wp_remote_get( 'https://api.runonflux.io/apps/listrunningapps', array(
		'timeout' => 15,
		'headers' => array(
			'Accept' => 'application/json',
		),
	) );

	if ( is_wp_error( $response ) ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'running_apps' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 503 ) );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! $data || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'running_apps' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return new WP_Error( 'api_error', __( 'Failed to fetch running apps data', 'fluxdata' ), array( 'status' => 503 ) );
	}

	if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'running_apps' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return new WP_Error( 'api_error', __( 'Invalid running apps data format', 'fluxdata' ), array( 'status' => 503 ) );
	}

	// Cache the result using Options API
	fluxdata_set_cache( 'running_apps', $data );

	// Update persistent "last known good" cache
	fluxdata_set_last_good( 'running_apps', $data );

	return $data;
}

/**
 * Get total cores count from Flux network
 * Calculates from node count API using tier defaults for fast, reliable results.
 *
 * @return array|WP_Error
 */
function fluxdata_get_total_cores() {
	// Try to get cached data using Options API
	$cached_data = fluxdata_get_cache( 'total_cores', FLUXDATA_CACHE_DURATION );
	if ( $cached_data !== false && is_array( $cached_data ) && isset( $cached_data['status'] ) ) {
		return $cached_data;
	}

	// Get node counts by tier
	$node_data = fluxdata_get_node_count();

	if ( is_wp_error( $node_data ) ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'total_cores' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return $node_data;
	}

	// Extract tier counts
	$cumulus_count = isset( $node_data['data']['cumulus-enabled'] ) ? (int) $node_data['data']['cumulus-enabled'] : 0;
	$nimbus_count = isset( $node_data['data']['nimbus-enabled'] ) ? (int) $node_data['data']['nimbus-enabled'] : 0;
	$stratus_count = isset( $node_data['data']['stratus-enabled'] ) ? (int) $node_data['data']['stratus-enabled'] : 0;

	// Calculate cores using tier defaults
	$cumulus_cores = $cumulus_count * FLUXDATA_CUMULUS_CORES;
	$nimbus_cores = $nimbus_count * FLUXDATA_NIMBUS_CORES;
	$stratus_cores = $stratus_count * FLUXDATA_STRATUS_CORES;
	$total_cores = $cumulus_cores + $nimbus_cores + $stratus_cores;

	$result = array(
		'status' => 'success',
		'data' => array(
			'total_cores' => $total_cores,
			'cumulus_cores' => $cumulus_cores,
			'nimbus_cores' => $nimbus_cores,
			'stratus_cores' => $stratus_cores,
		),
	);

	// Cache the result
	fluxdata_set_cache( 'total_cores', $result );

	// Update persistent "last known good" cache
	fluxdata_set_last_good( 'total_cores', $result );

	return $result;
}

/**
 * Get total RAM from Flux network
 * Calculates from node count API using tier defaults for fast, reliable results.
 *
 * @return array|WP_Error
 */
function fluxdata_get_total_ram() {
	// Try to get cached data using Options API
	$cached_data = fluxdata_get_cache( 'total_ram', FLUXDATA_CACHE_DURATION );
	if ( $cached_data !== false && is_array( $cached_data ) && isset( $cached_data['status'] ) ) {
		return $cached_data;
	}

	// Get node counts by tier
	$node_data = fluxdata_get_node_count();

	if ( is_wp_error( $node_data ) ) {
		// Try persistent fallback
		$fallback = fluxdata_get_last_good( 'total_ram' );
		if ( $fallback !== false ) {
			return $fallback;
		}
		return $node_data;
	}

	// Extract tier counts
	$cumulus_count = isset( $node_data['data']['cumulus-enabled'] ) ? (int) $node_data['data']['cumulus-enabled'] : 0;
	$nimbus_count = isset( $node_data['data']['nimbus-enabled'] ) ? (int) $node_data['data']['nimbus-enabled'] : 0;
	$stratus_count = isset( $node_data['data']['stratus-enabled'] ) ? (int) $node_data['data']['stratus-enabled'] : 0;

	// Calculate RAM using tier defaults (in GB)
	$cumulus_ram = $cumulus_count * FLUXDATA_CUMULUS_RAM;
	$nimbus_ram = $nimbus_count * FLUXDATA_NIMBUS_RAM;
	$stratus_ram = $stratus_count * FLUXDATA_STRATUS_RAM;
	$total_ram = $cumulus_ram + $nimbus_ram + $stratus_ram;

	$result = array(
		'status' => 'success',
		'data' => array(
			'total_ram' => $total_ram,
			'cumulus_ram' => $cumulus_ram,
			'nimbus_ram' => $nimbus_ram,
			'stratus_ram' => $stratus_ram,
		),
	);

	// Cache the result
	fluxdata_set_cache( 'total_ram', $result );

	// Update persistent "last known good" cache
	fluxdata_set_last_good( 'total_ram', $result );

	return $result;
}

/**
 * Centralized cache management using WordPress Options API
 * All FluxData cache is stored in a single option: 'fluxdata_cache'
 */

/**
 * Get cached data from centralized cache
 *
 * @param string $cache_key The cache key to retrieve
 * @param int $duration Cache duration in seconds
 * @return mixed|false Returns cached data or false if not found/expired
 */
function fluxdata_get_cache( $cache_key, $duration ) {
	$cache_data = get_option( 'fluxdata_cache', array() );
	
	if ( ! isset( $cache_data[ $cache_key ] ) ) {
		return false;
	}
	
	$cached_item = $cache_data[ $cache_key ];
	
	// Check if cache has expired
	if ( ! isset( $cached_item['timestamp'] ) || ( time() - $cached_item['timestamp'] ) > $duration ) {
		return false;
	}
	
	return isset( $cached_item['data'] ) ? $cached_item['data'] : false;
}

/**
 * Set cached data in centralized cache
 *
 * @param string $cache_key The cache key to store
 * @param mixed $data The data to cache
 * @return bool True on success, false on failure
 */
function fluxdata_set_cache( $cache_key, $data ) {
	$cache_data = get_option( 'fluxdata_cache', array() );

	// Add new cache entry
	$cache_data[ $cache_key ] = array(
		'data' => $data,
		'timestamp' => time()
	);

	// Clean up old cache entries (older than 1 hour)
	$current_time = time();
	foreach ( $cache_data as $key => $item ) {
		if ( isset( $item['timestamp'] ) && ( $current_time - $item['timestamp'] ) > 3600 ) {
			unset( $cache_data[ $key ] );
		}
	}

	return update_option( 'fluxdata_cache', $cache_data );
}

/**
 * Persistent "Last Known Good" Cache
 *
 * This cache NEVER expires - it only gets replaced by newer successful data.
 * Used as ultimate fallback when API fails and regular cache is expired.
 * Stored in: 'fluxdata_last_good' option
 */

/**
 * Get last known good data for a specific cache key
 *
 * @param string $cache_key The cache key to retrieve
 * @return mixed|false Returns cached data or false if not found
 */
function fluxdata_get_last_good( $cache_key ) {
	$last_good = get_option( 'fluxdata_last_good', array() );

	if ( ! isset( $last_good[ $cache_key ] ) || ! isset( $last_good[ $cache_key ]['data'] ) ) {
		return false;
	}

	return $last_good[ $cache_key ]['data'];
}

/**
 * Set last known good data for a specific cache key
 * Only call this on SUCCESSFUL API responses
 *
 * @param string $cache_key The cache key to store
 * @param mixed $data The data to cache (should be successful API response)
 * @return bool True on success, false on failure
 */
function fluxdata_set_last_good( $cache_key, $data ) {
	$last_good = get_option( 'fluxdata_last_good', array() );

	$last_good[ $cache_key ] = array(
		'data' => $data,
		'updated' => time()
	);

	return update_option( 'fluxdata_last_good', $last_good );
}

/**
 * Initialize default fallback values for fresh installs
 * These are reasonable estimates that will be replaced on first successful API call
 * Called on plugin activation or when no fallback data exists
 */
function fluxdata_init_default_fallbacks() {
	$last_good = get_option( 'fluxdata_last_good', array() );

	// Only seed if empty (fresh install)
	if ( ! empty( $last_good ) ) {
		return;
	}

	// Default values based on typical network state (will be replaced on first successful fetch)
	$defaults = array(
		'node_count' => array(
			'data' => array(
				'status' => 'success',
				'data' => array(
					'total' => 8200,
					'cumulus-enabled' => 4800,
					'nimbus-enabled' => 1800,
					'stratus-enabled' => 1600,
				),
			),
			'updated' => time(),
		),
		'running_apps' => array(
			'data' => array(
				'status' => 'success',
				'data' => array(), // Empty array, count will be 0
			),
			'updated' => time(),
		),
		'total_cores' => array(
			'data' => array(
				'status' => 'success',
				'data' => array(
					'total_cores' => 59200,
					'cumulus_cores' => 19200,
					'nimbus_cores' => 14400,
					'stratus_cores' => 25600,
				),
			),
			'updated' => time(),
		),
		'total_ram' => array(
			'data' => array(
				'status' => 'success',
				'data' => array(
					'total_ram' => 198400,
					'cumulus_ram' => 38400,
					'nimbus_ram' => 57600,
					'stratus_ram' => 102400,
				),
			),
			'updated' => time(),
		),
		'total_ssd' => array(
			'data' => array(
				'status' => 'success',
				'data' => array(
					'total_ssd' => 3260000,
					'cumulus_ssd' => 1056000,
					'nimbus_ssd' => 792000,
					'stratus_ssd' => 1408000,
				),
			),
			'updated' => time(),
		),
	);

	update_option( 'fluxdata_last_good', $defaults );
}

// Initialize default fallbacks when this file is loaded (runs once if empty)
fluxdata_init_default_fallbacks();

/**
 * Refresh all FluxData from APIs
 * Called by WP Cron every 10 minutes
 */
function fluxdata_refresh_all_data() {
	// Clear existing cache to force fresh fetches
	delete_option( 'fluxdata_cache' );

	// Fetch all data types - each function will:
	// 1. Fetch fresh data from API
	// 2. Cache it in fluxdata_cache
	// 3. Update fluxdata_last_good on success
	fluxdata_get_node_count();
	fluxdata_get_running_apps_count();
	fluxdata_get_total_cores();
	fluxdata_get_total_ram();
	fluxdata_get_total_ssd();
}

/**
 * Get display-ready value for a data type
 * Used by render.php to output formatted values
 *
 * @param string $data_type The data type (nodecount, runningapps, totalcores, totalram, totalssd)
 * @param bool $human_readable Whether to format in human-readable format
 * @return string Formatted display value
 */
function fluxdata_get_display_value( $data_type, $human_readable = false ) {
	$raw_value = null;

	// Get cached data from WordPress options
	$cached_data = get_option( 'fluxdata_cache', array() );

	// Map data types to cache keys
	$cache_key_map = array(
		'nodecount'   => 'node_count',
		'runningapps' => 'running_apps',
		'totalcores'  => 'total_cores',
		'totalram'    => 'total_ram',
		'totalssd'    => 'total_ssd',
	);

	$cache_key = isset( $cache_key_map[ $data_type ] ) ? $cache_key_map[ $data_type ] : $data_type;

	// Try to get from cache first
	if ( ! empty( $cached_data[ $cache_key ]['data'] ) ) {
		$api_data = $cached_data[ $cache_key ]['data'];
		$raw_value = fluxdata_extract_raw_value( $data_type, $api_data );
	}

	// If no cached value, try last known good
	if ( $raw_value === null ) {
		$last_good = get_option( 'fluxdata_last_good', array() );
		if ( ! empty( $last_good[ $cache_key ]['data'] ) ) {
			$api_data = $last_good[ $cache_key ]['data'];
			$raw_value = fluxdata_extract_raw_value( $data_type, $api_data );
		}
	}

	// Still no value? Return placeholder
	if ( $raw_value === null ) {
		return '—';
	}

	// Format the value
	return fluxdata_format_display_value( $data_type, $raw_value, $human_readable );
}

/**
 * Extract raw numeric value from API data based on data type
 *
 * @param string $data_type The data type
 * @param array $api_data The API response data
 * @return int|null Raw numeric value or null
 */
function fluxdata_extract_raw_value( $data_type, $api_data ) {
	switch ( $data_type ) {
		case 'nodecount':
			if ( isset( $api_data['data']['total'] ) && is_numeric( $api_data['data']['total'] ) ) {
				return (int) $api_data['data']['total'];
			}
			break;

		case 'runningapps':
			if ( isset( $api_data['data'] ) && is_array( $api_data['data'] ) ) {
				return count( $api_data['data'] );
			}
			break;

		case 'totalcores':
			if ( isset( $api_data['data']['total_cores'] ) && is_numeric( $api_data['data']['total_cores'] ) ) {
				return (int) $api_data['data']['total_cores'];
			}
			break;

		case 'totalram':
			if ( isset( $api_data['data']['total_ram'] ) && is_numeric( $api_data['data']['total_ram'] ) ) {
				return (int) $api_data['data']['total_ram'];
			}
			break;

		case 'totalssd':
			if ( isset( $api_data['data']['total_ssd'] ) && is_numeric( $api_data['data']['total_ssd'] ) ) {
				return (int) $api_data['data']['total_ssd'];
			}
			break;
	}

	return null;
}

/**
 * Format raw value for display based on data type and human readable preference
 *
 * @param string $data_type The data type
 * @param int $raw_value The raw numeric value
 * @param bool $human_readable Whether to use human-readable format
 * @return string Formatted display value
 */
function fluxdata_format_display_value( $data_type, $raw_value, $human_readable ) {
	switch ( $data_type ) {
		case 'totalram':
			if ( $human_readable ) {
				return fluxdata_format_ram( $raw_value, true );
			}
			return number_format( $raw_value ) . ' GB';

		case 'totalssd':
			if ( $human_readable ) {
				return fluxdata_format_ssd( $raw_value );
			}
			return number_format( $raw_value ) . ' GB';

		default:
			// nodecount, runningapps, totalcores
			if ( $human_readable ) {
				return fluxdata_format_number( $raw_value );
			}
			return number_format( $raw_value );
	}
}

/**
 * Get the most recent cache timestamp
 * Used by JS to determine if cached page data is stale
 *
 * @return int Unix timestamp of most recent cache update
 */
function fluxdata_get_cache_timestamp() {
	$cached_data = get_option( 'fluxdata_cache', array() );

	// Get the most recent timestamp from any cached item
	$latest = 0;
	foreach ( $cached_data as $item ) {
		if ( isset( $item['timestamp'] ) && $item['timestamp'] > $latest ) {
			$latest = $item['timestamp'];
		}
	}

	return $latest > 0 ? $latest : time();
}