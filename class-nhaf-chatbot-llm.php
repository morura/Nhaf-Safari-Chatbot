<?php
/**
 * LLM integration layer: OpenAI, Anthropic and Ollama.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_LLM
 */
class NHAF_Chatbot_LLM {

	/**
	 * Generate an assistant reply.
	 *
	 * @param string $user_message Sanitised user message.
	 * @param array  $history      Prior turns: [ ['role'=>'user'|'assistant','content'=>'...'], ... ].
	 * @param string $context      Knowledge / search / API context to inject.
	 * @return array|WP_Error { 'content' => string, 'tokens' => int }
	 */
	public static function generate( $user_message, $history = array(), $context = '' ) {
		$provider = NHAF_Chatbot_Settings::get( 'llm_provider', 'openai' );

		$system_prompt = NHAF_Chatbot_Settings::get( 'system_prompt' );
		if ( empty( $system_prompt ) ) {
			$system_prompt = NHAF_Chatbot_Activator::default_system_prompt();
		}

		if ( ! empty( $context ) ) {
			$system_prompt .= "\n\nUse the following reference information from Safari.com to ground your answers. If it does not contain the answer, say so honestly and suggest visiting Safari.com.\n\n---\n" . $context . "\n---";
		}

		/**
		 * Filter the system prompt before it is sent to the LLM.
		 *
		 * @param string $system_prompt The assembled system prompt.
		 * @param string $user_message  The current user message.
		 */
		$system_prompt = apply_filters( 'nhaf_chatbot_system_prompt', $system_prompt, $user_message );

		// Cache identical questions (ignoring history) to save tokens.
		$cache_key = 'nhaf_llm_' . md5( $provider . '|' . $system_prompt . '|' . $user_message );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && empty( $history ) ) {
			return array(
				'content' => $cached,
				'tokens'  => 0,
				'cached'  => true,
			);
		}

		switch ( $provider ) {
			case 'anthropic':
				$result = self::call_anthropic( $system_prompt, $user_message, $history );
				break;
			case 'ollama':
				$result = self::call_ollama( $system_prompt, $user_message, $history );
				break;
			case 'openai':
			default:
				$result = self::call_openai( $system_prompt, $user_message, $history );
				break;
		}

		if ( is_wp_error( $result ) ) {
			/**
			 * Fires when an LLM API call fails.
			 *
			 * @param WP_Error $result   The error.
			 * @param string   $provider Provider slug.
			 */
			do_action( 'nhaf_chatbot_on_llm_error', $result, $provider );
			NHAF_Chatbot_Security::log( 'LLM error (' . $provider . '): ' . $result->get_error_message() );
			return $result;
		}

		/**
		 * Filter the LLM response before it is returned to the user.
		 *
		 * @param string $content      Raw model output.
		 * @param string $user_message The user message.
		 */
		$result['content'] = apply_filters( 'nhaf_chatbot_before_response', $result['content'], $user_message );

		// Cache only when there is no per-user history (generic Q&A).
		if ( empty( $history ) && ! empty( $result['content'] ) ) {
			$ttl = (int) NHAF_Chatbot_Settings::get( 'cache_ttl_hours', 24 ) * HOUR_IN_SECONDS;
			set_transient( $cache_key, $result['content'], $ttl );
		}

