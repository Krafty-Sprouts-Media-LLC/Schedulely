<?php
/**
 * Filename: class-ai-order.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 04/05/2026
 * Last Modified: 2026
 * Description: AI queue-ordering client. Uses wp_ai_client_prompt() on WordPress 7.0+;
 *              falls back to a direct OpenAI-compatible HTTP request on older installs.
 *
 * @package Schedulely
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schedulely_AI_Order
 *
 * Returns a permutation of post IDs that spaces series posts apart.
 * Publish times are still assigned by Schedulely_Scheduler — this class
 * only reorders the queue, it does not schedule anything.
 *
 * Two paths:
 *  - WP 7.0+ path  : wp_ai_client_prompt() — provider-agnostic, no stored API key.
 *  - Legacy path   : wp_remote_post() to an OpenAI-compatible endpoint.
 *                    Deprecated. Will be removed in a future major version.
 */
class Schedulely_AI_Order {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Reorder post IDs using the configured AI, or return WP_Error on failure.
	 *
	 * On WordPress 7.0+ the WP AI client is used automatically — no API key
	 * stored in Schedulely settings is required. On older WordPress installs
	 * the legacy direct-HTTP path is used if a key is configured.
	 *
	 * @since 1.4.0
	 * @since 1.6.0 Added WP 7.0 path via wp_ai_client_prompt().
	 * @since 1.7.0 Now sends slug alongside title for better state detection.
	 *
	 * @param array<int> $post_ids List of positive post IDs.
	 * @return array<int>|WP_Error Ordered IDs (same length as input) or error.
	 */
	public function reorder_post_ids( array $post_ids ) {
		$post_ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );

		if ( count( $post_ids ) < 2 ) {
			return $post_ids;
		}

		if ( $this->wp_ai_available() ) {
			return $this->reorder_via_wp_ai( $post_ids );
		}

