<?php
/**
 * Admin settings controller.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Admin
 */
class NHAF_Chatbot_Admin {

	const MENU_SLUG = 'nhaf-safari-chatbot';

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_nhaf_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_nhaf_export_leads', array( 'NHAF_Chatbot_Leads', 'export_csv' ) );

		// AJAX.
		add_action( 'wp_ajax_nhaf_test_llm', array( __CLASS__, 'ajax_test_llm' ) );
		add_action( 'wp_ajax_nhaf_test_safari_api', array( __CLASS__, 'ajax_test_safari_api' ) );
		add_action( 'wp_ajax_nhaf_run_crawl', array( __CLASS__, 'ajax_run_crawl' ) );
		add_action( 'wp_ajax_nhaf_clear_kb', array( __CLASS__, 'ajax_clear_kb' ) );
		add_action( 'wp_ajax_nhaf_update_lead_status', array( __CLASS__, 'ajax_update_lead_status' ) );
	}

	/**
	 * Add the settings page under Settings.
	 */
	public static function add_menu() {
		add_options_page(
			__( 'Safari AI Chatbot', 'nhaf-safari-chatbot' ),
			__( 'Safari AI Chatbot', 'nhaf-safari-chatbot' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'nhaf-admin', NHAF_CHATBOT_URL . 'admin/css/admin.css', array(), NHAF_CHATBOT_VERSION );

		wp_enqueue_script(
			'nhaf-admin',
			NHAF_CHATBOT_URL . 'admin/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			NHAF_CHATBOT_VERSION,
			true
		);

		wp_localize_script(
			'nhaf-admin',
			'nhafAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'nhaf_admin_ajax' ),
				'i18n'    => array(
					'testing'      => __( 'Testing…', 'nhaf-safari-chatbot' ),
					'crawling'     => __( 'Crawling… this may take a moment.', 'nhaf-safari-chatbot' ),
					'confirmClear' => __( 'Clear the entire knowledge base? This cannot be undone.', 'nhaf-safari-chatbot' ),
					'success'      => __( 'Success', 'nhaf-safari-chatbot' ),
					'failed'       => __( 'Failed', 'nhaf-safari-chatbot' ),
				),
			)
		);
	}

	/**
	 * Get the active tab from the query string.
	 *
	 * @return string
	 */
	private static function active_tab() {
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		$allowed = array( 'general', 'llm', 'knowledge', 'safari_api', 'leads', 'security' );
		return in_array( $tab, $allowed, true ) ? $tab : 'general';
	}

	/**
	 * Render the settings page shell + active tab.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nhaf-safari-chatbot' ) );
		}

		$active = self::active_tab();
		$tabs   = array(
			'general'    => __( 'General', 'nhaf-safari-chatbot' ),
			'llm'        => __( 'LLM Configuration', 'nhaf-safari-chatbot' ),
			'knowledge'  => __( 'Knowledge Base', 'nhaf-safari-chatbot' ),
			'safari_api' => __( 'Safari.com API', 'nhaf-safari-chatbot' ),
			'leads'      => __( 'Lead Management', 'nhaf-safari-chatbot' ),
			'security'   => __( 'Security & Rate Limiting', 'nhaf-safari-chatbot' ),
		);

		echo '<div class="wrap nhaf-admin-wrap">';
		echo '<h1>' . esc_html__( 'Nomad Horizons Safari AI Chatbot', 'nhaf-safari-chatbot' ) . '</h1>';

		// Notices.
		if ( isset( $_GET['nhaf_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'nhaf-safari-chatbot' ) . '</p></div>';
		}

		// Tab nav.
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'options-general.php' ) );
			$class = ( $active === $slug ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
		}
		echo '</h2>';

		// Tab body.
		require NHAF_CHATBOT_PATH . 'admin/views/tab-' . $active . '.php';

		echo '</div>';
	}

	/**
	 * Open a settings form for a tab (shared markup).
	 *
	 * @param string $tab Tab slug.
	 */
	public static function form_open( $tab ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nhaf-settings-form">';
		echo '<input type="hidden" name="action" value="nhaf_save_settings" />';
		echo '<input type="hidden" name="nhaf_tab" value="' . esc_attr( $tab ) . '" />';
		wp_nonce_field( 'nhaf_save_settings_' . $tab, 'nhaf_nonce' );
	}

	/**
	 * Close the settings form with a submit button.
	 */
	public static function form_close() {
		submit_button( __( 'Save Changes', 'nhaf-safari-chatbot' ) );
		echo '</form>';
	}

	/**
	 * Handle settings save (admin-post).
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nhaf-safari-chatbot' ) );
		}

		$tab = isset( $_POST['nhaf_tab'] ) ? sanitize_key( wp_unslash( $_POST['nhaf_tab'] ) ) : 'general';

		// Verify nonce. Use wp_verify_nonce instead of check_admin_referer
		// to avoid HTTP_REFERER issues (some servers/CDNs strip this header).
		$nonce = isset( $_POST['nhaf_nonce'] ) ? wp_unslash( $_POST['nhaf_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'nhaf_save_settings_' . $tab ) ) {
			wp_die(
				esc_html__( 'Security verification failed. Please try again.', 'nhaf-safari-chatbot' ),
				esc_html__( 'Nonce verification failed', 'nhaf-safari-chatbot' ),
				array( 'response' => 403 )
			);
		}

		$update = self::sanitize_tab_input( $tab, $_POST );
		NHAF_Chatbot_Settings::save( $update );

		// Reschedule crawl if interval changed.
		if ( 'knowledge' === $tab ) {
			NHAF_Chatbot_Knowledge::maybe_reschedule_crawl();
		}

		$redirect = add_query_arg(
			array(
				'page'       => self::MENU_SLUG,
				'tab'        => $tab,
				'nhaf_saved' => 1,
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Sanitise per-tab input. Secret fields left blank are preserved (not overwritten).
	 *
	 * @param string $tab  Tab slug.
	 * @param array  $post Raw $_POST.
	 * @return array
	 */
	private static function sanitize_tab_input( $tab, $post ) {
		$out = array();
		$p   = wp_unslash( $post );

		$text     = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? sanitize_text_field( $p[ $k ] ) : ''; };
		$textarea = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? sanitize_textarea_field( $p[ $k ] ) : ''; };
		$int      = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? absint( $p[ $k ] ) : 0; };
		$float    = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? (float) $p[ $k ] : 0; };
		$bool     = function ( $k ) use ( $p ) { return empty( $p[ $k ] ) ? 0 : 1; };
		$color    = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? sanitize_hex_color( $p[ $k ] ) : ''; };

		// Secret helper: only update if non-empty.
		$secret = function ( $k ) use ( $p, &$out ) {
			if ( isset( $p[ $k ] ) && '' !== trim( $p[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( $p[ $k ] );
			}
		};

		switch ( $tab ) {
			case 'general':
				$out['enabled']          = $bool( 'enabled' );
				$out['widget_position']  = in_array( $text( 'widget_position' ), array( 'bottom-right', 'bottom-left' ), true ) ? $text( 'widget_position' ) : 'bottom-right';
				$out['offset_x']         = $int( 'offset_x' );
				$out['offset_y']         = $int( 'offset_y' );
				$out['welcome_message']  = $textarea( 'welcome_message' );
				$out['color_primary']    = $color( 'color_primary' ) ?: '#0b6b3a';
				$out['color_secondary']  = $color( 'color_secondary' ) ?: '#f4a300';
				$out['color_text']       = $color( 'color_text' ) ?: '#1a1a1a';
				$out['business_name']    = $text( 'business_name' );
				$out['contact_email']    = sanitize_email( $p['contact_email'] ?? '' );
				$out['excluded_post_types'] = isset( $p['excluded_post_types'] ) && is_array( $p['excluded_post_types'] )
					? array_map( 'sanitize_key', $p['excluded_post_types'] )
					: array();
				break;

			case 'llm':
				$provider = $text( 'llm_provider' );
				$out['llm_provider']        = in_array( $provider, array( 'openai', 'anthropic', 'ollama' ), true ) ? $provider : 'openai';
				$out['openai_model']        = $text( 'openai_model' );
				$out['openai_temperature']  = max( 0, min( 1, $float( 'openai_temperature' ) ) );
				$out['openai_max_tokens']   = max( 50, min( 4000, $int( 'openai_max_tokens' ) ) );
				$out['anthropic_model']     = $text( 'anthropic_model' );
				$out['anthropic_temperature'] = max( 0, min( 1, $float( 'anthropic_temperature' ) ) );
				$out['ollama_endpoint']     = esc_url_raw( $p['ollama_endpoint'] ?? '' );
				$out['ollama_model']        = $text( 'ollama_model' );
				$out['system_prompt']       = $textarea( 'system_prompt' );
				$secret( 'openai_api_key' );
				$secret( 'anthropic_api_key' );
				break;

			case 'knowledge':
				$out['recrawl_interval']     = in_array( $text( 'recrawl_interval' ), array( 'daily', 'weekly', 'monthly' ), true ) ? $text( 'recrawl_interval' ) : 'weekly';
				$out['enable_web_search']    = $bool( 'enable_web_search' );
				$out['search_provider']      = in_array( $text( 'search_provider' ), array( 'serpapi', 'google_cse' ), true ) ? $text( 'search_provider' ) : 'serpapi';
				$out['search_cse_id']        = $text( 'search_cse_id' );
				$out['search_results_count'] = max( 3, min( 10, $int( 'search_results_count' ) ) );
				$secret( 'search_api_key' );

				// Crawl URLs: newline separated textarea.
				$urls = array();
				if ( ! empty( $p['crawl_urls'] ) ) {
					foreach ( preg_split( '/\r\n|\r|\n/', $p['crawl_urls'] ) as $line ) {
						$line = esc_url_raw( trim( $line ) );
						if ( $line ) { $urls[] = $line; }
					}
				}
				$out['crawl_urls'] = $urls;
				break;

			case 'safari_api':
				$out['use_finder_token'] = $bool( 'use_finder_token' );
				$out['safari_api_base']  = esc_url_raw( $p['safari_api_base'] ?? '' );
				$out['safari_endpoints'] = isset( $p['safari_endpoints'] ) && is_array( $p['safari_endpoints'] )
					? array_map( 'sanitize_key', $p['safari_endpoints'] )
					: array();
				$secret( 'safari_api_token' );
				break;

			case 'leads':
				$out['lead_notify_email']  = sanitize_email( $p['lead_notify_email'] ?? '' );
				$out['autoresponder']      = $textarea( 'autoresponder' );
				$out['affiliate_base_url'] = esc_url_raw( $p['affiliate_base_url'] ?? '' );
				$out['affiliate_id']       = $text( 'affiliate_id' );
				break;

			case 'security':
				$out['rate_limit_per_min'] = $int( 'rate_limit_per_min' );
				$out['daily_limit_per_ip'] = $int( 'daily_limit_per_ip' );
				$out['max_message_length'] = max( 50, min( 5000, $int( 'max_message_length' ) ) );
				$out['recaptcha_enabled']  = $bool( 'recaptcha_enabled' );
				$out['recaptcha_version']  = in_array( $text( 'recaptcha_version' ), array( 'v2', 'v3' ), true ) ? $text( 'recaptcha_version' ) : 'v3';
				$out['recaptcha_site_key'] = $text( 'recaptcha_site_key' );
				$out['delete_data_on_uninstall'] = $bool( 'delete_data_on_uninstall' );
				$secret( 'recaptcha_secret' );

				$blocklist = array();
				if ( ! empty( $p['ip_blocklist'] ) ) {
					foreach ( preg_split( '/\r\n|\r|\n|,/', $p['ip_blocklist'] ) as $ip ) {
						$ip = trim( $ip );
						if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { $blocklist[] = $ip; }
					}
				}
				$out['ip_blocklist'] = $blocklist;
				break;
		}

		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* AJAX handlers                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Verify AJAX nonce + capability.
	 */
	private static function verify_ajax() {
		check_ajax_referer( 'nhaf_admin_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'nhaf-safari-chatbot' ) ), 403 );
		}
	}

	/**
	 * AJAX: test the configured LLM provider.
	 */
	public static function ajax_test_llm() {
		self::verify_ajax();
		$result = NHAF_Chatbot_LLM::test_connection();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Connection OK. Model responded.', 'nhaf-safari-chatbot' ), 'reply' => wp_strip_all_tags( $result['content'] ) ) );
	}

	/**
	 * AJAX: test the Safari.com API token.
	 */
	public static function ajax_test_safari_api() {
		self::verify_ajax();
		$token = NHAF_Chatbot_Knowledge::get_safari_token();
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'No Safari.com API token configured or found.', 'nhaf-safari-chatbot' ) ) );
		}
		$base     = rtrim( NHAF_Chatbot_Settings::get( 'safari_api_base', 'https://api.safari.com/v1' ), '/' );
		$response = wp_remote_get(
			$base . '/destinations',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success( array( 'message' => __( 'Safari.com API reachable (HTTP ', 'nhaf-safari-chatbot' ) . $code . ').' ) );
		}
		wp_send_json_error( array( 'message' => __( 'Safari.com API returned HTTP ', 'nhaf-safari-chatbot' ) . $code . '.' ) );
	}

	/**
	 * AJAX: run a crawl now.
	 */
	public static function ajax_run_crawl() {
		self::verify_ajax();
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.PHP.DiscouragedPHPFunctions
		$summary = NHAF_Chatbot_Knowledge::crawl();
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: crawled 2: skipped 3: failed */
					__( 'Crawl complete. Indexed: %1$d, unchanged: %2$d, failed: %3$d.', 'nhaf-safari-chatbot' ),
					$summary['crawled'],
					$summary['skipped'],
					$summary['failed']
				),
			)
		);
	}

	/**
	 * AJAX: clear knowledge base.
	 */
	public static function ajax_clear_kb() {
		self::verify_ajax();
		NHAF_Chatbot_Knowledge::clear();
		wp_send_json_success( array( 'message' => __( 'Knowledge base cleared.', 'nhaf-safari-chatbot' ) ) );
	}

	/**
	 * AJAX: update a lead's status.
	 */
	public static function ajax_update_lead_status() {
		check_ajax_referer( 'nhaf_admin_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'nhaf-safari-chatbot' ) ), 403 );
		}
		$id     = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'new';
		$ok     = NHAF_Chatbot_Leads::update_status( $id, $status );
		if ( $ok ) {
			wp_send_json_success( array( 'message' => __( 'Updated.', 'nhaf-safari-chatbot' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Update failed.', 'nhaf-safari-chatbot' ) ) );
	}
}
