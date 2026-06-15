<?php
/**
 * Fired during plugin deactivation.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Deactivator
 */
class NHAF_Chatbot_Deactivator {

	/**
	 * Clear scheduled events on deactivation. Data is preserved.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'nhaf_chatbot_recrawl_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nhaf_chatbot_recrawl_event' );
		}
		wp_clear_scheduled_hook( 'nhaf_chatbot_recrawl_event' );
		wp_clear_scheduled_hook( 'nhaf_chatbot_monthly_summary' );

		flush_rewrite_rules();
	}
}
