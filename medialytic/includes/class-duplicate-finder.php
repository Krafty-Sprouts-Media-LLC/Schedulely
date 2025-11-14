<?php
/**
 * Filename: class-duplicate-finder.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.2.0
 * Last Modified: 14/11/2025
 * Description: Duplicate image finder and remediation toolkit for Medialytic.
 *
 * @package Medialytic
 * @since 1.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides duplicate image detection and cleanup inside the WordPress admin.
 *
 * @since 1.2.0
 */
class Medialytic_Duplicate_Finder {

	/**
	 * Media page slug.
	 *
	 * @since 1.2.0
	 */
	const PAGE_SLUG = 'medialytic-duplicate-finder';

	/**
	 * AJAX nonce action.
	 *
	 * @since 1.2.0
	 */
	const NONCE_ACTION = 'medialytic_duplicate_finder';

	/**
	 * Option key storing cached results.
	 *
	 * @since 1.2.0
	 */
	const OPTION_RESULTS = 'medialytic_duplicate_scan_results';

	/**
	 * Option key storing dismissed group indexes.
	 *
	 * @since 1.2.0
	 */
	const OPTION_DISMISSED = 'medialytic_duplicate_dismissed_groups';

	/**
	 * Option key storing last scan timestamp.
	 *
	 * @since 1.2.0
	 */
	const OPTION_TIMESTAMP = 'medialytic_duplicate_scan_timestamp';

	/**
	 * Core instance.
	 *
	 * @var Medialytic_Core
	 * @since 1.2.0
	 */
	private $core;

	/**
	 * Stored admin page hook suffix.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	private $page_hook = '';

	/**
	 * Constructor.
	 *
	 * @param Medialytic_Core $core Core instance.
	 * @since 1.2.0
	 */
	public function __construct( Medialytic_Core $core ) {
		$this->core = $core;
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_media_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_medialytic_scan_duplicate_images', array( $this, 'scan_duplicates' ) );
		add_action( 'wp_ajax_medialytic_save_duplicate_scan', array( $this, 'save_scan_results' ) );
		add_action( 'wp_ajax_medialytic_get_duplicate_scan', array( $this, 'get_scan_results' ) );
		add_action( 'wp_ajax_medialytic_clear_duplicate_scan', array( $this, 'clear_scan_cache' ) );
		add_action( 'wp_ajax_medialytic_delete_duplicate_images', array( $this, 'delete_images' ) );
	}

