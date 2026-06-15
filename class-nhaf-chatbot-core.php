<?php
/**
 * Core orchestrator (singleton).
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Core
 */
class NHAF_Chatbot_Core {

	/**
	 * Singleton instance.
	 *
	 * @var NHAF_Chatbot_Core|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return NHAF_Chatbot_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot all subsystems.
	 */
	public function run() {
		// Custom cron schedules (monthly).
		add_filter( 'cron_schedules', array( 'NHAF_Chatbot_Knowledge', 'add_cron_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval

		// Knowledge / CPT / crawl scheduling.
		NHAF_Chatbot_Knowledge::init();

		// REST endpoints.
		NHAF_Chatbot_REST::init();

		// Shortcodes.
		NHAF_Chatbot_Shortcodes::init();

		// Notices + scheduled summary emails (cron handler must load everywhere).
		NHAF_Chatbot_Notices::init();

		// Front-end widget + assets.
		if ( ! is_admin() ) {
			NHAF_Chatbot_Public::init();
		}

		// Admin UI.
		if ( is_admin() ) {
			NHAF_Chatbot_Admin::init();
		}

		// Widget registration.
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// Settings link on the plugins screen.
		add_filter( 'plugin_action_links_' . NHAF_CHATBOT_BASENAME, array( $this, 'plugin_action_links' ) );

		// Ensure the recrawl event is scheduled.
		if ( ! wp_next_scheduled( 'nhaf_chatbot_recrawl_event' ) ) {
			NHAF_Chatbot_Knowledge::maybe_reschedule_crawl();
		}

		// Run DB upgrades if needed.
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ), 30 );
	}

	/**
	 * Register the sidebar widget.
	 */
	public function register_widget() {
		if ( class_exists( 'NHAF_Chatbot_Widget' ) ) {
			register_widget( 'NHAF_Chatbot_Widget' );
		}
	}

	/**
	 * Add a Settings link to the plugin row.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings = '<a href="' . esc_url( admin_url( 'options-general.php?page=nhaf-safari-chatbot' ) ) . '">' . esc_html__( 'Settings', 'nhaf-safari-chatbot' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Run database upgrades when the stored version is behind.
	 */
	public function maybe_upgrade() {
		$stored = get_option( 'nhaf_chatbot_db_version' );
		if ( $stored !== NHAF_CHATBOT_DB_VERSION ) {
			require_once NHAF_CHATBOT_PATH . 'includes/class-nhaf-chatbot-activator.php';
			NHAF_Chatbot_Activator::create_tables();
			update_option( 'nhaf_chatbot_db_version', NHAF_CHATBOT_DB_VERSION );
		}
	}
}
