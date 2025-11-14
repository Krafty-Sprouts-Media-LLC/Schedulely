<?php
/**
 * Medialytic Admin Class
 *
 * @package Medialytic
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin functionality for Medialytic
 *
 * @since 1.0.0
 */
class Medialytic_Admin {

	/**
	 * Core instance
	 *
	 * @var Medialytic_Core
	 * @since 1.0.0
	 */
	private $core;

	/**
	 * Constructor
	 *
	 * @param Medialytic_Core $core Core instance.
	 * @since 1.0.0
	 */
	public function __construct( $core ) {
		$this->core = $core;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Settings handling
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_medialytic_save_settings', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_post_medialytic_reset_settings', array( $this, 'handle_settings_reset' ) );
		
		// Admin notices
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	/**
	 * Register settings
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'medialytic_settings',
			'medialytic_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data
	 * @since 1.0.0
	 */
	public function sanitize_settings( $input ) {
		return $this->core->sanitize_settings( $input );
	}

	/**
	 * Handle settings save
	 *
	 * @since 1.0.0
	 */
	public function handle_settings_save() {
		// Check nonce and permissions
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'medialytic_settings' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Security check failed.', 'medialytic' ) );
		}

		// Save settings
		$settings = $this->core->sanitize_settings( $_POST );
		$result = $this->core->update_options( $settings );

		// Redirect with message
		$redirect_url = add_query_arg(
			array(
				'page' => 'medialytic-settings',
				'message' => $result ? 'settings_saved' : 'settings_error',
			),
			admin_url( 'options-general.php' )
		);

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle settings reset
	 *
	 * @since 1.0.0
	 */
	public function handle_settings_reset() {
		// Check nonce and permissions
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'medialytic_reset' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Security check failed.', 'medialytic' ) );
		}

		// Reset settings
		$result = $this->core->reset_settings();

		// Redirect with message
		$redirect_url = add_query_arg(
			array(
				'page' => 'medialytic-settings',
				'message' => $result ? 'settings_reset' : 'reset_error',
			),
			admin_url( 'options-general.php' )
		);

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show admin notices
	 *
	 * @since 1.0.0
	 */
	public function show_admin_notices() {
		if ( ! isset( $_GET['page'] ) || 'medialytic-settings' !== $_GET['page'] ) {
			return;
		}

		$message = $_GET['message'] ?? '';
		switch ( $message ) {
			case 'settings_saved':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Medialytic settings saved successfully.', 'medialytic' ) . '</p></div>';
				break;
			case 'settings_error':
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error saving Medialytic settings.', 'medialytic' ) . '</p></div>';
				break;
			case 'settings_reset':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Medialytic settings reset to defaults.', 'medialytic' ) . '</p></div>';
				break;
		}
	}

	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		$options = $this->core->get_options();
		$post_types = $this->core->get_available_post_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Medialytic Settings', 'medialytic' ); ?></h1>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'medialytic_settings' ); ?>
				<input type="hidden" name="action" value="medialytic_save_settings">
				
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="enabled"><?php esc_html_e( 'Enable Media Analytics', 'medialytic' ); ?></label>
							</th>
							<td>
								<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( $options['enabled'] ); ?>>
								<p class="description"><?php esc_html_e( 'Enable or disable media counting and analytics.', 'medialytic' ); ?></p>
							</td>
						</tr>
						
						<tr>
							<th scope="row"><?php esc_html_e( 'Post Types', 'medialytic' ); ?></th>
							<td>
								<fieldset>
									<?php foreach ( $post_types as $post_type ) : ?>
										<label>
											<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $options['post_types'], true ) ); ?>>
											<?php echo esc_html( $post_type->labels->name ); ?>
										</label><br>
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Select which post types should have media analytics.', 'medialytic' ); ?></p>
							</td>
						</tr>
						
						<tr>
							<th scope="row"><?php esc_html_e( 'Media Types', 'medialytic' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="count_images" value="1" <?php checked( $options['count_images'] ); ?>>
										<?php esc_html_e( 'Count Images', 'medialytic' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="count_videos" value="1" <?php checked( $options['count_videos'] ); ?>>
										<?php esc_html_e( 'Count Videos', 'medialytic' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="count_embeds" value="1" <?php checked( $options['count_embeds'] ); ?>>
										<?php esc_html_e( 'Count Embeds', 'medialytic' ); ?>
									</label>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Select which media types to count and analyze.', 'medialytic' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				
				<?php submit_button( __( 'Save Settings', 'medialytic' ) ); ?>
			</form>
			
			<h2><?php esc_html_e( 'Quick Actions', 'medialytic' ); ?></h2>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to reset all settings to defaults?', 'medialytic' ); ?>')" style="display: inline-block;">
				<?php wp_nonce_field( 'medialytic_reset' ); ?>
				<input type="hidden" name="action" value="medialytic_reset_settings">
				<?php submit_button( __( 'Reset to Defaults', 'medialytic' ), 'secondary', 'reset', false ); ?>
			</form>
			
			<h3><?php esc_html_e( 'Plugin Information', 'medialytic' ); ?></h3>
			<p><strong><?php esc_html_e( 'Version:', 'medialytic' ); ?></strong> 1.0.0</p>
			<p><strong><?php esc_html_e( 'Status:', 'medialytic' ); ?></strong> 
				<?php if ( $options['enabled'] ) : ?>
					<span style="color: #00a32a;"><?php esc_html_e( 'Active', 'medialytic' ); ?></span>
				<?php else : ?>
					<span style="color: #d63638;"><?php esc_html_e( 'Inactive', 'medialytic' ); ?></span>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}