<?php
/**
 * Registers per-post SEO fields for both the block editor (via
 * register_post_meta + show_in_rest, consumed by the Gutenberg sidebar
 * panel JS) and the classic editor (a plain meta box + save_post).
 *
 * @package Scvx\SEO\Metadata
 */

namespace Scvx\SEO\Metadata;

use Scvx\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MetaboxManager implements Registrable {

	private const NONCE_ACTION = 'scvx_save_meta';
	private const NONCE_NAME   = 'scvx_meta_nonce';

	private MetaRepository $repository;

	public function __construct( MetaRepository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_meta_fields' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_classic_metabox' ) );
		add_action( 'save_post', array( $this, 'save_classic_metabox' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function register_meta_fields(): void {
		$post_types = $this->supported_post_types();

		foreach ( $this->repository->field_sanitizers() as $field => $sanitizer ) {
			$key  = $this->repository->meta_key( $field );
			$type = $this->is_bool_field( $field ) ? 'boolean' : 'string';

			foreach ( $post_types as $post_type ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'single'            => true,
						'type'              => $type,
						'default'           => $this->is_bool_field( $field ) ? false : '',
						'show_in_rest'      => true,
						'sanitize_callback' => static function ( $value ) use ( $sanitizer ) {
							return call_user_func( $sanitizer, $value );
						},
						'auth_callback'     => static function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
		}
	}

	public function add_classic_metabox(): void {
		foreach ( $this->supported_post_types() as $post_type ) {
			add_meta_box(
				'scanvexa-seo-meta',
				__( 'Scanvexa SEO — AI Website Analyzer', 'scanvexa-seo' ),
				array( $this, 'render_classic_metabox' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public function render_classic_metabox( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$data = $this->repository->get_all( $post->ID );
		?>
		<div class="scvx-metabox" id="scanvexa-seo-root" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
			<p class="description">
				<?php esc_html_e( 'This panel also appears in the block editor sidebar with live AI generation and a real-time SEO score. In the classic editor, fill in the fields below manually or use the AI SEO menu to generate suggestions.', 'scanvexa-seo' ); ?>
			</p>
			<p>
				<label for="scvx_title"><strong><?php esc_html_e( 'SEO Title', 'scanvexa-seo' ); ?></strong></label><br />
				<input type="text" class="widefat" id="scvx_title" name="ai_seo[title]" value="<?php echo esc_attr( $data['title'] ); ?>" maxlength="70" />
			</p>
			<p>
				<label for="scvx_description"><strong><?php esc_html_e( 'Meta Description', 'scanvexa-seo' ); ?></strong></label><br />
				<textarea class="widefat" id="scvx_description" name="ai_seo[description]" rows="3" maxlength="320"><?php echo esc_textarea( $data['description'] ); ?></textarea>
			</p>
			<p>
				<label for="scvx_focus_keyword"><strong><?php esc_html_e( 'Focus Keyword', 'scanvexa-seo' ); ?></strong></label><br />
				<input type="text" class="widefat" id="scvx_focus_keyword" name="ai_seo[focus_keyword]" value="<?php echo esc_attr( $data['focus_keyword'] ); ?>" />
			</p>
			<p>
				<label for="scvx_canonical"><strong><?php esc_html_e( 'Canonical URL', 'scanvexa-seo' ); ?></strong></label><br />
				<input type="url" class="widefat" id="scvx_canonical" name="ai_seo[canonical]" value="<?php echo esc_attr( $data['canonical'] ); ?>" placeholder="https://" />
			</p>
			<p>
				<label>
					<input type="checkbox" name="ai_seo[robots_noindex]" value="1" <?php checked( ! empty( $data['robots_noindex'] ) ); ?> />
					<?php esc_html_e( 'Discourage search engines from indexing this content (noindex)', 'scanvexa-seo' ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	public function save_classic_metabox( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['ai_seo'] ) || ! is_array( $_POST['ai_seo'] ) ) {
			return;
		}

		$raw = wp_unslash( $_POST['ai_seo'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified above; every field is whitelisted and sanitized in MetaRepository::update() via field_sanitizers().
		$this->repository->update( $post_id, $raw );
	}

	public function enqueue_editor_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->supported_post_types(), true ) ) {
			return;
		}

		$asset_file = SCVX_DIR . 'admin/js/editor.asset.php';
		$deps       = array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-api-fetch', 'wp-editor' );
		$version    = SCVX_VERSION;

		if ( is_readable( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = $asset['dependencies'] ?? $deps;
			$version = $asset['version'] ?? $version;
		}

		wp_enqueue_script(
			'scanvexa-seo-editor',
			SCVX_URL . 'admin/js/editor.js',
			$deps,
			$version,
			true
		);

		wp_enqueue_style(
			'scanvexa-seo-editor',
			SCVX_URL . 'admin/css/editor.css',
			array(),
			SCVX_VERSION
		);

		wp_localize_script(
			'scanvexa-seo-editor',
			'scvxEditor',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'scvx/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'metaPrefix'   => '_scvx_',
				'titleLimit'   => 60,
				'descriptionLimit' => 160,
			)
		);
	}

	/**
	 * @return string[]
	 */
	private function supported_post_types(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		/**
		 * Filters which post types get SEO metadata fields.
		 *
		 * @param string[] $post_types
		 */
		return array_values( (array) apply_filters( 'scvx/supported_post_types', array_values( $post_types ) ) );
	}

	private function is_bool_field( string $field ): bool {
		return in_array( $field, array( 'robots_noindex', 'robots_nofollow', 'robots_noarchive' ), true );
	}
}
