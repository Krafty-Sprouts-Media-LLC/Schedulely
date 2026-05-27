<?php
/**
 * Filename: class-admin-assets.php
 * Description: Admin script and style enqueuing for Schedulely.
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
 * Class Schedulely_Admin_Assets
 *
 * Enqueues all admin scripts and styles for the Schedulely settings page.
 * All third-party libraries are served from the local assets/vendor/ directory
 * (vendored in Phase 1 — no CDN requests).
 *
 * Library versions:
 *   SweetAlert2  11.22.0  MIT  assets/vendor/sweetalert2/
 *   Select2       4.0.13  MIT  assets/vendor/select2/
 *   Flatpickr     4.6.13  MIT  assets/vendor/flatpickr/
 *
 * @since 1.6.0
 */
class Schedulely_Admin_Assets {

	/**
	 * Register hooks.
	 *
	 * @since 1.6.0
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Enqueue assets on the Schedulely settings page only.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Moved from Schedulely_Settings. Replaced CDN URLs with
	 *              local vendor copies. Removed Google Fonts enqueue.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'tools_page_schedulely' !== $hook ) {
			return;
		}

		// SweetAlert2 — vendored locally.
		wp_enqueue_style(
			'sweetalert2',
			SCHEDULELY_PLUGIN_URL . 'assets/vendor/sweetalert2/sweetalert2.min.css',
			[],
			'11.22.0'
		);
		wp_enqueue_script(
			'sweetalert2',
			SCHEDULELY_PLUGIN_URL . 'assets/vendor/sweetalert2/sweetalert2.min.js',
			[],
			'11.22.0',
			true
		);

		// Select2 4.0.13 stable — vendored locally.
		wp_enqueue_style(
			'select2',
			SCHEDULELY_PLUGIN_URL . 'assets/vendor/select2/select2.min.css',
			[],
			'4.0.13'
		);
		wp_enqueue_script(
			'select2',
			SCHEDULELY_PLUGIN_URL . 'assets/vendor/select2/select2.min.js',
			[ 'jquery' ],
			'4.0.13',
			true
		);

		// Flatpickr — vendored locally.
		wp_enqueue_style(
			'flatpickr',
			SCHEDULELY_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.css',
			[],
			'4.6.13'
		);
		wp_enqueue_script(
			'flatpickr',
			SCHEDULELY_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.js',
			[],
			'4.6.13',
			true
		);

		// Plugin styles.
		wp_enqueue_style(
			'schedulely-admin',
			SCHEDULELY_PLUGIN_URL . 'assets/css/admin.css',
			[],
			SCHEDULELY_VERSION
		);

		// Plugin scripts.
		wp_enqueue_script(
			'schedulely-admin',
			SCHEDULELY_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'sweetalert2', 'select2', 'flatpickr' ],
			SCHEDULELY_VERSION,
			true
		);

		// Localise script with nonces, URLs, and all translatable strings.
		wp_localize_script(
			'schedulely-admin',
			'schedulely_admin',
			[
				'nonce'               => wp_create_nonce( 'schedulely_admin' ),
				'ajaxurl'             => admin_url( 'admin-ajax.php' ),
				'scheduled_posts_url' => admin_url( 'edit.php?post_status=future' ),
				'strings'             => [
					'confirm_schedule'       => __( 'Schedule available posts now?', 'schedulely' ),
					'scheduling'             => __( 'Scheduling...', 'schedulely' ),
					'schedule_now'           => __( 'Schedule Now', 'schedulely' ),
					'test_ai_ok'             => __( 'Connection OK — the API accepted your key and returned a reply.', 'schedulely' ),
					'test_ai_fail'           => __( 'Connection test failed.', 'schedulely' ),
					'test_ai_running'        => __( 'Testing connection…', 'schedulely' ),
					'test_ai_contacting'     => __( 'Contacting the API — this can take a few seconds…', 'schedulely' ),
					'capacity_warning_title' => __( '⚠️ Capacity Warning', 'schedulely' ),
					'capacity_invalid'       => __( 'Invalid Settings', 'schedulely' ),
					'schedule_posts_title'   => __( 'Schedule Posts Now?', 'schedulely' ),
					'schedule_posts_body'    => __( 'This will schedule all available posts according to your settings. Do you want to continue?', 'schedulely' ),
					'yes_schedule'           => __( 'Yes, Schedule Now', 'schedulely' ),
					'cancel'                 => __( 'Cancel', 'schedulely' ),
					'success'                => __( 'Success!', 'schedulely' ),
					'error'                  => __( 'Error', 'schedulely' ),
					'view_scheduled'         => __( 'View Scheduled Posts', 'schedulely' ),
					'stay_here'              => __( 'Stay Here', 'schedulely' ),
					'validation_error'       => __( 'Validation Error', 'schedulely' ),
					'posts_per_day_range'    => __( 'Posts per day must be between 1 and 100.', 'schedulely' ),
					'interval_range'         => __( 'Minimum interval must be between 1 and 1440 minutes.', 'schedulely' ),
					'select_day'             => __( 'Please select at least one active day.', 'schedulely' ),
					'checking_capacity'      => __( 'Checking Capacity...', 'schedulely' ),
					'apply_fix'              => __( 'Apply Fix', 'schedulely' ),
					'applied'                => __( 'Applied!', 'schedulely' ),
					'recommended_fixes'      => __( 'Recommended Fixes', 'schedulely' ),
					'capacity_checking'      => __( 'Recalculating…', 'schedulely' ),
					'capacity_ok'            => __( 'Fits quota', 'schedulely' ),
					'capacity_warn'          => __( 'Below quota', 'schedulely' ),
					'capacity_error'         => __( 'Settings error', 'schedulely' ),
					'capacity_show_suggestions' => __( 'Show suggestions', 'schedulely' ),
					'capacity_hide_suggestions' => __( 'Hide suggestions', 'schedulely' ),
				],
			]
		);
	}
}
