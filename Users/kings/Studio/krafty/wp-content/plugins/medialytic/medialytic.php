<?php
/**
 * Plugin Name: Medialytic - Media counter and manager
 * Plugin URI: https://kraftysprouts.com/medialytic
 * Description: Media counter and manager for WordPress with comprehensive tracking, analytics, and automated management of images, videos, embeds, and media usage statistics.
 * Version: 1.7.0
 * Author: Krafty Sprouts Media, LLC
 * Author URI: https://kraftysprouts.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: medialytic
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package Medialytic
 * @since 1.0.0
 */
/**
 * Filename: medialytic.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 18/08/2025
 * Version: 1.7.0
 * Last Modified: 14/11/2025
 * Description: Bootstrap loader for the Medialytic media counter and manager plugin.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'MEDIALYTIC_VERSION', '1.7.0' );
define( 'MEDIALYTIC_PLUGIN_FILE', __FILE__ );
define( 'MEDIALYTIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDIALYTIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDIALYTIC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MEDIALYTIC_INCLUDES_DIR', MEDIALYTIC_PLUGIN_DIR . 'includes/' );

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class Medialytic {

	/**
	 * Plugin instance
	 *
	 * @var Medialytic
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Core instance
	 *
	 * @var Medialytic_Core
	 * @since 1.0.0
	 */
	public $core;

	/**
	 * Admin instance
	 *
	 * @var Medialytic_Admin
	 * @since 1.0.0
	 */
	public $admin;

	/**
	 * Image counter instance
	 *
	 * @var Medialytic_Image_Counter
	 * @since 1.0.0
	 */
	public $image_counter;

	/**
	 * Video counter instance
	 *
	 * @var Medialytic_Video_Counter
	 * @since 1.0.0
	 */
	public $video_counter;

	/**
	 * Embed counter instance
	 *
	 * @var Medialytic_Embed_Counter
	 * @since 1.0.0
	 */
	public $embed_counter;

	/**
	 * Duplicate finder instance
	 *
	 * @var Medialytic_Duplicate_Finder
	 * @since 1.2.0
	 */
	public $duplicate_finder;

	/**
	 * Featured image manager instance
	 *
	 * @var Medialytic_Featured_Image_Manager
	 * @since 1.4.0
	 */
	public $featured_image_manager;

	/**
	 * Auto upload images module
	 *
	 * @var Medialytic_Auto_Upload_Images
	 * @since 1.6.0
	 */
	public $auto_upload_images;

	/**
	 * Image title & alt optimizer
	 *
	 * @var Medialytic_Image_Title_Alt
	 * @since 1.7.0
	 */
	public $image_title_alt;

	/**
	 * Module manager instance
	 *
	 * @var Medialytic_Module_Manager
	 * @since 1.3.0
	 */
	public $modules;

	/**
	 * Get plugin instance
	 *
	 * @return Medialytic
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin
	 *
	 * @since 1.0.0
	 */
	private function init() {
		// Load autoloader
		$this->load_autoloader();

		// Register activation/deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( 'Medialytic', 'uninstall' ) );

		// Load textdomain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Initialize on plugins_loaded
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Load autoloader
	 *
	 * @since 1.0.0
	 */
	private function load_autoloader() {
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-core.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-admin.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-module-manager.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-image-counter.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-video-counter.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-embed-counter.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-duplicate-finder.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-featured-image-manager.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-auto-upload-image-handler.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-auto-upload-images.php';
		require_once MEDIALYTIC_INCLUDES_DIR . 'class-image-title-alt.php';
	}

	/**
	 * Initialize after plugins loaded
	 *
	 * @since 1.0.0
	 */
	public function plugins_loaded() {
		// Initialize core components
		$this->init_components();
	}

	/**
	 * Initialize core components
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		$this->core = new Medialytic_Core();

		$definitions = self::get_module_definitions();

		$this->modules = new Medialytic_Module_Manager( $this->core );
		$this->modules->register_modules( $definitions );
		$this->modules->boot();

		$this->image_counter    = $this->modules->get( 'image-counter' );
		$this->video_counter    = $this->modules->get( 'video-counter' );
		$this->embed_counter    = $this->modules->get( 'embed-counter' );
		$this->duplicate_finder = $this->modules->get( 'duplicate-finder' );
		$this->featured_image_manager = $this->modules->get( 'featured-image-manager' );
		$this->auto_upload_images     = $this->modules->get( 'auto-upload-images' );
		$this->image_title_alt        = $this->modules->get( 'image-title-alt' );

		// Initialize admin interface if in admin
		if ( is_admin() ) {
			$this->admin = new Medialytic_Admin( $this->core );
		}
	}

	/**
	 * Load plugin textdomain
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'medialytic',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Plugin activation
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Set default options
		$this->set_default_options();

		// Create database tables
		$this->create_database_tables();

		// Run module activation callbacks.
		foreach ( self::get_module_definitions() as $definition ) {
			if ( ! empty( $definition['activate'] ) && is_callable( $definition['activate'] ) ) {
				call_user_func( $definition['activate'] );
			}
		}
	}

	/**
	 * Plugin deactivation
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		// Clear any cached data
		delete_transient( 'medialytic_cache' );

		// Run module deactivation callbacks.
		foreach ( self::get_module_definitions() as $definition ) {
			if ( ! empty( $definition['deactivate'] ) && is_callable( $definition['deactivate'] ) ) {
				call_user_func( $definition['deactivate'] );
			}
		}
	}

	/**
	 * Plugin uninstall
	 *
	 * @since 1.0.0
	 */
	public static function uninstall() {
		if ( ! class_exists( 'Medialytic_Duplicate_Finder' ) ) {
			require_once MEDIALYTIC_INCLUDES_DIR . 'class-duplicate-finder.php';
		}
		if ( ! class_exists( 'Medialytic_Featured_Image_Manager' ) ) {
			require_once MEDIALYTIC_INCLUDES_DIR . 'class-featured-image-manager.php';
		}
		if ( ! class_exists( 'Medialytic_Auto_Upload_Images' ) ) {
			require_once MEDIALYTIC_INCLUDES_DIR . 'class-auto-upload-images.php';
		}
		if ( ! class_exists( 'Medialytic_Image_Title_Alt' ) ) {
			require_once MEDIALYTIC_INCLUDES_DIR . 'class-image-title-alt.php';
		}

		// Remove all options
		delete_option( 'medialytic_settings' );
		delete_option( 'medialytic_image_counter_needs_init' );
		delete_option( 'medialytic_image_counter_initialized' );
		delete_option( Medialytic_Auto_Upload_Images::OPTION_KEY );
		delete_option( Medialytic_Image_Title_Alt::OPTION_KEY );

		// Remove transients
		delete_transient( 'medialytic_cache' );

		// Drop database tables
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}medialytic_media_counts" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}medialytic_media_history" );

		// Module uninstall callbacks.
		foreach ( self::get_module_definitions() as $definition ) {
			if ( ! empty( $definition['uninstall'] ) && is_callable( $definition['uninstall'] ) ) {
				call_user_func( $definition['uninstall'] );
			}
		}
	}

	/**
	 * Set default options
	 *
	 * @since 1.0.0
	 */
	private function set_default_options() {
		$default_options = array(
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

		add_option( 'medialytic_settings', $default_options );
	}

	/**
	 * Create database tables
	 *
	 * @since 1.0.0
	 */
	private function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Media counts table
		$table_name = $wpdb->prefix . 'medialytic_media_counts';
		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			image_count int(11) NOT NULL DEFAULT 0,
			video_count int(11) NOT NULL DEFAULT 0,
			embed_count int(11) NOT NULL DEFAULT 0,
			total_media_count int(11) NOT NULL DEFAULT 0,
			last_updated datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY post_id (post_id),
			KEY last_updated (last_updated)
		) $charset_collate;";

		// Media history table
		$history_table = $wpdb->prefix . 'medialytic_media_history';
		$history_sql = "CREATE TABLE $history_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			image_count int(11) NOT NULL DEFAULT 0,
			video_count int(11) NOT NULL DEFAULT 0,
			embed_count int(11) NOT NULL DEFAULT 0,
			total_media_count int(11) NOT NULL DEFAULT 0,
			report_year int(4) NOT NULL,
			report_month int(2) NOT NULL,
			report_day int(2) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY post_date (post_id, report_year, report_month, report_day),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $history_sql );
	}

	/**
	 * Retrieve module definitions for registration and lifecycle management.
	 *
	 * @since 1.3.0
	 * @return array
	 */
	private static function get_module_definitions() {
		$modules = array(
			'image-counter'    => array(
				'slug'      => 'image-counter',
				'class'     => 'Medialytic_Image_Counter',
				'path'      => MEDIALYTIC_INCLUDES_DIR . 'class-image-counter.php',
				'priority'  => 5,
				'activate'  => array( 'Medialytic_Image_Counter', 'handle_activation' ),
				'deactivate'=> array( 'Medialytic_Image_Counter', 'handle_deactivation' ),
			),
			'video-counter'    => array(
				'slug'     => 'video-counter',
				'class'    => 'Medialytic_Video_Counter',
				'path'     => MEDIALYTIC_INCLUDES_DIR . 'class-video-counter.php',
				'priority' => 10,
			),
			'embed-counter'    => array(
				'slug'     => 'embed-counter',
				'class'    => 'Medialytic_Embed_Counter',
				'path'     => MEDIALYTIC_INCLUDES_DIR . 'class-embed-counter.php',
				'priority' => 15,
			),
			'duplicate-finder' => array(
				'slug'      => 'duplicate-finder',
				'class'     => 'Medialytic_Duplicate_Finder',
				'path'      => MEDIALYTIC_INCLUDES_DIR . 'class-duplicate-finder.php',
				'priority'  => 20,
				'uninstall' => array( 'Medialytic_Duplicate_Finder', 'cleanup_options' ),
			),
			'featured-image-manager' => array(
				'slug'      => 'featured-image-manager',
				'class'     => 'Medialytic_Featured_Image_Manager',
				'path'      => MEDIALYTIC_INCLUDES_DIR . 'class-featured-image-manager.php',
				'priority'  => 25,
				'activate'  => array( 'Medialytic_Featured_Image_Manager', 'activate' ),
				'deactivate'=> array( 'Medialytic_Featured_Image_Manager', 'deactivate' ),
				'uninstall' => array( 'Medialytic_Featured_Image_Manager', 'uninstall' ),
			),
			'image-title-alt' => array(
				'slug'     => 'image-title-alt',
				'class'    => 'Medialytic_Image_Title_Alt',
				'path'     => MEDIALYTIC_INCLUDES_DIR . 'class-image-title-alt.php',
				'priority' => 28,
			),
			'auto-upload-images' => array(
				'slug'     => 'auto-upload-images',
				'class'    => 'Medialytic_Auto_Upload_Images',
				'path'     => MEDIALYTIC_INCLUDES_DIR . 'class-auto-upload-images.php',
				'priority' => 30,
			),
		);

		foreach ( $modules as $module ) {
			if ( ! empty( $module['path'] ) && file_exists( $module['path'] ) ) {
				require_once $module['path'];
			}
		}

		return $modules;
	}
}

/**
 * Get plugin instance
 *
 * @return Medialytic
 * @since 1.0.0
 */
function medialytic() {
	return Medialytic::get_instance();
}

// Initialize plugin
medialytic();

// Add admin menu
if ( is_admin() ) {
	add_action( 'admin_menu', 'medialytic_admin_menu' );
}

/**
 * Add admin menu
 *
 * @since 1.0.0
 */
function medialytic_admin_menu() {
	add_options_page(
		__( 'Medialytic Settings', 'medialytic' ),
		__( 'Media Analytics', 'medialytic' ),
		'manage_options',
		'medialytic-settings',
		array( medialytic()->admin, 'render_settings_page' )
	);
}