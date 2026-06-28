<?php
/**
 * Filename: class-settings.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 06/10/2025
 * Last Modified: 2026
 * Description: Settings coordinator — sanitizers, form handler, and renderer.
 *              Admin menu, asset enqueuing, AJAX handlers, and notices each
 *              live in their own dedicated classes (extracted in 1.6.0).
 *
 * @package Schedulely
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schedulely_Settings
 *
 * Owns:
 *   - Sanitizer methods (called by the form handler)
 *   - handle_form_save() — processes POST saves
 *   - render_settings_page() — loads the admin template
 *   - render_upcoming_posts_list() — used inside the template
 *   - get_scheduled_posts_url() — helper for the template
 *   - get_statistics() — dashboard stat cards
 *
 * @since 1.0.0
 */
class Schedulely_Settings {

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Initialize the admin layer.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Delegates menu, assets, notices, and AJAX to dedicated classes.
	 *              Dropped register_setting() — form uses a direct $_POST handler.
	 */
	public function init(): void {
		( new Schedulely_Admin_Menu() )->register_hooks();
		( new Schedulely_Admin_Assets() )->register_hooks();
		( new Schedulely_Admin_Notices() )->register_hooks();
		( new Schedulely_Ajax_Handlers() )->register_hooks();
	}

	// -------------------------------------------------------------------------
	// Sanitizers (public — called from handle_form_save and AJAX handlers)
	// -------------------------------------------------------------------------

	/**
	 * Sanitize scheduling mode.
	 *
	 * @since 1.6.0
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_scheduling_mode( $value ): string {
		$allowed = [ 'random', 'sequential', 'hybrid' ];
		return in_array( $value, $allowed, true ) ? $value : Schedulely_Defaults::SCHEDULING_MODE;
	}

	/**
	 * Sanitize the queue-ordering method.
	 *
	 * @since 1.8.0
	 * @param mixed $value
	 * @return string 'ai' or 'php'.
	 */
	public function sanitize_ordering_method( $value ): string {
		$allowed = [ 'ai', 'php' ];
		return in_array( $value, $allowed, true ) ? $value : Schedulely_Defaults::ORDERING_METHOD;
	}

	/**
	 * Sanitize the PHP spacing strategy.
	 *
	 * @since 1.8.4
	 * @param mixed $value
	 * @return string 'even' or 'round_robin'.
	 */
	public function sanitize_php_spread( $value ): string {
		$allowed = [ 'even', 'round_robin' ];
		return in_array( $value, $allowed, true ) ? $value : Schedulely_Defaults::PHP_SPREAD;
	}

	/**
	 * Sanitize post status.
	 *
	 * @since 1.0.0
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_post_status( $value ): string {
		$statuses = get_post_stati( [ 'show_in_admin_status_list' => true ], 'names' );
		$excluded = [ 'publish', 'future', 'trash', 'auto-draft', 'inherit' ];
		$allowed  = array_diff( $statuses, $excluded );
		return in_array( $value, $allowed, true ) ? $value : Schedulely_Defaults::POST_STATUS;
	}

	/**
	 * Sanitize active days array.
	 *
	 * @since 1.0.0
	 * @param mixed $value
	 * @return array<int>
	 */
	public function sanitize_active_days( $value ): array {
		if ( ! is_array( $value ) ) {
			return Schedulely_Defaults::ACTIVE_DAYS;
		}
		$sanitized = array_map( 'intval', $value );
		$valid     = array_filter( $sanitized, fn( $d ) => $d >= 0 && $d <= 6 );
		return ! empty( $valid ) ? array_values( $valid ) : Schedulely_Defaults::ACTIVE_DAYS;
	}

	/**
	 * Sanitize a checkbox field.
	 *
	 * @since 1.0.0
	 * @param mixed $value
	 * @return bool
	 */
	public function sanitize_checkbox( $value ): bool {
		return ! empty( $value );
	}

	/**
	 * Sanitize the AI API base URL (must be HTTPS).
	 *
	 * @since 1.4.0
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_ai_base_url( $value ): string {
		$default = Schedulely_Defaults::AI_BASE_URL;
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $default;
		}
		$url = esc_url_raw( trim( $value ) );
		if ( '' === $url || 0 !== strpos( $url, 'https://' ) ) {
			return $default;
		}
		return untrailingslashit( $url );
	}

	/**
	 * Sanitize the AI model id.
	 *
	 * @since 1.4.0
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_ai_model( $value ): string {
		$default = Schedulely_Defaults::AI_MODEL;
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $default;
		}
		return sanitize_text_field( substr( trim( $value ), 0, 120 ) );
	}

	/**
	 * Sanitize the AI API key.
	 *
	 * @since 1.4.0
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_ai_api_key( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return sanitize_text_field( trim( wp_unslash( $value ) ) );
	}

	/**
	 * Sanitize the excluded-authors array.
	 *
	 * @since 1.0.0
	 * @param mixed $value
	 * @return array<int>
	 */
	public function sanitize_excluded_authors( $value ): array {
		return is_array( $value ) ? array_map( 'absint', $value ) : [];
	}

