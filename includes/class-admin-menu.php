<?php
/**
 * Filename: class-admin-menu.php
 * Description: WordPress admin menu registration for Schedulely.
 *              Extracted from Schedulely_Settings in Phase 2 refactor.
 *
 * @package Schedulely
 * @since   1.6.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schedulely_Admin_Menu
 *
 * Registers the Schedulely settings page under Tools in the WP admin menu.
 *
 * @since 1.6.0
 */
class Schedulely_Admin_Menu {

	/**
	 * Register hooks.
	 *
	 * @since 1.6.0
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	/**
	 * Register the Tools → Schedulely admin page.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 Moved from Schedulely_Settings.
	 */
	public function add_admin_menu(): void {
		add_management_page(
			__( 'Schedulely Settings', 'schedulely' ),
			__( 'Schedulely', 'schedulely' ),
			'manage_options',
			'schedulely',
			[ new Schedulely_Settings(), 'render_settings_page' ]
		);
	}
}
