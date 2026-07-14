<?php
/**
 * Plugin Name:       LP-CC Consent
 * Plugin URI:        https://lp-cc.de
 * Description:       Schlankes Cookie-Consent mit Geo-Split (EU/EEA/UK/CH = Opt-in-Banner, Rest der Welt = Opt-out), Script-Gating, 2-Klick-Embeds sowie integrierter Ausgabe von Facebook Pixel und GA4.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Michael H. / LP-CC
 * License:           GPL-2.0-or-later
 * Text Domain:       lp-cc-consent
 *
 * Architektur:
 *   - HTML ist für alle Besucher identisch (voll cache-kompatibel).
 *   - JS prüft Cookie "lpcc_consent"; fehlt er, wird der uncached
 *     REST-Endpoint /wp-json/lpcc/v1/geo befragt (GeoLite2-Country lokal).
 *   - EU-Region  -> Banner, Scripts blockiert bis Opt-in.
 *   - ROW-Region -> kein Banner, alles aktiv, Opt-out via Footer-Link.
 *   - Tracking-Snippets (FB Pixel, GA4) gibt das Plugin selbst gegated aus
 *     (script type="text/plain" data-lpcc="..."), kein Code im Theme nötig.
 *
 * @package LPCC
 */

defined( 'ABSPATH' ) || exit;

define( 'LPCC_VERSION', '1.0.0' );
define( 'LPCC_DIR', __DIR__ );
define( 'LPCC_URI', plugin_dir_url( __FILE__ ) );
define( 'LPCC_BASENAME', plugin_basename( __FILE__ ) );

// Cookie-Name + Consent-Version. Version erhöhen => alle Besucher werden neu gefragt.
define( 'LPCC_COOKIE', 'lpcc_consent' );
define( 'LPCC_CONSENT_VERSION', 1 );

// GeoLite2-DB liegt in uploads (überlebt Plugin-Updates)
define( 'LPCC_GEODB_DIR', WP_CONTENT_DIR . '/uploads/lp-cc-consent' );
define( 'LPCC_GEODB_FILE', LPCC_GEODB_DIR . '/GeoLite2-Country.mmdb' );

require_once LPCC_DIR . '/inc/settings.php';
require_once LPCC_DIR . '/inc/geo.php';
require_once LPCC_DIR . '/inc/output.php';

// ============================================================================
// OPTIONS-HELPER
// ============================================================================

/**
 * Einzelne Plugin-Option lesen (alle Settings liegen in einem Array).
 */
function lpcc_option( string $key, $default = '' ) {
	$opts = get_option( 'lpcc_settings', [] );
	return isset( $opts[ $key ] ) && '' !== $opts[ $key ] ? $opts[ $key ] : $default;
}

/**
 * Aktive Frontend-Sprache ('de' oder 'en').
 * Polylang wird bevorzugt, sonst WP-Locale. Via Filter erweiterbar.
 */
function lpcc_lang(): string {
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language( 'slug' );
	} else {
		$lang = substr( get_locale(), 0, 2 );
	}
	$lang = ( 'de' === $lang ) ? 'de' : 'en';

	return apply_filters( 'lpcc_lang', $lang );
}

// ============================================================================
// ASSETS
// ============================================================================

add_action( 'wp_enqueue_scripts', function (): void {

	$css = LPCC_DIR . '/assets/consent.css';
	$js  = LPCC_DIR . '/assets/consent.js';

	wp_enqueue_style(
		'lpcc-consent',
		LPCC_URI . 'assets/consent.css',
		[],
		file_exists( $css ) ? filemtime( $css ) : LPCC_VERSION
	);

	wp_enqueue_script(
		'lpcc-consent',
		LPCC_URI . 'assets/consent.js',
		[],
		file_exists( $js ) ? filemtime( $js ) : LPCC_VERSION,
		true
	);

	wp_localize_script( 'lpcc-consent', 'lpccCfg', [
		'geoEndpoint' => esc_url_raw( rest_url( 'lpcc/v1/geo' ) ),
		'cookie'      => LPCC_COOKIE,
		'version'     => LPCC_CONSENT_VERSION,
		'texts'       => lpcc_texts(),
	] );
} );

// Das Consent-JS darf NICHT von Theme-seitigen defer-Optimierungen ausgenommen
// werden müssen – es ist selbst defer-tauglich. Falls das Theme (wie
// dreamvilla) einen Skip-Filter anbietet, ist hier nichts nötig.

// ============================================================================
// AKTIVIERUNG / DEAKTIVIERUNG
// ============================================================================

register_activation_hook( __FILE__, function (): void {
	if ( ! wp_next_scheduled( 'lpcc_geodb_update' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'monthly', 'lpcc_geodb_update' );
	}
	if ( ! is_dir( LPCC_GEODB_DIR ) ) {
		wp_mkdir_p( LPCC_GEODB_DIR );
	}
} );

register_deactivation_hook( __FILE__, function (): void {
	wp_clear_scheduled_hook( 'lpcc_geodb_update' );
} );

// WP kennt "monthly" nicht von Haus aus
add_filter( 'cron_schedules', function ( array $schedules ): array {
	if ( ! isset( $schedules['monthly'] ) ) {
		$schedules['monthly'] = [
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => 'Once Monthly',
		];
	}
	return $schedules;
} );
