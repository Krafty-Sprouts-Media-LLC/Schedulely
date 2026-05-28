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
	 * @since 1.7.3 Dismiss nonce now enqueued via a dedicated inline script on
	 *              every admin page — previously it was attached to the settings-page
	 *              handle which is not loaded on other admin pages, so the dismiss
	 *              button silently did nothing outside Tools → Schedulely.
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

		// Register + enqueue a tiny inline script that works on ANY admin page.
		// We cannot rely on the schedulely-admin handle here because that is only
		// enqueued on the plugin's own settings page.
		wp_register_script( 'schedulely-dismiss', false, [ 'jquery' ], null, true );
		wp_enqueue_script( 'schedulely-dismiss' );
		wp_add_inline_script(
			'schedulely-dismiss',
			'(function($){
				var nonce = ' . wp_json_encode( wp_create_nonce( 'schedulely_dismiss_notice' ) ) . ';
				function sendDismiss(){
					$.post(ajaxurl,{action:"schedulely_dismiss_notice",nonce:nonce});
				}
				$(document).on("click",".schedulely-dismiss-notice",function(){
					$("#schedulely-welcome-notice").fadeOut();
					sendDismiss();
				});
				$(document).on("click","#schedulely-welcome-notice .notice-dismiss",function(){
					sendDismiss();
				});
			})(jQuery);'
		);

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
	}
}
