<?php
/**
 * Security helpers: rate limiting, IP handling, reCAPTCHA, logging.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Security
 */
class NHAF_Chatbot_Security {

	/**
	 * Get the client IP, accounting for common proxies, sanitised.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $candidates as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$value = wp_unslash( $_SERVER[ $header ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			// X-Forwarded-For may contain a list; take the first.
			$parts = explode( ',', $value );
			$ip    = trim( $parts[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Check whether an IP is on the admin blocklist.
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	public static function is_blocked( $ip ) {
		$blocklist = (array) NHAF_Chatbot_Settings::get( 'ip_blocklist', array() );
		$blocklist = array_filter( array_map( 'trim', $blocklist ) );
		return in_array( $ip, $blocklist, true );
	}

	/**
	 * Enforce per-minute and per-day rate limits for an IP.
	 *
	 * @param string $ip Client IP.
	 * @return true|WP_Error True if allowed, WP_Error if limited.
	 */
	public static function check_rate_limit( $ip ) {
		if ( self::is_blocked( $ip ) ) {
			return new WP_Error( 'nhaf_blocked', __( 'Access denied.', 'nhaf-safari-chatbot' ), array( 'status' => 403 ) );
		}

		$per_min = (int) NHAF_Chatbot_Settings::get( 'rate_limit_per_min', 20 );
		$per_day = (int) NHAF_Chatbot_Settings::get( 'daily_limit_per_ip', 200 );

		/**
		 * Filter the per-minute rate limit for a given IP.
		 *
		 * @param int    $per_min Default limit.
		 * @param string $ip      Client IP.
		 */
		$per_min = (int) apply_filters( 'nhaf_chatbot_rate_limit', $per_min, $ip );

		$ip_hash   = md5( $ip );
		$min_key   = 'nhaf_rl_min_' . $ip_hash;
		$day_key   = 'nhaf_rl_day_' . $ip_hash;

		$min_count = (int) get_transient( $min_key );
		$day_count = (int) get_transient( $day_key );

		if ( $per_min > 0 && $min_count >= $per_min ) {
			return new WP_Error(
				'nhaf_rate_limited',
				__( 'You are sending messages too quickly. Please wait a moment and try again.', 'nhaf-safari-chatbot' ),
				array( 'status' => 429 )
			);
		}

		if ( $per_day > 0 && $day_count >= $per_day ) {
			return new WP_Error(
				'nhaf_daily_limited',
				__( 'You have reached the daily message limit. Please try again tomorrow.', 'nhaf-safari-chatbot' ),
				array( 'status' => 429 )
			);
		}

		// Increment counters.
		set_transient( $min_key, $min_count + 1, MINUTE_IN_SECONDS );
		set_transient( $day_key, $day_count + 1, DAY_IN_SECONDS );

		return true;
	}

	/**
	 * Verify a reCAPTCHA token if reCAPTCHA is enabled.
	 *
	 * @param string $token Token from the client.
	 * @return true|WP_Error
	 */
	public static function verify_recaptcha( $token ) {
		if ( ! NHAF_Chatbot_Settings::get( 'recaptcha_enabled' ) ) {
			return true;
		}

		$secret = NHAF_Chatbot_Settings::get( 'recaptcha_secret' );
		if ( empty( $secret ) ) {
			return true; // Misconfigured - fail open rather than lock everyone out.
		}

		if ( empty( $token ) ) {
			return new WP_Error( 'nhaf_recaptcha_missing', __( 'Captcha verification failed.', 'nhaf-safari-chatbot' ), array( 'status' => 400 ) );
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => self::get_client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log( 'reCAPTCHA verify error: ' . $response->get_error_message() );
			return true; // Network error - fail open.
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'nhaf_recaptcha_failed', __( 'Captcha verification failed.', 'nhaf-safari-chatbot' ), array( 'status' => 400 ) );
		}

		// For v3, optionally enforce a score threshold.
		if ( 'v3' === NHAF_Chatbot_Settings::get( 'recaptcha_version' ) && isset( $body['score'] ) ) {
			if ( (float) $body['score'] < 0.5 ) {
				return new WP_Error( 'nhaf_recaptcha_low_score', __( 'Captcha verification failed.', 'nhaf-safari-chatbot' ), array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * Sanitise and length-limit a chat message.
	 *
	 * @param string $message Raw message.
	 * @return string
	 */
	public static function sanitize_message( $message ) {
		$message = wp_strip_all_tags( (string) $message );
		$message = trim( $message );
		$max     = (int) NHAF_Chatbot_Settings::get( 'max_message_length', 1000 );
		if ( $max > 0 && mb_strlen( $message ) > $max ) {
			$message = mb_substr( $message, 0, $max );
		}
		return $message;
	}

	/**
	 * Log an error to the WordPress debug log without exposing to users.
	 *
	 * @param string $message Message to log.
	 */
	public static function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[NHAF Safari Chatbot] ' . $message );
		}
	}
}
