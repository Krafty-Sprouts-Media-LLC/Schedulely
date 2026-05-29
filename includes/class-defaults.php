<?php
/**
 * Filename: class-defaults.php
 * Description: Centralised default values for all Schedulely options.
 *              Every get_option() call and every add_option() call in the
 *              plugin reads its default from here so the value can never
 *              drift between activation and runtime reads.
 *
 * @package Schedulely
 * @since   1.6.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schedulely_Defaults
 *
 * All constants are public so any class can read them directly:
 *   $default = Schedulely_Defaults::POSTS_PER_DAY;
 *
 * @since 1.6.0
 */
class Schedulely_Defaults {

	// -------------------------------------------------------------------------
	// Scheduling
	// -------------------------------------------------------------------------

	/** @var string Default post status to draw from. */
	const POST_STATUS = 'draft';

	/** @var int Default daily post quota. */
	const POSTS_PER_DAY = 8;

	/** @var string Default window start time (12-hour format). */
	const START_TIME = '5:00 PM';

	/** @var string Default window end time (12-hour format). */
	const END_TIME = '11:00 PM';

	/**
	 * Default active days of the week (0 = Sunday … 6 = Saturday).
	 * All seven days enabled by default.
	 *
	 * @var array<int>
	 */
	const ACTIVE_DAYS = [ 1, 2, 3, 4, 5, 6, 0 ];

	/** @var int Default minimum interval between posts in minutes. */
	const MIN_INTERVAL = 40;

	/** @var bool Default shuffle-queue state. */
	const SHUFFLE_QUEUE = true;

	/** @var array<string> Default post types to schedule. */
	const POST_TYPES = [ 'post' ];

	/**
	 * Default scheduling mode.
	 *
	 * 'random'     — Random times within the window (current default, organic appearance).
	 * 'sequential' — Perfectly even spacing, 100% efficiency, predictable.
	 * 'hybrid'     — Even slots, random time within each slot. Near-100% efficiency + organic look.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	const SCHEDULING_MODE = 'random';

	// -------------------------------------------------------------------------
	// Automation
	// -------------------------------------------------------------------------

	/**
	 * Default auto-schedule state.
	 *
	 * Intentionally OFF — the plugin must not silently start running automated
	 * tasks immediately on activation without the user opting in.
	 *
	 * @var bool
	 */
	const AUTO_SCHEDULE = false;

	// -------------------------------------------------------------------------
	// Author management
	// -------------------------------------------------------------------------

	/** @var bool Default randomize-authors state. */
	const RANDOMIZE_AUTHORS = false;

	/** @var array<int> Default excluded-author list (empty = none excluded). */
	const EXCLUDED_AUTHORS = [];

	/** @var array<int> Default preserved-author list (empty = none preserved). */
	const PRESERVED_AUTHORS = [];

	// -------------------------------------------------------------------------
	// Notifications
	// -------------------------------------------------------------------------

	/** @var bool Default email-notifications state. */
	const EMAIL_NOTIFICATIONS = true;

	/** @var array<int> Default notification-user list (empty = fall back to admin). */
	const NOTIFICATION_USERS = [];

	// -------------------------------------------------------------------------
	// AI ordering (legacy path)
	// -------------------------------------------------------------------------

	/** @var bool Default AI ordering state. */
	const AI_ORDER_ENABLED = false;

	/**
	 * Default queue-ordering method.
	 *
	 * 'ai'  — Ask the configured AI to order the queue, falling back to the
	 *         deterministic PHP ordering automatically if the request fails.
	 * 'php' — Skip the network entirely and order the queue in PHP by grouping
	 *         posts by slug-stem (target state removed) and interleaving them
	 *         for even series spacing. Instant, free, never times out.
	 *
	 * @since 1.8.0
	 * @since 1.8.2 Default changed from 'ai' to 'php'. Three independent 300-post
	 *              runs showed the AI looping to ~59K output tokens over ~6 minutes
	 *              and still needing PHP reconciliation to produce a usable order;
	 *              PHP ordering is instant, free, and deterministic.
	 * @var string
	 */
	const ORDERING_METHOD = 'php';

	/**
	 * Default PHP spacing strategy (used when ordering runs in PHP — either as the
	 * chosen method or as the automatic fallback after an AI failure).
	 *
	 * 'even'        — Position-based: each series is spread evenly across the whole
	 *                 queue (and therefore across publish days) end-to-end.
	 * 'round_robin' — One post per series per pass; simpler, but the largest series
	 *                 can bunch up near the tail of the queue.
	 *
	 * @since 1.8.4
	 * @var string
	 */
	const PHP_SPREAD = 'even';

	/** @var bool Default US timezone-aware queue ordering state. */
	const AI_US_TIMEZONE_ORDERING = false;

	/** @var string Default API key (empty = not configured). */
	const AI_API_KEY = '';

	/** @var string Default OpenAI-compatible base URL. */
	const AI_BASE_URL = 'https://api.deepseek.com/v1';

	/** @var string Default model identifier. */
	const AI_MODEL = 'deepseek-v4-flash';

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/** @var int Maximum posts fetched per scheduling run. */
	const MAX_POSTS_PER_RUN = 1500;

	/** @var int Safety buffer in seconds before the scheduling window opens. */
	const SCHEDULE_SAFETY_BUFFER_SECONDS = 1800; // 30 minutes
}
