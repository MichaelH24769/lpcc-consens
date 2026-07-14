<?php
/**
 * Settings-Seite (Einstellungen -> LP-CC Consent)
 *
 * Alle Werte liegen in einer Option "lpcc_settings" (Array):
 *   maxmind_key     MaxMind License Key (GeoLite2)
 *   fb_pixel_id     Facebook/Meta Pixel ID          -> Kategorie "marketing"
 *   ga4_id          GA4 Measurement ID (G-XXXX)     -> Kategorie "statistics"
 *   privacy_url_en  Link zur Privacy Policy (EN)
 *   privacy_url_de  Link zur Datenschutzerklärung (DE)
 *
 * @package LPCC
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function (): void {
	add_options_page(
		'LP-CC Consent',
		'LP-CC Consent',
		'manage_options',
		'lp-cc-consent',
		'lpcc_render_settings_page'
	);
} );

add_action( 'admin_init', function (): void {
	register_setting( 'lpcc_settings_group', 'lpcc_settings', [
		'type'              => 'array',
		'sanitize_callback' => 'lpcc_sanitize_settings',
	] );
} );

function lpcc_sanitize_settings( $input ): array {

	$old = get_option( 'lpcc_settings', [] );

	$clean = [
		'maxmind_key'    => sanitize_text_field( $input['maxmind_key'] ?? '' ),
		'fb_pixel_id'    => preg_replace( '/[^0-9]/', '', $input['fb_pixel_id'] ?? '' ),
		'ga4_id'         => sanitize_text_field( $input['ga4_id'] ?? '' ),
		'privacy_url_en' => esc_url_raw( $input['privacy_url_en'] ?? '' ),
		'privacy_url_de' => esc_url_raw( $input['privacy_url_de'] ?? '' ),
	];

	// Neuer/erstmaliger Key => GeoDB sofort laden (nicht auf Cron warten)
	$old_key = $old['maxmind_key'] ?? '';
	if ( '' !== $clean['maxmind_key'] && $clean['maxmind_key'] !== $old_key ) {
		add_action( 'shutdown', function () {
			$result = lpcc_download_geodb();
			if ( is_wp_error( $result ) ) {
				update_option( 'lpcc_geodb_error', $result->get_error_message(), false );
			} else {
				delete_option( 'lpcc_geodb_error' );
			}
		} );
	}

	return $clean;
}

function lpcc_render_settings_page(): void {

	$opts       = get_option( 'lpcc_settings', [] );
	$db_exists  = file_exists( LPCC_GEODB_FILE );
	$db_updated = (int) get_option( 'lpcc_geodb_updated', 0 );
	$db_error   = get_option( 'lpcc_geodb_error', '' );
	?>
	<div class="wrap">
		<h1>LP-CC Consent</h1>

		<h2 class="title">Status Geo-Datenbank</h2>
		<p>
			<?php if ( $db_exists ) : ?>
				<span style="color:#00a32a">&#9679;</span>
				GeoLite2-Country.mmdb vorhanden
				<?php if ( $db_updated ) : ?>
					(Stand: <?php echo esc_html( wp_date( 'd.m.Y H:i', $db_updated ) ); ?>)
				<?php endif; ?>
			<?php else : ?>
				<span style="color:#d63638">&#9679;</span>
				Keine Geo-Datenbank &ndash; alle Besucher erhalten das Opt-in-Banner (Fail-safe).
			<?php endif; ?>
			<?php if ( $db_error ) : ?>
				<br><strong style="color:#d63638">Letzter Download-Fehler:</strong>
				<?php echo esc_html( $db_error ); ?>
			<?php endif; ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'lpcc_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lpcc_maxmind_key">MaxMind License Key</label></th>
					<td>
						<input type="text" id="lpcc_maxmind_key" class="regular-text"
						       name="lpcc_settings[maxmind_key]"
						       value="<?php echo esc_attr( $opts['maxmind_key'] ?? '' ); ?>">
						<p class="description">
							Kostenloser Key unter maxmind.com &rarr; GeoLite2. Nach dem Speichern
							wird die Datenbank automatisch geladen; danach monatliches Auto-Update per Cron.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lpcc_fb_pixel_id">Facebook Pixel ID</label></th>
					<td>
						<input type="text" id="lpcc_fb_pixel_id" class="regular-text"
						       name="lpcc_settings[fb_pixel_id]"
						       value="<?php echo esc_attr( $opts['fb_pixel_id'] ?? '' ); ?>">
						<p class="description">Nur die numerische ID. Leer lassen = kein Pixel. Kategorie: Marketing.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lpcc_ga4_id">GA4 Measurement ID</label></th>
					<td>
						<input type="text" id="lpcc_ga4_id" class="regular-text"
						       name="lpcc_settings[ga4_id]" placeholder="G-XXXXXXXXXX"
						       value="<?php echo esc_attr( $opts['ga4_id'] ?? '' ); ?>">
						<p class="description">Leer lassen = kein GA4. Kategorie: Statistik.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lpcc_privacy_en">Privacy Policy URL (EN)</label></th>
					<td>
						<input type="url" id="lpcc_privacy_en" class="regular-text"
						       name="lpcc_settings[privacy_url_en]"
						       value="<?php echo esc_attr( $opts['privacy_url_en'] ?? '' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lpcc_privacy_de">Datenschutz-URL (DE)</label></th>
					<td>
						<input type="url" id="lpcc_privacy_de" class="regular-text"
						       name="lpcc_settings[privacy_url_de]"
						       value="<?php echo esc_attr( $opts['privacy_url_de'] ?? '' ); ?>">
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2 class="title">Integration</h2>
		<p>
			Widerrufs-Link (Footer): Shortcode <code>[lpcc_settings_link]</code> oder
			PHP <code>lpcc_settings_link()</code>.<br>
			Eigene Scripts gaten: <code>&lt;script type="text/plain" data-lpcc="marketing"&gt;&hellip;&lt;/script&gt;</code><br>
			Embeds (2-Klick): <code>&lt;iframe data-src="&hellip;" data-lpcc="marketing"&gt;&lt;/iframe&gt;</code>
			&ndash; Kategorien: <code>statistics</code> | <code>marketing</code>
		</p>
	</div>
	<?php
}
