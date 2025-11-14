<?php
/**
 * Filename: class-image-counter.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 18/08/2025
 * Version: 1.1.0
 * Last Modified: 14/11/2025
 * Description: Advanced image counting, caching, and bulk initialization.
 *
 * @package Medialytic
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image counting functionality with bulk initialization and admin UI support.
 *
 * @since 1.1.0
 */
class Medialytic_Image_Counter {

	/**
	 * Option key indicating initialization is required.
	 *
	 * @since 1.1.0
	 */
	const OPTION_NEEDS_INIT = 'medialytic_image_counter_needs_init';

	/**
	 * Option key indicating initialization is complete.
	 *
	 * @since 1.1.0
	 */
	const OPTION_INITIALIZED = 'medialytic_image_counter_initialized';

	/**
	 * Cron hook for bulk initialization batches.
	 *
	 * @since 1.1.0
	 */
	const CRON_HOOK = 'medialytic_image_counter_bulk_init';

	/**
	 * AJAX action used to dismiss initialization notice.
	 *
	 * @since 1.1.0
	 */
	const AJAX_ACTION_DISMISS_NOTICE = 'medialytic_dismiss_image_counter_notice';

	/**
	 * Cache group for image counts.
	 *
	 * @since 1.1.0
	 */
	const CACHE_GROUP = 'medialytic_image_counter';

	/**
	 * Core instance.
	 *
	 * @var Medialytic_Core
	 * @since 1.1.0
	 */
	private $core;

	/**
	 * Meta key used to store image counts.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private $meta_key;

	/**
	 * Constructor.
	 *
	 * @param Medialytic_Core $core Core instance.
	 * @since 1.1.0
	 */
	public function __construct( $core ) {
		$this->core     = $core;
		$this->meta_key = Medialytic_Core::META_KEYS['image'];
		$this->register_hooks();
	}

	/**
	 * Handle plugin activation requirements.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function handle_activation() {
		if ( ! get_option( self::OPTION_INITIALIZED ) ) {
			update_option( self::OPTION_NEEDS_INIT, true );
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Handle plugin deactivation cleanup.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function handle_deactivation() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Register runtime hooks.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_DISMISS_NOTICE, array( $this, 'dismiss_notice' ) );
		add_action( self::CRON_HOOK, array( $this, 'bulk_initialize_image_counts' ) );
		add_action( 'delete_post', array( $this, 'cleanup_image_count_meta' ) );

		$post_types = $this->core->get_option( 'post_types', array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_image_count_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'display_image_count_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'make_image_count_sortable' ) );
		}

		add_action( 'pre_get_posts', array( $this, 'handle_image_count_sorting' ) );
	}

	/**
	 * Initialize runtime requirements.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function init() {
		if ( ! $this->core->get_option( 'count_images', true ) ) {
			return;
		}

		if ( get_option( self::OPTION_NEEDS_INIT ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_HOOK );
		}
	}

	/**
	 * Enqueue inline admin script for notice dismissal.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( self::OPTION_NEEDS_INIT ) ) {
			return;
		}

		wp_add_inline_script(
			'jquery',
			sprintf(
				'
				jQuery( document ).ready( function( $ ) {
					$( document ).on( "click", ".medialytic-image-counter-notice .notice-dismiss", function() {
						$.post( ajaxurl, {
							action: "%1$s",
							nonce: "%2$s"
						} );
					} );
				} );
				',
				self::AJAX_ACTION_DISMISS_NOTICE,
				wp_create_nonce( self::AJAX_ACTION_DISMISS_NOTICE )
			)
		);
	}

	/**
	 * Dismiss initialization notice via AJAX.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function dismiss_notice() {
		check_ajax_referer( self::AJAX_ACTION_DISMISS_NOTICE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'medialytic' ), 403 );
		}

		delete_option( self::OPTION_NEEDS_INIT );

		wp_send_json_success();
	}

	/**
	 * Add image count column to admin list tables.
	 *
	 * @param array $columns Existing columns.
	 * @since 1.1.0
	 * @return array
	 */
	public function add_image_count_column( $columns ) {
		$columns['medialytic_image_count'] = __( 'Images', 'medialytic' );
		return $columns;
	}

	/**
	 * Display image count column value.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @since 1.1.0
	 * @return void
	 */
	public function display_image_count_column( $column, $post_id ) {
		if ( 'medialytic_image_count' !== $column ) {
			return;
		}

		$count = $this->get_image_count( $post_id );

		if ( $count > 0 ) {
			printf( '<span class="medialytic-image-count">%s</span>', esc_html( number_format_i18n( $count ) ) );
		} else {
			echo '<em>—</em>';
		}
	}

	/**
	 * Make image count column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @since 1.1.0
	 * @return array
	 */
	public function make_image_count_sortable( $columns ) {
		$columns['medialytic_image_count'] = 'medialytic_image_count';
		return $columns;
	}

