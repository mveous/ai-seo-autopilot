<?php
/**
 * Bootstraps the bundled WordPress AI Client SDK and registers the OpenAI,
 * Google, and Anthropic AI Client providers with it.
 *
 * The SDK core (wordpress/wp-ai-client) is composer-installed in the
 * plugin's root vendor/, while the three provider packages live in their
 * own isolated composer project under includes/ai-providers/vendor/. That
 * split — and the defensive class_exists()/hasProvider() checks below —
 * mirrors how the SDK's own packages expect to be bundled, so this plugin
 * can't fatal if another plugin on the same site also bundles it.
 *
 * @package AISEOAutopilot\AI
 */

namespace AISEOAutopilot\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIClient {

	private static bool $initialized = false;

	/**
	 * Registers the bootstrap hooks. Safe to call multiple times.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'init', array( self::class, 'register' ), 5 );
		add_filter( 'http_request_timeout', array( self::class, 'increase_timeout_for_ai_hosts' ), 9999, 2 );
	}

	/**
	 * AI completions can take longer than WordPress's default HTTP timeout
	 * allows for, so raise it specifically for requests to AI provider hosts.
	 *
	 * @param int    $timeout Current timeout in seconds.
	 * @param string $url     Request URL.
	 */
	public static function increase_timeout_for_ai_hosts( int $timeout, string $url ): int {
		foreach ( array( 'api.openai.com', 'generativelanguage.googleapis.com', 'api.anthropic.com' ) as $host ) {
			if ( false !== strpos( $url, $host ) ) {
				return 60;
			}
		}

		return $timeout;
	}

	/**
	 * Loads the SDK and registers every bundled provider with its default
	 * registry. Safe to call more than once; already-registered providers
	 * are skipped.
	 */
	public static function register(): void {
		$has_native = self::has_native_ai_client();

		// On WordPress 7.0+, core bundles the AI Client SDK natively — fully
		// wired (HTTP transport, cache, event dispatcher) — under the exact
		// same unprefixed \WordPress\AiClient\* class names our own bundled
		// copy uses, but with its OWN internally-scoped dependency versions.
		// Loading our bundled copy's autoloader on top of that is what
		// actually breaks: it's not merely redundant, it makes our own
		// WordPress\AI_Client\* glue classes (see bootstrap_bundled_sdk())
		// autoloadable, and those extend abstract classes whose method
		// signatures are tied to internally-scoped dependency types —
		// declaring them against core's differently-scoped versions is a
		// fatal "incompatible declaration", not just a conflict. So on
		// 7.0+ we never require our bundled SDK autoloader or reference
		// any WordPress\AI_Client\* class at all, and rely entirely on
		// core's already-configured registry instead.
		if ( ! $has_native ) {
			$sdk_autoload = AI_SEO_AUTOPILOT_DIR . 'vendor/wordpress/wp-ai-client/autoload.php';

			if ( file_exists( $sdk_autoload ) ) {
				require_once $sdk_autoload;
			}
		}

		$providers_autoload = AI_SEO_AUTOPILOT_DIR . 'includes/ai-providers/vendor/autoload.php';

		if ( file_exists( $providers_autoload ) ) {
			require_once $providers_autoload;
		}

		if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
			return;
		}

		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		if ( ! $has_native ) {
			self::bootstrap_bundled_sdk( $registry );
		}

		self::register_provider( $registry, \WordPress\OpenAiAiProvider\Provider\OpenAiProvider::class );
		self::register_provider( $registry, \WordPress\GoogleAiProvider\Provider\GoogleProvider::class );
		self::register_provider( $registry, \WordPress\AnthropicAiProvider\Provider\AnthropicProvider::class );
	}

	/**
	 * Whether WordPress core provides the AI Client SDK natively. Mirrors
	 * the check the wp-ai-client package's own functions.php uses, without
	 * requiring that file first (see the comment in register() for why we
	 * can't load it unconditionally).
	 */
	private static function has_native_ai_client(): bool {
		return function_exists( 'wp_get_wp_version' ) && version_compare( wp_get_wp_version(), '7.0-alpha', '>=' );
	}

	/**
	 * Wires up our bundled copy of the SDK's WordPress integration — event
	 * dispatcher, cache, and an HTTP transporter running over
	 * wp_remote_request() instead of a raw discovered cURL/Guzzle client.
	 * Only ever called when core does NOT provide the SDK natively (see the
	 * has_native guard in register()).
	 *
	 * @param \WordPress\AiClient\Providers\ProviderRegistry $registry
	 */
	private static function bootstrap_bundled_sdk( $registry ): void {
		if ( class_exists( \WordPress\AI_Client\AI_Client::class ) ) {
			\WordPress\AI_Client\AI_Client::init();
		}

		if ( class_exists( \WordPress\AI_Client\HTTP\WP_AI_Client_Discovery_Strategy::class ) ) {
			\WordPress\AI_Client\HTTP\WP_AI_Client_Discovery_Strategy::init();
		}

		if ( ! class_exists( \WordPress\AiClient\Providers\Http\HttpTransporterFactory::class ) ) {
			return;
		}

		try {
			$registry->setHttpTransporter( \WordPress\AiClient\Providers\Http\HttpTransporterFactory::createTransporter() );
		} catch ( \Throwable $e ) {
			// Leave the registry without a transporter; complete_via_ai_client()
			// will surface the resulting SDK exception as a WP_Error.
		}
	}

	/**
	 * @param \WordPress\AiClient\Providers\ProviderRegistry $registry
	 * @param class-string                                    $class
	 */
	private static function register_provider( $registry, string $class ): void {
		if ( class_exists( $class ) && ! $registry->hasProvider( $class ) ) {
			$registry->registerProvider( $class );
		}
	}

	/**
	 * Whether the bundled AI Client SDK loaded successfully.
	 */
	public static function is_available(): bool {
		return class_exists( \WordPress\AiClient\AiClient::class );
	}
}
