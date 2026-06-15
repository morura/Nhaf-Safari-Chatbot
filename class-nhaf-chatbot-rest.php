<?php
/**
 * REST API endpoints under /wp-json/nhaf-chatbot/v1/.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_REST
 */
class NHAF_Chatbot_REST {

	const NAMESPACE = 'nhaf-chatbot/v1';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_chat' ),
				'permission_callback' => array( __CLASS__, 'public_permission' ),
				'args'                => array(
					'message'    => array( 'required' => true, 'type' => 'string' ),
					'session_id' => array( 'required' => false, 'type' => 'string' ),
					'page_url'   => array( 'required' => false, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_lead' ),
				'permission_callback' => array( __CLASS__, 'public_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_leads' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/crawl',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_crawl' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_health' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Permission for public endpoints: verify the REST nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function public_permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'nhaf_bad_nonce', __( 'Security check failed. Please refresh the page.', 'nhaf-safari-chatbot' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Permission for admin endpoints.
	 *
	 * @return bool
	 */
	public static function admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle a chat turn.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_chat( $request ) {
		if ( ! NHAF_Chatbot_Settings::get( 'enabled' ) ) {
			return new WP_Error( 'nhaf_disabled', __( 'The chatbot is currently unavailable.', 'nhaf-safari-chatbot' ), array( 'status' => 503 ) );
		}

		$ip = NHAF_Chatbot_Security::get_client_ip();

		$rate = NHAF_Chatbot_Security::check_rate_limit( $ip );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$captcha = NHAF_Chatbot_Security::verify_recaptcha( $request->get_param( 'recaptcha_token' ) );
		if ( is_wp_error( $captcha ) ) {
			return $captcha;
		}

		$message = NHAF_Chatbot_Security::sanitize_message( $request->get_param( 'message' ) );
		if ( '' === $message ) {
			return new WP_Error( 'nhaf_empty_message', __( 'Please enter a message.', 'nhaf-safari-chatbot' ), array( 'status' => 400 ) );
		}

		$session_id = NHAF_Chatbot_Session::normalize_id( $request->get_param( 'session_id' ) );
		$page_url   = esc_url_raw( (string) $request->get_param( 'page_url' ) );

		// Graceful degradation: provider not configured yet.
		if ( ! NHAF_Chatbot_LLM::is_configured() ) {
			$fallback = __( "I'm still learning about safaris. Please check back soon or contact us directly.", 'nhaf-safari-chatbot' );
			$contact  = NHAF_Chatbot_Settings::get( 'contact_email', '' );
			if ( $contact ) {
				$fallback .= ' ' . sprintf( /* translators: %s: contact email */ __( 'You can reach us at %s.', 'nhaf-safari-chatbot' ), $contact );
			}
			return rest_ensure_response(
				array(
					'reply'      => self::format_reply( $fallback ),
					'session_id' => $session_id,
					'show_form'  => false,
				)
			);
		}

		// Detect booking intent before generating a reply.
		$booking_intent = NHAF_Chatbot_LLM::detect_booking_intent( $message );

		$history = NHAF_Chatbot_Session::get_history( $session_id );
		$context = NHAF_Chatbot_Knowledge::build_context( $message );

		$result = NHAF_Chatbot_LLM::generate( $message, $history, $context );

		if ( is_wp_error( $result ) ) {
			// Friendly fallback - never leak provider errors to the visitor.
			return new WP_REST_Response(
				array(
					'reply'          => __( "I'm having trouble reaching my knowledge base right now. Please try again shortly, or visit Safari.com directly.", 'nhaf-safari-chatbot' ),
					'session_id'     => $session_id,
					'booking_intent' => $booking_intent,
					'show_form'      => $booking_intent,
					'error'          => true,
				),
				200
			);
		}

		$reply = $result['content'];

		// Persist session + audit log.
		NHAF_Chatbot_Session::append( $session_id, $message, $reply );
		NHAF_Chatbot_Session::log_conversation(
			array(
				'session_id'   => $session_id,
				'user_message' => $message,
				'bot_response' => $reply,
				'ip_address'   => $ip,
				'page_url'     => $page_url,
				'tokens_used'  => $result['tokens'] ?? 0,
			)
		);

		return new WP_REST_Response(
			array(
				'reply'          => self::format_reply( $reply ),
				'session_id'     => $session_id,
				'booking_intent' => $booking_intent,
				'show_form'      => $booking_intent,
				'cached'         => ! empty( $result['cached'] ),
			),
			200
		);
	}

	/**
	 * Sanitise assistant output for safe rendering (server side).
	 *
	 * @param string $reply Raw reply.
	 * @return string
	 */
	private static function format_reply( $reply ) {
		// Auto-link destinations (e.g., "Kenya" → affiliate link).
		$reply = NHAF_Chatbot_Linker::apply_destination_links( $reply );

		$allowed = array(
			'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'p'      => array(),
		);

		/**
		 * Filter the allowed HTML tags in chat responses.
		 *
		 * @param array $allowed Allowed HTML map for wp_kses.
		 */
		$allowed = apply_filters( 'nhaf_chatbot_allowed_html', $allowed );

		// Convert newlines to <br> then sanitise.
		$reply = wpautop( $reply );
		return wp_kses( $reply, $allowed );
	}

	/**
	 * Handle a booking lead submission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_lead( $request ) {
		$ip   = NHAF_Chatbot_Security::get_client_ip();
		$rate = NHAF_Chatbot_Security::check_rate_limit( $ip );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$captcha = NHAF_Chatbot_Security::verify_recaptcha( $request->get_param( 'recaptcha_token' ) );
		if ( is_wp_error( $captcha ) ) {
			return $captcha;
		}

		$payload = array(
			'name'            => $request->get_param( 'name' ),
			'email'           => $request->get_param( 'email' ),
			'phone'           => $request->get_param( 'phone' ),
			'travelers'       => $request->get_param( 'travelers' ),
			'destination'     => $request->get_param( 'destination' ),
			'preferred_month' => $request->get_param( 'preferred_month' ),
			'message'         => $request->get_param( 'message' ),
		);

		$result = NHAF_Chatbot_Leads::create( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success'       => true,
				'reference'     => $result['reference'],
				'affiliate_url' => $result['affiliate_url'],
				'message'       => $result['message'],
			),
			200
		);
	}

	/**
	 * Admin: list leads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_get_leads( $request ) {
		$limit  = absint( $request->get_param( 'limit' ) ) ?: 100;
		$offset = absint( $request->get_param( 'offset' ) );
		return new WP_REST_Response( NHAF_Chatbot_Leads::get_leads( $limit, $offset ), 200 );
	}

	/**
	 * Admin: trigger a crawl.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_crawl( $request ) {
		$urls = $request->get_param( 'urls' );
		$urls = is_array( $urls ) ? array_map( 'esc_url_raw', $urls ) : array();
		$summary = NHAF_Chatbot_Knowledge::crawl( $urls );
		return new WP_REST_Response( array( 'success' => true, 'summary' => $summary ), 200 );
	}

	/**
	 * Health check.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_health() {
		return new WP_REST_Response(
			array(
				'status'   => 'ok',
				'version'  => NHAF_CHATBOT_VERSION,
				'provider' => NHAF_Chatbot_Settings::get( 'llm_provider' ),
				'enabled'  => (bool) NHAF_Chatbot_Settings::get( 'enabled' ),
				'time'     => current_time( 'mysql' ),
			),
			200
		);
	}
}
