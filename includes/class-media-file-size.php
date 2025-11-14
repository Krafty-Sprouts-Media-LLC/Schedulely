<?php
/**
 * Filename: class-media-file-size.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.8.0
 * Last Modified: 14/11/2025
 * Description: Adds file-size analytics, indexing, and variant previews to the Media Library.
 *
 * Credits:
 * - Based on "Media Library File Size" by SS88 LLC (GPLv2+). Logic and UI were reimplemented and modernised for Medialytic.
 *
 * @package Medialytic
 * @since 1.8.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media Library file-size utilities.
 *
 * @since 1.8.0
 */
class Medialytic_Media_File_Size {

	const META_PRIMARY = 'SS88MLFS';
	const META_VARIANTS = 'SS88MLFSV';

	/**
	 * Cached variant metadata.
	 *
	 * @var array
	 */
	private $variant_data = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'manage_media_columns', array( $this, 'register_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'register_sortable_column' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_sorting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer-upload.php', array( $this, 'print_variant_data_script' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'update_attachment_metadata' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'update_attachment_metadata' ), PHP_INT_MAX, 2 );
		add_action( 'wp_ajax_medialytic_media_size_index', array( $this, 'ajax_index' ) );
		add_action( 'wp_ajax_medialytic_media_size_index_count', array( $this, 'ajax_index_count' ) );
	}

	/**
	 * Register column in the media list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function register_column( $columns ) {
		$columns['medialytic_media_file_size'] = __( 'File Size', 'medialytic' );
		return $columns;
	}

	/**
	 * Mark column sortable.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function register_sortable_column( $columns ) {
		$columns['medialytic_media_file_size'] = 'medialytic_media_file_size';
		return $columns;
	}

	/**
	 * Handle sorting requests.
	 *
	 * @param WP_Query $query Query instance.
	 * @return void
	 */
	public function handle_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'medialytic_media_file_size' === ( $_REQUEST['orderby'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query->set( 'meta_key', self::META_PRIMARY );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Render column contents.
	 *
	 * @param string $column Column name.
	 * @param int    $attachment_id Attachment ID.
	 * @return void
	 */
	public function render_column( $column, $attachment_id ) {
		if ( 'medialytic_media_file_size' !== $column ) {
			return;
		}

		echo wp_kses_post( $this->build_cell_markup( $attachment_id ) );
	}

	/**
	 * Update attachment metadata with size info.
	 *
	 * @param array $data Metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function update_attachment_metadata( $data, $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) && empty( $data['filesize'] ) ) {
			return $data;
		}

		$this->store_sizes( $data, $attachment_id );
		return $data;
	}

	/**
	 * Store primary + variant sizes.
	 *
	 * @param array $metadata Metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return void
	 */
	private function store_sizes( $metadata, $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		$size = ! empty( $metadata['filesize'] ) ? (int) $metadata['filesize'] : ( file_exists( $file ) ? (int) filesize( $file ) : 0 );

		if ( $size <= 0 ) {
			return;
		}

		update_post_meta( $attachment_id, self::META_PRIMARY, $size );
		update_post_meta( $attachment_id, self::META_VARIANTS, $this->calculate_variant_size( $metadata, $file ) );
	}

	/**
	 * Calculate total variant size.
	 *
	 * @param array  $metadata Metadata.
	 * @param string $file Path.
	 * @return int
	 */
	private function calculate_variant_size( $metadata, $file ) {
		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return 0;
		}

		$total = 0;
		$dir   = pathinfo( $file, PATHINFO_DIRNAME );

		foreach ( $metadata['sizes'] as $variant ) {
			if ( ! empty( $variant['filesize'] ) ) {
				$total += (int) $variant['filesize'];
				continue;
			}

			if ( ! empty( $variant['file'] ) ) {
				$path = trailingslashit( $dir ) . $variant['file'];
				if ( file_exists( $path ) ) {
					$total += (int) filesize( $path );
				}
			}
		}

		return $total;
	}

	/**
	 * Build the HTML markup for the media column cell.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function build_cell_markup( $attachment_id ) {
		$metadata    = wp_get_attachment_metadata( $attachment_id );
		$primary     = (int) get_post_meta( $attachment_id, self::META_PRIMARY, true );
		$variant_sum = (int) get_post_meta( $attachment_id, self::META_VARIANTS, true );

		if ( ! $primary && isset( $metadata['filesize'] ) ) {
			$primary = (int) $metadata['filesize'];
		}

		if ( ! $primary ) {
			return '<em>' . esc_html__( 'Unknown', 'medialytic' ) . '</em>';
		}

		$markup = size_format( $primary );

		if ( $variant_sum > 0 ) {
			$markup .= sprintf( '<small>(+%s)</small>', size_format( $variant_sum ) );
		}

		if ( ! empty( $metadata['sizes'] ) ) {
			$markup .= sprintf(
				' <button class="medialytic-media-size-variants-button" data-attachment-id="%1$d">%2$s</button>',
				$attachment_id,
				esc_html__( 'View Variants', 'medialytic' )
			);

			$this->variant_data[ $attachment_id ] = $this->collect_variant_payload( $metadata, $attachment_id );
		}

		return $markup;
	}

	/**
	 * Collect variant payload for JS modal.
	 *
	 * @param array $metadata Metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	private function collect_variant_payload( $metadata, $attachment_id ) {
		if ( empty( $metadata['sizes'] ) ) {
			return array();
		}

		$file     = get_attached_file( $attachment_id );
		$dir      = pathinfo( $file, PATHINFO_DIRNAME );
		$base_url = pathinfo( wp_get_attachment_url( $attachment_id ), PATHINFO_DIRNAME );
		$payload  = array();

		foreach ( $metadata['sizes'] as $size_key => $variant ) {
			$size_path = trailingslashit( $dir ) . $variant['file'];
			$variant_size = isset( $variant['filesize'] ) ? (int) $variant['filesize'] : ( file_exists( $size_path ) ? (int) filesize( $size_path ) : 0 );

			$payload[] = array(
				'size'        => $size_key,
				'width'       => (int) ( $variant['width'] ?? 0 ),
				'height'      => (int) ( $variant['height'] ?? 0 ),
				'filesize_hr' => $variant_size ? size_format( $variant_size ) : __( 'Unknown', 'medialytic' ),
				'filename'    => trailingslashit( $base_url ) . $variant['file'],
			);
		}

		return $payload;
	}

	/**
	 * Enqueue scripts/styles when viewing the media library list view.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'upload.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'medialytic-media-file-size',
			MEDIALYTIC_PLUGIN_URL . 'assets/css/media-file-size.css',
			array(),
			MEDIALYTIC_VERSION
		);

		wp_enqueue_script(
			'medialytic-media-file-size',
			MEDIALYTIC_PLUGIN_URL . 'assets/js/media-file-size.js',
			array( 'jquery' ),
			MEDIALYTIC_VERSION,
			true
		);

		wp_localize_script(
			'medialytic-media-file-size',
			'medialyticMediaSize',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'medialytic_media_file_size' ),
				'strings' => array(
					'indexMedia'   => __( 'Index Media', 'medialytic' ),
					'reindexMedia' => __( 'Reindex Media', 'medialytic' ),
					'indexError'   => __( 'Unable to index your media library.', 'medialytic' ),
				),
			)
		);

	}

	/**
	 * Print variant JSON for the modal.
	 *
	 * @return void
	 */
	public function print_variant_data_script() {
		$data = $this->variant_data;
		if ( empty( $data ) ) {
			$data = array();
		}

		printf(
			'<script>window.medialyticMediaSizeVariants = %s;</script>',
			wp_json_encode( $data )
		);
	}

	/**
	 * AJAX: index attachment sizes.
	 *
	 * @return void
	 */
	public function ajax_index() {
		check_ajax_referer( 'medialytic_media_file_size', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'body' => __( 'Insufficient permissions.', 'medialytic' ) ), 403 );
		}

		$reindex      = ! empty( $_POST['reindex'] );
		$batch_size   = 100;
		$page         = 1;
		$processed    = 0;
		$updated_rows = array();

		do {
			$args = array(
				'post_type'      => 'attachment',
				'posts_per_page' => $batch_size,
				'paged'          => $page,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => self::META_PRIMARY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_VARIANTS,
						'compare' => 'NOT EXISTS',
					),
				),
			);

			if ( $reindex ) {
				unset( $args['meta_query'] );
			}

			$attachments = get_posts( $args );
			if ( empty( $attachments ) ) {
				break;
			}

			foreach ( $attachments as $attachment_id ) {
				$metadata = wp_get_attachment_metadata( $attachment_id );
				$this->store_sizes( $metadata, $attachment_id );
				$processed++;

				if ( $processed <= 1000 ) {
					$updated_rows[] = array(
						'attachment_id' => $attachment_id,
						'html'          => $this->build_cell_markup( $attachment_id ),
					);
				}
			}

			$page++;
		} while ( count( $attachments ) === $batch_size );

		if ( ! $processed ) {
			wp_send_json_error(
				array(
					'httpcode' => 99,
					'body'     => __( 'No attachments were indexed. This usually means the files are not stored locally.', 'medialytic' ),
				)
			);
		}

		$variants = array();
		foreach ( $updated_rows as $row ) {
			$attachment_id = $row['attachment_id'];
			if ( isset( $this->variant_data[ $attachment_id ] ) ) {
				$variants[ $attachment_id ] = $this->variant_data[ $attachment_id ];
			}
		}

		wp_send_json_success(
			array(
				'html'    => $updated_rows,
				'variants'=> $variants,
				'message' => $reindex
					? sprintf( __( 'Re-indexed %s attachments.', 'medialytic' ), number_format_i18n( $processed ) )
					: sprintf( __( 'Indexed %s attachments. Your media library is now up to date.', 'medialytic' ), number_format_i18n( $processed ) ),
			)
		);
	}

	/**
	 * AJAX: Return total media library size.
	 *
	 * @return void
	 */
	public function ajax_index_count() {
		check_ajax_referer( 'medialytic_media_file_size', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array(), 403 );
		}

		global $wpdb;
		$total_primary  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_PRIMARY ) );
		$total_variants = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_VARIANTS ) );

		if ( ! $total_primary && ! $total_variants ) {
			wp_send_json_error();
		}

		wp_send_json_success(
			array(
				'TotalMLSize'       => size_format( $total_primary + $total_variants ),
				'TotalMLSize_Title' => $total_variants ? size_format( $total_primary ) . ' + ' . size_format( $total_variants ) . '<br>' . __( 'variants', 'medialytic' ) : '',
			)
		);
	}

	/**
	 * Delete metadata keys on uninstall.
	 *
	 * @return void
	 */
	public static function cleanup() {
		delete_post_meta_by_key( self::META_PRIMARY );
		delete_post_meta_by_key( self::META_VARIANTS );
	}
}