		return $result;
	}

	/**
	 * Build the OpenAI/Anthropic-style messages array from history.
	 *
	 * @param string $user_message Current message.
	 * @param array  $history      Prior turns.
	 * @return array
	 */
	private static function build_messages( $user_message, $history ) {
		$messages = array();
		foreach ( $history as $turn ) {
			if ( empty( $turn['role'] ) || ! isset( $turn['content'] ) ) {
				continue;
			}
			$role = ( 'assistant' === $turn['role'] ) ? 'assistant' : 'user';
			$messages[] = array(
				'role'    => $role,
				'content' => (string) $turn['content'],
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => $user_message,
		);
		return $messages;
	}

	/**
	 * POST to an external API with retry + exponential backoff.
	 *
	 * Retries up to 3 attempts on network errors and 5xx/429 responses,
	 * sleeping 1s then 2s between attempts.
	 *
	 * @param string $url  Endpoint URL.
	 * @param array  $args wp_remote_post args.
	 * @return array|WP_Error
	 */
	private static function remote_post_with_retry( $url, $args ) {
		$attempts = 3;
		$response = new WP_Error( 'nhaf_http', __( 'Request failed.', 'nhaf-safari-chatbot' ) );

		for ( $i = 0; $i < $attempts; $i++ ) {
			if ( $i > 0 ) {
				sleep( (int) pow( 2, $i - 1 ) ); // 1s, 2s.
			}

			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				NHAF_Chatbot_Security::log( sprintf( 'LLM request attempt %d failed: %s', $i + 1, $response->get_error_message() ) );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 429 === $code || $code >= 500 ) {
				NHAF_Chatbot_Security::log( sprintf( 'LLM request attempt %d returned HTTP %d, retrying.', $i + 1, $code ) );
				continue;
			}

			return $response;
		}

		return $response;
	}

	/**
	 * Whether the active LLM provider has the credentials it needs.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$provider = NHAF_Chatbot_Settings::get( 'llm_provider', 'openai' );

		switch ( $provider ) {
			case 'anthropic':
				return '' !== (string) NHAF_Chatbot_Settings::get( 'anthropic_api_key', '' );
			case 'ollama':
				return '' !== (string) NHAF_Chatbot_Settings::get( 'ollama_endpoint', '' );
			case 'openai':
			default:
				return '' !== (string) NHAF_Chatbot_Settings::get( 'openai_api_key', '' );
		}
	}

	/**
	 * Call the OpenAI Chat Completions API.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_message  User message.
	 * @param array  $history       History.
	 * @return array|WP_Error
	 */
	private static function call_openai( $system_prompt, $user_message, $history ) {
		$api_key = NHAF_Chatbot_Settings::get( 'openai_api_key' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'nhaf_no_key', __( 'OpenAI API key is not configured.', 'nhaf-safari-chatbot' ) );
		}

		$messages   = array( array( 'role' => 'system', 'content' => $system_prompt ) );
		$messages   = array_merge( $messages, self::build_messages( $user_message, $history ) );

		$body = array(
			'model'       => NHAF_Chatbot_Settings::get( 'openai_model', 'gpt-3.5-turbo' ),
			'messages'    => $messages,
			'temperature' => (float) NHAF_Chatbot_Settings::get( 'openai_temperature', 0.5 ),
			'max_tokens'  => (int) NHAF_Chatbot_Settings::get( 'openai_max_tokens', 600 ),
		);

		$args = array(
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		);

		/** This filter is documented for all providers. */
		$args = apply_filters( 'nhaf_chatbot_llm_request_args', $args, 'openai', $body );

		$response = self::remote_post_with_retry( 'https://api.openai.com/v1/chat/completions', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'nhaf_openai_http', $msg );
		}

		$content = isset( $data['choices'][0]['message']['content'] ) ? trim( $data['choices'][0]['message']['content'] ) : '';
		$tokens  = isset( $data['usage']['total_tokens'] ) ? (int) $data['usage']['total_tokens'] : 0;

		if ( '' === $content ) {
			return new WP_Error( 'nhaf_empty', __( 'The assistant returned an empty response.', 'nhaf-safari-chatbot' ) );
		}

		return array( 'content' => $content, 'tokens' => $tokens );
	}

	/**
	 * Call the Anthropic Messages API.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_message  User message.
	 * @param array  $history       History.
	 * @return array|WP_Error
	 */
	private static function call_anthropic( $system_prompt, $user_message, $history ) {
		$api_key = NHAF_Chatbot_Settings::get( 'anthropic_api_key' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'nhaf_no_key', __( 'Anthropic API key is not configured.', 'nhaf-safari-chatbot' ) );
		}

		$body = array(
			'model'       => NHAF_Chatbot_Settings::get( 'anthropic_model', 'claude-3-haiku-20240307' ),
			'system'      => $system_prompt,
			'messages'    => self::build_messages( $user_message, $history ),
			'temperature' => (float) NHAF_Chatbot_Settings::get( 'anthropic_temperature', 0.5 ),
			'max_tokens'  => (int) NHAF_Chatbot_Settings::get( 'openai_max_tokens', 600 ),
		);

		$args = array(
			'timeout' => 45,
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		);

		$args = apply_filters( 'nhaf_chatbot_llm_request_args', $args, 'anthropic', $body );

		$response = self::remote_post_with_retry( 'https://api.anthropic.com/v1/messages', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'nhaf_anthropic_http', $msg );
		}

		$content = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$content .= $block['text'];
				}
			}
		}
		$content = trim( $content );

		$tokens = 0;
		if ( isset( $data['usage']['input_tokens'], $data['usage']['output_tokens'] ) ) {
			$tokens = (int) $data['usage']['input_tokens'] + (int) $data['usage']['output_tokens'];
		}

		if ( '' === $content ) {
			return new WP_Error( 'nhaf_empty', __( 'The assistant returned an empty response.', 'nhaf-safari-chatbot' ) );
		}

		return array( 'content' => $content, 'tokens' => $tokens );
	}

	/**
	 * Call a local Ollama endpoint.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_message  User message.
	 * @param array  $history       History.
	 * @return array|WP_Error
	 */
	private static function call_ollama( $system_prompt, $user_message, $history ) {
		$endpoint = rtrim( NHAF_Chatbot_Settings::get( 'ollama_endpoint', 'http://localhost:11434' ), '/' );
		$model    = NHAF_Chatbot_Settings::get( 'ollama_model', 'llama2' );

		$messages = array( array( 'role' => 'system', 'content' => $system_prompt ) );
		$messages = array_merge( $messages, self::build_messages( $user_message, $history ) );

		$body = array(
			'model'    => $model,
			'messages' => $messages,
			'stream'   => false,
		);

		$args = array(
			'timeout' => 60,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		);

		$args = apply_filters( 'nhaf_chatbot_llm_request_args', $args, 'ollama', $body );

		$response = self::remote_post_with_retry( $endpoint . '/api/chat', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			return new WP_Error( 'nhaf_ollama_http', __( 'Local LLM returned an error. Is Ollama running?', 'nhaf-safari-chatbot' ) . ' (HTTP ' . $code . ')' );
		}

		$content = isset( $data['message']['content'] ) ? trim( $data['message']['content'] ) : '';

		if ( '' === $content ) {
			return new WP_Error( 'nhaf_empty', __( 'The assistant returned an empty response.', 'nhaf-safari-chatbot' ) );
		}

		return array( 'content' => $content, 'tokens' => 0 );
	}

	/**
	 * Lightweight booking-intent detection (keyword based, no extra API call).
	 *
	 * @param string $message User message.
	 * @return bool
	 */
	public static function detect_booking_intent( $message ) {
		$message  = strtolower( $message );
		$keywords = array(
			'book', 'booking', 'reserve', 'reservation', 'how do i book', 'i want to book',
			'price', 'pricing', 'cost', 'quote', 'how much', 'enquire', 'enquiry', 'inquiry',
			'availability', 'available', 'deposit', 'pay', 'package deal',
		);

		/**
		 * Filter the booking-intent keyword list / detection prompt.
		 *
		 * @param array  $keywords Default keyword list.
		 * @param string $message  The user message.
		 */
		$keywords = apply_filters( 'nhaf_chatbot_booking_detection_prompt', $keywords, $message );

		foreach ( (array) $keywords as $kw ) {
			if ( false !== strpos( $message, strtolower( $kw ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Test connectivity to the configured provider.
	 *
	 * @return array|WP_Error
	 */
	public static function test_connection() {
		return self::generate( 'Reply with the single word: OK', array(), '' );
	}
}
