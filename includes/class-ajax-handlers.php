<?php
/**
 * Filename: class-ajax-handlers.php
 * Description: All wp_ajax_* and admin-post action handlers for Schedulely.
 *              Extracted from Schedulely_Settings in Phase 2 refactor.
 *
 * @package Schedulely
 * @since   1.6.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schedulely_Ajax_Handlers
 *
 * Registers and handles all Schedulely AJAX endpoints and admin-post actions.
 * Each public method is a self-contained handler: verify nonce → check cap →
 * do work → send response.
 *
 * @since 1.6.0
 */
class Schedulely_Ajax_Handlers {

	/**
	 * Register all AJAX and admin-post action hooks.
	 *
	 * @since 1.6.0
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_schedulely_check_capacity',       [ $this, 'handle_check_capacity' ] );
		add_action( 'wp_ajax_schedulely_dismiss_notice',       [ $this, 'handle_dismiss_notice' ] );
		add_action( 'wp_ajax_schedulely_toggle_auto_schedule', [ $this, 'handle_toggle_auto_schedule' ] );
		add_action( 'wp_ajax_schedulely_test_ai_connection',   [ $this, 'handle_test_ai_connection' ] );
		add_action( 'admin_post_schedulely_clear_ai_reorder_log', [ $this, 'handle_clear_ai_reorder_log' ] );
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: calculate whether current settings fit within the time window.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Moved from Schedulely_Settings.
	 */
	public function handle_check_capacity(): void {
		check_ajax_referer( 'schedulely_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'schedulely' ) ] );
		}

		$start_time    = sanitize_text_field( wp_unslash( $_POST['start_time']    ?? get_option( 'schedulely_start_time',    Schedulely_Defaults::START_TIME ) ) );
		$end_time      = sanitize_text_field( wp_unslash( $_POST['end_time']      ?? get_option( 'schedulely_end_time',      Schedulely_Defaults::END_TIME ) ) );
		$min_interval  = absint( $_POST['min_interval']  ?? get_option( 'schedulely_min_interval',  Schedulely_Defaults::MIN_INTERVAL ) );
		$posts_per_day = absint( $_POST['posts_per_day'] ?? get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY ) );

		$scheduler     = new Schedulely_Scheduler();
		$capacity_data = $scheduler->calculate_capacity( $start_time, $end_time, $min_interval, $posts_per_day );

		// Add an AI-generated suggestion when capacity doesn't meet quota and WP 7.0 AI is available.
		if ( apply_filters( 'schedulely_feature_ai_capacity_hint', true )
			&& ! $capacity_data['meets_quota']
			&& $capacity_data['valid']
			&& function_exists( 'wp_ai_client_prompt' ) ) {

			try {
				$builder = wp_ai_client_prompt(
					sprintf(
						'WordPress scheduling plugin. Time window: %s – %s. Min interval between posts: %d minutes. Target: %d posts/day. Actual capacity: ~%d posts/day. In 1–2 plain-text sentences, explain the trade-off in practical terms and suggest the most natural fix for a non-technical user.',
						esc_html( $start_time ),
						esc_html( $end_time ),
						$min_interval,
						$posts_per_day,
						$capacity_data['capacity']
					)
				)->using_temperature( 0.4 );

				if ( $builder->is_supported_for_text_generation() ) {
					$hint = trim( (string) $builder->generate_text() );
					if ( '' !== $hint ) {
						$capacity_data['ai_hint'] = esc_html( $hint );
					}
				}
			} catch ( \Throwable $e ) {
				// Silently skip — the programmatic suggestions are always present.
			}
		}

		wp_send_json_success( $capacity_data );
	}

	/**
	 * AJAX: dismiss the activation welcome notice for the current admin.
	 *
	 * Stores dismiss state per-user via user meta so different admins can each
	 * see the notice independently.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Switched from site-wide option to per-user meta.
	 */
	public function handle_dismiss_notice(): void {
		check_ajax_referer( 'schedulely_dismiss_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_user_meta( $user_id, 'schedulely_welcome_dismissed', true );
		}
		wp_send_json_success();
	}

	/**
	 * AJAX: enable or disable the automatic cron-driven scheduling.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Moved from Schedulely_Settings.
	 */
	public function handle_toggle_auto_schedule(): void {
		check_ajax_referer( 'schedulely_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'schedulely' ) ] );
		}

		$enabled   = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		$timestamp = wp_next_scheduled( 'schedulely_auto_schedule' );

		update_option( 'schedulely_auto_schedule', $enabled );

		if ( $enabled ) {
			if ( ! $timestamp ) {
				wp_schedule_event( time(), 'twicedaily', 'schedulely_auto_schedule' );
			}
		} else {
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'schedulely_auto_schedule' );
			}
		}

		wp_send_json_success( [
			'message' => $enabled
				? __( 'Auto-schedule enabled. Posts will be scheduled automatically twice daily.', 'schedulely' )
				: __( 'Auto-schedule disabled. Use "Run Schedule Now" to schedule posts manually.', 'schedulely' ),
			'enabled' => $enabled,
		] );
	}

	/**
	 * AJAX: test the configured AI provider connection.
	 *
	 * @since 1.4.3
	 * @since 1.6.0 Moved from Schedulely_Settings.
	 */
	public function handle_test_ai_connection(): void {
		check_ajax_referer( 'schedulely_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'schedulely' ) ] );
		}

		$ai     = new Schedulely_AI_Order();
		$result = $ai->test_api_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$message = __( 'Connection OK — the API accepted your key and returned a reply.', 'schedulely' );
		if ( is_array( $result ) && ! empty( $result['message'] ) ) {
			$message = $result['message'];
		}

		wp_send_json_success( [ 'message' => $message ] );
	}

	/**
	 * admin-post: clear the AI queue-reorder log.
	 *
	 * @since 1.5.4
	 * @since 1.6.0 Moved from Schedulely_Settings. Nonce checked before capability (consistent order).
	 */
	public function handle_clear_ai_reorder_log(): void {
		check_admin_referer( 'schedulely_clear_ai_reorder_log', 'schedulely_clear_ai_reorder_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'schedulely' ) );
		}

		update_option( 'schedulely_ai_reorder_log', [], false );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'                     => 'schedulely',
					'schedulely_ai_log_cleared' => '1',
				],
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
}
