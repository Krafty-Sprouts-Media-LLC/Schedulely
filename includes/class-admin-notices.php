<?php
/**
 * Filename: class-admin-notices.php
 * Description: Admin notice management for Schedulely.
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
 * Class Schedulely_Admin_Notices
 *
 * Handles admin notices shown outside the plugin's own settings page
 * (e.g. the post-activation welcome notice).
 *
 * @since 1.6.0
 */
class Schedulely_Admin_Notices {

	/**
	 * Register hooks.
	 *
	 * @since 1.6.0
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', [ $this, 'show_welcome_notice' ] );
	}

	/**
	 * Show the post-activation welcome notice.
	 *
	 * Shown once per administrator (per-user state). Dismissed via
	 * Schedulely_Ajax_Handlers::handle_dismiss_notice() which sets user meta.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Moved from Schedulely_Settings. Per-user dismiss via user_meta.
	 */
	public function show_welcome_notice(): void {
		// Per-user: check whether this specific admin has dismissed.
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		if ( get_user_meta( $user_id, 'schedulely_welcome_dismissed', true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && 'tools_page_schedulely' === $screen->id ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible" id="schedulely-welcome-notice">
			<h3><?php esc_html_e( '🚀 Schedulely Activated!', 'schedulely' ); ?></h3>
			<p>
				<?php esc_html_e( 'Thank you for installing Schedulely! To get started, configure your scheduling settings.', 'schedulely' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=schedulely' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Go to Settings', 'schedulely' ); ?>
				</a>
				<button type="button" class="button schedulely-dismiss-notice" data-notice="welcome">
					<?php esc_html_e( 'Dismiss', 'schedulely' ); ?>
				</button>
			</p>
		</div>
		<?php
		// Output the dismiss nonce as a global JS variable so admin.js can read
		// it without an inline <script> block inside the notice markup.
		wp_add_inline_script(
			'schedulely-admin',
			'window.schedulely_dismiss_nonce = ' . wp_json_encode( wp_create_nonce( 'schedulely_dismiss_notice' ) ) . ';',
			'after'
		);
	}
}
