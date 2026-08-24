<?php
/**
 * Minimal PSR-4 autoloader.
 *
 * Works standalone (no Composer required at runtime) so the plugin never
 * fatals in environments where `composer install` hasn't been run. If a
 * Composer autoloader is present it is loaded first and takes precedence.
 *
 * @package AISEOAutopilot\Core
 */

namespace AISEOAutopilot\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {

	/**
	 * Registered namespace prefix => base directory map.
	 *
	 * @var array<string,string>
	 */
	private static array $prefixes = array();

	/**
	 * Register a PSR-4 namespace prefix and its base directory.
	 */
	public static function register( string $prefix, string $base_dir ): void {
		$prefix = trim( $prefix, '\\' ) . '\\';
		self::$prefixes[ $prefix ] = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;

		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Attempt to load a class for the given fully-qualified class name.
	 */
	public static function load( string $class ): void {
		foreach ( self::$prefixes as $prefix => $base_dir ) {
			if ( 0 !== strpos( $class, $prefix ) ) {
				continue;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$path     = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

			if ( is_readable( $path ) ) {
				require $path;
			}

			return;
		}
	}
}
