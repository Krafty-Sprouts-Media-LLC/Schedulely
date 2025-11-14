<?php
/**
 * Filename: class-featured-image-manager.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.5.0
 * Last Modified: 14/11/2025
 * Description: Featured image utilities for admin thumbnails, RSS injection, and fallback handling.
 *
 * Credits:
 * - Concepts inspired by Smart Featured Image Manager (Krafty Sprouts Media, LLC)
 * - Admin column UX influenced by Featured Image Admin Thumb (Sean Hayes)
 * - Fallback behaviour informed by Default Featured Image (Jan Willem Oostendorp)
 * - Original WordPress Codex snippets for RSS thumbnails
 *
 * @package Medialytic
 * @since 1.4.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Featured image manager module for Medialytic.
 *
 * @since 1.4.0
 */
class Medialytic_Featured_Image_Manager {

	const OPTION_KEY = 'medialytic_featured_image_manager';

	/**
	 * Core instance.
	 *
	 * @var Medialytic_Core
	 */
	private $core;

	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Cached list of post types managed by the module.
	 *
	 * @var array
	 */
	private $managed_post_types = array();

	/**
	 * Constructor.
	 *
	 * @param Medialytic_Core $core Core instance.
	 */
	public function __construct( Medialytic_Core $core ) {
		$this->core = $core;
		$this->load_options();
		$this->register_hooks();
	}

	/**
	 * Handle activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, self::get_default_options() );
		}
	}

	/**
	 * Handle deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Nothing explicit yet; placeholder for future cron cleanups.
	}

	/**
	 * Handle uninstall.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type_columns' ) );
		add_action( 'save_post', array( $this, 'maybe_assign_fallback_featured_image' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'register_media_settings_bridge' ) );

		add_filter( 'the_content_feed', array( $this, 'inject_featured_image_into_feed' ), 10 );
		add_filter( 'the_excerpt_rss', array( $this, 'inject_featured_image_into_feed' ), 10 );
		add_filter( 'get_post_metadata', array( $this, 'supply_fallback_thumbnail_meta' ), 10, 4 );
		add_filter( 'post_thumbnail_html', array( $this, 'maybe_render_fallback_thumbnail_html' ), 10, 5 );
		add_action( 'wp_ajax_medialytic_set_featured_image', array( $this, 'handle_set_featured_image_request' ) );
		add_action( 'admin_head', array( $this, 'print_admin_column_styles' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Load stored options.
	 *
	 * @return void
	 */
	private function load_options() {
		$defaults     = self::get_default_options();
		$stored       = get_option( self::OPTION_KEY, $defaults );
		$this->options = wp_parse_args( $stored, $defaults );
	}