	/**
	 * Register the media library submenu page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function register_media_page() {
		$this->page_hook = add_media_page(
			__( 'Duplicate Image Finder', 'medialytic' ),
			__( 'Find Duplicates', 'medialytic' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue assets for the duplicate finder interface.
	 *
	 * @param string $hook Current admin page hook.
	 * @since 1.2.0
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'medialytic-duplicate-finder',
			MEDIALYTIC_PLUGIN_URL . 'assets/css/duplicate-finder.css',
			array(),
			MEDIALYTIC_VERSION
		);

		wp_enqueue_script(
			'medialytic-duplicate-finder',
			MEDIALYTIC_PLUGIN_URL . 'assets/js/duplicate-finder.js',
			array( 'jquery' ),
			MEDIALYTIC_VERSION,
			true
		);

		wp_localize_script(
			'medialytic-duplicate-finder',
			'medialyticDuplicateFinder',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'strings' => array(
					'noDuplicates'      => __( 'No duplicate images found!', 'medialytic' ),
					'noDuplicatesFound' => __( 'No duplicates found', 'medialytic' ),
					'groupTitle'        => __( 'Similar to: %s', 'medialytic' ),
					'dismissGroup'      => __( 'Dismiss Group', 'medialytic' ),
					'original'          => __( 'Original', 'medialytic' ),
					'copy'              => __( 'Copy %d', 'medialytic' ),
					'title'             => __( 'Title: ', 'medialytic' ),
					'filename'          => __( 'Filename: ', 'medialytic' ),
					'size'              => __( 'Size: ', 'medialytic' ),
					'deleteSingle'      => __( 'Delete This', 'medialytic' ),
					'loadedFromCache'   => __( 'Loaded cached results – showing %d group(s)', 'medialytic' ),
					'foundGroups'       => __( 'Found %d group(s) of duplicates', 'medialytic' ),
					'loadingCache'      => __( 'Loading cached results…', 'medialytic' ),
					'noCache'           => __( 'No cached scan available.', 'medialytic' ),
					'cacheTimestamp'    => __( 'Loaded scan from %s', 'medialytic' ),
					'cacheParseError'   => __( 'Unable to parse cached scan data.', 'medialytic' ),
					'scanning'          => __( 'Scanning for duplicate images…', 'medialytic' ),
					'error'             => __( 'Error: %s', 'medialytic' ),
					'genericError'      => __( 'An unexpected error occurred. Please try again.', 'medialytic' ),
					'confirmClear'      => __( 'This will clear the cached results and run a fresh scan. Continue?', 'medialytic' ),
					'noSelection'       => __( 'Please select images to delete.', 'medialytic' ),
					'confirmDelete'     => __( 'Are you sure you want to delete %d image(s)?', 'medialytic' ),
					'allGroupsCleared'  => __( 'All duplicate groups cleared! ✓', 'medialytic' ),
				),
			)
		);
	}

	/**
	 * Render the media page interface.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function render_admin_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Duplicate Image Finder', 'medialytic' ); ?></h1>
			<div class="medialytic-dif-container">
				<div class="medialytic-dif-header">
					<button id="medialytic-dif-scan-btn" class="button button-primary">
						<?php esc_html_e( 'Scan for Duplicates', 'medialytic' ); ?>
					</button>
					<button id="medialytic-dif-load-cache-btn" class="button button-secondary">
						<?php esc_html_e( 'Load Last Scan', 'medialytic' ); ?>
					</button>
					<button id="medialytic-dif-clear-cache-btn" class="button button-secondary">
						<?php esc_html_e( 'Clear Cache & Rescan', 'medialytic' ); ?>
					</button>
					<button id="medialytic-dif-delete-selected-btn" class="button button-secondary" style="display:none;">
						<?php esc_html_e( 'Delete Selected', 'medialytic' ); ?>
					</button>
					<span id="medialytic-dif-scan-status"></span>
				</div>
				<div id="medialytic-dif-results"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Verify nonce and permissions for AJAX calls.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function validate_request() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'medialytic' ) );
		}
	}

	/**
	 * Scan the media library for duplicate images based on filename heuristics.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function scan_duplicates() {
		$this->validate_request();

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'post_mime_type' => 'image',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$groups    = array();
		$processed = array();

		foreach ( $attachments as $attachment_id ) {
			if ( in_array( $attachment_id, $processed, true ) ) {
				continue;
			}

			$attachment = get_post( $attachment_id );
			if ( ! $attachment ) {
				continue;
			}

			$base_name = $this->get_base_filename( $attachment_id );
			$similar   = array();

			foreach ( $attachments as $compare_id ) {
				if ( $compare_id === $attachment_id || in_array( $compare_id, $processed, true ) ) {
					continue;
				}

				$compare_base = $this->get_base_filename( $compare_id );

				if ( $base_name === $compare_base ) {
					$similar[]  = $this->prepare_image_payload( $compare_id );
					$processed[] = $compare_id;
				}
			}

			if ( ! empty( $similar ) ) {
				array_unshift( $similar, $this->prepare_image_payload( $attachment_id ) );

				$groups[]    = array(
					'base_name' => $base_name,
					'images'    => $similar,
				);
				$processed[] = $attachment_id;
			}
		}

		wp_send_json_success(
			array(
				'groups' => $groups,
				'total'  => count( $groups ),
			)
		);
	}

	/**
	 * Save scan results and dismissed groups.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function save_scan_results() {
		$this->validate_request();

		$results   = isset( $_POST['results'] ) ? wp_unslash( $_POST['results'] ) : '';
		$dismissed = isset( $_POST['dismissed'] ) ? (array) wp_unslash( $_POST['dismissed'] ) : array();
		$dismissed = array_map( 'absint', $dismissed );

		update_option( self::OPTION_RESULTS, $results );
		update_option( self::OPTION_DISMISSED, $dismissed );
		update_option( self::OPTION_TIMESTAMP, current_time( 'mysql' ) );

		wp_send_json_success();
	}

	/**
	 * Retrieve cached scan results.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function get_scan_results() {
		$this->validate_request();

		$results = get_option( self::OPTION_RESULTS, '' );

		if ( empty( $results ) ) {
			wp_send_json_error( __( 'No cached results found.', 'medialytic' ) );
		}

		wp_send_json_success(
			array(
				'results'   => $results,
				'dismissed' => get_option( self::OPTION_DISMISSED, array() ),
				'timestamp' => get_option( self::OPTION_TIMESTAMP, '' ),
			)
		);
	}

	/**
	 * Clear cached scan data.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function clear_scan_cache() {
		$this->validate_request();
		self::cleanup_options();
		wp_send_json_success();
	}

	/**
	 * Delete selected attachment IDs permanently.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function delete_images() {
		$this->validate_request();

		$image_ids_raw = isset( $_POST['image_ids'] ) ? wp_unslash( $_POST['image_ids'] ) : array();
		$image_ids     = array_filter( array_map( 'absint', (array) $image_ids_raw ) );

		if ( empty( $image_ids ) ) {
			wp_send_json_error( __( 'No images selected.', 'medialytic' ) );
		}

		$deleted = array();
		$failed  = array();

		foreach ( $image_ids as $image_id ) {
			if ( wp_delete_attachment( $image_id, true ) ) {
				$deleted[] = $image_id;
			} else {
				$failed[] = $image_id;
			}
		}

		wp_send_json_success(
			array(
				'deleted' => $deleted,
				'failed'  => $failed,
				'message' => sprintf(
					_n( '%d image deleted successfully.', '%d images deleted successfully.', count( $deleted ), 'medialytic' ),
					count( $deleted )
				),
			)
		);
	}

	/**
	 * Remove stored options during uninstall or cache clearing.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function cleanup_options() {
		delete_option( self::OPTION_RESULTS );
		delete_option( self::OPTION_DISMISSED );
		delete_option( self::OPTION_TIMESTAMP );
	}

	/**
	 * Prepare the payload for a given attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @since 1.2.0
	 * @return array
	 */
	private function prepare_image_payload( $attachment_id ) {
		$post = get_post( $attachment_id );

		return array(
			'id'       => $attachment_id,
			'title'    => $post ? $post->post_title : '',
			'filename' => basename( get_attached_file( $attachment_id ) ),
			'url'      => wp_get_attachment_url( $attachment_id ),
			'thumb'    => $this->get_image_thumbnail( $attachment_id ),
			'size'     => $this->get_file_size( $attachment_id ),
		);
	}

