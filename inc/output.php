<?php
/**
 * Frontend-Ausgabe
 *
 *   - Banner + Präferenzen-Dialog im Footer (per JS ein-/ausgeblendet)
 *   - Gegatete Tracking-Snippets (FB Pixel, GA4) im <head>
 *   - Shortcode/Helper für den Widerrufs-Link
 *
 * @package LPCC
 */

defined( 'ABSPATH' ) || exit;

// ============================================================================
// TEXTE (EN/DE, via Filter überschreibbar)
// ============================================================================

function lpcc_texts(): array {

	$lang = lpcc_lang();

	$texts = [
		'en' => [
			'title'        => 'We value your privacy',
			'intro'        => 'We use cookies to analyze traffic and improve your experience. Marketing cookies (e.g. Meta Pixel) are only set with your consent.',
			'accept_all'   => 'Accept all',
			'deny'         => 'Essential only',
			'preferences'  => 'Preferences',
			'save'         => 'Save selection',
			'privacy'      => 'Privacy Policy',
			'cat_ess'      => 'Essential',
			'cat_ess_desc' => 'Required for the website to function. Always active.',
			'cat_stat'     => 'Statistics',
			'cat_stat_desc'=> 'Anonymous usage statistics (Google Analytics).',
			'cat_mkt'      => 'Marketing',
			'cat_mkt_desc' => 'Marketing and remarketing (Meta Pixel) and embedded content such as maps and virtual tours.',
			'load_embed'   => 'Load external content',
			'embed_note'   => 'Loading this content transfers data to a third-party provider (e.g. Google, Matterport).',
			'embed_always' => 'Always allow embedded content',
			'footer_link'  => 'Privacy Settings',
		],
		'de' => [
			'title'        => 'Wir respektieren Deine Privatsphäre',
			'intro'        => 'Wir verwenden Cookies zur Reichweitenmessung und Verbesserung des Angebots. Marketing-Cookies (z. B. Meta Pixel) werden nur mit Deiner Einwilligung gesetzt.',
			'accept_all'   => 'Alle akzeptieren',
			'deny'         => 'Nur essenzielle',
			'preferences'  => 'Einstellungen',
			'save'         => 'Auswahl speichern',
			'privacy'      => 'Datenschutzerklärung',
			'cat_ess'      => 'Essenziell',
			'cat_ess_desc' => 'Für die Funktion der Website erforderlich. Immer aktiv.',
			'cat_stat'     => 'Statistik',
			'cat_stat_desc'=> 'Anonyme Nutzungsstatistik (Google Analytics).',
			'cat_mkt'      => 'Marketing',
			'cat_mkt_desc' => 'Marketing und Remarketing (Meta Pixel) sowie eingebettete Inhalte wie Karten und virtuelle Rundgänge.',
			'load_embed'   => 'Externen Inhalt laden',
			'embed_note'   => 'Beim Laden werden Daten an einen Drittanbieter übertragen (z. B. Google, Matterport).',
			'embed_always' => 'Eingebettete Inhalte immer erlauben',
			'footer_link'  => 'Privatsphäre-Einstellungen',
		],
	];

	$texts = apply_filters( 'lpcc_texts_all', $texts );
	$set   = $texts[ $lang ] ?? $texts['en'];

	$set['privacy_url'] = ( 'de' === $lang )
		? lpcc_option( 'privacy_url_de', lpcc_option( 'privacy_url_en' ) )
		: lpcc_option( 'privacy_url_en' );

	return apply_filters( 'lpcc_texts', $set, $lang );
}

// ============================================================================
// GEGATETE TRACKING-SNIPPETS
// ============================================================================

add_action( 'wp_head', 'lpcc_output_tracking_snippets', 5 );