	/**
	 * Get default option values.
	 *
	 * @return array
	 */
	public static function get_default_options() {
		$post_types = array_keys( get_post_types_by_support( array( 'thumbnail' ) ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		return array(
			'enabled'            => true,
			'post_types'         => $post_types,
			'admin_column'       => true,
			'admin_column_size'  => 'thumbnail',
			'fallback_image_id'  => 0,
			'fallback_image_url' => '',
			'auto_assign'        => true,
			'feed_enabled'       => true,
			'feed_position'      => 'before_content',
			'feed_image_size'    => 'full',
			'feed_add_caption'   => false,
			'feed_caption_field' => 'title',
			'feed_force_https'   => true,
		);
	}

	/**
	 * Retrieve the post types managed by the module.
	 *
	 * @return array
	 */
	private function get_managed_post_types() {
		if ( empty( $this->managed_post_types ) ) {
			$post_types = isset( $this->options['post_types'] ) ? (array) $this->options['post_types'] : array();
			if ( empty( $post_types ) ) {
				$post_types = array_keys( get_post_types_by_support( array( 'thumbnail' ) ) );
			}

			$this->managed_post_types = apply_filters( 'medialytic_featured_image_post_types', $post_types );
		}

		return $this->managed_post_types;
	}

	/**
	 * Register admin settings page.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Medialytic Featured Images', 'medialytic' ),
			__( 'Featured Images', 'medialytic' ),
			'manage_options',
			'medialytic-featured-images',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings with WordPress.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_KEY,
			self::OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Bridge into Settings → Media for familiarity.
	 *
	 * @return void
	 */
	public function register_media_settings_bridge() {
		add_settings_field(
			'medialytic_fallback_featured_image',
			__( 'Medialytic fallback featured image', 'medialytic' ),
			array( $this, 'render_media_settings_bridge_field' ),
			'media',
			'default'
		);
	}

	/**
	 * Render the Media settings bridge field.
	 *
	 * @return void
	 */
	public function render_media_settings_bridge_field() {
		printf(
			'<p>%s</p><p><a class="button" href="%s">%s</a></p>',
			esc_html__( 'Configure the default featured image via the Medialytic Featured Image Manager.', 'medialytic' ),
			esc_url( admin_url( 'options-general.php?page=medialytic-featured-images' ) ),
			esc_html__( 'Open Featured Image Manager', 'medialytic' )
		);
	}

	/**
	 * Enqueue admin assets for the settings page.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_medialytic-featured-images' === $hook ) {
			wp_enqueue_media();
			wp_add_inline_script(
				'jquery',
				'
				jQuery( function ( $ ) {
					var frame;

					$( "#medialytic-select-fallback" ).on( "click", function ( e ) {
						e.preventDefault();

						if ( frame ) {
							frame.open();
							return;
						}

						frame = wp.media({
							title: "' . esc_js( __( 'Select fallback image', 'medialytic' ) ) . '",
							button: { text: "' . esc_js( __( 'Use this image', 'medialytic' ) ) . '" },
							multiple: false
						});

						frame.on( "select", function () {
							var attachment = frame.state().get( "selection" ).first().toJSON();
							$( "#fallback_image_id" ).val( attachment.id );
							$( "#fallback_image_url" ).val( attachment.url );
							$( "#medialytic-fallback-preview" ).attr( "src", attachment.sizes?.thumbnail ? attachment.sizes.thumbnail.url : attachment.url ).show();
						});

						frame.open();
					});

					$( "#medialytic-clear-fallback" ).on( "click", function ( e ) {
						e.preventDefault();
						$( "#fallback_image_id" ).val( "" );
						$( "#fallback_image_url" ).val( "" );
						$( "#medialytic-fallback-preview" ).hide();
					});
				} );
				'
			);
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		$post_type = $screen->post_type ?: 'post';
		if ( ! in_array( $post_type, $this->get_managed_post_types(), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'medialytic-featured-thumb-admin',
			MEDIALYTIC_PLUGIN_URL . 'assets/js/admin-featured-thumbs.js',
			array( 'jquery', 'wp-util', 'media-editor' ),
			MEDIALYTIC_VERSION,
			true
		);

		wp_localize_script(
			'medialytic-featured-thumb-admin',
			'medialyticFeaturedThumbs',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'l10n'    => array(
					'title'      => __( 'Select featured image', 'medialytic' ),
					'button'     => __( 'Use featured image', 'medialytic' ),
					'set'        => __( 'Set image', 'medialytic' ),
					'change'     => __( 'Change image', 'medialytic' ),
					'remove'     => __( 'Remove this featured image?', 'medialytic' ),
					'updating'   => __( 'Updating…', 'medialytic' ),
					'error'      => __( 'Unable to update the featured image. Please try again.', 'medialytic' ),
				),
			)
		);
	}

	/**
	 * Render settings page markup.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$this->load_options();
		$options     = $this->options;
		$post_types  = get_post_types( array( 'public' => true ), 'objects' );
		$image_sizes = $this->get_available_image_sizes();
		$fallback_id = (int) $options['fallback_image_id'];
		$fallback_url = $options['fallback_image_url'];

		if ( $fallback_id > 0 ) {
			$thumb = wp_get_attachment_image_src( $fallback_id, 'thumbnail' );
			if ( $thumb ) {
				$fallback_url = $thumb[0];
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Medialytic Featured Image Manager', 'medialytic' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( self::OPTION_KEY );
				?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable module', 'medialytic' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $options['enabled'] ); ?> />
									<?php esc_html_e( 'Turn on featured image management', 'medialytic' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Post types', 'medialytic' ); ?></th>
							<td>
								<?php foreach ( $post_types as $post_type ) : ?>
									<label style="display:block;">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $options['post_types'], true ) ); ?> />
										<?php echo esc_html( $post_type->labels->name ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Admin thumbnail column', 'medialytic' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_column]" value="1" <?php checked( $options['admin_column'] ); ?> />
									<?php esc_html_e( 'Show featured image previews in list tables', 'medialytic' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Fallback image', 'medialytic' ); ?></th>
							<td>
								<div style="margin-bottom:10px;">
									<?php if ( $fallback_url ) : ?>
										<img id="medialytic-fallback-preview" src="<?php echo esc_url( $fallback_url ); ?>" style="max-width:120px;height:auto;" alt="" />
									<?php else : ?>
										<img id="medialytic-fallback-preview" src="" style="max-width:120px;height:auto;display:none;" alt="" />
									<?php endif; ?>
								</div>
								<input type="hidden" id="fallback_image_id" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fallback_image_id]" value="<?php echo esc_attr( $options['fallback_image_id'] ); ?>" />
								<input type="text" id="fallback_image_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fallback_image_url]" value="<?php echo esc_attr( $options['fallback_image_url'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'https://example.com/image.jpg', 'medialytic' ); ?>" />
								<p class="description">
									<button class="button" id="medialytic-select-fallback"><?php esc_html_e( 'Select image', 'medialytic' ); ?></button>
									<button class="button button-link-delete" id="medialytic-clear-fallback"><?php esc_html_e( 'Clear', 'medialytic' ); ?></button>
								</p>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_assign]" value="1" <?php checked( $options['auto_assign'] ); ?> />
									<?php esc_html_e( 'Automatically set fallback image as featured image when none is provided', 'medialytic' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'RSS feed output', 'medialytic' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[feed_enabled]" value="1" <?php checked( $options['feed_enabled'] ); ?> />
									<?php esc_html_e( 'Include featured images in RSS feeds', 'medialytic' ); ?>
								</label>
								<p>
									<label>
										<?php esc_html_e( 'Image size', 'medialytic' ); ?>
										<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[feed_image_size]">
											<?php foreach ( $image_sizes as $size => $label ) : ?>
												<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $options['feed_image_size'], $size ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>
								</p>
								<p>
									<label>
										<?php esc_html_e( 'Position', 'medialytic' ); ?>
										<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[feed_position]">
											<option value="before_content" <?php selected( $options['feed_position'], 'before_content' ); ?>><?php esc_html_e( 'Before content', 'medialytic' ); ?></option>
											<option value="after_content" <?php selected( $options['feed_position'], 'after_content' ); ?>><?php esc_html_e( 'After content', 'medialytic' ); ?></option>
											<option value="replace_content" <?php selected( $options['feed_position'], 'replace_content' ); ?>><?php esc_html_e( 'Replace content', 'medialytic' ); ?></option>
										</select>
									</label>
								</p>
								<p>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[feed_add_caption]" value="1" <?php checked( $options['feed_add_caption'] ); ?> />
										<?php esc_html_e( 'Add caption below image', 'medialytic' ); ?>
									</label>
									<label style="margin-left:10px;">
										<?php esc_html_e( 'Caption source', 'medialytic' ); ?>
										<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[feed_caption_field]">
											<option value="title" <?php selected( $options['feed_caption_field'], 'title' ); ?>><?php esc_html_e( 'Image title', 'medialytic' ); ?></option>
											<option value="alt" <?php selected( $options['feed_caption_field'], 'alt' ); ?>><?php esc_html_e( 'Alt text', 'medialytic' ); ?></option>
											<option value="caption" <?php selected( $options['feed_caption_field'], 'caption' ); ?>><?php esc_html_e( 'Caption', 'medialytic' ); ?></option>
											<option value="description" <?php selected( $options['feed_caption_field'], 'description' ); ?>><?php esc_html_e( 'Description', 'medialytic' ); ?></option>
										</select>
									</label>
								</p>
								<p>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[feed_force_https]" value="1" <?php checked( $options['feed_force_https'] ); ?> />
										<?php esc_html_e( 'Force HTTPS URLs inside feeds', 'medialytic' ); ?>
									</label>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize settings prior to saving.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults      = self::get_default_options();
		$sanitized     = array();
		$boolean_keys  = array(
			'enabled',
			'admin_column',
			'auto_assign',
			'feed_enabled',
			'feed_add_caption',
			'feed_force_https',
		);

		foreach ( $boolean_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		$sanitized['post_types']        = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? array_map( 'sanitize_text_field', $input['post_types'] ) : $defaults['post_types'];
		$sanitized['admin_column_size'] = isset( $input['admin_column_size'] ) ? sanitize_text_field( $input['admin_column_size'] ) : $defaults['admin_column_size'];

		$sanitized['fallback_image_id']  = isset( $input['fallback_image_id'] ) ? absint( $input['fallback_image_id'] ) : 0;
		$sanitized['fallback_image_url'] = isset( $input['fallback_image_url'] ) ? esc_url_raw( $input['fallback_image_url'] ) : '';

		$sanitized['feed_position']      = isset( $input['feed_position'] ) ? sanitize_text_field( $input['feed_position'] ) : $defaults['feed_position'];
		$sanitized['feed_image_size']    = isset( $input['feed_image_size'] ) ? sanitize_text_field( $input['feed_image_size'] ) : $defaults['feed_image_size'];
		$sanitized['feed_caption_field'] = isset( $input['feed_caption_field'] ) ? sanitize_text_field( $input['feed_caption_field'] ) : $defaults['feed_caption_field'];

		$this->options = wp_parse_args( $sanitized, $defaults );

		return $this->options;
	}

	/**
	 * Register admin columns across enabled post types.
	 *
	 * @return void
	 */
	public function register_post_type_columns() {
		if ( empty( $this->options['enabled'] ) || empty( $this->options['admin_column'] ) ) {
			return;
		}

		$post_types              = $this->get_managed_post_types();
		$this->managed_post_types = $post_types;

		foreach ( $post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_thumbnail_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_thumbnail_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'register_sortable_column' ) );
		}

		add_action( 'pre_get_posts', array( $this, 'handle_admin_sorting' ) );
	}