	/**
	 * Sanitize the preserved-authors array.
	 *
	 * @since 1.2.7
	 * @param mixed $value
	 * @return array<int>
	 */
	public function sanitize_preserved_authors( $value ): array {
		return is_array( $value ) ? array_map( 'absint', $value ) : [];
	}

	/**
	 * Sanitize the post-types array.
	 *
	 * @since 1.3.3
	 * @param mixed $value
	 * @return array<string>
	 */
	public function sanitize_post_types( $value ): array {
		if ( ! is_array( $value ) ) {
			return Schedulely_Defaults::POST_TYPES;
		}
		$registered = get_post_types( [ 'public' => true ], 'names' );
		$valid       = array_filter( $value, fn( $pt ) => post_type_exists( $pt ) && in_array( $pt, $registered, true ) );
		return ! empty( $valid ) ? array_values( $valid ) : Schedulely_Defaults::POST_TYPES;
	}

	/**
	 * Sanitize the notification-users array.
	 * Only users with publish_posts capability are accepted.
	 *
	 * @since 1.0.0
	 * @param mixed $value
	 * @return array<int>
	 */
	public function sanitize_notification_users( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$sanitized = [];
		foreach ( $value as $uid ) {
			$uid  = absint( $uid );
			$user = get_user_by( 'id', $uid );
			if ( $user && user_can( $user, 'publish_posts' ) ) {
				$sanitized[] = $uid;
			}
		}
		return $sanitized;
	}