function lpcc_output_tracking_snippets(): void {

	if ( is_admin() ) {
		return;
	}

	$pixel = lpcc_option( 'fb_pixel_id' );
	$ga4   = lpcc_option( 'ga4_id' );

	// Meta/Facebook Pixel – Kategorie "marketing"
	if ( $pixel ) : ?>
<script type="text/plain" data-lpcc="marketing" data-lpcc-name="meta-pixel">
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo esc_js( $pixel ); ?>');
fbq('track', 'PageView');
</script>
<?php
	// Bewusst KEIN <noscript>-Pixel: der würde ohne JS das Consent-Gating umgehen.
	endif;

	// GA4 – Kategorie "statistics"
	if ( $ga4 ) : ?>
<script type="text/plain" data-lpcc="statistics" data-lpcc-name="ga4"
        data-lpcc-src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4 ); ?>"></script>
<script type="text/plain" data-lpcc="statistics" data-lpcc-name="ga4-init">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?php echo esc_js( $ga4 ); ?>', { 'anonymize_ip': true });
</script>
	<?php endif;
}

// ============================================================================
// BANNER-MARKUP (Footer, initial versteckt)
// ============================================================================

add_action( 'wp_footer', 'lpcc_output_banner', 50 );

function lpcc_output_banner(): void {

	if ( is_admin() ) {
		return;
	}

	$t = lpcc_texts();
	?>
	<div id="lpcc-banner" class="lpcc-banner" hidden role="dialog"
	     aria-modal="false" aria-labelledby="lpcc-banner-title">
		<div class="lpcc-banner__inner">
			<p id="lpcc-banner-title" class="lpcc-banner__title"><?php echo esc_html( $t['title'] ); ?></p>
			<p class="lpcc-banner__text">
				<?php echo esc_html( $t['intro'] ); ?>
				<?php if ( $t['privacy_url'] ) : ?>
					<a href="<?php echo esc_url( $t['privacy_url'] ); ?>"><?php echo esc_html( $t['privacy'] ); ?></a>
				<?php endif; ?>
			</p>

			<div id="lpcc-prefs" class="lpcc-prefs" hidden>
				<label class="lpcc-cat">
					<input type="checkbox" checked disabled>
					<span><strong><?php echo esc_html( $t['cat_ess'] ); ?></strong>
					<small><?php echo esc_html( $t['cat_ess_desc'] ); ?></small></span>
				</label>
				<label class="lpcc-cat">
					<input type="checkbox" id="lpcc-cat-statistics">
					<span><strong><?php echo esc_html( $t['cat_stat'] ); ?></strong>
					<small><?php echo esc_html( $t['cat_stat_desc'] ); ?></small></span>
				</label>
				<label class="lpcc-cat">
					<input type="checkbox" id="lpcc-cat-marketing">
					<span><strong><?php echo esc_html( $t['cat_mkt'] ); ?></strong>
					<small><?php echo esc_html( $t['cat_mkt_desc'] ); ?></small></span>
				</label>
			</div>

			<div class="lpcc-banner__actions">
				<button type="button" class="lpcc-btn lpcc-btn--primary" id="lpcc-accept-all">
					<?php echo esc_html( $t['accept_all'] ); ?>
				</button>
				<button type="button" class="lpcc-btn" id="lpcc-deny">
					<?php echo esc_html( $t['deny'] ); ?>
				</button>
				<button type="button" class="lpcc-btn lpcc-btn--ghost" id="lpcc-toggle-prefs"
				        data-save-label="<?php echo esc_attr( $t['save'] ); ?>">
					<?php echo esc_html( $t['preferences'] ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
}

// ============================================================================
// WIDERRUFS-LINK
// ============================================================================

/**
 * Footer-Link zum erneuten Öffnen des Banners (EU) bzw. Opt-out (ROW).
 */
function lpcc_settings_link( string $class = 'lpcc-settings-link' ): string {
	$t = lpcc_texts();
	return sprintf(
		'<a href="#" class="%s" onclick="window.lpcc&&window.lpcc.open();return false;">%s</a>',
		esc_attr( $class ),
		esc_html( $t['footer_link'] )
	);
}

add_shortcode( 'lpcc_settings_link', function (): string {
	return lpcc_settings_link();
} );