	/**
	 * Add thumbnail column definition.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_thumbnail_column( $columns ) {
		$insertion = array( 'medialytic_featured_image' => __( 'Featured Image', 'medialytic' ) );

		return $this->array_insert_after( $columns, 'title', $insertion );
	}

	/**
	 * Register sortable column.
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array
	 */
	public function register_sortable_column( $columns ) {
		$columns['medialytic_featured_image'] = 'medialytic_featured_image';
		return $columns;
	}

	/**
	 * Adjust query when sorting by featured image column.
	 *
	 * @param WP_Query $query Query instance.
	 * @return void
	 */
	public function handle_admin_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'medialytic_featured_image' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', '_thumbnail_id' );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Render the thumbnail column.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_thumbnail_column( $column, $post_id ) {
		if ( 'medialytic_featured_image' !== $column ) {
			return;
		}

		echo $this->get_thumbnail_column_markup( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Insert array after specific key.
	 *
	 * @param array  $array Array to modify.
	 * @param string $key Key to insert after.
	 * @param array  $to_insert Data to insert.
	 * @return array
	 */
	private function array_insert_after( $array, $key, $to_insert ) {
		$result = array();

		foreach ( $array as $array_key => $array_value ) {
			$result[ $array_key ] = $array_value;
			if ( $array_key === $key ) {
				foreach ( $to_insert as $insert_key => $insert_value ) {
					$result[ $insert_key ] = $insert_value;
				}
			}
		}

		return $result;
	}

	/**
	 * Build the thumbnail column markup.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_thumbnail_column_markup( $post_id ) {
		$size          = $this->options['admin_column_size'] ?: 'thumbnail';
		$has_thumbnail = has_post_thumbnail( $post_id );
		$nonce         = wp_create_nonce( 'medialytic_set_featured_image_' . $post_id );
		$preview       = '';

		if ( $has_thumbnail ) {
			$preview = wp_get_attachment_image( get_post_thumbnail_id( $post_id ), $size );
		} else {
			$fallback_preview = $this->get_image_for_post( $post_id, $size );
			if ( $fallback_preview ) {
				$preview = sprintf(
					'<img src="%s" alt="" style="max-width:80px;height:auto;" />',
					esc_url( $fallback_preview['url'] )
				);
			}
		}

		if ( empty( $preview ) ) {
			$preview = '<span class="medialytic-thumb-placeholder">&mdash;</span>';
		}

		ob_start();
		?>
		<div class="medialytic-thumb-cell" data-medialytic-thumb="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<div class="medialytic-thumb-preview">
				<?php echo wp_kses_post( $preview ); ?>
			</div>
			<div class="medialytic-thumb-actions">
				<button type="button" class="button-link medialytic-featured-image-toggle" data-post-id="<?php echo esc_attr( $post_id ); ?>">
					<?php echo esc_html( $has_thumbnail ? __( 'Change', 'medialytic' ) : __( 'Set image', 'medialytic' ) ); ?>
				</button>
				<?php if ( $has_thumbnail ) : ?>
					<span class="text-muted">|</span>
					<button type="button" class="button-link medialytic-featured-image-remove" data-post-id="<?php echo esc_attr( $post_id ); ?>">
						<?php esc_html_e( 'Remove', 'medialytic' ); ?>
					</button>
				<?php endif; ?>
			</div>
			<span class="spinner"></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle AJAX featured image assignments from the list table.
	 *
	 * @return void
	 */
	public function handle_set_featured_image_request() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Missing post ID.', 'medialytic' ) );
		}

