<?php
/**
 * Admin notices and scheduled summary emails.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Notices
 */
class NHAF_Chatbot_Notices {

	const SUMMARY_EVENT = 'nhaf_chatbot_monthly_summary';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'configuration_notices' ) );
		add_action( self::SUMMARY_EVENT, array( __CLASS__, 'send_monthly_summary' ) );
		add_action( 'nhaf_chatbot_on_llm_error', array( __CLASS__, 'notify_llm_failure' ), 10, 2 );

		if ( ! wp_next_scheduled( self::SUMMARY_EVENT ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'nhaf_monthly', self::SUMMARY_EVENT );
		}
	}

	/**
	 * Show configuration notices to admins on relevant screens.
	 */
	public static function configuration_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$on_own = $screen && false !== strpos( (string) $screen->id, NHAF_Chatbot_Admin::MENU_SLUG );

		// Only nag on the dashboard, plugins screen and our own settings page.
		if ( $screen && ! $on_own && ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}

		$settings_url = add_query_arg(
			array( 'page' => NHAF_Chatbot_Admin::MENU_SLUG, 'tab' => 'llm' ),
			admin_url( 'options-general.php' )
		);

		if ( NHAF_Chatbot_Settings::get( 'enabled' ) && ! NHAF_Chatbot_LLM::is_configured() ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Safari AI Chatbot:', 'nhaf-safari-chatbot' ),
				esc_html__( 'no LLM provider is configured, so the chatbot cannot answer questions yet.', 'nhaf-safari-chatbot' ),
				esc_url( $settings_url ),
				esc_html__( 'Configure now', 'nhaf-safari-chatbot' )
			);
		}

		$endpoints = (array) NHAF_Chatbot_Settings::get( 'safari_endpoints', array() );
		if ( ! empty( $endpoints ) && '' === (string) NHAF_Chatbot_Knowledge::get_safari_token() ) {
			$api_url = add_query_arg(
				array( 'page' => NHAF_Chatbot_Admin::MENU_SLUG, 'tab' => 'safari_api' ),
				admin_url( 'options-general.php' )
			);
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Safari AI Chatbot:', 'nhaf-safari-chatbot' ),
				esc_html__( 'Safari.com API integration is enabled but no API token was found.', 'nhaf-safari-chatbot' ),
				esc_url( $api_url ),
				esc_html__( 'Add a token', 'nhaf-safari-chatbot' )
			);
		}
	}

	/**
	 * Email a monthly lead summary to the notification address.
	 */
	public static function send_monthly_summary() {
		global $wpdb;

		$table = $wpdb->prefix . 'nhaf_chatbot_leads';
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT status, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY status", $since ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$total = 0;
		$lines = array();
		foreach ( (array) $rows as $row ) {
			$total  += (int) $row['total'];
			$lines[] = sprintf( '%s: %d', ucfirst( $row['status'] ), (int) $row['total'] );
		}

		$to = NHAF_Chatbot_Settings::get( 'lead_notify_email', get_option( 'admin_email' ) );
		if ( empty( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Safari chatbot — monthly lead summary', 'nhaf-safari-chatbot' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body  = sprintf( /* translators: %d: lead count */ __( "Leads captured in the last 30 days: %d\n\n", 'nhaf-safari-chatbot' ), $total );
		$body .= $lines ? implode( "\n", $lines ) . "\n\n" : '';
		$body .= __( 'View and export leads: ', 'nhaf-safari-chatbot' ) . add_query_arg(
			array( 'page' => NHAF_Chatbot_Admin::MENU_SLUG, 'tab' => 'leads' ),
			admin_url( 'options-general.php' )
		);

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Email the admin when the LLM provider fails (throttled to once per hour).
	 *
	 * @param WP_Error $error    The error.
	 * @param string   $provider Provider slug.
	 */
	public static function notify_llm_failure( $error, $provider ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		if ( get_transient( 'nhaf_llm_failure_notified' ) ) {
			return;
		}
		set_transient( 'nhaf_llm_failure_notified', 1, HOUR_IN_SECONDS );

		$to = NHAF_Chatbot_Settings::get( 'lead_notify_email', get_option( 'admin_email' ) );
		if ( empty( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Safari chatbot — LLM request failed', 'nhaf-safari-chatbot' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body = sprintf(
			/* translators: 1: provider 2: error message */
			__( "The chatbot could not get a response from the %1\$s provider.\n\nError: %2\$s\n\nVisitors are being shown a friendly fallback message. Please check your API key and provider status.", 'nhaf-safari-chatbot' ),
			$provider,
			$error->get_error_message()
		);

		wp_mail( $to, $subject, $body );
	}
}
