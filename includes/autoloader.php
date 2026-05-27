<?php
/**
 * Filename: autoloader.php
 * Description: Prefix-aware autoloader for Schedulely classes.
 *              Converts Schedulely_Foo_Bar → includes/class-foo-bar.php
 *              and Schedulely_Admin_Foo   → includes/admin/class-foo.php
 *              (once the admin sub-directory is created in Phase 2).
 *
 * @package Schedulely
 * @since   1.6.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedulely class autoloader.
 *
 * Called via spl_autoload_register(). Handles:
 *   - Schedulely_*   →  includes/class-{slug}.php
 *
 * The slug is derived by lower-casing the class name, stripping the
 * "schedulely_" prefix, and replacing underscores with hyphens.
 *
 * Examples:
 *   Schedulely_Scheduler        → includes/class-scheduler.php
 *   Schedulely_Settings         → includes/class-settings.php
 *   Schedulely_Admin_Menu       → includes/class-admin-menu.php
 *   Schedulely_Defaults         → includes/class-defaults.php
 *
 * @since 1.6.0
 *
 * @param string $class_name The fully-qualified class name PHP needs to load.
 */
function schedulely_autoloader( string $class_name ): void {
	// Only handle Schedulely classes.
	if ( 0 !== strpos( $class_name, 'Schedulely_' ) ) {
		return;
	}

	// Strip the prefix, convert to lower-kebab-case.
	$without_prefix = substr( $class_name, strlen( 'Schedulely_' ) );
	$slug           = strtolower( str_replace( '_', '-', $without_prefix ) );
	$file           = SCHEDULELY_PLUGIN_DIR . 'includes/class-' . $slug . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

spl_autoload_register( 'schedulely_autoloader' );
