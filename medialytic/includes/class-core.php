<?php
/**
 * Filename: class-core.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 18/08/2025
 * Version: 1.1.0
 * Last Modified: 14/11/2025
 * Description: Core utilities for Medialytic media analytics.
 *
 * @package Medialytic
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core functionality for Medialytic
 *
 * @since 1.0.0
 */
class Medialytic_Core {

	/**
	 * Plugin options
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $options;

	/**
	 * Meta keys for different media types
	 *
	 * @var array
	 * @since 1.0.0
	 */
	const META_KEYS = array(
		'image' => 'medialytic_image_count',
		'video' => 'medialytic_video_count',
		'embed' => 'medialytic_embed_count',
		'total' => 'medialytic_total_media_count',
	);

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_options();
		$this->init_hooks();
	}

	/**
	 * Load plugin options
	 *
	 * @since 1.0.0
	 */
	private function load_options() {
		$default_options = $this->get_default_options();
		$this->options = get_option( 'medialytic_settings', $default_options );
		$this->options = wp_parse_args( $this->options, $default_options );
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Auto count on save if enabled
		if ( $this->get_option( 'enabled', true ) ) {
			add_action( 'save_post', array( $this, 'auto_count_media' ), 10, 2 );
		}

		// Add admin columns if enabled
		if ( $this->get_option( 'show_in_admin_columns', true ) ) {
			$this->add_admin_columns();
		}
	}

	/**
	 * Get default options
	 *
	 * @return array Default options
	 * @since 1.0.0
	 */
	public function get_default_options() {
		return array(
			'enabled' => true,
			'post_types' => array( 'post', 'page' ),
			'count_images' => true,
			'count_videos' => true,
			'count_embeds' => true,
			'show_in_admin_columns' => true,
			'dashboard_widget' => true,
			'historical_tracking' => true,
			'cache_duration' => 12 * HOUR_IN_SECONDS,
			'debug_mode' => false,
		);
	}

	/**
	 * Check if plugin is enabled
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_enabled() {
		return (bool) $this->get_option( 'enabled', true );
	}

	/**
	 * Get plugin option
	 *
	 * @param string $key Option key.
	 * @param mixed  $default Default value.
	 * @return mixed Option value
	 * @since 1.0.0
	 */
	public function get_option( $key, $default = null ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
	}

	/**
	 * Get all options
	 *
	 * @return array All options
	 * @since 1.0.0
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Update options
	 *
	 * @param array $options New options.
	 * @return bool Success status
	 * @since 1.0.0
	 */
	public function update_options( $options ) {
		$this->options = $options;
		return update_option( 'medialytic_settings', $options );
	}

	/**
	 * Check if post type is enabled
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_post_type_enabled( $post_type ) {
		$enabled_post_types = $this->get_option( 'post_types', array( 'post', 'page' ) );
		return in_array( $post_type, $enabled_post_types, true );
	}

	/**
	 * Get available post types
	 *
	 * @return array Post types
	 * @since 1.0.0
	 */
	public function get_available_post_types() {
		return get_post_types( array( 'public' => true ), 'objects' );
	}

	/**
	 * Auto count media on post save
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @since 1.0.0
	 */
	public function auto_count_media( $post_id, $post ) {
		// Skip if not enabled
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Skip if post type not enabled
		if ( ! $this->is_post_type_enabled( $post->post_type ) ) {
			return;
		}

		// Skip revisions and autosaves
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Count different media types
		$counts = $this->count_all_media( $post->post_content );

		// Update post meta
		update_post_meta( $post_id, self::META_KEYS['image'], $counts['images'] );
		update_post_meta( $post_id, self::META_KEYS['video'], $counts['videos'] );
		update_post_meta( $post_id, self::META_KEYS['embed'], $counts['embeds'] );
		update_post_meta( $post_id, self::META_KEYS['total'], $counts['total'] );

		// Store in database table
		$this->store_media_counts( $post_id, $counts );

		// Store historical data if enabled
		if ( $this->get_option( 'historical_tracking', true ) ) {
			$this->store_historical_data( $post_id, $counts );
		}

		$this->debug_log( "Media counts updated for post {$post_id}: {$counts['images']} images, {$counts['videos']} videos, {$counts['embeds']} embeds" );
	}

	/**
	 * Count all media types in content
	 *
	 * @param string $content Content to analyze.
	 * @return array Media counts
	 * @since 1.0.0
	 */
	public function count_all_media( $content ) {
		$counts = array(
			'images' => 0,
			'videos' => 0,
			'embeds' => 0,
			'total' => 0,
		);

		if ( empty( $content ) ) {
			return $counts;
		}

		// Count images
		if ( $this->get_option( 'count_images', true ) ) {
			$counts['images'] = $this->count_images( $content );
		}

		// Count videos
		if ( $this->get_option( 'count_videos', true ) ) {
			$counts['videos'] = $this->count_videos( $content );
		}

		// Count embeds
		if ( $this->get_option( 'count_embeds', true ) ) {
			$counts['embeds'] = $this->count_embeds( $content );
		}

		// Calculate total
		$counts['total'] = $counts['images'] + $counts['videos'] + $counts['embeds'];

		return $counts;
	}

	/**
	 * Count images in content
	 *
	 * @param string $content Content to analyze.
	 * @return int Image count
	 * @since 1.0.0
	 */
	public function count_images( $content ) {
		if ( class_exists( 'Medialytic_Image_Counter' ) && method_exists( 'Medialytic_Image_Counter', 'count_images_from_content' ) ) {
			return Medialytic_Image_Counter::count_images_from_content( $content );
		}

		// Fallback logic retained for safety if the image counter class is unavailable.
		preg_match_all( '/<img[^>]+>/i', $content, $matches );
		$img_count = count( $matches[0] );

		preg_match_all( '/<!-- wp:image[^>]*-->.*?<!-- \/wp:image -->/s', $content, $block_matches );
		$block_count = count( $block_matches[0] );

		preg_match_all( '/\[gallery[^\]]*\]/', $content, $gallery_matches );
		$gallery_count = 0;
		foreach ( $gallery_matches[0] as $gallery ) {
			if ( preg_match( '/ids=["\']([^"\']*)["\']/i', $gallery, $id_matches ) ) {
				$ids = explode( ',', $id_matches[1] );
				$gallery_count += count( array_filter( $ids ) );
			}
		}

		return max( $img_count, $block_count ) + $gallery_count;
	}

	/**
	 * Count videos in content
	 *
	 * @param string $content Content to analyze.
	 * @return int Video count
	 * @since 1.0.0
	 */
	public function count_videos( $content ) {
		// Count video tags
		preg_match_all( '/<video[^>]*>.*?<\/video>/is', $content, $video_matches );
		$video_count = count( $video_matches[0] );

		// Count WordPress video blocks
		preg_match_all( '/<!-- wp:video[^>]*-->.*?<!-- \/wp:video -->/s', $content, $block_matches );
		$block_count = count( $block_matches[0] );

		// Count video shortcodes
		preg_match_all( '/\[video[^\]]*\]/', $content, $shortcode_matches );
		$shortcode_count = count( $shortcode_matches[0] );

		return max( $video_count, $block_count, $shortcode_count );
	}

	/**
	 * Count embeds in content
	 *
	 * @param string $content Content to analyze.
	 * @return int Embed count
	 * @since 1.0.0
	 */
	public function count_embeds( $content ) {
		// Count iframe embeds
		preg_match_all( '/<iframe[^>]*>.*?<\/iframe>/is', $content, $iframe_matches );
		$iframe_count = count( $iframe_matches[0] );

		// Count embed blocks
		preg_match_all( '/<!-- wp:embed[^>]*-->.*?<!-- \/wp:embed -->/s', $content, $embed_matches );
		$embed_count = count( $embed_matches[0] );

		// Count common embed shortcodes
		$embed_shortcodes = array( 'youtube', 'vimeo', 'twitter', 'instagram', 'facebook', 'soundcloud', 'spotify' );
		$shortcode_count = 0;
		foreach ( $embed_shortcodes as $shortcode ) {
			preg_match_all( '/\[' . $shortcode . '[^\]]*\]/', $content, $matches );
			$shortcode_count += count( $matches[0] );
		}

		return max( $iframe_count, $embed_count ) + $shortcode_count;
	}

	/**
	 * Get media counts for a post
	 *
	 * @param int $post_id Post ID.
	 * @return array Media counts
	 * @since 1.0.0
	 */
	public function get_media_counts( $post_id ) {
		return array(
			'images' => (int) get_post_meta( $post_id, self::META_KEYS['image'], true ),
			'videos' => (int) get_post_meta( $post_id, self::META_KEYS['video'], true ),
			'embeds' => (int) get_post_meta( $post_id, self::META_KEYS['embed'], true ),
			'total' => (int) get_post_meta( $post_id, self::META_KEYS['total'], true ),
		);
	}

	/**
	 * Store media counts in database
	 *
	 * @param int   $post_id Post ID.
	 * @param array $counts Media counts.
	 * @since 1.0.0
	 */
	private function store_media_counts( $post_id, $counts ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'medialytic_media_counts';
		$current_date = current_time( 'mysql' );

		$wpdb->replace(
			$table_name,
			array(
				'post_id' => $post_id,
				'image_count' => $counts['images'],
				'video_count' => $counts['videos'],
				'embed_count' => $counts['embeds'],
				'total_media_count' => $counts['total'],
				'last_updated' => $current_date,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Store historical data
	 *
	 * @param int   $post_id Post ID.
	 * @param array $counts Media counts.
	 * @since 1.0.0
	 */
	private function store_historical_data( $post_id, $counts ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'medialytic_media_history';
		$current_date = current_time( 'mysql' );
		$date_parts = explode( '-', $current_date );

		$wpdb->replace(
			$table_name,
			array(
				'post_id' => $post_id,
				'image_count' => $counts['images'],
				'video_count' => $counts['videos'],
				'embed_count' => $counts['embeds'],
				'total_media_count' => $counts['total'],
				'report_year' => (int) $date_parts[0],
				'report_month' => (int) $date_parts[1],
				'report_day' => (int) substr( $date_parts[2], 0, 2 ),
				'created_at' => $current_date,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Add admin columns
	 *
	 * @since 1.0.0
	 */
	private function add_admin_columns() {
		$post_types = $this->get_option( 'post_types', array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_media_count_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'display_media_count_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'make_media_count_column_sortable' ) );
		}

		add_action( 'pre_get_posts', array( $this, 'handle_media_count_column_sorting' ) );
	}

	/**
	 * Add media count column to admin
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns
	 * @since 1.0.0
	 */
	public function add_media_count_column( $columns ) {
		$columns['medialytic_media_count'] = __( 'Media Count', 'medialytic' );
		return $columns;
	}

	/**
	 * Display media count in admin column
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @since 1.0.0
	 */
	public function display_media_count_column( $column, $post_id ) {
		if ( 'medialytic_media_count' === $column ) {
			$counts = $this->get_media_counts( $post_id );
			
			if ( $counts['total'] > 0 ) {
				echo '<strong>' . number_format( $counts['total'] ) . '</strong> total<br>';
				echo '<small>';
				if ( $counts['images'] > 0 ) {
					echo $counts['images'] . ' img ';
				}
				if ( $counts['videos'] > 0 ) {
					echo $counts['videos'] . ' vid ';
				}
				if ( $counts['embeds'] > 0 ) {
					echo $counts['embeds'] . ' emb';
				}
				echo '</small>';
			} else {
				echo '<em>—</em>';
			}
		}
	}

	/**
	 * Make media count column sortable
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified columns
	 * @since 1.0.0
	 */
	public function make_media_count_column_sortable( $columns ) {
		$columns['medialytic_media_count'] = 'medialytic_media_count';
		return $columns;
	}

	/**
	 * Handle media count column sorting
	 *
	 * @param WP_Query $query Query object.
	 * @since 1.0.0
	 */
	public function handle_media_count_column_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'medialytic_media_count' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', self::META_KEYS['total'] );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data
	 * @since 1.0.0
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Boolean options
		$boolean_options = array(
			'enabled',
			'count_images',
			'count_videos',
			'count_embeds',
			'show_in_admin_columns',
			'dashboard_widget',
			'historical_tracking',
			'debug_mode',
		);

		foreach ( $boolean_options as $option ) {
			$sanitized[ $option ] = isset( $input[ $option ] ) ? (bool) $input[ $option ] : false;
		}

		// Array options
		$sanitized['post_types'] = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? array_map( 'sanitize_text_field', $input['post_types'] ) : array( 'post', 'page' );

		// Numeric options
		$sanitized['cache_duration'] = isset( $input['cache_duration'] ) ? absint( $input['cache_duration'] ) : 12 * HOUR_IN_SECONDS;

		return $sanitized;
	}

	/**
	 * Reset settings to defaults
	 *
	 * @return bool Success status
	 * @since 1.0.0
	 */
	public function reset_settings() {
		$default_options = $this->get_default_options();
		$this->options = $default_options;
		return update_option( 'medialytic_settings', $default_options );
	}

	/**
	 * Debug log
	 *
	 * @param string $message Log message.
	 * @since 1.0.0
	 */
	public function debug_log( $message ) {
		if ( $this->get_option( 'debug_mode', false ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Medialytic] ' . $message );
		}
	}
}