	// -------------------------------------------------------------------------
	// Form handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the settings form save.
	 *
	 * Called from render_settings_page() when $_POST['schedulely_save_settings'] is present.
	 * Verifies nonce and capability, then writes each option through its sanitizer.
	 * wp_unslash() applied to every $_POST value before sanitisation.
	 *
	 * @since 1.6.0
	 */
	private function handle_form_save(): void {		check_admin_referer( 'schedulely_settings_save' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to perform this action.', 'schedulely' ) );
		}

		update_option( 'schedulely_post_status',
			$this->sanitize_post_status( wp_unslash( $_POST['schedulely_post_status'] ?? Schedulely_Defaults::POST_STATUS ) ) );

		update_option( 'schedulely_posts_per_day',
			max( 1, min( 100, absint( $_POST['schedulely_posts_per_day'] ?? Schedulely_Defaults::POSTS_PER_DAY ) ) ) );

		update_option( 'schedulely_start_time',
			sanitize_text_field( wp_unslash( $_POST['schedulely_start_time'] ?? Schedulely_Defaults::START_TIME ) ) );

		update_option( 'schedulely_end_time',
			sanitize_text_field( wp_unslash( $_POST['schedulely_end_time'] ?? Schedulely_Defaults::END_TIME ) ) );

		update_option( 'schedulely_active_days',
			$this->sanitize_active_days( $_POST['schedulely_active_days'] ?? [] ) );

		update_option( 'schedulely_min_interval',
			max( 1, min( 1440, absint( $_POST['schedulely_min_interval'] ?? Schedulely_Defaults::MIN_INTERVAL ) ) ) );

		update_option( 'schedulely_pool_size',
			max( 1, min( 10000, absint( $_POST['schedulely_pool_size'] ?? Schedulely_Defaults::MAX_POSTS_PER_RUN ) ) ) );

		update_option( 'schedulely_manual_batch_size',
			max(
				Schedulely_Defaults::MANUAL_BATCH_SIZE_MIN,
				min(
					Schedulely_Defaults::MANUAL_BATCH_SIZE_MAX,
					absint( $_POST['schedulely_manual_batch_size'] ?? Schedulely_Defaults::MANUAL_BATCH_SIZE )
				)
			) );

		update_option( 'schedulely_shuffle_queue',
			$this->sanitize_checkbox( $_POST['schedulely_shuffle_queue'] ?? false ) );

		update_option( 'schedulely_scheduling_mode',
			$this->sanitize_scheduling_mode( wp_unslash( $_POST['schedulely_scheduling_mode'] ?? Schedulely_Defaults::SCHEDULING_MODE ) ) );

		update_option( 'schedulely_ai_order_enabled',
			$this->sanitize_checkbox( $_POST['schedulely_ai_order_enabled'] ?? false ) );

		update_option( 'schedulely_ordering_method',
			$this->sanitize_ordering_method( wp_unslash( $_POST['schedulely_ordering_method'] ?? '' ) ) );

		update_option( 'schedulely_php_spread',
			$this->sanitize_php_spread( wp_unslash( $_POST['schedulely_php_spread'] ?? '' ) ) );

		update_option( 'schedulely_ai_us_timezone_ordering',
			$this->sanitize_checkbox( $_POST['schedulely_ai_us_timezone_ordering'] ?? false ) );

		update_option( 'schedulely_ai_base_url',
			$this->sanitize_ai_base_url( wp_unslash( $_POST['schedulely_ai_base_url'] ?? '' ) ) );

		update_option( 'schedulely_ai_model',
			$this->sanitize_ai_model( wp_unslash( $_POST['schedulely_ai_model'] ?? '' ) ) );

		if ( ! empty( $_POST['schedulely_ai_clear_api_key'] ) ) {
			update_option( 'schedulely_ai_api_key', '' );
		} elseif ( isset( $_POST['schedulely_ai_api_key'] )
			&& '' !== trim( wp_unslash( (string) $_POST['schedulely_ai_api_key'] ) ) ) {
			update_option( 'schedulely_ai_api_key',
				$this->sanitize_ai_api_key( wp_unslash( $_POST['schedulely_ai_api_key'] ) ) );
		}

		update_option( 'schedulely_randomize_authors',
			$this->sanitize_checkbox( $_POST['schedulely_randomize_authors'] ?? false ) );

		update_option( 'schedulely_excluded_authors',
			$this->sanitize_excluded_authors( $_POST['schedulely_excluded_authors'] ?? [] ) );

		update_option( 'schedulely_preserved_authors',
			$this->sanitize_preserved_authors( $_POST['schedulely_preserved_authors'] ?? [] ) );

		update_option( 'schedulely_post_types',
			$this->sanitize_post_types( $_POST['schedulely_post_types'] ?? Schedulely_Defaults::POST_TYPES ) );

		update_option( 'schedulely_auto_schedule',
			$this->sanitize_checkbox( $_POST['schedulely_auto_schedule'] ?? false ) );

		update_option( 'schedulely_email_notifications',
			$this->sanitize_checkbox( $_POST['schedulely_email_notifications'] ?? false ) );

		update_option( 'schedulely_ai_email_summary',
			$this->sanitize_checkbox( $_POST['schedulely_ai_email_summary'] ?? false ) );

		update_option( 'schedulely_notification_users',
			$this->sanitize_notification_users( $_POST['schedulely_notification_users'] ?? [] ) );

		add_settings_error(
			'schedulely_messages',
			'schedulely_message',
			__( 'Settings saved successfully!', 'schedulely' ),
			'success'
		);
	}

	// -------------------------------------------------------------------------
	// Renderer
	// -------------------------------------------------------------------------

