<?php
/**
 * Medialytic Embed Counter Class
 *
 * @package Medialytic
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embed counting functionality
 *
 * @since 1.0.0
 */
class Medialytic_Embed_Counter {

	/**
	 * Core instance
	 *
	 * @var Medialytic_Core
	 * @since 1.0.0
	 */
	private $core;

	/**
	 * Constructor
	 *
	 * @param Medialytic_Core $core Core instance.
	 * @since 1.0.0
	 */
	public function __construct( $core ) {
		$this->core = $core;
	}

	/**
	 * Count embeds in content
	 *
	 * @param string $content Content to analyze.
	 * @return int Embed count
	 * @since 1.0.0
	 */
	public function count( $content ) {
		return $this->core->count_embeds( $content );
	}
}