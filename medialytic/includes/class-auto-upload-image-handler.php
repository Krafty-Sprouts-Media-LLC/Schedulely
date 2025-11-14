<?php
/**
 * Filename: class-auto-upload-image-handler.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.6.0
 * Last Modified: 14/11/2025
 * Description: Handles downloading, naming, resizing, and attaching remote images.
 *
 * Credits:
 * - Based on the original Auto Upload Images plugin by Ali Irani (https://github.com/airani/wp-auto-upload).
 *   Reimplemented and modernised for Medialytic with permission under GPLv2+.
 *
 * @package Medialytic
 * @since 1.6.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsulates the remote download and attachment workflow.
 *
 * @since 1.6.0
 */
class Medialytic_Auto_Upload_Image_Handler {

	/**
	 * Raw URL in the post content.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Original alt attribute captured from the markup.
	 *
	 * @var string|null
	 */
	private $alt;

	/**
	 * Post context.
	 *
	 * @var array
	 */
	private $post;

	/**
	 * Module options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param string $url     Image URL.
	 * @param string $alt     Image alt text.
	 * @param array  $post    Post array.
	 * @param array  $options Module options.
	 */
	public function __construct( $url, $alt, array $post, array $options ) {
		$this->url     = $url;
		$this->alt     = $alt;
		$this->post    = $post;
		$this->options = $options;
	}

	/**
	 * Download, store, and attach the remote image.
	 *
	 * @return array|null
	 */
	public function process() {
		if ( ! $this->is_download_allowed() ) {
			return null;
		}

		$remote = $this->download_remote_image();
		if ( is_wp_error( $remote ) ) {
			return null;
		}

		$upload = $this->store_file( $remote );
		if ( is_wp_error( $upload ) || empty( $upload['path'] ) ) {
			return null;
		}

		$attachment_id = $this->attach_to_media_library( $upload['path'], $upload['url'] );

		if ( $attachment_id && $this->should_resize() ) {
			$resized = $this->resize_image( $upload );
			if ( $resized && ! is_wp_error( $resized ) ) {
				$upload = $resized;
				$attachment_id = $this->attach_to_media_library( $upload['path'], $upload['url'] );
			}
		}

		return array(
			'url'           => $upload['url'],
			'path'          => $upload['path'],
			'attachment_id' => $attachment_id,
			'alt'           => $this->get_alt_text(),
		);
	}

