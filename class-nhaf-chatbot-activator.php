<?php
/**
 * Fired during plugin activation.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Activator
 *
 * Creates custom tables, seeds default options and registers cron events.
 */
class NHAF_Chatbot_Activator {

	/**
	 * Run activation routines.
	 */
	public static function activate() {
		self::check_requirements();
		self::create_tables();
		self::seed_default_options();
		self::register_cpt_for_flush();

		// Generate a per-site encryption salt if not present.
		if ( ! get_option( 'nhaf_chatbot_enc_salt' ) ) {
			add_option( 'nhaf_chatbot_enc_salt', wp_generate_password( 64, true, true ) );
		}

		flush_rewrite_rules();
		update_option( 'nhaf_chatbot_db_version', NHAF_CHATBOT_DB_VERSION );
	}

	/**
	 * Create the custom database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$leads_table     = $wpdb->prefix . 'nhaf_chatbot_leads';
		$convo_table     = $wpdb->prefix . 'nhaf_chatbot_conversations';
		$knowledge_table = $wpdb->prefix . 'nhaf_chatbot_knowledge';

		$sql_leads = "CREATE TABLE {$leads_table} (
			id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(100) NOT NULL,
			email VARCHAR(100) NOT NULL,
			phone VARCHAR(50) DEFAULT '',
			travelers INT DEFAULT 0,
			destination VARCHAR(100) DEFAULT '',
			preferred_month VARCHAR(50) DEFAULT '',
			message TEXT,
			user_ip VARCHAR(45) DEFAULT '',
			user_agent TEXT,
			reference VARCHAR(40) DEFAULT '',
			status VARCHAR(20) DEFAULT 'new',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY email (email),
			KEY status (status)
		) {$charset_collate};";

		$sql_convo = "CREATE TABLE {$convo_table} (
			id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			session_id VARCHAR(64) NOT NULL,
			user_message TEXT,
			bot_response TEXT,
			ip_address VARCHAR(45) DEFAULT '',
			page_url VARCHAR(500) DEFAULT '',
			tokens_used INT DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY session_id (session_id)
		) {$charset_collate};";

		$sql_knowledge = "CREATE TABLE {$knowledge_table} (
			id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			url VARCHAR(500) NOT NULL,
			content LONGTEXT,
			content_hash VARCHAR(64) DEFAULT '',
			last_crawled DATETIME DEFAULT NULL,
			status VARCHAR(20) DEFAULT 'pending',
			UNIQUE KEY url (url(191))
		) {$charset_collate};";

		dbDelta( $sql_leads );
		dbDelta( $sql_convo );
		dbDelta( $sql_knowledge );
	}

	/**
	 * Seed sensible default settings (without overwriting existing).
	 */
	/**
	 * Verify PHP version and required extensions; abort activation if missing.
	 */
	private static function check_requirements() {
		$missing = array();

		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			$missing[] = 'PHP 7.4+';
		}
		foreach ( array( 'curl', 'json', 'openssl' ) as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing[] = 'ext-' . $ext;
			}
		}

		if ( $missing ) {
			deactivate_plugins( NHAF_CHATBOT_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: comma-separated requirements */
						__( 'Nomad Horizons Safari AI Chatbot requires: %s. Please ask your host to enable these and try again.', 'nhaf-safari-chatbot' ),
						implode( ', ', $missing )
					)
				),
				esc_html__( 'Plugin requirements not met', 'nhaf-safari-chatbot' ),
				array( 'back_link' => true )
			);
		}
	}

	public static function seed_default_options() {
		$existing = get_option( NHAF_CHATBOT_OPTION_KEY );
		if ( false !== $existing ) {
			return;
		}

		$defaults = array(
			// General.
			'enabled'             => 1,
			'excluded_post_types' => array(),
			'widget_position'     => 'bottom-right',
			'offset_x'            => 20,
			'offset_y'            => 20,
			'welcome_message'     => __( "Hi! I'm your Safari AI assistant. Ask me anything about African safaris, destinations, or how to book your adventure!", 'nhaf-safari-chatbot' ),
			'color_primary'       => '#0b6b3a',
			'color_secondary'     => '#f4a300',
			'color_text'          => '#1a1a1a',
			'business_name'       => get_bloginfo( 'name' ),
			'contact_email'       => get_option( 'admin_email' ),

			// LLM.
			'llm_provider'        => 'openai',
			'openai_api_key'      => '',
			'openai_model'        => 'gpt-3.5-turbo',
			'openai_temperature'  => 0.5,
			'openai_max_tokens'   => 600,
			'anthropic_api_key'   => '',
			'anthropic_model'     => 'claude-3-haiku-20240307',
			'anthropic_temperature' => 0.5,
			'ollama_endpoint'     => 'http://localhost:11434',
			'ollama_model'        => 'llama2',
			'system_prompt'       => self::default_system_prompt(),

			// Knowledge base.
			'recrawl_interval'    => 'weekly',
			'crawl_urls'          => array(),
			'enable_web_search'   => 0,
			'search_provider'     => 'serpapi',
			'search_api_key'      => '',
			'search_results_count' => 5,

			// Safari.com API.
			'use_finder_token'    => 1,
			'safari_api_token'    => '',
			'safari_api_base'     => 'https://api.safari.com/v1',
			'safari_endpoints'    => array( 'destinations', 'experiences', 'safaris' ),

			// Leads.
			'lead_notify_email'   => get_option( 'admin_email' ),
			'autoresponder'       => __( "Thanks for your enquiry! A Nomad Horizons safari specialist will be in touch within 24 hours.", 'nhaf-safari-chatbot' ),
			'affiliate_base_url'  => 'https://www.safari.com/book',
			'affiliate_id'        => '',

			// Security.
			'rate_limit_per_min'  => 20,
			'daily_limit_per_ip'  => 200,
			'ip_blocklist'        => array(),
			'max_message_length'  => 1000,
			'recaptcha_enabled'   => 0,
			'recaptcha_version'   => 'v3',
			'recaptcha_site_key'  => '',
			'recaptcha_secret'    => '',

			// Caching / sessions.
			'cache_ttl_hours'     => 24,
			'context_message_count' => 10,
		);

		add_option( NHAF_CHATBOT_OPTION_KEY, $defaults );
	}

	/**
	 * Register the knowledge CPT so rewrite rules can be flushed on activation.
	 */
	private static function register_cpt_for_flush() {
		if ( class_exists( 'NHAF_Chatbot_Knowledge' ) ) {
			NHAF_Chatbot_Knowledge::register_post_type();
		}
	}

	/**
	 * Default system prompt used for the LLM.
	 *
	 * @return string
	 */
	public static function default_system_prompt() {
		return 'You are a helpful safari travel assistant for Safari.com. Answer questions about African safaris, destinations (Kenya, Tanzania, South Africa, etc.), wildlife, best times to visit, packing tips, and booking processes. If asked about booking, guide users to fill out the booking form. Never invent pricing or availability - direct users to Safari.com for current prices.';
	}
}
