<?php
/**
 * Filename: class-image-title-alt.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.7.0
 * Last Modified: 14/11/2025
 * Description: Cleans attachment titles, captions, descriptions, alt text, and filenames.
 *
 * Credits:
 * - Based on the "Auto Image Title & Alt" plugin by Diego de Guindos (GPLv2+).
 *   Functionality has been reimplemented, modernised, and integrated into Medialytic.
 *
 * @package Medialytic
 * @since 1.7.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image metadata optimizer.
 *
 * @since 1.7.0
 */
class Medialytic_Image_Title_Alt {

	const OPTION_KEY = 'medialytic_image_title_alt';

	/**
	 * Core instance (unused for now, reserved for future integrations).
	 *
	 * @var Medialytic_Core|null
	 */
	private $core;

	/**
	 * Stored options.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Constructor.
	 */
	public function __construct( $core = null ) {
		$this->core    = $core;
		$this->options = $this->get_options();
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'refresh_options' ) );
		$this->register_settings_hooks();
		$this->register_runtime_hooks();
	}

	/**
	 * Refresh cached options.
	 *
	 * @return void
	 */
	public function refresh_options() {
		$this->options = $this->get_options();
	}

	/**
	 * Get default option values.
	 *
	 * @return array
	 */
	private function get_default_options() {
		return array(
			'enabled'       => true,
			'fields'        => array( 'post_title', 'alt_text' ),
			'capitalization'=> 'ucwords',
			'rename_mode'   => 'images_only',
		);
	}

	/**
	 * Retrieve stored options.
	 *
	 * @return array
	 */
	private function get_options() {
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $stored, $this->get_default_options() );
	}

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	private function register_settings_hooks() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register runtime hooks when enabled.
	 *
	 * @return void
	 */
	private function register_runtime_hooks() {
		if ( empty( $this->options['enabled'] ) ) {
			return;
		}

		add_action( 'add_attachment', array( $this, 'handle_attachment' ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'rename_uploaded_files' ) );
		add_action( 'add_meta_boxes_attachment', array( $this, 'register_meta_box' ) );
		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_medialytic_image_title_alt', array( $this, 'ajax_optimize_attachment' ) );
	}

	/**
	 * Register settings page.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Medialytic Image Title & Alt', 'medialytic' ),
			__( 'Image Title & Alt', 'medialytic' ),
			'manage_options',
			'medialytic-image-title-alt',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'medialytic_image_title_alt',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_options(),
			)
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw data.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = $this->get_default_options();

		$sanitized = array(
			'enabled'        => ! empty( $input['enabled'] ),
			'fields'         => array(),
			'capitalization' => $defaults['capitalization'],
			'rename_mode'    => $defaults['rename_mode'],
		);

		$allowed_fields = array( 'post_title', 'alt_text', 'post_excerpt', 'post_content' );
		if ( ! empty( $input['fields'] ) && is_array( $input['fields'] ) ) {
			$sanitized['fields'] = array_values(
				array_intersect( $allowed_fields, array_map( 'sanitize_text_field', $input['fields'] ) )
			);
		}

		if ( empty( $sanitized['fields'] ) ) {
			$sanitized['fields'] = array();
		}

		$allowed_caps = array( 'ucwords', 'lowercase', 'uppercase', 'none' );
		if ( ! empty( $input['capitalization'] ) ) {
			$cap = sanitize_text_field( $input['capitalization'] );
			$sanitized['capitalization'] = in_array( $cap, $allowed_caps, true ) ? $cap : $defaults['capitalization'];
		}

		$allowed_modes = array( 'images_only', 'all_files', 'none' );
		if ( ! empty( $input['rename_mode'] ) ) {
			$mode = sanitize_text_field( $input['rename_mode'] );
			$sanitized['rename_mode'] = in_array( $mode, $allowed_modes, true ) ? $mode : $defaults['rename_mode'];
		}

		return $sanitized;
	}

	/**
	 * Render settings UI.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$this->refresh_options();
		$fields = array(
			'post_title'   => __( 'Image Title', 'medialytic' ),
			'alt_text'     => __( 'Alt Text', 'medialytic' ),
			'post_excerpt' => __( 'Caption (Excerpt)', 'medialytic' ),
			'post_content' => __( 'Description (Content)', 'medialytic' ),
		);

		$capitalizations = array(
			'ucwords'   => __( 'Capitalized (Default)', 'medialytic' ),
			'lowercase' => __( 'lowercase', 'medialytic' ),
			'uppercase' => __( 'UPPERCASE', 'medialytic' ),
			'none'      => __( 'Do not modify', 'medialytic' ),
		);

		$rename_modes = array(
			'images_only' => __( 'Rename images only', 'medialytic' ),
			'all_files'   => __( 'Rename all uploaded files', 'medialytic' ),
			'none'        => __( 'Do not rename files', 'medialytic' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Medialytic Image Title & Alt', 'medialytic' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Automatically converts raw filenames into clean titles, captions, descriptions, and alt text. Based on the Auto Image Title & Alt plugin by Diego de Guindos, now maintained inside Medialytic.', 'medialytic' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'medialytic_image_title_alt' );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable module', 'medialytic' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $this->options['enabled'] ); ?> />
								<?php esc_html_e( 'Optimize titles/alt text for new uploads and provide quick actions in the Media Library.', 'medialytic' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fields to update', 'medialytic' ); ?></th>
						<td>
							<?php foreach ( $fields as $key => $label ) : ?>
								<label style="display:block;">
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fields][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $this->options['fields'], true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Capitalization', 'medialytic' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[capitalization]">
								<?php foreach ( $capitalizations as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $this->options['capitalization'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'File renaming', 'medialytic' ); ?></th>
						<td>
							<?php foreach ( $rename_modes as $value => $label ) : ?>
								<label style="display:block;">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rename_mode]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $this->options['rename_mode'], $value ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Renamed files are converted to lowercase alphanumeric slugs for SEO-friendly URLs.', 'medialytic' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle attachment uploads.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function handle_attachment( $attachment_id ) {
		$this->apply_to_attachment( $attachment_id );
	}

	/**
	 * Apply metadata updates to an attachment.
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $fields_override Override fields.
	 * @param string|null $capitalization_override Override capitalization.
	 * @return void
	 */
	public function apply_to_attachment( $attachment_id, $fields_override = null, $capitalization_override = null ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$title = $this->build_clean_title( $attachment_id );
		if ( '' === $title ) {
			return;
		}

		$fields = is_array( $fields_override ) ? $fields_override : ( $this->options['fields'] ?? array() );
		if ( empty( $fields ) ) {
			return;
		}

		$format = $capitalization_override ?: ( $this->options['capitalization'] ?? 'ucwords' );
		$title  = $this->apply_capitalization( $title, $format );

		$update_args = array(
			'ID' => $attachment_id,
		);

		if ( in_array( 'post_title', $fields, true ) ) {
			$update_args['post_title'] = $title;
		}
		if ( in_array( 'post_excerpt', $fields, true ) ) {
			$update_args['post_excerpt'] = $title;
		}
		if ( in_array( 'post_content', $fields, true ) ) {
			$update_args['post_content'] = $title;
		}

		if ( count( $update_args ) > 1 ) {
			wp_update_post( $update_args );
		}

		if ( in_array( 'alt_text', $fields, true ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
		}
	}

	/**
	 * Build the clean title from filename.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function build_clean_title( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		if ( ! $path ) {
			$post = get_post( $attachment_id );
			$path = $post ? $post->post_title : '';
		}

		if ( empty( $path ) ) {
			return '';
		}

		$filename = pathinfo( $path, PATHINFO_FILENAME );
		$clean    = preg_replace( '%\s*[-_\s]+\s*%', ' ', $filename );
		return trim( $clean );
	}

	/**
	 * Apply capitalization.
	 *
	 * @param string $title Title.
	 * @param string $format Format.
	 * @return string
	 */
	private function apply_capitalization( $title, $format ) {
		switch ( $format ) {
			case 'lowercase':
				return strtolower( $title );
			case 'uppercase':
				return strtoupper( $title );
			case 'ucwords':
				return ucwords( strtolower( $title ) );
			default:
				return $title;
		}
	}

	/**
	 * Rename uploaded files when configured.
	 *
	 * @param array $file Upload array.
	 * @return array
	 */
	public function rename_uploaded_files( $file ) {
		$mode = $this->options['rename_mode'] ?? 'images_only';

		if ( 'none' === $mode ) {
			return $file;
		}

		$is_image = 0 === strpos( $file['type'], 'image/' );
		if ( 'images_only' === $mode && ! $is_image ) {
			return $file;
		}

		$info      = pathinfo( $file['name'] );
		$filename  = strtolower( $info['filename'] ?? '' );
		$extension = isset( $info['extension'] ) ? '.' . $info['extension'] : '';

		$filename = remove_accents( $filename );
		$filename = preg_replace( '/[^a-z0-9]+/', '-', $filename );
		$filename = trim( $filename, '-' );

		if ( '' === $filename ) {
			$filename = 'file';
		}

		$file['name'] = $filename . $extension;
		return $file;
	}

	/**
	 * Register meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'medialytic_image_title_alt',
			__( 'Optimize image title & tags', 'medialytic' ),
			array( $this, 'render_meta_box' ),
			'attachment',
			'side'
		);
	}

	/**
	 * Render meta box content.
	 *
	 * @param WP_Post $post Attachment.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		?>
		<button type="button" class="button button-primary medialytic-image-meta-trigger" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<?php esc_html_e( 'Optimize title & tags', 'medialytic' ); ?>
		</button>
		<p class="description" style="margin-top:8px;">
			<?php esc_html_e( 'Applies the cleaned file name to the selected fields (title, caption, description, alt) using your settings.', 'medialytic' ); ?>
		</p>
		<div class="medialytic-image-meta-feedback" style="margin-top:8px;"></div>
		<?php
	}

	/**
	 * Add media column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_media_column( $columns ) {
		$columns['medialytic_image_meta'] = __( 'Image Title & Alt', 'medialytic' );
		return $columns;
	}

	/**
	 * Render media column buttons.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Attachment ID.
	 * @return void
	 */
	public function render_media_column( $column, $post_id ) {
		if ( 'medialytic_image_meta' !== $column ) {
			return;
		}

		if ( ! wp_attachment_is_image( $post_id ) ) {
			echo '<em>' . esc_html__( 'Not an image', 'medialytic' ) . '</em>';
			return;
		}

		printf(
			'<button type="button" class="button medialytic-image-meta-trigger" data-context="list" data-post-id="%1$d">%2$s</button>',
			$post_id,
			esc_html__( 'Optimize title & tags', 'medialytic' )
		);
	}

	/**
	 * Enqueue admin JS.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		$should_load = in_array( $hook, array( 'attachment.php', 'post.php', 'post-new.php' ), true );

		if ( 'upload.php' === $hook ) {
			$mode = $_GET['mode'] ?? 'grid'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$should_load = ( 'list' === $mode );
		}

		if ( ! $should_load ) {
			return;
		}

		wp_enqueue_script(
			'medialytic-image-title-alt',
			MEDIALYTIC_PLUGIN_URL . 'assets/js/image-title-alt.js',
			array( 'jquery' ),
			MEDIALYTIC_VERSION,
			true
		);

		wp_localize_script(
			'medialytic-image-title-alt',
			'medialyticImageMeta',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'medialytic_image_title_alt' ),
				'fields'         => $this->options['fields'],
				'capitalization' => $this->options['capitalization'],
				'messages'       => array(
					'updating' => __( 'Updating…', 'medialytic' ),
					'success'  => __( 'Title and tags updated.', 'medialytic' ),
					'error'    => __( 'Unable to update this image.', 'medialytic' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for manual optimization.
	 *
	 * @return void
	 */
	public function ajax_optimize_attachment() {
		check_ajax_referer( 'medialytic_image_title_alt', 'nonce' );

		$attachment_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( __( 'Invalid attachment.', 'medialytic' ) );
		}

		$fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] )
			? array_map( 'sanitize_text_field', $_POST['fields'] )
			: $this->options['fields'];

		$capitalization = sanitize_text_field( $_POST['capitalization'] ?? $this->options['capitalization'] );

		$this->apply_to_attachment( $attachment_id, $fields, $capitalization );

		wp_send_json_success( __( 'Title and tags updated.', 'medialytic' ) );
	}
}