		return $this->reorder_via_legacy_http( $post_ids );
	}

	/**
	 * Reorder post IDs with timezone group assignments.
	 *
	 * Used when US timezone-aware ordering is enabled. Returns an array of
	 * [ 'id' => int, 'timezone_group' => string ] maps, where timezone_group
	 * is one of: 'eastern', 'central', 'mountain', 'pacific', 'general'.
	 *
	 * Falls back to plain reorder_post_ids() on AI failure, assigning all
	 * posts to 'general' so the scheduler still works.
	 *
	 * @since 1.7.0
	 *
	 * @param array<int> $post_ids
	 * @return array<array{id:int,timezone_group:string}>
	 */
	public function reorder_post_ids_with_timezone( array $post_ids ): array {
		$post_ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );

		if ( count( $post_ids ) < 2 ) {
			return array_map( fn( $id ) => [ 'id' => $id, 'timezone_group' => 'general' ], $post_ids );
		}

		if ( $this->wp_ai_available() ) {
			$result = $this->reorder_via_wp_ai_timezone( $post_ids );
		} else {
			$result = $this->reorder_via_legacy_http_timezone( $post_ids );
		}

		if ( is_wp_error( $result ) ) {
			// Graceful fallback — plain order, all general.
			schedulely_log_error( 'Timezone AI reorder failed, falling back: ' . $result->get_error_message() );
			return array_map( fn( $id ) => [ 'id' => $id, 'timezone_group' => 'general' ], $post_ids );
		}

		return $result;
	}

	/**
	 * Test the AI connection.
	 *
	 * On WP 7.0+ checks whether text generation is supported via the configured
	 * connector. On older installs sends a minimal test request to the legacy
	 * endpoint.
	 *
	 * @since 1.4.3
	 * @since 1.6.0 WP 7.0 path uses wp_ai_client_prompt() availability check.
	 *
	 * @return array{message: string}|WP_Error
	 */
	public function test_api_connection() {
		if ( $this->wp_ai_available() ) {
			return [
				'message' => __( 'Connection OK — an AI provider is configured and supports text generation.', 'schedulely' ),
			];
		}

		return $this->test_legacy_connection();
	}

	// -------------------------------------------------------------------------
	// WP 7.0 path
	// -------------------------------------------------------------------------

	/**
	 * Check whether wp_ai_client_prompt() is available and a provider supports
	 * text generation. Result is cached for the duration of the request.
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	private function wp_ai_available(): bool {
		static $available = null;
		if ( null !== $available ) {
			return $available;
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$available = false;
			return false;
		}

		try {
			$available = (bool) wp_ai_client_prompt( '' )->is_supported_for_text_generation();
		} catch ( \Throwable $e ) {
			$available = false;
		}

		return $available;
	}

	/**
	 * Reorder post IDs via wp_ai_client_prompt() (WP 7.0+ path).
	 *
	 * @since 1.6.0
	 * @param array<int> $post_ids
	 * @return array<int>|WP_Error
	 */
	private function reorder_via_wp_ai( array $post_ids ) {
		$lines = $this->build_prompt_lines( $post_ids );
		$prompt = $this->build_user_prompt( $lines, count( $post_ids ) );

		try {
			$builder = wp_ai_client_prompt( $prompt )
				->using_system_instruction( $this->get_system_instruction() )
				->using_temperature( 1.0 );

			/**
			 * Fires just before Schedulely sends an AI reorder request.
			 * Lets the pro plugin or third parties register model preferences.
			 *
			 * @since 1.6.0
			 */
			do_action( 'schedulely_pre_ai_reorder' );

			if ( ! $builder->is_supported_for_text_generation() ) {
				return new WP_Error(
					'schedulely_ai_unsupported',
					sprintf(
						/* translators: %s: URL to the WP connectors screen */
						__( 'No AI provider supports text generation. Configure one at %s.', 'schedulely' ),
						esc_url( admin_url( 'options-connectors.php' ) )
					)
				);
			}

			$content = (string) $builder->generate_text();

		} catch ( \Throwable $e ) {
			schedulely_log_error( 'WP AI reorder exception: ' . $e->getMessage() );
			return new WP_Error( 'schedulely_ai_exception', $e->getMessage() );
		}

		return $this->process_ai_response( $post_ids, $content, 'wp_ai', null );
	}

	// -------------------------------------------------------------------------
	// Timezone-aware reorder methods (1.7.0)
	// -------------------------------------------------------------------------

	/**
	 * Timezone-aware reorder via WP 7.0 AI client.
	 *
	 * @since 1.7.0
	 * @param array<int> $post_ids
	 * @return array<array{id:int,timezone_group:string}>|WP_Error
	 */
	private function reorder_via_wp_ai_timezone( array $post_ids ) {
		$lines  = $this->build_prompt_lines( $post_ids );
		$prompt = $this->build_user_prompt( $lines, count( $post_ids ) );

		try {
			$builder = wp_ai_client_prompt( $prompt )
				->using_system_instruction( $this->get_system_instruction( true ) )
				->using_temperature( 0.4 );

			do_action( 'schedulely_pre_ai_reorder' );

			if ( ! $builder->is_supported_for_text_generation() ) {
				return new WP_Error( 'schedulely_ai_unsupported', __( 'No AI provider supports text generation.', 'schedulely' ) );
			}

			$content = (string) $builder->generate_text();

		} catch ( \Throwable $e ) {
			schedulely_log_error( 'WP AI timezone reorder exception: ' . $e->getMessage() );
			return new WP_Error( 'schedulely_ai_exception', $e->getMessage() );
		}

		return $this->process_timezone_response( $post_ids, $content, 'wp_ai', null );
	}

	/**
	 * Timezone-aware reorder via legacy HTTP path.
	 *
	 * @since 1.7.0
	 * @param array<int> $post_ids
	 * @return array<array{id:int,timezone_group:string}>|WP_Error
	 */
	private function reorder_via_legacy_http_timezone( array $post_ids ) {
		$api_key = apply_filters( 'schedulely_ai_api_key', get_option( 'schedulely_ai_api_key', '' ) );
		if ( '' === trim( (string) $api_key ) ) {
			return new WP_Error( 'schedulely_ai_no_key', __( 'No API key configured.', 'schedulely' ) );
		}

		$base  = $this->get_api_base_url();
		$model = $this->get_model();
		$url   = rtrim( $base, '/' ) . '/chat/completions';
		$lines = $this->build_prompt_lines( $post_ids );
		$body  = $this->build_request_body( $model, $lines, count( $post_ids ), true );

		$post_count      = count( $post_ids );
		$default_timeout = max( 120, min( 1200, 60 + (int) round( $post_count * 0.45 ) ) );
		$timeout         = (int) apply_filters( 'schedulely_ai_request_timeout', $default_timeout, $post_ids );
		$timeout         = min( max( 30, $timeout ), (int) apply_filters( 'schedulely_ai_request_timeout_max', 1200 ) );

		$response = wp_remote_post( $url, [
			'timeout' => $timeout,
			'headers' => $this->build_ai_http_headers( $api_key ),
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		if ( 200 !== $code || ! is_array( $decoded ) ) {
			return new WP_Error( 'schedulely_ai_http', sprintf( __( 'AI request failed (HTTP %s).', 'schedulely' ), $code ) );
		}

		$content = $this->extract_assistant_text( $decoded );
		if ( '' === $content ) {
			return new WP_Error( 'schedulely_ai_empty', __( 'AI returned an empty message.', 'schedulely' ) );
		}

		$usage_tokens = isset( $decoded['usage']['total_tokens'] ) ? (int) $decoded['usage']['total_tokens'] : null;
		return $this->process_timezone_response( $post_ids, $content, $model, $usage_tokens );
	}

	/**
	 * Parse and validate a timezone-aware AI response.
	 *
	 * Expected JSON shape:
	 *   { "ordered_ids": [1,2,3,...], "timezone_groups": {"1":"eastern","2":"pacific",...} }
	 *
	 * Falls back gracefully if timezone_groups is missing — assigns all to 'general'.
	 *
	 * @since 1.7.0
	 * @param array<int>  $post_ids
	 * @param string      $content
	 * @param string      $model
	 * @param int|null    $usage_tokens
	 * @return array<array{id:int,timezone_group:string}>|WP_Error
	 */
	private function process_timezone_response( array $post_ids, string $content, string $model, ?int $usage_tokens ) {
		$content = trim( $content );
		if ( preg_match( '/^```(?:json)?\s*(\{.*\})\s*```$/s', $content, $m ) ) {
			$content = $m[1];
		}

		$obj = json_decode( $content, true );

		// Graceful fallback: ordered_ids present but timezone_groups missing.
		if ( is_array( $obj ) && isset( $obj['ordered_ids'] ) && is_array( $obj['ordered_ids'] )
			&& ( ! isset( $obj['timezone_groups'] ) || ! is_array( $obj['timezone_groups'] ) ) ) {
			$ordered = $this->reconcile_ordered_ids_with_input( $post_ids, array_map( 'absint', $obj['ordered_ids'] ) );
			return array_map( fn( $id ) => [ 'id' => $id, 'timezone_group' => 'general' ], $ordered );
		}

		if ( ! is_array( $obj )
			|| ! isset( $obj['ordered_ids'] ) || ! is_array( $obj['ordered_ids'] )
			|| ! isset( $obj['timezone_groups'] ) || ! is_array( $obj['timezone_groups'] ) ) {
			return new WP_Error( 'schedulely_ai_tz_shape', __( 'AI timezone response missing required keys.', 'schedulely' ) );
		}

		$ordered      = $this->reconcile_ordered_ids_with_input( $post_ids, array_map( 'absint', $obj['ordered_ids'] ) );
		$valid_groups = [ 'eastern', 'central', 'mountain', 'pacific', 'general' ];
		$groups_map   = $obj['timezone_groups'];

		$result = [];
		foreach ( $ordered as $id ) {
			$group = isset( $groups_map[ (string) $id ] ) ? (string) $groups_map[ (string) $id ] : 'general';
			if ( ! in_array( $group, $valid_groups, true ) ) {
				$group = 'general';
			}
			$result[] = [ 'id' => $id, 'timezone_group' => $group ];
		}

		$this->log_attempt( 'success', $model, count( $post_ids ), null, $usage_tokens,
			'', '',
			schedulely_ai_log_sanitize_excerpt( $content, 1200 ), '',
			__( 'Timezone-aware queue order applied.', 'schedulely' )
		);

		return $result;
	}

	// -------------------------------------------------------------------------
	// Legacy HTTP path (pre-WP-7.0)
	// -------------------------------------------------------------------------

	/**
	 * Reorder post IDs via a direct OpenAI-compatible HTTP request (legacy path).
	 *
	 * @deprecated 1.6.0 Use the WP 7.0 path when wp_ai_client_prompt() is available.
	 * @since 1.4.0
	 * @param array<int> $post_ids
	 * @return array<int>|WP_Error
	 */
	private function reorder_via_legacy_http( array $post_ids ) {
		$api_key = apply_filters( 'schedulely_ai_api_key', get_option( 'schedulely_ai_api_key', '' ) );
		if ( '' === trim( (string) $api_key ) ) {
			return new WP_Error(
				'schedulely_ai_no_key',
				__( 'No API key is configured for AI queue ordering.', 'schedulely' )
			);
		}

		$base  = $this->get_api_base_url();
		$model = $this->get_model();
		$url   = rtrim( $base, '/' ) . '/chat/completions';

		$lines = $this->build_prompt_lines( $post_ids );
		$body  = $this->build_request_body( $model, $lines, count( $post_ids ) );
		$body  = apply_filters( 'schedulely_ai_chat_completions_body', $body, $post_ids );

		$post_count      = count( $post_ids );
		$default_timeout = max( 120, min( 1200, 60 + (int) round( $post_count * 0.45 ) ) );
		$timeout         = (int) apply_filters( 'schedulely_ai_request_timeout', $default_timeout, $post_ids );
		$timeout         = max( 30, $timeout );
		$max_timeout     = (int) apply_filters( 'schedulely_ai_request_timeout_max', 1200 );
		$max_timeout     = min( 1200, max( 120, $max_timeout ) );
		$timeout         = min( $timeout, $max_timeout );

		$response = wp_remote_post(
			$url,
			[
				'timeout' => $timeout,
				'headers' => $this->build_ai_http_headers( $api_key ),
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->log_attempt( 'error', $model, $post_count, null, null,
				$response->get_error_code(),
				schedulely_ai_log_sanitize_excerpt( $response->get_error_message(), 500 ),
				'', '',
				__( 'HTTP transport error before a response body was received.', 'schedulely' )
			);
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		$usage_tokens = is_array( $decoded ) && isset( $decoded['usage']['total_tokens'] )
			? (int) $decoded['usage']['total_tokens'] : null;

		if ( 200 !== $code ) {
			$err = new WP_Error(
				'schedulely_ai_http',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body excerpt */
					__( 'AI request failed (HTTP %1$s): %2$s', 'schedulely' ),
					(string) $code,
					$this->excerpt_error_body( $raw )
				)
			);
			$this->log_attempt( 'error', $model, $post_count, $code, $usage_tokens,
				'schedulely_ai_http',
				schedulely_ai_log_sanitize_excerpt( $err->get_error_message(), 500 ),
				'', schedulely_ai_log_sanitize_excerpt( $raw, 2000 ),
				__( 'Non-200 response.', 'schedulely' )
			);
			return $err;
		}

		if ( ! is_array( $decoded ) ) {
			$err = new WP_Error( 'schedulely_ai_json', __( 'AI response was not valid JSON.', 'schedulely' ) );
			$this->log_attempt( 'error', $model, $post_count, $code, $usage_tokens,
				'schedulely_ai_json',
				schedulely_ai_log_sanitize_excerpt( $err->get_error_message(), 500 ),
				'', schedulely_ai_log_sanitize_excerpt( $raw, 2000 ),
				__( 'Body was not valid JSON after HTTP 200.', 'schedulely' )
			);
			return $err;
		}

		$content = $this->extract_assistant_text( $decoded );
		if ( '' === $content ) {
			$err = new WP_Error( 'schedulely_ai_empty', __( 'AI returned an empty message.', 'schedulely' ) );
			$this->log_attempt( 'error', $model, $post_count, $code, $usage_tokens,
				'schedulely_ai_empty',
				schedulely_ai_log_sanitize_excerpt( $err->get_error_message(), 500 ),
				'', schedulely_ai_log_sanitize_excerpt( $raw, 2000 ),
				__( 'No assistant text found in response.', 'schedulely' )
			);
			return $err;
		}

		return $this->process_ai_response( $post_ids, $content, $model, $usage_tokens );
	}
	/**
	 * Test the legacy direct-HTTP connection.
	 *
	 * @since 1.4.3
	 * @deprecated 1.6.0
	 * @return array{message: string}|WP_Error
	 */
	private function test_legacy_connection() {
		$api_key = apply_filters( 'schedulely_ai_api_key', get_option( 'schedulely_ai_api_key', '' ) );
		if ( '' === trim( (string) $api_key ) ) {
			return new WP_Error(
				'schedulely_ai_no_key',
				__( 'No API key is configured.', 'schedulely' )
			);
		}

		$base = $this->get_api_base_url();
		$model = $this->get_model();
		$url  = rtrim( $base, '/' ) . '/chat/completions';

		$body = [
			'model'       => $model,
			'messages'    => [ [ 'role' => 'user', 'content' => 'Reply with the single word: ok' ] ],
			'max_tokens'  => 32,
			'temperature' => 0.2,
		];
		$body    = apply_filters( 'schedulely_ai_test_connection_body', $body );
		$timeout = (int) apply_filters( 'schedulely_ai_test_request_timeout', 45 );
		$timeout = min( 120, max( 15, $timeout ) );

		$response = wp_remote_post(
			$url,
			[
				'timeout' => $timeout,
				'headers' => $this->build_ai_http_headers( $api_key ),
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		if ( 200 !== $code ) {
			return new WP_Error(
				'schedulely_ai_http',
				sprintf(
					/* translators: 1: HTTP status code 2: response excerpt */
					__( 'Connection test failed (HTTP %1$s): %2$s', 'schedulely' ),
					(string) $code,
					$this->excerpt_error_body( $raw )
				)
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'schedulely_ai_test_json',
				sprintf(
					/* translators: %s: response excerpt */
					__( 'HTTP 200 but the body was not JSON. Excerpt: %s', 'schedulely' ),
					$this->excerpt_error_body( $raw )
				)
			);
		}

		if ( isset( $decoded['error'] ) ) {
			$msg = is_array( $decoded['error'] )
				? ( $decoded['error']['message'] ?? wp_json_encode( $decoded['error'] ) )
				: (string) $decoded['error'];
			return new WP_Error(
				'schedulely_ai_api_error',
				sprintf( /* translators: %s: API error */ __( 'API returned an error: %s', 'schedulely' ), $msg )
			);
		}

		$text         = $this->extract_assistant_text( $decoded );
		$total_tokens = isset( $decoded['usage']['total_tokens'] ) ? (int) $decoded['usage']['total_tokens'] : 0;

		if ( '' !== $text ) {
			return [ 'message' => __( 'Connection OK — the API returned an assistant message.', 'schedulely' ) ];
		}

		if ( $total_tokens > 0 ) {
			return [
				'message' => sprintf(
					/* translators: %d: token count */
					__( 'Connection OK — the API accepted the request (reported %d total tokens).', 'schedulely' ),
					$total_tokens
				),
			];
		}

		return new WP_Error(
			'schedulely_ai_test_shape',
			sprintf(
				/* translators: %s: response excerpt */
				__( 'HTTP 200 but no assistant text or usage data found. Check the Base URL and model. Excerpt: %s', 'schedulely' ),
				$this->excerpt_error_body( $raw )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Shared response processing
	// -------------------------------------------------------------------------

	/**
	 * Parse and validate the AI text response, log the outcome, and return
	 * the ordered IDs or a WP_Error.
	 *
	 * @since 1.6.0
	 *
	 * @param array<int>  $post_ids     Original input IDs.
	 * @param string      $content      Raw assistant text.
	 * @param string      $model        Model identifier or 'wp_ai' for WP 7.0 path.
	 * @param int|null    $usage_tokens Token count from legacy path; null on WP 7.0 path.
	 * @return array<int>|WP_Error
	 */
	private function process_ai_response( array $post_ids, string $content, string $model, ?int $usage_tokens ) {
		$post_count = count( $post_ids );
		$ordered    = $this->parse_ordered_ids_from_content( $content );

		if ( is_wp_error( $ordered ) ) {
			$this->log_attempt( 'error', $model, $post_count, null, $usage_tokens,
				$ordered->get_error_code(),
				schedulely_ai_log_sanitize_excerpt( $ordered->get_error_message(), 500 ),
				schedulely_ai_log_sanitize_excerpt( $content, 2000 ), '',
				__( 'Assistant text could not be parsed into ordered_ids JSON.', 'schedulely' )
			);
			return $ordered;
		}

		$parsed_raw = $ordered;
		$strict_ok  = $this->is_valid_permutation( $post_ids, $ordered );

		if ( ! $strict_ok && apply_filters( 'schedulely_ai_reconcile_invalid_ordered_ids', true ) ) {
			$ordered = $this->reconcile_ordered_ids_with_input( $post_ids, $parsed_raw );
		}

		if ( ! $this->is_valid_permutation( $post_ids, $ordered ) ) {
			$err = new WP_Error(
				'schedulely_ai_perm',
				__( 'AI response did not contain the same post IDs as the input list.', 'schedulely' )
			);
			$this->log_attempt( 'error', $model, $post_count, null, $usage_tokens,
				'schedulely_ai_perm',
				schedulely_ai_log_sanitize_excerpt( $err->get_error_message(), 500 ),
				schedulely_ai_log_sanitize_excerpt( $content, 2000 ), '',
				sprintf(
					/* translators: 1: parsed count 2: expected count */
					__( 'Parsed %1$d IDs; multiset does not match %2$d input IDs.', 'schedulely' ),
					count( $parsed_raw ),
					$post_count
				)
			);
			return $err;
		}

		$note = $strict_ok
			? __( 'Queue order was applied.', 'schedulely' )
			: sprintf(
				/* translators: 1: model count 2: expected count */
				__( 'Queue order applied after reconciliation (model returned %1$d IDs for %2$d posts).', 'schedulely' ),
				count( $parsed_raw ),
				$post_count
			);

		$this->log_attempt( 'success', $model, $post_count, null, $usage_tokens,
			'', '',
			schedulely_ai_log_sanitize_excerpt( $content, 1200 ), '',
			$note
		);

		return $ordered;
	}

	// -------------------------------------------------------------------------
	// Prompt builders
	// -------------------------------------------------------------------------

	/**
	 * Build "post_id TAB title TAB slug" lines for the prompt.
	 *
	 * Slug is included because it is derived from the primary keyword and
	 * reliably contains the target US state name (e.g. pit-bull-laws-in-texas).
	 *
	 * @since 1.6.0
	 * @since 1.7.0 Added slug column for better state detection.
	 * @param array<int> $post_ids
	 * @return array<string>
	 */
	private function build_prompt_lines( array $post_ids ): array {
		$lines = [];
		foreach ( $post_ids as $post_id ) {
			$title = get_post_field( 'post_title', $post_id, 'raw' );
			$title = is_string( $title ) ? wp_strip_all_tags( $title ) : '';
			$slug  = get_post_field( 'post_name', $post_id, 'raw' );
			$slug  = is_string( $slug ) ? $slug : '';
			$lines[] = (string) $post_id . "\t" . $title . "\t" . $slug;
		}
		return $lines;
	}

	/**
	 * Build the user-turn prompt string.
	 *
	 * @since 1.6.0
	 * @param array<string> $lines
	 * @param int           $count
	 * @return string
	 */
	private function build_user_prompt( array $lines, int $count ): string {
		return sprintf(
			"Reorder these %d posts. Respond with JSON only: {\"ordered_ids\":[...]}\n\n",
			$count
		) . implode( "\n", $lines );
	}

	/**
	 * Return the system instruction for the AI reorder task.
	 *
	 * Kept in English only — not passed through gettext — for model reliability.
	 *
	 * @since 1.6.0
	 * @since 1.7.0 Added $timezone_mode parameter.
	 *
	 * @param bool $timezone_mode When true, returns the US timezone-aware instruction.
	 * @return string
	 */
	private function get_system_instruction( bool $timezone_mode = false ): string {
		if ( $timezone_mode ) {
			return $this->get_timezone_system_instruction();
		}

		return 'You reorder WordPress posts for publication. Each line is: numeric_post_id TAB title TAB slug. '
			. 'Detect posts that belong to the same series or template. '
			. 'Your goal is to maximize variety in the sequence. '
			. 'Do not place similar or same-series posts close together. '
			. 'Maintain a minimum spacing of at least 3 to 5 other posts between similar titles whenever the mix allows. '
			. 'If perfect spacing is not possible, distribute similar posts as evenly as you can across the entire list. '
			. 'Prioritize diversity of topics over original order. '
			. 'Return a JSON object with a single key "ordered_ids" whose value is an array of integers. '
			. 'It MUST list every input post ID exactly once: same length as the input list, no duplicates, no made-up IDs, no omissions. '
			. 'Output only valid JSON, no markdown fences, no commentary.';
	}

	/**
	 * System instruction for US timezone-aware ordering.
	 *
	 * Returns both ordered_ids AND timezone_groups so the scheduler can assign
	 * each post to the correct time band within the publishing window.
	 *
	 * @since 1.7.0
	 * @return string
	 */
	private function get_timezone_system_instruction(): string {
		return 'You reorder WordPress posts for a US audience. Each line is: numeric_post_id TAB title TAB slug. '
			. 'Step 1 — Extract the US state from each post\'s title or slug. '
			. 'Step 2 — Assign each post to its primary US timezone group: '
			. '"eastern" (CT, ME, MA, NH, RI, VT, NY, NJ, PA, DE, MD, DC, VA, WV, NC, SC, GA, FL, OH, MI, IN, KY, TN), '
			. '"central" (WI, IL, MO, AR, LA, MS, AL, MN, IA, ND, SD, NE, KS, OK, TX), '
			. '"mountain" (MT, ID, WY, CO, NM, AZ, UT, NV), '
			. '"pacific" (WA, OR, CA, AK, HI). '
			. 'If a state spans multiple timezones use its eastern-most zone. '
			. 'Posts with no identifiable US state get group "general". '
			. 'Step 3 — Order posts: Eastern first, then Central, then Mountain, then Pacific last. '
			. 'Distribute "general" posts evenly as spacers between timezone groups. '
			. 'Within each timezone group, alternate topics so no two adjacent posts share the same subject series '
			. '(e.g. no back-to-back "Pit Bull Laws" or "Hunting Laws" posts). '
			. 'Maintain a minimum spacing of 3 to 5 posts between same-series titles across the full list. '
			. 'Return a JSON object with exactly two keys: '
			. '"ordered_ids" — array of all input post IDs in the final order (every ID exactly once, no omissions, no invented IDs); '
			. '"timezone_groups" — object mapping each post ID (as string key) to its group string. '
			. 'Valid group values: "eastern", "central", "mountain", "pacific", "general". '
			. 'Output only valid JSON, no markdown fences, no commentary.';
	}

	/**
	 * Build the full Chat Completions request body (legacy path only).
	 *
	 * @since 1.4.0
	 * @since 1.7.0 Added $timezone_mode parameter.
	 * @since 1.7.1 Raises max_tokens to 32768 in timezone mode — a 1,500-post
	 *              timezone response needs ~23,000 output tokens; the default
	 *              16,384 would truncate it for large pools.
	 * @param string        $model
	 * @param array<string> $lines
	 * @param int           $count
	 * @param bool          $timezone_mode
	 * @return array<string,mixed>
	 */
	private function build_request_body( string $model, array $lines, int $count, bool $timezone_mode = false ): array {
		// Timezone mode needs more output tokens: ordered_ids + timezone_groups
		// for 1,500 posts ≈ 23,000 tokens. Standard mode needs far less.
		$default_max_tokens = $timezone_mode ? 32768 : 16384;

		$body = [
			'model'           => $model,
			'messages'        => [
				[ 'role' => 'system', 'content' => $this->get_system_instruction( $timezone_mode ) ],
				[ 'role' => 'user',   'content' => $this->build_user_prompt( $lines, $count ) ],
			],
			'temperature'     => $timezone_mode ? 0.4 : 1.0,
			'top_p'           => 1.0,
			'max_tokens'      => (int) apply_filters( 'schedulely_ai_max_output_tokens', $default_max_tokens ),
			'response_format' => [ 'type' => 'json_object' ],
		];
		return $body;
	}

	// -------------------------------------------------------------------------
	// Parsing & validation
	// -------------------------------------------------------------------------

	/**
	 * Parse ordered_ids from model content (JSON, possibly wrapped in code fences).
	 *
	 * @since 1.4.0
	 * @param string $content Raw assistant message.
	 * @return array<int>|WP_Error
	 */
	private function parse_ordered_ids_from_content( string $content ) {
		$content = trim( $content );
		if ( preg_match( '/^```(?:json)?\s*(\{.*\})\s*```$/s', $content, $m ) ) {
			$content = $m[1];
		}
		$obj = json_decode( $content, true );
		if ( ! is_array( $obj ) || ! isset( $obj['ordered_ids'] ) || ! is_array( $obj['ordered_ids'] ) ) {
			return new WP_Error(
				'schedulely_ai_shape',
				__( 'AI JSON must contain an "ordered_ids" array.', 'schedulely' )
			);
		}
		return array_map( 'absint', $obj['ordered_ids'] );
	}

	/**
	 * Check that $ordered is the same multiset as $original.
	 *
	 * @since 1.4.0
	 * @param array<int> $original
	 * @param array<int> $ordered
	 * @return bool
	 */
	private function is_valid_permutation( array $original, array $ordered ): bool {
		if ( count( $original ) !== count( $ordered ) ) {
			return false;
		}
		$a = array_map( 'intval', $original );
		$b = array_map( 'intval', $ordered );
		sort( $a );
		sort( $b );
		return $a === $b;
	}

	/**
	 * Reconcile model ordered_ids with the actual input list.
	 *
	 * Keeps valid IDs in model order, drops unknowns/duplicates, appends
	 * any still-missing IDs in original input order.
	 *
	 * @since 1.5.9
	 * @param array<int> $input_ids
	 * @param array<int> $model_order
	 * @return array<int>
	 */
	private function reconcile_ordered_ids_with_input( array $input_ids, array $model_order ): array {
		$input_ids = array_map( 'intval', array_values( $input_ids ) );
		$need      = array_count_values( $input_ids );
		$result    = [];

		foreach ( $model_order as $raw_id ) {
			$id = (int) $raw_id;
			if ( isset( $need[ $id ] ) && $need[ $id ] > 0 ) {
				$result[] = $id;
				$need[ $id ]--;
			}
		}
		foreach ( $input_ids as $id ) {
			while ( isset( $need[ $id ] ) && $need[ $id ] > 0 ) {
				$result[] = $id;
				$need[ $id ]--;
			}
		}
		return $result;
	}

	// -------------------------------------------------------------------------
	// Legacy helpers
	// -------------------------------------------------------------------------

	/**
	 * Extract assistant text from a decoded Chat Completions response.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $decoded
	 * @return string
	 */
	private function extract_assistant_text( array $decoded ): string {
		if ( empty( $decoded['choices'][0] ) || ! is_array( $decoded['choices'][0] ) ) {
			return '';
		}
		$choice = $decoded['choices'][0];
		$text   = '';

		if ( isset( $choice['text'] ) && is_string( $choice['text'] ) && '' !== trim( $choice['text'] ) ) {
			$text = trim( $choice['text'] );
		}

		$msg = isset( $choice['message'] ) && is_array( $choice['message'] ) ? $choice['message'] : null;
		if ( null !== $msg ) {
			$content = $msg['content'] ?? null;
			if ( is_string( $content ) && '' !== trim( $content ) ) {
				$text = trim( $content );
			} elseif ( is_array( $content ) ) {
				$parts = [];
				foreach ( $content as $part ) {
					if ( is_array( $part ) && isset( $part['text'] ) ) {
						$parts[] = (string) $part['text'];
					}
				}
				$text = trim( implode( '', $parts ) );
			}
			if ( '' === $text
				&& isset( $msg['reasoning_content'] )
				&& is_string( $msg['reasoning_content'] )
				&& '' !== trim( $msg['reasoning_content'] ) ) {
				$text = trim( $msg['reasoning_content'] );
			}
		}
		return $text;
	}

	/**
	 * Sanitized API base URL (legacy path).
	 *
	 * @since 1.4.0
	 * @return string
	 */
	private function get_api_base_url(): string {
		$builtin  = 'https://api.deepseek.com/v1';
		$fallback = (string) apply_filters( 'schedulely_ai_default_base_url', $builtin );
		$stored   = get_option( 'schedulely_ai_base_url', $fallback );
		$url      = is_string( $stored ) ? trim( $stored ) : '';
		if ( '' === $url ) {
			return untrailingslashit( $fallback );
		}
		$url = esc_url_raw( $url );
		if ( '' === $url || str_starts_with( $url, 'https://' ) === false ) {
			return untrailingslashit( $fallback );
		}
		return untrailingslashit( $url );
	}

	/**
	 * Model id (legacy path).
	 *
	 * @since 1.4.0
	 * @return string
	 */
	private function get_model(): string {
		$builtin  = 'deepseek-v4-flash';
		$fallback = (string) apply_filters( 'schedulely_ai_default_model', $builtin );
		$model    = get_option( 'schedulely_ai_model', $fallback );
		if ( ! is_string( $model ) || '' === trim( $model ) ) {
			return $fallback;
		}
		return sanitize_text_field( substr( trim( $model ), 0, 120 ) );
	}

	/**
	 * HTTP headers for legacy Chat Completions requests.
	 *
	 * @since 1.4.0
	 * @param string $api_key Bearer token (never logged).
	 * @return array<string,string>
	 */
	private function build_ai_http_headers( string $api_key ): array {
		$ver = defined( 'SCHEDULELY_VERSION' ) ? SCHEDULELY_VERSION : '1.0';
		return [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'User-Agent'    => (string) apply_filters(
				'schedulely_ai_http_user_agent',
				'Schedulely/' . $ver . '; ' . home_url( '/' )
			),
		];
	}

	/**
	 * Shorten a raw response body for error messages.
	 *
	 * @since 1.4.0
	 * @param string $raw
	 * @return string
	 */
	private function excerpt_error_body( string $raw ): string {
		$raw = wp_strip_all_tags( $raw );
		return strlen( $raw ) > 200 ? substr( $raw, 0, 200 ) . '…' : $raw;
	}

	// -------------------------------------------------------------------------
	// Logging
	// -------------------------------------------------------------------------

	/**
	 * Store a reorder attempt in the rolling log.
	 *
	 * @since 1.5.4
	 * @since 1.6.0 Extracted into a single helper used by both paths.
	 *
	 * @param string      $outcome         'success' or 'error'.
	 * @param string      $model           Model id or 'wp_ai'.
	 * @param int         $post_count      Number of posts in the queue.
	 * @param int|null    $http_code       HTTP status code (legacy path only).
	 * @param int|null    $usage_tokens    Token count from provider (legacy path only).
	 * @param string      $error_code      WP_Error code or empty string.
	 * @param string      $error_message   WP_Error message or empty string.
	 * @param string      $assistant_excerpt Excerpt of the assistant text.
	 * @param string      $raw_excerpt     Excerpt of the raw response body.
	 * @param string      $note            Human-readable note.
	 */
	private function log_attempt(
		string $outcome,
		string $model,
		int $post_count,
		?int $http_code,
		?int $usage_tokens,
		string $error_code,
		string $error_message,
		string $assistant_excerpt,
		string $raw_excerpt,
		string $note
	): void {
		if ( ! function_exists( 'schedulely_append_ai_reorder_log' ) ) {
			return;
		}
		schedulely_append_ai_reorder_log( [
			'outcome'           => $outcome,
			'model'             => $model,
			'post_count'        => $post_count,
			'http_code'         => $http_code,
			'usage_total_tokens' => $usage_tokens,
			'error_code'        => $error_code,
			'error_message'     => $error_message,
			'assistant_excerpt' => $assistant_excerpt,
			'raw_excerpt'       => $raw_excerpt,
			'note'              => $note,
		] );
	}
}
