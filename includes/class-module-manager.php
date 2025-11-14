<?php
/**
 * Filename: class-module-manager.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.3.0
 * Last Modified: 14/11/2025
 * Description: Module registration and lifecycle management for Medialytic.
 *
 * @package Medialytic
 * @since 1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles registration, bootstrapping, and retrieval of Medialytic modules.
 *
 * @since 1.3.0
 */
class Medialytic_Module_Manager {

	/**
	 * Core instance.
	 *
	 * @var Medialytic_Core
	 * @since 1.3.0
	 */
	private $core;

	/**
	 * Registered module definitions.
	 *
	 * @var array
	 * @since 1.3.0
	 */
	private $definitions = array();

	/**
	 * Instantiated module objects keyed by slug.
	 *
	 * @var array
	 * @since 1.3.0
	 */
	private $instances = array();

	/**
	 * Constructor.
	 *
	 * @param Medialytic_Core $core Core instance.
	 * @since 1.3.0
	 */
	public function __construct( Medialytic_Core $core ) {
		$this->core = $core;
	}

	/**
	 * Register module definitions.
	 *
	 * @param array $modules Module definition array.
	 * @since 1.3.0
	 * @return void
	 */
	public function register_modules( array $modules ) {
		$this->definitions = $this->sort_modules_by_priority( $modules );
	}

	/**
	 * Instantiate registered modules.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function boot() {
		foreach ( $this->definitions as $slug => $definition ) {
			if ( $this->is_module_disabled( $definition ) ) {
				continue;
			}

			$this->maybe_require_file( $definition );

			$instance = $this->instantiate_module( $definition );

			if ( null === $instance ) {
				continue;
			}

			$this->instances[ $slug ] = $instance;
		}
	}

	/**
	 * Retrieve a module instance.
	 *
	 * @param string $slug Module slug.
	 * @since 1.3.0
	 * @return mixed|null
	 */
	public function get( $slug ) {
		return isset( $this->instances[ $slug ] ) ? $this->instances[ $slug ] : null;
	}

	/**
	 * Get all module instances.
	 *
	 * @since 1.3.0
	 * @return array
	 */
	public function all() {
		return $this->instances;
	}

	/**
	 * Sort module definitions by priority.
	 *
	 * @param array $modules Module definitions.
	 * @since 1.3.0
	 * @return array
	 */
	private function sort_modules_by_priority( array $modules ) {
		uasort(
			$modules,
			function ( $a, $b ) {
				$priority_a = isset( $a['priority'] ) ? (int) $a['priority'] : 10;
				$priority_b = isset( $b['priority'] ) ? (int) $b['priority'] : 10;

				if ( $priority_a === $priority_b ) {
					return 0;
				}

				return ( $priority_a < $priority_b ) ? -1 : 1;
			}
		);

		return $modules;
	}

	/**
	 * Determine if a module is disabled via callback.
	 *
	 * @param array $definition Module definition.
	 * @since 1.3.0
	 * @return bool
	 */
	private function is_module_disabled( array $definition ) {
		if ( empty( $definition['enabled_callback'] ) || ! is_callable( $definition['enabled_callback'] ) ) {
			return false;
		}

		return ! (bool) call_user_func( $definition['enabled_callback'], $this->core );
	}

	/**
	 * Require a module file if provided.
	 *
	 * @param array $definition Module definition.
	 * @since 1.3.0
	 * @return void
	 */
	private function maybe_require_file( array $definition ) {
		if ( empty( $definition['path'] ) ) {
			return;
		}

		$path = $definition['path'];

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Instantiate a module class or factory.
	 *
	 * @param array $definition Module definition.
	 * @since 1.3.0
	 * @return mixed|null
	 */
	private function instantiate_module( array $definition ) {
		if ( isset( $definition['factory'] ) && is_callable( $definition['factory'] ) ) {
			return call_user_func( $definition['factory'], $this->core );
		}

		$class = isset( $definition['class'] ) ? $definition['class'] : '';

		if ( ! $class || ! class_exists( $class ) ) {
			return null;
		}

		return new $class( $this->core );
	}
}