	/**
	 * Ensure the remote host is allowed.
	 *
	 * @return bool
	 */
	private function is_download_allowed() {
		$target_host = $this->normalize_host( $this->url );
		$site_host   = $this->normalize_host( home_url() );

		if ( ! $target_host || $target_host === $site_host ) {
			return false;
		}

		if ( empty( $this->options['exclude_domains'] ) ) {
			return true;
		}

		$domains = array_filter(
			array_map(
				'trim',
				preg_split( "/\r\n|\r|\n/", $this->options['exclude_domains'] )
			)
		);

		foreach ( $domains as $domain ) {
			if ( $target_host === $this->normalize_host( $domain ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Download the remote image.
	 *
	 * @return array|WP_Error
	 */
	private function download_remote_image() {
		$url  = $this->normalize_url( $this->url );
		$args = array(
			'headers' => array(
				'Accept' => 'image/*',
			),
			'timeout' => 30,
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'medialytic_auto_upload_http_error', esc_html__( 'Remote server returned an error.', 'medialytic' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'medialytic_auto_upload_empty', esc_html__( 'Remote response was empty.', 'medialytic' ) );
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = wp_tempnam();
		if ( ! $tmp ) {
			return new WP_Error( 'medialytic_auto_upload_tmp', esc_html__( 'Unable to create temporary file.', 'medialytic' ) );
		}

		file_put_contents( $tmp, $body );
		$mime = wp_get_image_mime( $tmp );
		if ( ! $mime || 0 !== strpos( $mime, 'image/' ) ) {
			@unlink( $tmp );
			return new WP_Error( 'medialytic_auto_upload_mime', esc_html__( 'Downloaded file is not a valid image.', 'medialytic' ) );
		}

		return array(
			'body' => $body,
			'mime' => $mime,
			'tmp'  => $tmp,
		);
	}

	/**
	 * Store the downloaded file in the uploads directory.
	 *
	 * @param array $remote Remote payload.
	 * @return array|WP_Error
	 */
	private function store_file( array $remote ) {
		$subdir  = $this->get_upload_subdir();
		$uploads = wp_upload_dir( $subdir );

		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'medialytic_auto_upload_dir', $uploads['error'] );
		}

		wp_mkdir_p( $uploads['path'] );

		$ext      = $this->extension_from_mime( $remote['mime'] );
		$filename = $this->generate_filename() . ( $ext ? '.' . $ext : '' );
		$path             = trailingslashit( $uploads['path'] ) . $filename;
		$url              = trailingslashit( $uploads['url'] ) . $filename;
		$counter          = 1;
		$already_existing = false;

		while ( file_exists( $path ) ) {
			if ( sha1_file( $path ) === sha1( $remote['body'] ) ) {
				$already_existing = true;
				break;
			}

			$path = trailingslashit( $uploads['path'] ) . $counter . '_' . $filename;
			$url  = trailingslashit( $uploads['url'] ) . $counter . '_' . $filename;
			$counter++;
		}

		if ( ! $already_existing ) {
			file_put_contents( $path, $remote['body'] );
			if ( ! file_exists( $path ) ) {
				@unlink( $remote['tmp'] );
				return new WP_Error( 'medialytic_auto_upload_write', esc_html__( 'Failed to save the downloaded image.', 'medialytic' ) );
			}
		}

		@unlink( $remote['tmp'] );

		return array(
			'path' => $path,
			'url'  => $url,
		);
	}

	/**
	 * Attach the file to the current post.
	 *
	 * @param string $path File path.
	 * @param string $url  File URL.
	 * @return int|false
	 */
	private function attach_to_media_library( $path, $url ) {
		$filetype = wp_check_filetype( $path );

		$attachment = array(
			'guid'           => $url,
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( $this->get_alt_text() ?: basename( $path, '.' . $filetype['ext'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $path, $this->post['ID'] ?? 0 );
		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Resize the image when configured.
	 *
	 * @param array $upload File info.
	 * @return array|false|WP_Error
	 */
	private function resize_image( array $upload ) {
		$width  = absint( $this->options['max_width'] );
		$height = absint( $this->options['max_height'] );

		if ( ! $width && ! $height ) {
			return $upload;
		}

		$resized = image_make_intermediate_size( $upload['path'], $width ?: null, $height ?: null );
		if ( ! $resized ) {
			return $upload;
		}

		$new_path = path_join( dirname( $upload['path'] ), $resized['file'] );
		$new_url  = path_join( dirname( $upload['url'] ), $resized['file'] );

		return array(
			'path' => $new_path,
			'url'  => $new_url,
		);
	}

	/**
	 * Whether resizing is required.
	 *
	 * @return bool
	 */
	private function should_resize() {
		return ! empty( $this->options['max_width'] ) || ! empty( $this->options['max_height'] );
	}

	/**
	 * Generate filename based on the user-defined template.
	 *
	 * @return string
	 */
	private function generate_filename() {
		$template = ! empty( $this->options['image_name'] ) ? $this->options['image_name'] : '%filename%';

		return sanitize_file_name( $this->resolve_patterns( $template ) ?: uniqid( 'img_', false ) );
	}

	/**
	 * Build the alt text from template.
	 *
	 * @return string
	 */
	private function get_alt_text() {
		$template = ! empty( $this->options['alt_name'] ) ? $this->options['alt_name'] : '%image_alt%';

		return sanitize_text_field( $this->resolve_patterns( $template ) );
	}

	/**
	 * Replace placeholders in user templates.
	 *
	 * @param string $pattern Pattern.
	 * @return string
	 */
	private function resolve_patterns( $pattern ) {
		$original_filename = $this->get_original_filename();
		$post_date_gmt     = isset( $this->post['post_date_gmt'] ) ? $this->post['post_date_gmt'] : current_time( 'mysql', true );
		$post_date         = strtotime( $post_date_gmt );

		$map = array(
			'%filename%'    => $original_filename,
			'%image_alt%'   => $this->alt,
			'%today_date%'  => date_i18n( 'Y-m-d' ),
			'%today_day%'   => date_i18n( 'd' ),
			'%year%'        => date_i18n( 'Y' ),
			'%month%'       => date_i18n( 'm' ),
			'%post_date%'   => gmdate( 'Y-m-d', $post_date ),
			'%post_year%'   => gmdate( 'Y', $post_date ),
			'%post_month%'  => gmdate( 'm', $post_date ),
			'%post_day%'    => gmdate( 'd', $post_date ),
			'%random%'      => wp_generate_password( 6, false ),
			'%timestamp%'   => time(),
			'%postname%'    => $this->post['post_name'] ?? '',
			'%post_id%'     => $this->post['ID'] ?? 0,
			'%url%'         => $this->normalize_host( home_url(), true, true ),
		);

		return str_replace( array_keys( $map ), array_values( $map ), $pattern );
	}

	/**
	 * Extract filename from URL.
	 *
	 * @return string|null
	 */
	private function get_original_filename() {
		$parts = pathinfo( $this->url );
		return isset( $parts['filename'] ) ? $parts['filename'] : null;
	}

	/**
	 * Determine the uploads subdirectory.
	 *
	 * @return string
	 */
	private function get_upload_subdir() {
		$date = isset( $this->post['post_date_gmt'] ) ? $this->post['post_date_gmt'] : current_time( 'mysql', true );
		return gmdate( 'Y/m', strtotime( $date ) );
	}

	/**
	 * Convert MIME type to file extension.
	 *
	 * @param string $mime Mime type.
	 * @return string|null
	 */
	private function extension_from_mime( $mime ) {
		$mimes = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/bmp'  => 'bmp',
			'image/tiff' => 'tif',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
		);

		return $mimes[ $mime ] ?? null;
	}

	/**
	 * Normalise protocol-relative URLs.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function normalize_url( $url ) {
		if ( preg_match( '/^\/\//', $url ) ) {
			return 'https:' . $url;
		}

		return $url;
	}

	/**
	 * Extract host from URL.
	 *
	 * @param string $url    URL.
	 * @param bool   $scheme Include scheme.
	 * @param bool   $www    Preserve www prefix.
	 * @return string|null
	 */
	private function normalize_host( $url, $scheme = false, $www = false ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return null;
		}

		$host = $parts['host'];
		if ( ! $www ) {
			$host = preg_replace( '/^www\d*\./i', '', $host );
		}

		if ( ! empty( $parts['port'] ) ) {
			$host .= ':' . absint( $parts['port'] );
		}

		if ( $scheme && ! empty( $parts['scheme'] ) ) {
			return $parts['scheme'] . '://' . $host;
		}

		return $host;
	}
}

