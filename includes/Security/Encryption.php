<?php
/**
 * Symmetric encryption for at-rest secrets (AI provider API keys).
 *
 * Uses AES-256-GCM with a key derived from WordPress's own secret
 * authentication salts, so no additional secret management is required.
 * This protects keys against casual disclosure (DB dumps, backups,
 * read-only file access) — it is not a substitute for server hardening,
 * since anyone with PHP execution on the site can derive the same key.
 *
 * @package AISEOAutopilot\Security
 */

namespace AISEOAutopilot\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Encryption {

	private const CIPHER = 'aes-256-gcm';
	private const PREFIX = 'aiseo:v1:';

	/**
	 * Encrypt a plaintext string. Returns a self-describing, versioned
	 * string safe to store in an option/postmeta value.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$key    = self::key();
		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$iv     = random_bytes( $iv_len );
		$tag    = '';

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ciphertext ) {
			// Fail safe: never store plaintext silently mislabeled as encrypted.
			return '';
		}

		return self::PREFIX . base64_encode( $iv . $tag . $ciphertext );
	}

	/**
	 * Decrypt a value previously produced by encrypt(). Returns null if
	 * the value is empty, malformed, or fails authentication.
	 */
	public static function decrypt( string $stored ): ?string {
		if ( '' === $stored || 0 !== strpos( $stored, self::PREFIX ) ) {
			return null;
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw ) {
			return null;
		}

		$iv_len  = openssl_cipher_iv_length( self::CIPHER );
		$tag_len = 16;

		if ( strlen( $raw ) < $iv_len + $tag_len ) {
			return null;
		}

		$iv         = substr( $raw, 0, $iv_len );
		$tag        = substr( $raw, $iv_len, $tag_len );
		$ciphertext = substr( $raw, $iv_len + $tag_len );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag );

		return false === $plaintext ? null : $plaintext;
	}

	/**
	 * Mask a secret for display purposes only, e.g. "sk-...a1B2".
	 */
	public static function mask( string $plaintext ): string {
		$len = strlen( $plaintext );
		if ( $len <= 8 ) {
			return str_repeat( '•', max( 4, $len ) );
		}

		return substr( $plaintext, 0, 3 ) . str_repeat( '•', 6 ) . substr( $plaintext, -4 );
	}

	private static function key(): string {
		$secret = '';

		if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
			$secret .= AUTH_KEY;
		}
		if ( defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ) {
			$secret .= SECURE_AUTH_KEY;
		}
		if ( defined( 'AUTH_SALT' ) && AUTH_SALT ) {
			$secret .= AUTH_SALT;
		}

		if ( '' === $secret ) {
			// Extremely unlikely on a real WP install, but never fatal.
			$secret = wp_salt( 'auth' );
		}

		return hash( 'sha256', $secret, true );
	}
}