	/**
	 * Retrieve a thumbnail URL for the attachment with graceful fallback.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @since 1.2.0
	 * @return string
	 */
	private function get_image_thumbnail( $attachment_id ) {
		$thumb = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		if ( ! $thumb ) {
			$thumb = wp_get_attachment_image_url( $attachment_id, 'medium' );
		}

		if ( ! $thumb ) {
			$thumb = wp_get_attachment_url( $attachment_id );
		}

		return $thumb;
	}

	/**
	 * Generate the base filename used for duplicate comparisons.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @since 1.2.0
	 * @return string
	 */
	private function get_base_filename( $attachment_id ) {
		$file = basename( get_attached_file( $attachment_id ) );
		$name = pathinfo( $file, PATHINFO_FILENAME );

		$name = preg_replace( '/-\d+$/', '', $name );
		$name = preg_replace( '/-scaled$/', '', $name );
		$name = preg_replace( '/-\d+x\d+$/', '', $name );

		return strtolower( $name );
	}

	/**
	 * Human readable file size for attachments.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @since 1.2.0
	 * @return string
	 */
	private function get_file_size( $attachment_id ) {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			return __( 'Unknown', 'medialytic' );
		}

		return $this->format_bytes( filesize( $path ) );
	}

	/**
	 * Convert a byte value into a human-readable string.
	 *
	 * @param int $bytes Byte value.
	 * @since 1.2.0
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return round( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			return round( $bytes / 1024, 2 ) . ' KB';
		}

		return $bytes . ' ' . __( 'bytes', 'medialytic' );
	}
}

