<?php
/**
 * Filename: class-abilities.php
 * Description: WordPress 7.0 Abilities registration for Schedulely.
 *              Exposes Schedulely's capabilities to the WP command palette,
 *              REST API, AI agents, and MCP clients.
 *
 * @package Schedulely
 * @since   1.6.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schedulely_Abilities
 *
 * Registers the Schedulely ability category and all individual abilities.
 * All abilities use the 'schedulely/' namespace.
 *
 * Only registered when the WordPress Abilities API is available
 * (WordPress 7.0+ with the AI plugin active).
 *
 * @since 1.6.0
 */
class Schedulely_Abilities {

	/**
	 * Register hooks for the Abilities API.
	 *
	 * Called from schedulely_init() only when wp_register_ability() exists.
	 *
	 * @since 1.6.0
	 */
	public function register_hooks(): void {
		// Register category on the appropriate hook.
		add_action( 'wp_register_ability_categories', [ $this, 'register_category' ] );
		add_action( 'init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register the 'schedulely' ability category.
	 *
	 * @since 1.6.0
	 */
	public function register_category(): void {
		wp_register_ability_category( 'schedulely', [
			'label'       => __( 'Schedulely', 'schedulely' ),
			'description' => __( 'Post scheduling actions provided by the Schedulely plugin.', 'schedulely' ),
		] );
	}

	/**
	 * Register all Schedulely abilities.
	 *
	 * @since 1.6.0
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_run_schedule();
		$this->register_check_capacity();
		$this->register_get_furthest_scheduled_date();
		$this->register_preview_next_slot();
		$this->register_run_ai_reorder();
	}

	// -------------------------------------------------------------------------
	// Individual ability registrations
	// -------------------------------------------------------------------------

	/**
	 * Ability: schedulely/run-schedule
	 * Trigger a scheduling pass (equivalent to clicking "Schedule Now").
	 *
	 * @since 1.6.0
	 */
	private function register_run_schedule(): void {
		wp_register_ability( 'schedulely/run-schedule', [
			'label'       => __( 'Run scheduling pass', 'schedulely' ),
			'description' => __( 'Schedules available posts according to current Schedulely settings. Equivalent to clicking "Run Schedule Now" in the admin.', 'schedulely' ),
			'category'    => 'schedulely',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'dry_run' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'When true, returns what would be scheduled without committing any changes.', 'schedulely' ),
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'            => [ 'type' => 'boolean' ],
					'scheduled_count'    => [ 'type' => 'integer' ],
					'completed_last_date' => [ 'type' => 'boolean' ],
					'ai_queue_ordered'   => [ 'type' => 'boolean' ],
					'message'            => [ 'type' => 'string' ],
					'errors'             => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
			],
			'execute_callback'    => [ $this, 'execute_run_schedule' ],
			'permission_callback' => [ $this, 'permission_manage_options' ],
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	/**
	 * Ability: schedulely/check-capacity
	 * Compute whether a given time window / quota combination is feasible.
	 *
	 * @since 1.6.0
	 */
	private function register_check_capacity(): void {
		wp_register_ability( 'schedulely/check-capacity', [
			'label'       => __( 'Check scheduling capacity', 'schedulely' ),
			'description' => __( 'Calculates whether the configured time window and minimum interval can fit the desired posts-per-day quota.', 'schedulely' ),
			'category'    => 'schedulely',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'start_time'    => [ 'type' => 'string', 'description' => __( 'Window start time e.g. "5:00 PM".', 'schedulely' ) ],
					'end_time'      => [ 'type' => 'string', 'description' => __( 'Window end time e.g. "11:00 PM".', 'schedulely' ) ],
					'min_interval'  => [ 'type' => 'integer', 'description' => __( 'Minimum minutes between posts.', 'schedulely' ) ],
					'posts_per_day' => [ 'type' => 'integer', 'description' => __( 'Target posts per day.', 'schedulely' ) ],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'valid'         => [ 'type' => 'boolean' ],
					'capacity'      => [ 'type' => 'integer' ],
					'desired_quota' => [ 'type' => 'integer' ],
					'meets_quota'   => [ 'type' => 'boolean' ],
					'suggestions'   => [ 'type' => 'array' ],
					'error'         => [ 'type' => [ 'string', 'null' ] ],
				],
			],
			'execute_callback'    => [ $this, 'execute_check_capacity' ],
			'permission_callback' => [ $this, 'permission_manage_options' ],
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	/**
	 * Ability: schedulely/get-furthest-scheduled-date
	 * Return the furthest scheduled date and how full it is.
	 *
	 * @since 1.6.0
	 */
	private function register_get_furthest_scheduled_date(): void {
		wp_register_ability( 'schedulely/get-furthest-scheduled-date', [
			'label'       => __( 'Get furthest scheduled date', 'schedulely' ),
			'description' => __( 'Returns the furthest future scheduled date and how many posts are on it relative to the daily quota.', 'schedulely' ),
			'category'    => 'schedulely',
			'input_schema' => [ 'type' => 'object', 'properties' => [] ],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'anchor_date'      => [ 'type' => [ 'string', 'null' ], 'format' => 'date' ],
					'posts_scheduled'  => [ 'type' => 'integer' ],
					'posts_quota'      => [ 'type' => 'integer' ],
					'is_complete'      => [ 'type' => 'boolean' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_get_furthest_scheduled_date' ],
			'permission_callback' => [ $this, 'permission_manage_options' ],
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	/**
	 * Ability: schedulely/preview-next-slot
	 * Return the datetime that would be assigned to the next post(s) without committing.
	 *
	 * @since 1.6.0
	 */
	private function register_preview_next_slot(): void {
		wp_register_ability( 'schedulely/preview-next-slot', [
			'label'       => __( 'Preview next scheduling slot', 'schedulely' ),
			'description' => __( 'Returns the datetime(s) that would be assigned to the next post(s) in the queue without making any changes.', 'schedulely' ),
			'category'    => 'schedulely',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'count' => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 20 ],
				],
			],
			'output_schema' => [
				'type'  => 'array',
				'items' => [
					'type'       => 'object',
					'properties' => [
						'anchor_date'   => [ 'type' => 'string', 'format' => 'date' ],
						'scheduled_at'  => [ 'type' => 'string', 'format' => 'date-time' ],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_preview_next_slot' ],
			'permission_callback' => [ $this, 'permission_manage_options' ],
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	/**
	 * Ability: schedulely/run-ai-reorder
	 * Preview the AI's ordering suggestion without scheduling.
	 *
	 * @since 1.6.0
	 */
	private function register_run_ai_reorder(): void {
		wp_register_ability( 'schedulely/run-ai-reorder', [
			'label'       => __( 'Preview AI queue reorder', 'schedulely' ),
			'description' => __( 'Sends the current post queue to the AI and returns the suggested order without scheduling anything.', 'schedulely' ),
			'category'    => 'schedulely',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'post_ids' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
						'description' => __( 'Post IDs to reorder. If omitted, uses the current eligible post queue.', 'schedulely' ),
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'ordered_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'reordered'   => [ 'type' => 'boolean' ],
					'reason'      => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_run_ai_reorder' ],
			'permission_callback' => [ $this, 'permission_manage_options' ],
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	// -------------------------------------------------------------------------
	// Execute callbacks
	// -------------------------------------------------------------------------

	/**
	 * Execute: schedulely/run-schedule
	 *
	 * @since 1.6.0
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_run_schedule( $input ) {
		$dry_run = ! empty( $input['dry_run'] );

		if ( $dry_run ) {
			// Dry run — return what would happen without committing.
			$scheduler   = new Schedulely_Scheduler();
			$last_date   = $scheduler->get_last_scheduled_date();
			$quota        = (int) get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY );
			$posts_on_last = $last_date ? $scheduler->count_posts_on_date( $last_date ) : 0;

			return [
				'success'             => true,
				'scheduled_count'     => 0,
				'completed_last_date' => ( $last_date && $posts_on_last < $quota ),
				'ai_queue_ordered'    => false,
				'message'             => __( 'Dry run — no posts were scheduled.', 'schedulely' ),
				'errors'              => [],
			];
		}

		try {
			$scheduler = new Schedulely_Scheduler();
			// Allow AI reordering on Ability runs — the caller is interactive.
			$results   = $scheduler->run_schedule( true );

			if ( $results['success'] ) {
				update_option( 'schedulely_last_run', time() );

				if ( get_option( 'schedulely_email_notifications', Schedulely_Defaults::EMAIL_NOTIFICATIONS ) ) {
					$notifier = new Schedulely_Notifications();
					$notifier->send_scheduling_notification( $results );
				}
			}

			return $results;

		} catch ( \Throwable $e ) {
			schedulely_log_error( 'Ability run-schedule error: ' . $e->getMessage() );
			return new WP_Error( 'schedulely_error', $e->getMessage() );
		}
	}

	/**
	 * Execute: schedulely/check-capacity
	 *
	 * @since 1.6.0
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public function execute_check_capacity( $input ) {
		$start_time    = sanitize_text_field( $input['start_time']    ?? get_option( 'schedulely_start_time',    Schedulely_Defaults::START_TIME ) );
		$end_time      = sanitize_text_field( $input['end_time']      ?? get_option( 'schedulely_end_time',      Schedulely_Defaults::END_TIME ) );
		$min_interval  = absint( $input['min_interval']  ?? get_option( 'schedulely_min_interval',  Schedulely_Defaults::MIN_INTERVAL ) );
		$posts_per_day = absint( $input['posts_per_day'] ?? get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY ) );

		$scheduler = new Schedulely_Scheduler();
		return $scheduler->calculate_capacity( $start_time, $end_time, $min_interval, $posts_per_day );
	}

	/**
	 * Execute: schedulely/get-furthest-scheduled-date
	 *
	 * @since 1.6.0
	 * @return array<string,mixed>
	 */
	public function execute_get_furthest_scheduled_date() {
		$scheduler   = new Schedulely_Scheduler();
		$anchor_date = $scheduler->get_last_scheduled_date();
		$quota        = (int) get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY );
		$count        = $anchor_date ? $scheduler->count_posts_on_date( $anchor_date ) : 0;

		return [
			'anchor_date'     => $anchor_date,
			'posts_scheduled' => $count,
			'posts_quota'     => $quota,
			'is_complete'     => ( $count >= $quota ),
		];
	}

	/**
	 * Execute: schedulely/preview-next-slot
	 *
	 * Generates up to $count datetime slots without saving anything.
	 *
	 * @since 1.6.0
	 * @param array<string,mixed> $input
	 * @return array<array<string,string>>
	 */
	public function execute_preview_next_slot( $input ) {
		$count     = min( 20, max( 1, (int) ( $input['count'] ?? 1 ) ) );
		$scheduler = new Schedulely_Scheduler();
		$slots     = [];
		$used_ts   = [];
		$anchor    = $scheduler->get_last_scheduled_date() ?? wp_date( 'Y-m-d' );

		// Use reflection to call the private generate_random_datetime method.
		// This is an acceptable trade-off — ability previews don't mutate state.
		$ref = new \ReflectionClass( $scheduler );

		if ( ! $ref->hasMethod( 'generate_random_datetime' ) ) {
			return $slots;
		}

		$method = $ref->getMethod( 'generate_random_datetime' );
		$method->setAccessible( true );

		for ( $i = 0; $i < $count; $i++ ) {
			$dt = $method->invoke( $scheduler, $anchor, $used_ts );
			if ( false === $dt ) {
				break;
			}
			$ts      = strtotime( $dt );
			$slots[] = [
				'anchor_date'  => $anchor,
				'scheduled_at' => $dt,
			];
			if ( false !== $ts ) {
				$used_ts[] = $ts;
			}
		}

		return $slots;
	}

	/**
	 * Execute: schedulely/run-ai-reorder
	 *
	 * @since 1.6.0
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_run_ai_reorder( $input ) {
		if ( ! empty( $input['post_ids'] ) && is_array( $input['post_ids'] ) ) {
			$post_ids = array_map( 'absint', $input['post_ids'] );
		} else {
			// Use the current eligible queue.
			$scheduler = new Schedulely_Scheduler();
			$ref       = new \ReflectionClass( $scheduler );
			$method    = $ref->getMethod( 'get_available_posts' );
			$method->setAccessible( true );
			$post_ids  = $method->invoke( $scheduler );
		}

		$ai      = new Schedulely_AI_Order();
		$ordered = $ai->reorder_post_ids( $post_ids );

		if ( is_wp_error( $ordered ) ) {
			return $ordered;
		}

		return [
			'ordered_ids' => $ordered,
			'reordered'   => ( $ordered !== $post_ids ),
			'reason'      => __( 'AI reorder applied. No posts were scheduled.', 'schedulely' ),
		];
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Permission gate for manage_options capabilities.
	 *
	 * @since 1.6.0
	 * @return bool|WP_Error
	 */
	public function permission_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use this ability.', 'schedulely' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}
}
