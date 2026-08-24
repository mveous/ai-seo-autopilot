<?php
/**
 * Single source of truth for reading/writing per-post SEO metadata.
 * Every field is stored under a `_ai_seo_*` postmeta key with a
 * dedicated sanitizer, so nothing else in the plugin touches
 * get_post_meta()/update_post_meta() directly for these fields.
 *
 * @package AISEOAutopilot\SEO\Metadata
 */

namespace AISEOAutopilot\SEO\Metadata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MetaRepository {

	private const PREFIX = '_ai_seo_';

	/**
	 * Field => sanitizer callback. Single source of truth for both the
	 * metabox save handler and the REST controller.
	 *
	 * @return array<string,callable>
	 */
	public function field_sanitizers(): array {
		return array(
			'title'            => 'sanitize_text_field',
			'description'      => 'sanitize_textarea_field',
			'canonical'        => array( $this, 'sanitize_url_or_empty' ),
			'robots_noindex'   => array( $this, 'sanitize_bool' ),
			'robots_nofollow'  => array( $this, 'sanitize_bool' ),
			'robots_noarchive' => array( $this, 'sanitize_bool' ),
			'og_title'         => 'sanitize_text_field',
			'og_description'   => 'sanitize_textarea_field',
			'og_image'         => array( $this, 'sanitize_url_or_empty' ),
			'twitter_title'    => 'sanitize_text_field',
			'twitter_description' => 'sanitize_textarea_field',
			'twitter_image'    => array( $this, 'sanitize_url_or_empty' ),
			'focus_keyword'    => 'sanitize_text_field',
			'schema_type'      => 'sanitize_key',
			'schema_json'      => array( $this, 'sanitize_schema_json' ),
		);
	}

	/**
	 * Validates that a value is well-formed JSON representing an object
	 * or array before it's allowed to be stored as a schema override.
	 * Re-encoding (rather than storing the raw string) guarantees what
	 * lands in the database is always syntactically valid JSON.
	 */
	private function sanitize_schema_json( $value ): string {
		if ( is_array( $value ) ) {
			$decoded = $value;
		} else {
			$decoded = json_decode( (string) $value, true );
		}

		if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			return '';
		}

		$encoded = wp_json_encode( $decoded );

		return false === $encoded ? '' : $encoded;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_all( int $post_id ): array {
		$data = array();

		foreach ( array_keys( $this->field_sanitizers() ) as $field ) {
			$data[ $field ] = $this->get( $post_id, $field );
		}

		return $data;
	}

	public function get( int $post_id, string $field ) {
		$value = get_post_meta( $post_id, self::PREFIX . $field, true );

		if ( '' === $value && $this->is_bool_field( $field ) ) {
			return false;
		}

		return $value;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function update( int $post_id, array $data ): void {
		$sanitizers = $this->field_sanitizers();

		foreach ( $data as $field => $value ) {
			if ( ! isset( $sanitizers[ $field ] ) ) {
				continue;
			}

			$clean = call_user_func( $sanitizers[ $field ], $value );

			if ( '' === $clean || false === $clean ) {
				delete_post_meta( $post_id, self::PREFIX . $field );
				continue;
			}

			update_post_meta( $post_id, self::PREFIX . $field, $clean );
		}
	}

	public function delete_all( int $post_id ): void {
		foreach ( array_keys( $this->field_sanitizers() ) as $field ) {
			delete_post_meta( $post_id, self::PREFIX . $field );
		}
	}

	public function meta_key( string $field ): string {
		return self::PREFIX . $field;
	}

	private function is_bool_field( string $field ): bool {
		return in_array( $field, array( 'robots_noindex', 'robots_nofollow', 'robots_noarchive' ), true );
	}

	private function sanitize_bool( $value ): bool {
		return (bool) $value;
	}

	private function sanitize_url_or_empty( $value ): string {
		$value = trim( (string) $value );

		return '' === $value ? '' : esc_url_raw( $value );
	}
}