	/**
	 * Render the Tools → Schedulely settings page.
	 *
	 * Prepares data variables and loads the template file. The template has
	 * access to $stats, $stored_ai_key_raw, $stored_ai_key_len, $ai_reorder_log,
	 * and $this.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Form save extracted to handle_form_save().
	 *              HTML template extracted to templates/admin/settings-page.php.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'schedulely' ) );
		}

		if ( isset( $_GET['schedulely_ai_log_cleared'] ) && '1' === $_GET['schedulely_ai_log_cleared'] ) {
			add_settings_error( 'schedulely_messages', 'schedulely_ai_log_cleared', __( 'AI reorder log cleared.', 'schedulely' ), 'success' );
		}

		if ( isset( $_POST['schedulely_save_settings'] ) ) {
			$this->handle_form_save();
		}

		$stats             = $this->get_statistics();
		$stored_ai_key_raw = get_option( 'schedulely_ai_api_key', '' );
		$stored_ai_key_len = ( is_string( $stored_ai_key_raw ) && '' !== trim( $stored_ai_key_raw ) ) ? strlen( $stored_ai_key_raw ) : 0;
		$ai_reorder_log    = get_option( 'schedulely_ai_reorder_log', [] );
		if ( ! is_array( $ai_reorder_log ) ) {
			$ai_reorder_log = [];
		}

		require SCHEDULELY_PLUGIN_DIR . 'templates/admin/settings-page.php';
	}

	// -------------------------------------------------------------------------
	// Template helpers (called from templates/admin/settings-page.php via $this)
	// -------------------------------------------------------------------------

	/**
	 * Render the upcoming-posts list for the Activity sidebar.
	 *
	 * @since 1.0.0
	 */
	public function render_upcoming_posts_list(): void {
		$post_types      = get_option( 'schedulely_post_types', Schedulely_Defaults::POST_TYPES );
		$scheduled_posts = get_posts( [
			'post_type'              => $post_types,
			'post_status'            => 'future',
			'posts_per_page'         => 5,
			'orderby'                => 'date',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		echo '<ul class="activity-list">';

		if ( empty( $scheduled_posts ) ) {
			echo '<li class="activity-item"><div class="activity-content" style="color:#646970;">'
				. esc_html__( 'No upcoming posts scheduled.', 'schedulely' )
				. '</div></li>';
		} else {
			foreach ( $scheduled_posts as $post ) {
				echo '<li class="activity-item">';
				echo '<div class="activity-dot dot-green"></div>';
				echo '<div class="activity-content">';
				echo '<strong>' . esc_html( $post->post_title ) . '</strong>';
				echo '<span class="activity-time">' . esc_html( wp_date( 'M j, g:i A', strtotime( $post->post_date ) ) ) . '</span>';
				echo '</div>';
				echo '</li>';
			}
		}

		echo '</ul>';
	}

	/**
	 * Return the admin URL for viewing scheduled posts.
	 *
	 * @since 1.3.4
	 * @param string|null $post_type Specific post type, or null for all configured types.
	 * @return string
	 */
	public function get_scheduled_posts_url( ?string $post_type = null ): string {
		$post_types = get_option( 'schedulely_post_types', Schedulely_Defaults::POST_TYPES );

		if ( $post_type && in_array( $post_type, $post_types, true ) ) {
			return admin_url( 'edit.php?post_status=future&post_type=' . esc_attr( $post_type ) );
		}
		if ( count( $post_types ) === 1 ) {
			return admin_url( 'edit.php?post_status=future&post_type=' . esc_attr( $post_types[0] ) );
		}
		return admin_url( 'edit.php?post_status=future' );
	}

	// -------------------------------------------------------------------------
	// Dashboard statistics
	// -------------------------------------------------------------------------

	/**
	 * Gather the statistics shown in the stat cards.
	 *
	 * Uses wp_count_posts (heavily cached) instead of a get_posts with
	 * posts_per_page=-1 to avoid loading all post IDs into memory.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Replaced unbounded get_posts with wp_count_posts.
	 *
	 * @since 1.8.7 Added pool_size and pool_overflow for per-run batch warnings.
	 *
	 * @return array{available_posts: int, pool_size: int, pool_overflow: int, next_scheduled: string, last_date_status: string, last_run: string}
	 */
	private function get_statistics(): array {
		$post_types      = get_option( 'schedulely_post_types', Schedulely_Defaults::POST_TYPES );
		$available_posts = Schedulely_Scheduler::count_eligible_posts();
		$pool_size       = Schedulely_Scheduler::get_pool_size_limit();
		$pool_overflow   = max( 0, $available_posts - $pool_size );

		// Next scheduled post.
		$next_posts = get_posts( [
			'post_type'              => $post_types,
			'post_status'            => 'future',
			'posts_per_page'         => 1,
			'orderby'                => 'date',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );
		$next_scheduled = ! empty( $next_posts )
			? wp_date( 'M j, Y - g:i A', strtotime( $next_posts[0]->post_date ) )
			: __( 'None', 'schedulely' );

		// Last-scheduled date status.
		$scheduler        = new Schedulely_Scheduler();
		$last_date        = $scheduler->get_last_scheduled_date();
		$last_date_status = __( 'None', 'schedulely' );

		if ( $last_date ) {
			$quota       = (int) get_option( 'schedulely_posts_per_day', Schedulely_Defaults::POSTS_PER_DAY );
			$posts_count = $scheduler->count_posts_on_date( $last_date );
			$is_complete = $posts_count >= $quota;

			$last_date_status  = ( wp_date( 'M j, Y', strtotime( $last_date ) ) ?? '' ) . ' - ';
			$last_date_status .= $is_complete
				? __( 'Complete', 'schedulely' ) . ' ✓'
				: $posts_count . '/' . $quota;
		}

		// Last run timestamp.
		$last_run      = (int) get_option( 'schedulely_last_run', 0 );
		$last_run_text = $last_run > 0
			? ( wp_date( 'M j, Y - g:i A', $last_run ) ?? __( 'Never', 'schedulely' ) )
			: __( 'Never', 'schedulely' );

		return [
			'available_posts'   => $available_posts,
			'pool_size'         => $pool_size,
			'pool_overflow'     => $pool_overflow,
			'next_scheduled'    => $next_scheduled,
			'last_date_status'  => $last_date_status,
			'last_run'          => $last_run_text,
		];
	}
}
