<?php
/**
 * Shared plumbing for REST controllers: namespace, permission helpers,
 * and consistent response shapes.
 *
 * @package AISEOAutopilot\REST\Controllers
 */

namespace AISEOAutopilot\REST\Controllers;

use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Features\CapabilityManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractController {

	protected const NAMESPACE = 'ai-seo/v1';

	abstract public function register_routes(): void;

	/**
	 * Build a `permission_callback` bound to a CapabilityManager action.
	 */
	protected function permission( string $action ): callable {
		return static function () use ( $action ): bool {
			/** @var CapabilityManager|null $capabilities */
			$capabilities = Plugin::instance()->get( 'capabilities' );

			return $capabilities ? $capabilities->current_user_can( $action ) : current_user_can( 'manage_options' );
		};
	}

	protected function success( $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	protected function error( \WP_Error $error, int $status = 400 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => $error->get_error_message(),
				'code'    => $error->get_error_code(),
			),
			$status
		);
	}
}
