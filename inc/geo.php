<?php
/**
 * Geo-Erkennung
 *
 *   - REST-Endpoint GET /wp-json/lpcc/v1/geo  ->  { region: "eu"|"row", country: "US" }
 *   - Lookup über lokale GeoLite2-Country.mmdb (MaxMind, gebündelter Pure-PHP-Reader)
 *   - Monatlicher Cron lädt DB-Updates (License Key aus den Settings)
 *   - Fail-safe: ohne DB/Key wird "eu" zurückgegeben => Banner für alle
 *
 * @package LPCC
 */

defined( 'ABSPATH' ) || exit;

// EU/EEA + UK + CH: überall Opt-in-Pflicht (DSGVO / UK-GDPR / revDSG+ePrivacy-Praxis)
function lpcc_optin_countries(): array {
	$countries = [
		// EU-27
		'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
		'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
		'SI', 'ES', 'SE',
		// EEA
		'IS', 'LI', 'NO',
		// UK + CH
		'GB', 'CH',
	];
	return apply_filters( 'lpcc_optin_countries', $countries );
}

// ============================================================================
// REST-ENDPOINT (uncached)
// ============================================================================

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'lpcc/v1', '/geo', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'lpcc_rest_geo',
	] );
} );

function lpcc_rest_geo(): WP_REST_Response {

	$country = lpcc_lookup_country( lpcc_client_ip() );

	// Fail-safe: unbekannt => EU-Verhalten (Banner)
	$region = ( null === $country || in_array( $country, lpcc_optin_countries(), true ) )
		? 'eu'
		: 'row';

	$response = new WP_REST_Response( [
		'region'  => $region,
		'country' => $country ?: 'XX',
	] );

	// Niemals cachen – weder Browser noch Page-Cache/CDN
	$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	$response->header( 'Pragma', 'no-cache' );

	return $response;
}

/**
 * Client-IP ermitteln. REMOTE_ADDR ist die einzige nicht spoofbare Quelle;
 * hinter einem Reverse-Proxy kann per Filter auf einen Header umgestellt werden.
 */
function lpcc_client_ip(): string {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	return apply_filters( 'lpcc_client_ip', $ip );
}

/**
 * ISO-Ländercode zur IP oder null, wenn kein Lookup möglich.
 */
function lpcc_lookup_country( string $ip ): ?string {

	if ( '' === $ip || ! file_exists( LPCC_GEODB_FILE ) ) {
		return null;
	}

	// Lokale/private IPs (Dev-Umgebungen) => kein Lookup
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return null;
	}

	try {
		require_once LPCC_DIR . '/lib/autoload.php';

		$reader = new \MaxMind\Db\Reader( LPCC_GEODB_FILE );
		$record = $reader->get( $ip );
		$reader->close();

		return $record['country']['iso_code'] ?? null;

	} catch ( \Throwable $e ) {
		return null;
	}
}

// ============================================================================
// GEODB-DOWNLOAD (Cron + manuell nach Settings-Save)
// ============================================================================

add_action( 'lpcc_geodb_update', 'lpcc_run_geodb_update' );

/**
 * Wrapper um lpcc_download_geodb(): Ergebnis in Options loggen, damit
 * Fehler (auch beim automatischen Cron-Update) auf der Settings-Seite sichtbar sind.
 */
function lpcc_run_geodb_update(): void {
	$result = lpcc_download_geodb();
	if ( is_wp_error( $result ) ) {
		update_option( 'lpcc_geodb_error', $result->get_error_message(), false );
	} else {
		delete_option( 'lpcc_geodb_error' );
	}
}

/**
 * GeoLite2-Country.mmdb von MaxMind laden und nach uploads entpacken.
 *
 * @return true|WP_Error
 */
function lpcc_download_geodb() {

	$key = lpcc_option( 'maxmind_key' );
	if ( '' === $key ) {
		return new WP_Error( 'lpcc_no_key', 'Kein MaxMind License Key hinterlegt.' );
	}

	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$url = add_query_arg( [
		'edition_id'  => 'GeoLite2-Country',
		'license_key' => rawurlencode( $key ),
		'suffix'      => 'tar.gz',
	], 'https://download.maxmind.com/app/geoip_download' );

	$tmp = download_url( $url, 120 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	if ( ! is_dir( LPCC_GEODB_DIR ) ) {
		wp_mkdir_p( LPCC_GEODB_DIR );
	}

	// tar.gz entpacken und die .mmdb herausziehen
	try {
		// PharData braucht .tar.gz-Endung
		$tarGz = LPCC_GEODB_DIR . '/geolite2.tar.gz';
		copy( $tmp, $tarGz );
		unlink( $tmp );

		$phar = new PharData( $tarGz );
		$phar->decompress(); // -> geolite2.tar

		$tar   = new PharData( LPCC_GEODB_DIR . '/geolite2.tar' );
		$found = false;

		foreach ( new RecursiveIteratorIterator( $tar ) as $file ) {
			if ( 'mmdb' === strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) ) ) {
				copy( $file->getPathname(), LPCC_GEODB_FILE );
				$found = true;
				break;
			}
		}

		@unlink( $tarGz );
		@unlink( LPCC_GEODB_DIR . '/geolite2.tar' );

		if ( ! $found ) {
			return new WP_Error( 'lpcc_no_mmdb', 'Keine .mmdb im MaxMind-Archiv gefunden.' );
		}

		update_option( 'lpcc_geodb_updated', time(), false );
		return true;

	} catch ( \Throwable $e ) {
		return new WP_Error( 'lpcc_extract_failed', 'Entpacken fehlgeschlagen: ' . $e->getMessage() );
	}
}
