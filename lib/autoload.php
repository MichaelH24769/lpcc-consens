<?php
/**
 * Minimaler PSR-4-Autoloader für den gebündelten MaxMind-DB-Reader.
 * Quelle: https://github.com/maxmind/MaxMind-DB-Reader-php (Apache-2.0, s. LICENSE)
 *
 * @package LPCC
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register( function ( string $class ): void {
	if ( 0 !== strpos( $class, 'MaxMind\\' ) ) {
		return;
	}
	$path = __DIR__ . '/' . str_replace( '\\', '/', $class ) . '.php';
	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );
