HEAD
# LP-CC Consent

Schlankes, wiederverwendbares Cookie-Consent-Plugin für WordPress mit Geo-Split.

## Konzept

| Region | Verhalten |
|---|---|
| EU/EEA/UK/CH | Opt-in-Banner, Tracking blockiert bis Zustimmung (DSGVO) |
| Rest der Welt (v. a. US) | Kein Banner, Tracking aktiv, Opt-out via Footer-Link (CCPA/FDBR) |
| Geo unbekannt / keine DB | Fail-safe: Banner für alle |

- HTML für alle Besucher identisch → **voll cache-kompatibel** (Geo-Entscheidung per uncached REST-Request, Ergebnis im Cookie, 1 Request pro Besucher).
- Geo-Lookup lokal via **MaxMind GeoLite2-Country** (gebündelter Pure-PHP-Reader, keine Server-Extension). Monatliches Auto-Update per WP-Cron.
- **Kein Overkill:** kein TCF, kein Consent Mode, kein Consent-Logging, keine Scanner.

## Setup

1. Plugin installieren & aktivieren.
2. Kostenlosen MaxMind-Account anlegen → GeoLite2 License Key erzeugen.
3. Einstellungen → **LP-CC Consent**: Key eintragen (DB wird sofort geladen), Facebook Pixel ID und/oder GA4 Measurement ID eintragen, Datenschutz-URLs setzen.
4. Widerrufs-Link in den Footer: `[lpcc_settings_link]` oder `<?php echo lpcc_settings_link(); ?>`.

## Tracking

FB Pixel (Kategorie *marketing*) und GA4 (Kategorie *statistics*) gibt das Plugin selbst korrekt gegated aus — **kein Tracking-Code im Theme**.

## Eigene Scripts gaten

```html
<script type="text/plain" data-lpcc="marketing">
  /* wird erst nach Marketing-Opt-in ausgeführt */
</script>
<script type="text/plain" data-lpcc="statistics" data-lpcc-src="https://example.com/ext.js"></script>
```

## Embeds (2-Klick-Lösung)

```html
<iframe data-src="https://www.google.com/maps/embed?..." data-lpcc="marketing"
        width="600" height="450" loading="lazy"></iframe>
```

Ohne Consent erscheint ein Platzhalter mit „Inhalt laden"-Button und „immer erlauben"-Checkbox.

## Theming

Alles über CSS-Variablen (`--lpcc-bg`, `--lpcc-fg`, `--lpcc-accent`, `--lpcc-accent-fg`, `--lpcc-font`, `--lpcc-radius`, `--lpcc-embed-bg`, …) — Defaults neutral dunkel, Überschreiben im Theme-CSS.

## Filter & API

| Hook / API | Zweck |
|---|---|
| `lpcc_texts` / `lpcc_texts_all` | Banner-Texte anpassen / Sprachen ergänzen |
| `lpcc_lang` | Sprachermittlung überschreiben |
| `lpcc_optin_countries` | Länderliste für Opt-in-Pflicht anpassen |
| `lpcc_client_ip` | IP-Quelle hinter Reverse-Proxy umstellen |
| `window.lpcc.open()` | Banner/Einstellungen öffnen (Widerruf) |
| `window.lpcc.get()` / `.set({...})` | Consent-Status lesen/setzen (JS) |

## Consent-Version

`LPCC_CONSENT_VERSION` in `lp-cc-consent.php` erhöhen ⇒ alle Besucher werden neu gefragt (z. B. nach Hinzufügen neuer Tracking-Dienste).

## Lizenz Dritter

`lib/MaxMind/` — MaxMind-DB-Reader-php, Apache-2.0 (siehe `lib/MaxMind/LICENSE`).
ac2211d024ece14d6b5002c28b879e8ebdff1d6e