	/**
	 * Handle sorting requests for image count column.
	 *
	 * @param WP_Query $query Query instance.
	 * @since 1.1.0
	 * @return void
	 */
	public function handle_image_count_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'medialytic_image_count' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', $this->meta_key );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Show admin notice while initialization is running.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( self::OPTION_NEEDS_INIT ) ) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible medialytic-image-counter-notice">';
		echo '<p>' . esc_html__( 'Medialytic is initializing existing image counts. This will run in the background and may take a few moments.', 'medialytic' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Retrieve cached image count for a post.
	 *
	 * @param int $post_id Post ID.
	 * @since 1.1.0
	 * @return int
	 */
	public function get_image_count( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return 0;
		}

		$cache_key = 'medialytic_image_count_' . $post_id;
		$count     = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $count ) {
			return (int) $count;
		}

		$count = get_post_meta( $post_id, $this->meta_key, true );

		if ( '' === $count ) {
			$count = $this->count_images_for_post( $post_id );
		}

		wp_cache_set( $cache_key, (int) $count, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return (int) $count;
	}

	/**
	 * Count images for a specific post and persist the result.
	 *
	 * @param int $post_id Post ID.
	 * @since 1.1.0
	 * @return int
	 */
	private function count_images_for_post( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || ! $this->core->is_post_type_enabled( $post->post_type ) ) {
			return 0;
		}

		$count = self::count_images_from_content( $post->post_content );
		update_post_meta( $post_id, $this->meta_key, $count );

		return $count;
	}

	/**
	 * Count images in content using DOMDocument, regex, block, and gallery detection.
	 *
	 * @param string $content Content to analyze.
	 * @since 1.1.0
	 * @return int
	 */
	public static function count_images_from_content( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return 0;
		}

		$dom_count    = self::count_images_with_dom( $content );
		$regex_count  = self::count_images_with_regex( $content );
		$block_count  = self::count_image_blocks( $content );
		$gallery_count = self::count_gallery_images( $content );

		return max( $dom_count, $regex_count, $block_count ) + $gallery_count;
	}

	/**
	 * Count images via DOMDocument for high accuracy.
	 *
	 * @param string $content Content to analyze.
	 * @since 1.1.0
	 * @return int
	 */
	private static function count_images_with_dom( $content ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return 0;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );

		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8">' . wp_kses_post( $content ),
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();

		if ( ! $loaded ) {
			return 0;
		}

		return $dom->getElementsByTagName( 'img' )->length;
	}

	/**
	 * Count images using regex fallback.
	 *
	 * @param string $content Content to analyze.
	 * @since 1.1.0
	 * @return int
	 */
	private static function count_images_with_regex( $content ) {
		$content = wp_kses_post( $content );

		$match_count = preg_match_all( '/<img[^>]*>/i', $content );

		return is_int( $match_count ) ? $match_count : 0;
	}

	/**
	 * Count Gutenberg image blocks.
	 *
	 * @param string $content Content to analyze.
	 * @since 1.1.0
	 * @return int
	 */
	private static function count_image_blocks( $content ) {
		$match_count = preg_match_all( '/<!-- wp:image[^>]*-->.*?<!-- \\/wp:image -->/s', $content );
		return is_int( $match_count ) ? $match_count : 0;
	}

	/**
	 * Count gallery shortcode images.
	 *
	 * @param string $content Content to analyze.
	 * @since 1.1.0
	 * @return int
	 */
	private static function count_gallery_images( $content ) {
		preg_match_all( '/\\[gallery[^\\]]*\\]/', $content, $gallery_matches );

		if ( empty( $gallery_matches[0] ) ) {
			return 0;
		}

		$gallery_count = 0;

		foreach ( $gallery_matches[0] as $gallery ) {
			if ( preg_match( '/ids=["\']([^"\']+)["\']/', $gallery, $id_matches ) ) {
				$ids            = array_filter( array_map( 'trim', explode( ',', $id_matches[1] ) ) );
				$gallery_count += count( $ids );
			}
		}

		return $gallery_count;
	}

	/**
	 * Initialize image counts for posts missing metadata.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function bulk_initialize_image_counts() {
		$post_types = $this->core->get_option( 'post_types', array( 'post', 'page' ) );

		$query_args = array(
			'post_type'      => $post_types,
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => $this->meta_key,
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$posts = get_posts( $query_args );

		foreach ( $posts as $post_id ) {
			$this->count_images_for_post( $post_id );
		}

		if ( count( $posts ) === $query_args['posts_per_page'] ) {
			wp_schedule_single_event( time() + 10, self::CRON_HOOK );
		} else {
			update_option( self::OPTION_INITIALIZED, true );
			delete_option( self::OPTION_NEEDS_INIT );
		}
	}

	/**
	 * Clean up image count metadata and caches when a post is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @since 1.1.0
	 * @return void
	 */
	public function cleanup_image_count_meta( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return;
		}

		wp_cache_delete( 'medialytic_image_count_' . $post_id, self::CACHE_GROUP );
		delete_post_meta( $post_id, $this->meta_key );
	}
}