		check_ajax_referer( 'medialytic_set_featured_image_' . $post_id, 'nonce' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'You do not have permission to edit this post.', 'medialytic' ), 403 );
		}

		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
		} else {
			delete_post_thumbnail( $post_id );
		}

		wp_send_json_success(
			array(
				'html' => $this->get_thumbnail_column_markup( $post_id ),
			)
		);
	}

	/**
	 * Output lightweight styles for the admin column UI.
	 *
	 * @return void
	 */
	public function print_admin_column_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		if ( ! in_array( $screen->post_type, $this->get_managed_post_types(), true ) ) {
			return;
		}

		?>
		<style id="medialytic-featured-image-column">
			.column-medialytic_featured_image {
				width: 120px;
			}
			.medialytic-thumb-cell {
				display: flex;
				flex-direction: column;
				gap: 4px;
				align-items: flex-start;
			}
			.medialytic-thumb-preview img {
				display: block;
				max-width: 100%;
				height: auto;
				border-radius: 4px;
				box-shadow: 0 1px 2px rgba(0,0,0,0.08);
			}
			.medialytic-thumb-actions {
				display: flex;
				gap: 4px;
				align-items: center;
				flex-wrap: wrap;
			}
			.medialytic-thumb-cell .spinner {
				float: none;
				margin-top: 4px;
			}
			.medialytic-thumb-placeholder {
				color: #757575;
			}
		</style>
		<?php
	}

	/**
	 * Maybe assign fallback featured image on save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function maybe_assign_fallback_featured_image( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $this->options['enabled'] || ! $this->options['auto_assign'] ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->options['post_types'], true ) ) {
			return;
		}

		if ( get_post_thumbnail_id( $post_id ) ) {
			return;
		}

		if ( empty( $this->options['fallback_image_id'] ) ) {
			return;
		}

		set_post_thumbnail( $post_id, (int) $this->options['fallback_image_id'] );
	}

	/**
	 * Supply fallback thumbnail meta when requested.
	 *
	 * @param mixed  $value    Existing metadata value.
	 * @param int    $object_id Object ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $single   Whether a single value was requested.
	 * @return mixed
	 */
	public function supply_fallback_thumbnail_meta( $value, $object_id, $meta_key, $single ) {
		if ( '_thumbnail_id' !== $meta_key && ! empty( $meta_key ) ) {
			return $value;
		}

		if ( ! empty( $value ) ) {
			return $value;
		}

		if ( empty( $this->options['enabled'] ) || empty( $this->options['fallback_image_id'] ) ) {
			return $value;
		}

		$post_type = get_post_type( $object_id );
		if ( ! $post_type || ! in_array( $post_type, $this->get_managed_post_types(), true ) ) {
			return $value;
		}

		$fallback_id = (int) $this->options['fallback_image_id'];
		if ( ! $fallback_id ) {
			return $value;
		}

		$resolved = apply_filters( 'medialytic_featured_image_fallback_id', $fallback_id, $object_id );

		if ( $single ) {
			return $resolved;
		}

		return array( $resolved );
	}

	/**
	 * Ensure fallback HTML renders when needed.
	 *
	 * @param string       $html Existing HTML.
	 * @param int          $post_id Post ID.
	 * @param int          $post_thumbnail_id Thumbnail ID.
	 * @param string|array $size Size.
	 * @param array|string $attr Attributes.
	 * @return string
	 */
	public function maybe_render_fallback_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		if ( $html ) {
			return $html;
		}

		$image = $this->get_image_for_post( $post_id, $size );
		if ( ! $image ) {
			return $html;
		}

		if ( ! empty( $image['id'] ) ) {
			return wp_get_attachment_image( $image['id'], $size, false, $attr );
		}

		$attrs = '';
		if ( is_array( $attr ) ) {
			foreach ( $attr as $attr_key => $attr_value ) {
				$attrs .= sprintf( ' %s="%s"', esc_attr( $attr_key ), esc_attr( $attr_value ) );
			}
		}

		return sprintf(
			'<img src="%s" alt="%s"%s />',
			esc_url( $image['url'] ),
			esc_attr( $image['alt'] ?? '' ),
			$attrs
		);
	}

	/**
	 * Inject featured image into RSS feed content/excerpt.
	 *
	 * @param string $content Feed content.
	 * @return string
	 */
	public function inject_featured_image_into_feed( $content ) {
		if ( empty( $this->options['enabled'] ) || empty( $this->options['feed_enabled'] ) ) {
			return $content;
		}

		global $post;

		if ( ! $post || ! in_array( $post->post_type, $this->options['post_types'], true ) ) {
			return $content;
		}

		$image_data = $this->get_image_for_post( $post->ID, $this->options['feed_image_size'] ?: 'full' );

		if ( ! $image_data ) {
			return $content;
		}

		$image_html = $this->build_feed_image_html( $image_data );

		switch ( $this->options['feed_position'] ) {
			case 'after_content':
				return $content . $image_html;
			case 'replace_content':
				return $image_html;
			case 'before_content':
			default:
				return $image_html . $content;
		}
	}

	/**
	 * Build feed-ready image HTML.
	 *
	 * @param array $image_data Image data array.
	 * @return string
	 */
	private function build_feed_image_html( $image_data ) {
		$url    = $this->options['feed_force_https'] ? $this->force_https( $image_data['url'] ) : $image_data['url'];
		$width  = (int) $image_data['width'];
		$height = (int) $image_data['height'];
		$alt    = esc_attr( $image_data['alt'] );

		$html = sprintf(
			'<img src="%s" alt="%s" width="%d" height="%d" style="max-width:100%%;height:auto;" />',
			esc_url( $url ),
			$alt,
			$width,
			$height
		);

		if ( $this->options['feed_add_caption'] ) {
			$caption = $this->get_caption_from_image( $image_data );
			if ( $caption ) {
				$html = sprintf(
					'<figure>%s<figcaption>%s</figcaption></figure>',
					$html,
					esc_html( $caption )
				);
			}
		}

		return $html . "\n\n";
	}

	/**
	 * Retrieve caption text from image data.
	 *
	 * @param array $image_data Image data.
	 * @return string
	 */
	private function get_caption_from_image( $image_data ) {
		switch ( $this->options['feed_caption_field'] ) {
			case 'alt':
				return $image_data['alt'] ?? '';
			case 'caption':
				return $image_data['caption'] ?? '';
			case 'description':
				return $image_data['description'] ?? '';
			case 'title':
			default:
				return $image_data['title'] ?? '';
		}
	}

	/**
	 * Retrieve featured image or fallback data.
	 *
	 * @param int $post_id Post ID.
	 * @return array|false
	 */
	private function get_image_for_post( $post_id, $size = null ) {
		if ( null === $size ) {
			$size = $this->options['feed_image_size'] ?: 'full';
		}

		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			$data = $this->build_image_data_from_attachment( $thumbnail_id, $size );
			if ( $data ) {
				return $data;
			}
		}

		if ( $this->options['fallback_image_id'] ) {
			return $this->build_image_data_from_attachment( (int) $this->options['fallback_image_id'], $size );
		}

		if ( $this->options['fallback_image_url'] ) {
			return array(
				'id'          => 0,
				'url'         => $this->force_https( $this->options['fallback_image_url'] ),
				'width'       => 0,
				'height'      => 0,
				'alt'         => '',
				'title'       => '',
				'caption'     => '',
				'description' => '',
			);
		}

		return false;
	}

	/**
	 * Build image data array from attachment ID.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size Image size.
	 * @return array|false
	 */
	private function build_image_data_from_attachment( $attachment_id, $size ) {
		$image = wp_get_attachment_image_src( $attachment_id, $size );

		if ( ! $image ) {
			return false;
		}

		$attachment = get_post( $attachment_id );

		return array(
			'id'          => $attachment_id,
			'url'         => $image[0],
			'width'       => $image[1],
			'height'      => $image[2],
			'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'title'       => $attachment ? $attachment->post_title : '',
			'caption'     => $attachment ? $attachment->post_excerpt : '',
			'description' => $attachment ? $attachment->post_content : '',
		);
	}

	/**
	 * Force HTTPS for URLs.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function force_https( $url ) {
		if ( empty( $url ) ) {
			return $url;
		}

		return str_replace( 'http://', 'https://', $url );
	}

	/**
	 * Retrieve available image sizes keyed by slug.
	 *
	 * @return array
	 */
	private function get_available_image_sizes() {
		$sizes  = array();
		$labels = array(
			'thumbnail'    => __( 'Thumbnail', 'medialytic' ),
			'medium'       => __( 'Medium', 'medialytic' ),
			'medium_large' => __( 'Medium Large', 'medialytic' ),
			'large'        => __( 'Large', 'medialytic' ),
			'full'         => __( 'Full Size', 'medialytic' ),
		);

		foreach ( get_intermediate_image_sizes() as $size ) {
			$sizes[ $size ] = $labels[ $size ] ?? ucwords( str_replace( '_', ' ', $size ) );
		}

		return $sizes;
	}
}

