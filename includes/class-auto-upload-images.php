<?php
/**
 * Filename: class-auto-upload-images.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.6.0
 * Last Modified: 14/11/2025
 * Description: Automatically imports external images found in post content.
 *
 * Credits:
 * - Derived from the GPLv2+ Auto Upload Images plugin by Ali Irani (https://github.com/airani/wp-auto-upload).
 *   Completely refactored, modernised, and integrated into Medialytic with configuration options preserved.
 *
 * @package Medialytic
 * @since 1.6.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto Upload Images module for Medialytic.
 *
 * @since 1.6.0
 */
class Medialytic_Auto_Upload_Images {

	/**
	 * Option key.
	 */
	const OPTION_KEY = 'medialytic_auto_upload_images';

	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options = $this->get_options();
		$this->register_hooks();
	}

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	private function get_default_options() {
		return array(
			'enabled'           => true,
			'base_url'          => home_url(),
			'image_name'        => '%filename%',
			'alt_name'          => '%image_alt%',
			'max_width'         => '',
			'max_height'        => '',
			'exclude_domains'   => '',
			'exclude_posttypes' => array(),
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
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_submission' ) );
	}

	/**
	 * Whether the module is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		return ! empty( $this->options['enabled'] );
	}

	/**
	 * Filter post data and import images if needed.
	 *
	 * @param array $data    Sanitized post data.
	 * @param array $postarr Raw post array.
	 * @return array
	 */
	public function filter_post_data( $data, $postarr ) {
		if ( ! $this->is_enabled() ) {
			return $data;
		}

		if ( $this->should_skip_post( $postarr ) ) {
			return $data;
		}

		$processed = $this->process_content_images( $postarr );
		if ( $processed ) {
			$data['post_content'] = $processed;
		}

		return $data;
	}

	/**
	 * Determine if the post should be skipped.
	 *
	 * @param array $postarr Post data.
	 * @return bool
	 */
	private function should_skip_post( $postarr ) {
		if ( empty( $postarr['post_content'] ) ) {
		 return true;
		}

		if ( wp_is_post_revision( $postarr['ID'] ?? 0 ) || wp_is_post_autosave( $postarr['ID'] ?? 0 ) ) {
			return true;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return true;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return true;
		}

		$excluded = $this->options['exclude_posttypes'] ?? array();
		if ( is_array( $excluded ) && ! empty( $postarr['post_type'] ) && in_array( $postarr['post_type'], $excluded, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Process the post content and import images.
	 *
	 * @param array $postarr Post array.
	 * @return string|false
	 */
	private function process_content_images( $postarr ) {
		$content = isset( $postarr['post_content'] ) ? $postarr['post_content'] : '';
		if ( empty( $content ) ) {
			return false;
		}

		$images = $this->find_image_candidates( wp_unslash( $content ) );
		if ( empty( $images ) ) {
			return false;
		}

		$updated = $content;
		foreach ( $images as $image ) {
			$handler = new Medialytic_Auto_Upload_Image_Handler(
				$image['url'],
				$image['alt'],
				$postarr,
				$this->options
			);

			$result = $handler->process();
			if ( empty( $result['url'] ) ) {
				continue;
			}

			$replacement_url = $this->build_final_image_url( $result['url'] );
			$updated         = preg_replace(
				'/' . preg_quote( $image['url'], '/' ) . '/',
				$replacement_url,
				$updated
			);

			if ( ! empty( $image['alt'] ) && ! empty( $result['alt'] ) ) {
				$updated = preg_replace(
					'/alt=["\']' . preg_quote( $image['alt'], '/' ) . '["\']/',
					'alt="' . esc_attr( $result['alt'] ) . '"',
					$updated,
					1
				);
			}
		}

		return $updated;
	}

	/**
	 * Build the final image URL, honouring base URL overrides.
	 *
	 * @param string $uploaded_url Uploaded URL.
	 * @return string
	 */
	private function build_final_image_url( $uploaded_url ) {
		$base_url = trim( $this->options['base_url'] );
		if ( empty( $base_url ) ) {
			return $uploaded_url;
		}

		$path = wp_parse_url( $uploaded_url, PHP_URL_PATH );
		if ( empty( $path ) ) {
			return $uploaded_url;
		}

		$base = trailingslashit( $base_url );

		return untrailingslashit( $base ) . $path;
	}

	/**
	 * Find image URLs and alt attributes within content.
	 *
	 * @param string $content Post content.
	 * @return array
	 */
	private function find_image_candidates( $content ) {
		$matches = array();

		preg_match_all( '/<img[^>]+>/i', $content, $nodes );
		if ( empty( $nodes[0] ) ) {
			return array();
		}

		foreach ( $nodes[0] as $node ) {
			$src = $this->extract_attribute( $node, 'src' );
			if ( empty( $src ) ) {
				continue;
			}

			$matches[] = array(
				'url' => esc_url_raw( $src ),
				'alt' => $this->extract_attribute( $node, 'alt' ),
			);

			$srcset = $this->extract_attribute( $node, 'srcset' );
			if ( $srcset ) {
				$srcset_urls = preg_split( '/\s*,\s*/', $srcset );
				foreach ( $srcset_urls as $srcset_entry ) {
					$srcset_parts = preg_split( '/\s+/', trim( $srcset_entry ) );
					if ( ! empty( $srcset_parts[0] ) ) {
						$matches[] = array(
							'url' => esc_url_raw( $srcset_parts[0] ),
							'alt' => $this->extract_attribute( $node, 'alt' ),
						);
					}
				}
			}
		}

		return $matches;
	}

	/**
	 * Extract a single attribute from an HTML string.
	 *
	 * @param string $html HTML snippet.
	 * @param string $attribute Attribute name.
	 * @return string|null
	 */
	private function extract_attribute( $html, $attribute ) {
		$pattern = sprintf( '/%s=["\']([^"\']+)["\']/i', preg_quote( $attribute, '/' ) );
		if ( preg_match( $pattern, $html, $matches ) ) {
			return html_entity_decode( $matches[1], ENT_QUOTES );
		}

		return null;
	}

	/**
	 * Register the options page.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Medialytic Auto Upload Images', 'medialytic' ),
			__( 'Auto Upload Images', 'medialytic' ),
			'manage_options',
			'medialytic-auto-upload-images',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Handle form submission.
	 *
	 * @return void
	 */
	public function handle_settings_submission() {
		if ( empty( $_POST['medialytic_auto_upload_images_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( wp_unslash( $_POST['medialytic_auto_upload_images_nonce'] ), 'medialytic_auto_upload_images' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$raw = wp_unslash( $_POST[ self::OPTION_KEY ] ?? array() );
		$this->options = $this->sanitize_settings( $raw );
		update_option( self::OPTION_KEY, $this->options );

		add_settings_error(
			'medialytic_auto_upload_images',
			'medialytic_auto_upload_images',
			esc_html__( 'Auto Upload Images settings saved.', 'medialytic' ),
			'updated'
		);
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param array $input Raw values.
	 * @return array
	 */
	private function sanitize_settings( $input ) {
		$defaults = $this->get_default_options();

		$sanitized = array(
			'enabled'           => ! empty( $input['enabled'] ),
			'base_url'          => esc_url_raw( $input['base_url'] ?? $defaults['base_url'] ),
			'image_name'        => sanitize_text_field( $input['image_name'] ?? $defaults['image_name'] ),
			'alt_name'          => sanitize_text_field( $input['alt_name'] ?? $defaults['alt_name'] ),
			'max_width'         => $input['max_width'] ? absint( $input['max_width'] ) : '',
			'max_height'        => $input['max_height'] ? absint( $input['max_height'] ) : '',
			'exclude_domains'   => sanitize_textarea_field( $input['exclude_domains'] ?? '' ),
			'exclude_posttypes' => array(),
		);

		if ( ! empty( $input['exclude_posttypes'] ) && is_array( $input['exclude_posttypes'] ) ) {
			$sanitized['exclude_posttypes'] = array_map( 'sanitize_text_field', $input['exclude_posttypes'] );
		}

		return $sanitized;
	}

	/**
	 * Render the options page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$all_post_types = get_post_types( array( 'public' => true ), 'objects' );

		settings_errors( 'medialytic_auto_upload_images', true );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Medialytic Auto Upload Images', 'medialytic' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Automatically detect external images in post content, import them to the Media Library, and update URLs. This module modernises the original Auto Upload Images plugin by Ali Irani.', 'medialytic' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'medialytic_auto_upload_images', 'medialytic_auto_upload_images_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable auto-upload', 'medialytic' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $this->options['enabled'] ); ?> />
								<?php esc_html_e( 'Automatically import remote images when saving posts.', 'medialytic' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Base URL override', 'medialytic' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[base_url]" value="<?php echo esc_attr( $this->options['base_url'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional host to use for newly uploaded images (e.g., CDN URL). Leave empty to use the default uploads URL.', 'medialytic' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Filename template', 'medialytic' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[image_name]" value="<?php echo esc_attr( $this->options['image_name'] ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Placeholders: %filename%, %image_alt%, %url%, %today_date%, %today_day%, %post_date%, %post_year%, %post_month%, %post_day%, %random%, %timestamp%, %postname%, %post_id%.', 'medialytic' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Alt text template', 'medialytic' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[alt_name]" value="<?php echo esc_attr( $this->options['alt_name'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Resize images', 'medialytic' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'Max width', 'medialytic' ); ?>
								<input type="number" class="small-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_width]" value="<?php echo esc_attr( $this->options['max_width'] ); ?>" />
							</label>
							<label style="margin-left: 15px;">
								<?php esc_html_e( 'Max height', 'medialytic' ); ?>
								<input type="number" class="small-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_height]" value="<?php echo esc_attr( $this->options['max_height'] ); ?>" />
							</label>
							<p class="description"><?php esc_html_e( 'Leave blank to keep original dimensions.', 'medialytic' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Exclude domains', 'medialytic' ); ?></th>
						<td>
							<textarea class="large-text code" rows="5" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[exclude_domains]"><?php echo esc_textarea( $this->options['exclude_domains'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One domain per line. Matching domains will be skipped and left untouched.', 'medialytic' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Exclude post types', 'medialytic' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $all_post_types as $post_type ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[exclude_posttypes][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $this->options['exclude_posttypes'], true ) ); ?> />
										<?php echo esc_html( $post_type->labels->singular_name ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Changes', 'medialytic' ) ); ?>
			</form>
		</div>
		<?php
	}
}

