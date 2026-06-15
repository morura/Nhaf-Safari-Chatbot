<?php
/**
 * Conversation/session management using transients.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Session
 */
class NHAF_Chatbot_Session {

	const TTL = DAY_IN_SECONDS; // 24h inactivity expiry.

	/**
	 * Validate / normalise a session id from the client.
	 *
	 * @param string $session_id Raw id.
	 * @return string
	 */
	public static function normalize_id( $session_id ) {
		$session_id = preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $session_id );
		if ( empty( $session_id ) || strlen( $session_id ) < 8 ) {
			$session_id = 'sess-' . wp_generate_password( 24, false, false );
		}
		return substr( $session_id, 0, 64 );
	}

	/**
	 * Transient key for a session.
	 *
	 * @param string $session_id Session id.
	 * @return string
	 */
	private static function key( $session_id ) {
		return 'nhaf_sess_' . md5( $session_id );
	}

	/**
	 * Get stored history (array of role/content turns).
	 *
	 * @param string $session_id Session id.
	 * @return array
	 */
	public static function get_history( $session_id ) {
		$history = get_transient( self::key( $session_id ) );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Append a user/assistant exchange and trim to the configured window.
	 *
	 * @param string $session_id   Session id.
	 * @param string $user_message User text.
	 * @param string $bot_response Assistant text.
	 */
	public static function append( $session_id, $user_message, $bot_response ) {
		$history   = self::get_history( $session_id );
		$history[] = array( 'role' => 'user', 'content' => $user_message );
		$history[] = array( 'role' => 'assistant', 'content' => $bot_response );

		$max_turns = (int) NHAF_Chatbot_Settings::get( 'context_message_count', 10 );
		$max_items = max( 2, $max_turns * 2 );
		if ( count( $history ) > $max_items ) {
			$history = array_slice( $history, -$max_items );
		}

		set_transient( self::key( $session_id ), $history, self::TTL );
	}

	/**
	 * Clear a session.
	 *
	 * @param string $session_id Session id.
	 */
	public static function clear( $session_id ) {
		delete_transient( self::key( $session_id ) );
	}

	/**
	 * Persist a conversation row for analytics/auditing.
	 *
	 * @param array $row Conversation row.
	 */
	public static function log_conversation( array $row ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nhaf_chatbot_conversations';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'session_id'   => substr( (string) $row['session_id'], 0, 64 ),
				'user_message' => (string) $row['user_message'],
				'bot_response' => (string) $row['bot_response'],
				'ip_address'   => (string) $row['ip_address'],
				'page_url'     => esc_url_raw( (string) $row['page_url'] ),
				'tokens_used'  => absint( $row['tokens_used'] ?? 0 ),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}
