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

	/**
	 * US state (full name) -> timezone group.
	 *
	 * Mirrors the groupings the AI prompt used. States spanning two zones are
	 * pre-assigned to one zone (their eastern-most), so no runtime tie-breaking
	 * is needed. District of Columbia is handled separately in
	 * timezone_group_from_text() because "washington" alone is the Pacific state.
	 *
	 * @since 1.7.7
	 * @var array<string,array<int,string>>
	 */
	private const STATE_TIMEZONE_MAP = [
		'eastern'  => [
			'connecticut', 'maine', 'massachusetts', 'new hampshire', 'rhode island',
			'vermont', 'new york', 'new jersey', 'pennsylvania', 'delaware', 'maryland',
			'west virginia', 'virginia', 'north carolina', 'south carolina', 'georgia',
			'florida', 'ohio', 'michigan', 'indiana', 'kentucky', 'tennessee',
		],
		'central'  => [
			'wisconsin', 'illinois', 'missouri', 'arkansas', 'louisiana', 'mississippi',
			'alabama', 'minnesota', 'iowa', 'north dakota', 'south dakota', 'nebraska',
			'kansas', 'oklahoma', 'texas',
		],
		'mountain' => [
			'montana', 'idaho', 'wyoming', 'colorado', 'new mexico', 'arizona', 'utah', 'nevada',
		],
		'pacific'  => [
			'washington', 'oregon', 'california', 'alaska', 'hawaii',
		],
	];

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

		// When the deterministic PHP method is selected, never touch the network.
		// Logged (model 'php') so the AI Reorder Log shows PHP ran and why.
		if ( 'php' === $this->get_ordering_method() ) {
			$ordered = $this->order_via_php( $post_ids );
			$this->log_attempt( 'success', 'php', count( $post_ids ), null, null,
				'', '',
				$this->php_order_excerpt( $ordered ), '',
				__( 'PHP ordering applied — ordering method is set to PHP; the AI was not called.', 'schedulely' )
					. ' ' . $this->php_grouping_summary( $post_ids )
			);
			return $ordered;
		}

		$ordered = $this->wp_ai_available()
			? $this->reorder_via_wp_ai( $post_ids )
			: $this->reorder_via_legacy_http( $post_ids );

		// Auto-fallback: a failed AI request must not lose the run. Order the
		// queue deterministically in PHP instead of leaving it un-ordered. The AI
		// failure already wrote its own ERROR row; this adds a follow-up row so
		// the log tells the full story: "AI failed → PHP fallback applied".
		if ( is_wp_error( $ordered ) ) {
			$code = $ordered->get_error_code();
			schedulely_log_error(
				'AI reorder failed; falling back to deterministic PHP ordering: ' . $ordered->get_error_message()
			);
			$php_ordered = $this->order_via_php( $post_ids );
			$this->log_attempt( 'success', 'php', count( $post_ids ), null, null,
				(string) $code, '',
				$this->php_order_excerpt( $php_ordered ), '',
				sprintf(
					/* translators: %s: the AI error code that triggered the fallback */
					__( 'PHP ordering applied as automatic fallback after the AI request failed (%s).', 'schedulely' ),
					(string) $code
				) . ' ' . $this->php_grouping_summary( $post_ids )
			);
			return $php_ordered;
		}

		return $ordered;
	}

	/**
	 * Deterministically order post IDs in PHP — no network, no timeout.
	 *
	 * Mirrors what the AI was asked to do for "series spacing": stop near-
	 * identical posts (same topic, different target state) from clustering. We
	 * group IDs by slug-stem — the slug with the US state name stripped out — so
	 * "best-pizza-in-texas" and "best-pizza-in-ohio" share the stem
	 * "best pizza in", then round-robin across groups so same-stem posts are
	 * spread evenly through the queue instead of sitting back-to-back.
	 *
	 * Used both as the explicit 'php' ordering method and as the automatic
	 * fallback when an AI request fails.
	 *
	 * @since 1.8.0
	 * @param array<int> $post_ids
	 * @return array<int>
	 */
	private function order_via_php( array $post_ids ): array {
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $post_ids, false, false );
		}

		$groups = $this->group_by_slug_stem( $post_ids );

		return ( 'round_robin' === $this->get_php_spread_method() )
			? $this->spread_round_robin( $groups )
			: $this->spread_even( $groups );
	}

	/**
	 * Even (position-based) spread — the default and recommended strategy.
	 *
	 * Each post is given a normalized target position (i + 0.5) / n in [0,1)
	 * within its own series, so a series of n posts claims n evenly-spaced points
	 * across the whole queue. Sorting every post by that target merges the series
	 * into one sequence where each series stays evenly spaced from start to
	 * finish — unlike round-robin, which lets the largest series bunch up at the
	 * tail once the smaller series run out. Because day assignment follows queue
	 * order, this also spreads each series evenly across the publish calendar.
	 *
	 * PHP 8.0+ sort is stable, so posts that tie on target keep their input order
	 * (largest series first, from group_by_slug_stem()).
	 *
	 * @since 1.8.4
	 * @param array<string,array<int>> $groups Slug-stem => post IDs (largest first).
	 * @return array<int>
	 */
	private function spread_even( array $groups ): array {
		$tagged = [];
		foreach ( $groups as $ids ) {
			$ids = array_values( $ids );
			$n   = count( $ids );
			foreach ( $ids as $i => $id ) {
				$tagged[] = [ 'pos' => ( $i + 0.5 ) / $n, 'id' => (int) $id ];
			}
		}
		usort( $tagged, fn( $a, $b ) => $a['pos'] <=> $b['pos'] );
		return array_map( static fn( $t ) => $t['id'], $tagged );
	}

	/**
	 * Round-robin spread — legacy strategy, kept as a switchable option.
	 *
	 * Emits one post from each series per pass. Evenly spaced while every series
	 * still has posts, but the largest series clusters near the tail once the
	 * smaller series are exhausted. Retained for anyone who prefers it.
	 *
	 * @since 1.8.0
	 * @since 1.8.4 Extracted from order_via_php() behind the spread option.
	 * @param array<string,array<int>> $groups Slug-stem => post IDs (largest first).
	 * @return array<int>
	 */
	private function spread_round_robin( array $groups ): array {
		$ordered = [];
		do {
			$progress = false;
			foreach ( $groups as &$ids ) {
				if ( ! empty( $ids ) ) {
					$ordered[] = array_shift( $ids );
					$progress  = true;
				}
			}
			unset( $ids );
		} while ( $progress );

		return $ordered;
	}

	/**
	 * Read the configured PHP spacing strategy ('even' or 'round_robin').
	 *
	 * @since 1.8.4
	 * @return string
	 */
	private function get_php_spread_method(): string {
		$spread = get_option( 'schedulely_php_spread', Schedulely_Defaults::PHP_SPREAD );
		$spread = apply_filters( 'schedulely_php_spread', $spread );
		return in_array( $spread, [ 'even', 'round_robin' ], true ) ? $spread : Schedulely_Defaults::PHP_SPREAD;
	}

	/**
	 * Group post IDs by slug-stem, largest group first.
	 *
	 * Shared by order_via_php() (which interleaves the groups) and
	 * php_grouping_summary() (which describes them for the log). Largest first
	 * means the most-repeated topics get the widest spread when interleaved.
	 *
	 * @since 1.8.2
	 * @param array<int> $post_ids
	 * @return array<string,array<int>> Map of slug-stem => list of post IDs.
	 */
	private function group_by_slug_stem( array $post_ids ): array {
		$groups = [];
		foreach ( $post_ids as $id ) {
			$groups[ $this->slug_stem( (int) $id ) ][] = (int) $id;
		}
		uasort( $groups, fn( $a, $b ) => count( $b ) <=> count( $a ) );
		return $groups;
	}

	/**
	 * Human-readable summary of how PHP ordering grouped the queue.
	 *
	 * Lets the AI Reorder Log show *why* the order looks the way it does — how
	 * many series were detected and how big the largest one is — so the spacing
	 * decision is visible without inspecting every scheduled post.
	 *
	 * @since 1.8.2
	 * @param array<int> $post_ids
	 * @return string
	 */
	private function php_grouping_summary( array $post_ids ): string {
		$groups = $this->group_by_slug_stem( $post_ids );
		$series = count( $groups );

		$largest_stem = '';
		$largest_n    = 0;
		foreach ( $groups as $stem => $ids ) {
			if ( count( $ids ) > $largest_n ) {
				$largest_n    = count( $ids );
				$largest_stem = (string) $stem;
			}
		}

		$strategy = ( 'round_robin' === $this->get_php_spread_method() )
			? __( 'round-robin', 'schedulely' )
			: __( 'even distribution', 'schedulely' );

		return sprintf(
			/* translators: 1: number of series 2: total posts 3: largest series slug-stem 4: size of largest series 5: spacing strategy */
			__( '%1$d series detected across %2$d posts; largest "%3$s" = %4$d posts, spread by %5$s.', 'schedulely' ),
			$series,
			count( $post_ids ),
			$largest_stem,
			$largest_n,
			$strategy
		);
	}

	/**
	 * Build a log excerpt of the PHP-produced order, mirroring the AI rows.
	 *
	 * Same {"ordered_ids":[...]} shape the AI path logs, truncated by the shared
	 * sanitizer, so the resulting sequence is visible in the AI Reorder Log
	 * instead of only a "PHP ran" confirmation.
	 *
	 * @since 1.8.2
	 * @param array<int> $ordered
	 * @return string
	 */
	private function php_order_excerpt( array $ordered ): string {
		$json = '{"ordered_ids":[' . implode( ',', array_map( 'intval', $ordered ) ) . ']}';
		if ( function_exists( 'schedulely_ai_log_sanitize_excerpt' ) ) {
			return schedulely_ai_log_sanitize_excerpt( $json, 1200 );
		}
		return strlen( $json ) > 1200 ? substr( $json, 0, 1200 ) . '…' : $json;
	}

	/**
	 * Resolve a post's slug-stem: its slug with any US state name removed.
	 *
	 * Falls back to the title (slugified) when a post has no slug yet, so
	 * grouping still works for drafts. Multi-word state names are stripped
	 * before single-word ones (longest-first) so "west virginia" doesn't leave
	 * a stray "west" behind.
	 *
	 * @since 1.8.0
	 * @param int $post_id
	 * @return string
	 */
	private function slug_stem( int $post_id ): string {
		$slug = get_post_field( 'post_name', $post_id, 'raw' );
		$slug = is_string( $slug ) ? strtolower( $slug ) : '';
		if ( '' === $slug ) {
			$title = get_post_field( 'post_title', $post_id, 'raw' );
			$slug  = is_string( $title ) ? sanitize_title( $title ) : '';
		}

		$hay = ' ' . trim( (string) preg_replace( '/[^a-z]+/', ' ', $slug ) ) . ' ';
		foreach ( $this->states_by_length_desc() as $state ) {
			$hay = str_replace( ' ' . $state . ' ', ' ', $hay );
		}
		$stem = trim( (string) preg_replace( '/\s+/', ' ', $hay ) );

		return '' === $stem ? $slug : $stem;
	}

	/**
	 * Flat list of every US state name, sorted longest-first.
	 *
	 * Longest-first matters when stripping states from a slug: removing
	 * "virginia" before "west virginia" would orphan the "west". Cached for the
	 * request since the source map is a constant.
	 *
	 * @since 1.8.0
	 * @return array<int,string>
	 */
	private function states_by_length_desc(): array {
		static $list = null;
		if ( null !== $list ) {
			return $list;
		}
		$list = [];
		foreach ( self::STATE_TIMEZONE_MAP as $states ) {
			foreach ( $states as $state ) {
				$list[] = $state;
			}
		}
		usort( $list, fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
		return $list;
	}

	/**
	 * Read the configured queue-ordering method ('ai' or 'php').
	 *
	 * @since 1.8.0
	 * @return string 'ai' or 'php'.
	 */
	private function get_ordering_method(): string {
		$method = get_option( 'schedulely_ordering_method', Schedulely_Defaults::ORDERING_METHOD );
		$method = apply_filters( 'schedulely_ordering_method', $method );
		return in_array( $method, [ 'ai', 'php' ], true ) ? $method : Schedulely_Defaults::ORDERING_METHOD;
	}

	/**
	 * Reorder post IDs with timezone group assignments.
	 *
	 * Used when US timezone-aware ordering is enabled. Returns an array of
	 * [ 'id' => int, 'timezone_group' => string ] maps, where timezone_group
	 * is one of: 'eastern', 'central', 'mountain', 'pacific', 'general'.
	 *
	 * The two jobs are split by reliability:
	 *  - Ordering (series spacing) is fuzzy work, so the AI does it via the
	 *    shared reorder_post_ids() path.
	 *  - Timezone classification is a fixed state->zone lookup, so PHP does it
	 *    deterministically in classify_timezone_group(). This never times out
	 *    and is always correct, unlike asking the model to emit a per-post map.
	 *
	 * If AI ordering fails the queue is ordered deterministically in PHP instead,
	 * and timezone groups are still assigned. Never returns WP_Error — the
	 * scheduler relies on this.
	 *
	 * @since 1.7.0
	 * @since 1.7.7 Timezone groups are now computed in PHP; the AI only orders.
	 *
	 * @param array<int> $post_ids
	 * @return array<array{id:int,timezone_group:string}>
	 */
	public function reorder_post_ids_with_timezone( array $post_ids ): array {
		$post_ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );

		if ( empty( $post_ids ) ) {
			return [];
		}

		// reorder_post_ids() already falls back to deterministic PHP ordering on
		// AI failure; the guard below is belt-and-suspenders so a WP_Error can
		// never reach the timezone mapping.
		$ordered = $this->reorder_post_ids( $post_ids );
		if ( is_wp_error( $ordered ) ) {
			schedulely_log_error(
				'AI queue ordering failed; using deterministic PHP ordering, timezone groups still applied: ' . $ordered->get_error_message()
			);
			$ordered = $this->order_via_php( $post_ids );
		}

		return array_map(
			fn( $id ) => [ 'id' => (int) $id, 'timezone_group' => $this->classify_timezone_group( (int) $id ) ],
			$ordered
		);
	}

	/**
	 * Map a single post to its US timezone group from its title and slug.
	 *
	 * Deterministic — no AI involved. Reads the same fields the AI prompt used
	 * (title + slug) so state detection is just as good, without the latency,
	 * cost, or truncation risk of asking the model for a per-post map.
	 *
	 * @since 1.7.7
	 * @param int $post_id
	 * @return string One of 'eastern', 'central', 'mountain', 'pacific', 'general'.
	 */
	private function classify_timezone_group( int $post_id ): string {
		$title = get_post_field( 'post_title', $post_id, 'raw' );
		$title = is_string( $title ) ? wp_strip_all_tags( $title ) : '';
		$slug  = get_post_field( 'post_name', $post_id, 'raw' );
		$slug  = is_string( $slug ) ? $slug : '';
		return $this->timezone_group_from_text( $title . ' ' . $slug );
	}

	/**
	 * Resolve a US timezone group from free text (title + slug).
	 *
	 * Matches whole state names only (word boundaries), so "arkansas" never
	 * triggers a "kansas" match and city names like "indianapolis" do not
	 * falsely resolve to "indiana". States spanning two zones are pre-assigned
	 * to a single zone in the map below, mirroring the old AI prompt's rules
	 * (e.g. Texas -> central, Florida -> eastern). Washington, D.C. is Eastern
	 * and is checked before the State of Washington (Pacific).
	 *
	 * @since 1.7.7
	 * @param string $text
	 * @return string One of 'eastern', 'central', 'mountain', 'pacific', 'general'.
	 */
	private function timezone_group_from_text( string $text ): string {
		// Lowercase, collapse every non-letter run to a single space, then pad
		// with spaces so " state " substring tests behave as whole-word matches.
		$hay = ' ' . trim( (string) preg_replace( '/[^a-z]+/', ' ', strtolower( $text ) ) ) . ' ';

		// District of Columbia is Eastern; check before the State of Washington.
		if ( str_contains( $hay, ' district of columbia ' )
			|| str_contains( $hay, ' washington dc ' )
			|| str_contains( $hay, ' washington d c ' ) ) {
			return 'eastern';
		}

		foreach ( self::STATE_TIMEZONE_MAP as $zone => $states ) {
			foreach ( $states as $state ) {
				if ( str_contains( $hay, ' ' . $state . ' ' ) ) {
					return $zone;
				}
			}
		}

		return 'general';
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
			$available = (bool) wp_ai_client_prompt( 'test' )->is_supported_for_text_generation();
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

		// Raise the WP AI client timeout for large pools.
		$timeout_filter = $this->get_wp_ai_timeout_filter( count( $post_ids ) );
		add_filter( 'wp_ai_client_default_request_timeout', $timeout_filter, 999 );

		try {
			$builder = wp_ai_client_prompt( $prompt )
				->using_system_instruction( $this->get_system_instruction() )
				->using_temperature( $this->get_reorder_temperature() )
				// Deliberate 64K output ceiling. Far above any real answer
				// (~15K even at 1,500 posts) so it never truncates valid output,
				// but well below the model's 384K maximum so a degenerate
				// repetition loop is cut off instead of running toward 384K.
				->using_max_tokens( $this->get_reorder_max_tokens() )
				// Request JSON output mode. For OpenAI-compatible providers
				// (DeepSeek) this sends response_format {"type":"json_object"},
				// constraining the decoder to emit parseable, properly-closed
				// JSON. This is the guardrail the pre-connector legacy path had
				// (and lost on the WP AI path): it prevents the truncated,
				// unparseable output that produced schedulely_ai_shape errors,
				// and makes "close the array and stop" a reachable state. The
				// prompt contains the word "JSON", satisfying DeepSeek's
				// json_object precondition. Reconciliation still backstops any
				// duplicate/short multiset the model returns.
				->as_json_response();

			/**
			 * Fires just before Schedulely sends an AI reorder request.
			 * Lets the pro plugin or third parties register model preferences.
			 *
			 * @since 1.6.0
			 */
			do_action( 'schedulely_pre_ai_reorder' );

			if ( ! $builder->is_supported_for_text_generation() ) {
				remove_filter( 'wp_ai_client_default_request_timeout', $timeout_filter, 999 );
				$err = new WP_Error(
					'schedulely_ai_unsupported',
					sprintf(
						/* translators: %s: URL to the WP connectors screen */
						__( 'No AI provider supports text generation. Configure one at %s.', 'schedulely' ),
						esc_url( admin_url( 'options-connectors.php' ) )
					)
				);
				$this->log_attempt( 'error', 'wp_ai', count( $post_ids ), null, null,
					$err->get_error_code(),
					schedulely_ai_log_sanitize_excerpt( $err->get_error_message(), 500 ),
					'', '',
					__( 'No configured AI provider supports text generation; queue kept its input order.', 'schedulely' )
				);
				return $err;
			}

			$result = $builder->generate_text();

			// generate_text() can return a WP_Error on failure rather than throwing.
			// Casting that to string fatals ("Object of class WP_Error could not be
			// converted to string"), which previously masked the real failure reason.
			if ( is_wp_error( $result ) ) {
				remove_filter( 'wp_ai_client_default_request_timeout', $timeout_filter, 999 );
				$this->log_attempt( 'error', 'wp_ai', count( $post_ids ), null, null,
					$result->get_error_code(),
					schedulely_ai_log_sanitize_excerpt( $result->get_error_message(), 500 ),
					'', '',
					__( 'WP AI client returned an error while generating text; queue kept its input order.', 'schedulely' )
				);
				return $result;
			}

			$content = is_string( $result ) ? $result : '';

		} catch ( \Throwable $e ) {
			remove_filter( 'wp_ai_client_default_request_timeout', $timeout_filter, 999 );
			schedulely_log_error( 'WP AI reorder exception: ' . $e->getMessage() );
			$this->log_attempt( 'error', 'wp_ai', count( $post_ids ), null, null,
				'schedulely_ai_exception',
				schedulely_ai_log_sanitize_excerpt( $e->getMessage(), 500 ),
				'', '',
				__( 'WP AI client threw while generating text (often a request timeout on large pools); queue kept its input order.', 'schedulely' )
			);
			return new WP_Error( 'schedulely_ai_exception', $e->getMessage() );
		}

		remove_filter( 'wp_ai_client_default_request_timeout', $timeout_filter, 999 );
		return $this->process_ai_response( $post_ids, $content, 'wp_ai', null );
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
			$err = new WP_Error(
				'schedulely_ai_no_key',
				__( 'No API key is configured for AI queue ordering.', 'schedulely' )
			);
			$this->log_attempt( 'error', $this->get_model(), count( $post_ids ), null, null,
				'schedulely_ai_no_key',
				schedulely_ai_log_sanitize_excerpt( $err->get_error_message(), 500 ),
				'', '',
				__( 'No API key configured; queue kept its input order.', 'schedulely' )
			);
			return $err;
		}

		$base  = $this->get_api_base_url();
		$model = $this->get_model();
		$url   = rtrim( $base, '/' ) . '/chat/completions';

		$lines = $this->build_prompt_lines( $post_ids );
		$body  = $this->build_request_body( $model, $lines, count( $post_ids ) );
		$body  = apply_filters( 'schedulely_ai_chat_completions_body', $body, $post_ids );

		$post_count = count( $post_ids );
		// Effectively uncapped (generous finite default). Do not use 0 — WordPress's
		// HTTP client reads it as a ~1s timeout, not "infinite", and fails instantly.
		$timeout = (int) apply_filters( 'schedulely_ai_request_timeout', 3600, $post_ids );
		$timeout = max( 1, $timeout );

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
	 * Build "post_id TAB slug" lines for the prompt.
	 *
	 * Only the slug is sent. Series posts share an identical slug stem with just
	 * the state differing (e.g. rabies-vaccine-requirements-for-cats-in-tennessee
	 * vs ...-north-carolina), which is the cleanest "same template" signal, while
	 * being shorter and punctuation-free compared to the title. The title is
	 * deliberately omitted to roughly halve prompt size — it carries the same
	 * pattern with extra tokens and no extra ordering value.
	 *
	 * @since 1.6.0
	 * @since 1.7.0 Added slug column for better state detection.
	 * @since 1.7.10 Slug-only; dropped the title column to shrink the prompt.
	 * @param array<int> $post_ids
	 * @return array<string>
	 */
	private function build_prompt_lines( array $post_ids ): array {
		$lines = [];
		foreach ( $post_ids as $post_id ) {
			$slug = get_post_field( 'post_name', $post_id, 'raw' );
			$slug = is_string( $slug ) ? $slug : '';
			if ( '' === $slug ) {
				$title = get_post_field( 'post_title', $post_id, 'raw' );
				$slug  = is_string( $title ) ? sanitize_title( $title ) : '';
			}
			$lines[] = (string) $post_id . "\t" . $slug;
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
		$header = sprintf(
			"Reorder these %1\$d posts. Output a JSON object {\"ordered_ids\":[...]} whose array contains all %1\$d post IDs, each appearing exactly once, in your new order. Do not repeat any ID. Do not invent IDs. The array length must be exactly %1\$d. Stop immediately after the %1\$d-th ID.\n\n",
			$count
		);
		return $header . implode( "\n", $lines );
	}

	/**
	 * Return the system instruction for the AI reorder task.
	 *
	 * Kept in English only — not passed through gettext — for model reliability.
	 * Covers ordering/series-spacing only; timezone classification is handled
	 * deterministically in PHP (see classify_timezone_group()).
	 *
	 * @since 1.6.0
	 * @return string
	 */
	private function get_system_instruction(): string {
		return 'You reorder WordPress posts for publication. Each line is: numeric_post_id TAB slug. '
			. 'The slug is the URL keyword; posts in the same series share an almost identical slug with only a word or two differing. '
			. 'Detect posts that belong to the same series or template. '
			. 'Your goal is to maximize variety in the sequence. '
			. 'Do not place similar or same-series posts close together. '
			. 'Maintain a minimum spacing of at least 3 to 5 other posts between similar slugs whenever the mix allows. '
			. 'If perfect spacing is not possible, distribute similar posts as evenly as you can across the entire list. '
			. 'Prioritize diversity of topics over original order. '
			. 'Return a JSON object with a single key "ordered_ids" whose value is an array of integers. '
			. 'It MUST list every input post ID exactly once: same length as the input list, no duplicates, no made-up IDs, no omissions. '
			. 'Never repeat an ID you have already emitted. As soon as every ID has appeared once, close the array and stop — do not keep generating. '
			. 'Example: for the 4 input lines "12 cats-in-texas", "7 dogs-in-ohio", "30 cats-in-ohio", "4 dogs-in-texas", a complete valid response is exactly {"ordered_ids":[12,7,30,4]} — every input ID present once, similar slugs spaced apart, the array closed, and nothing after it. '
			. 'Output only valid JSON, no markdown fences, no commentary.';
	}

	/**
	 * Build the full Chat Completions request body (legacy path only).
	 *
	 * @since 1.4.0
	 * @param string        $model
	 * @param array<string> $lines
	 * @param int           $count
	 * @return array<string,mixed>
	 */
	private function build_request_body( string $model, array $lines, int $count ): array {
		$body = [
			'model'           => $model,
			'messages'        => [
				[ 'role' => 'system', 'content' => $this->get_system_instruction() ],
				[ 'role' => 'user',   'content' => $this->build_user_prompt( $lines, $count ) ],
			],
			'temperature'     => $this->get_reorder_temperature(),
			'top_p'           => 1.0,
			'max_tokens'      => $this->get_reorder_max_tokens(),
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
	 * Return a closure that overrides the WP AI client timeout.
	 *
	 * Deliberately NOT scaled by post count. Earlier versions multiplied a made-up
	 * per-post rate (e.g. 60 + post_count×0.45) which guessed how fast the model
	 * is — a number we have no data for. At 300 posts that produced a 195s ceiling
	 * that hung up the call before DeepSeek replied (the model wasn't slow, our
	 * guess was wrong). A timeout should guard against a *hung* request, not cap a
	 * *slow* one, so this is a single generous flat budget. The reorder log then
	 * records the real duration, which is the only legitimate basis for any limit.
	 *
	 * @since 1.7.6
	 * @since 1.7.12 Flat budget; removed the per-post guess. Filterable.
	 * @since 1.7.14 Generous finite default (3600s). We do NOT use 0: although 0 means
	 *               "wait indefinitely" in real Guzzle, the WP AI client routes through
	 *               WordPress's own HTTP client (ClientWithOptionsInterface), where 0
	 *               collapses to a ~1-second timeout and fails instantly (cURL error 28,
	 *               "timed out after 1003 ms"). A large finite value is effectively
	 *               uncapped — it never cuts off a working request — without that pitfall.
	 *               If a run still dies at a round number, the ceiling is PHP
	 *               max_execution_time or the web/cron server, not this value.
	 * @param int $post_count Number of posts in the queue (passed to the filter).
	 * @return \Closure
	 */
	private function get_wp_ai_timeout_filter( int $post_count ): \Closure {
		// Effectively uncapped. Do not pass 0 here (the WP HTTP client treats it as a
		// ~1s timeout, not "infinite"). Only guard against a negative override.
		$timeout = (int) apply_filters( 'schedulely_ai_reorder_timeout', 3600, $post_count );
		$timeout = max( 1, $timeout );
		return function () use ( $timeout ) {
			return (float) $timeout;
		};
	}

	/**
	 * Sampling temperature for the reorder request.
	 *
	 * A low temperature (default 0.3) is deliberate. The reorder task is a
	 * permutation, not a creative one — high temperature (we previously used 1.0)
	 * makes the model wander and is a known trigger for degenerate repetition
	 * loops, where it re-emits the same handful of IDs hundreds of times instead
	 * of finishing. Keeping it low produces a clean, finite answer.
	 *
	 * @since 1.7.14
	 * @return float Clamped to the valid 0.0–2.0 range.
	 */
	private function get_reorder_temperature(): float {
		$temp = (float) apply_filters( 'schedulely_ai_reorder_temperature', 0.3 );
		return max( 0.0, min( 2.0, $temp ) );
	}

	/**
	 * Maximum output tokens for the reorder request.
	 *
	 * Deliberately set to 64K (65,536). This is NOT the "low cap that truncates
	 * a valid answer" we avoid — a legitimate reorder of even a 1,500-post pool
	 * is only ~12–15K tokens, so 64K is roughly 4× the largest real response and
	 * cannot cut one off. It is a runaway-loop fuse: deepseek-v4-flash's true
	 * maximum output is 384K, so leaving this unset would let a degenerate
	 * repetition loop run toward 384K (many minutes, large cost) before failing.
	 * DeepSeek's JSON-mode guide also explicitly recommends setting max_tokens to
	 * prevent the JSON being truncated mid-string. Filterable for operators who
	 * want a different ceiling; floored at 1 and capped at the model's 384K
	 * documented maximum so we never send an invalid request.
	 *
	 * @since 1.8.0
	 * @return int
	 */
	private function get_reorder_max_tokens(): int {
		$max = (int) apply_filters( 'schedulely_ai_reorder_max_tokens', 65536 );
		return max( 1, min( 384000, $max ) );
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
