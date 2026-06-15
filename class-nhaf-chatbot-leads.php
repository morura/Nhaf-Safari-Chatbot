<?php
/**
 * Booking lead capture and management.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Leads
 */
class NHAF_Chatbot_Leads {

	/**
	 * Save a lead and trigger notifications.
	 *
	 * @param array $input Raw lead data.
	 * @return array|WP_Error { reference, affiliate_url, message }
	 */
	public static function create( array $input ) {
		global $wpdb;

		$name  = sanitize_text_field( $input['name'] ?? '' );
		$email = sanitize_email( $input['email'] ?? '' );

		if ( empty( $name ) ) {
			return new WP_Error( 'nhaf_lead_name', __( 'Please provide your name.', 'nhaf-safari-chatbot' ), array( 'status' => 400 ) );
		}
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'nhaf_lead_email', __( 'Please provide a valid email address.', 'nhaf-safari-chatbot' ), array( 'status' => 400 ) );
		}

		$reference = 'NH-' . strtoupper( wp_generate_password( 8, false, false ) );

		$data = array(
			'name'            => $name,
			'email'           => $email,
			'phone'           => sanitize_text_field( $input['phone'] ?? '' ),
			'travelers'       => absint( $input['travelers'] ?? 0 ),
			'destination'     => sanitize_text_field( $input['destination'] ?? '' ),
			'preferred_month' => sanitize_text_field( $input['preferred_month'] ?? '' ),
			'message'         => sanitize_textarea_field( $input['message'] ?? '' ),
			'user_ip'         => NHAF_Chatbot_Security::get_client_ip(),
			'user_agent'      => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
			'reference'       => $reference,
			'status'          => 'new',
			'created_at'      => current_time( 'mysql' ),
		);

		$table  = $wpdb->prefix . 'nhaf_chatbot_leads';
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			$data,
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			NHAF_Chatbot_Security::log( 'Lead insert failed: ' . $wpdb->last_error );
			return new WP_Error( 'nhaf_lead_db', __( 'We could not save your enquiry. Please try again.', 'nhaf-safari-chatbot' ), array( 'status' => 500 ) );
		}

		$lead_id = (int) $wpdb->insert_id;

		self::notify_admin( $data );
		self::send_autoresponder( $data );

		$affiliate_url = self::build_affiliate_url( $data );

		/**
		 * Fires after a lead is successfully saved.
		 *
		 * @param int   $lead_id Inserted lead ID.
		 * @param array $data    Lead data.
		 */
		do_action( 'nhaf_chatbot_after_lead_submit', $lead_id, $data );

		$thanks = NHAF_Chatbot_Settings::get( 'autoresponder' );

		return array(
			'reference'     => $reference,
			'affiliate_url' => $affiliate_url,
			'message'       => $thanks,
		);
	}

	/**
	 * Email the admin / configured notification address.
	 *
	 * @param array $data Lead data.
	 */
	private static function notify_admin( $data ) {
		$to = NHAF_Chatbot_Settings::get( 'lead_notify_email', get_option( 'admin_email' ) );
		if ( empty( $to ) || ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: lead reference */
			__( 'New Safari Enquiry [%s]', 'nhaf-safari-chatbot' ),
			$data['reference']
		);

		$lines = array(
			__( 'A new safari enquiry was submitted via the AI chatbot:', 'nhaf-safari-chatbot' ),
			'',
			__( 'Reference: ', 'nhaf-safari-chatbot' ) . $data['reference'],
			__( 'Name: ', 'nhaf-safari-chatbot' ) . $data['name'],
			__( 'Email: ', 'nhaf-safari-chatbot' ) . $data['email'],
			__( 'Phone: ', 'nhaf-safari-chatbot' ) . $data['phone'],
			__( 'Travelers: ', 'nhaf-safari-chatbot' ) . $data['travelers'],
			__( 'Destination: ', 'nhaf-safari-chatbot' ) . $data['destination'],
			__( 'Preferred month: ', 'nhaf-safari-chatbot' ) . $data['preferred_month'],
			__( 'Message: ', 'nhaf-safari-chatbot' ) . $data['message'],
			'',
			__( 'IP: ', 'nhaf-safari-chatbot' ) . $data['user_ip'],
		);

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Send an autoresponder to the lead.
	 *
	 * @param array $data Lead data.
	 */
	private static function send_autoresponder( $data ) {
		$message = NHAF_Chatbot_Settings::get( 'autoresponder' );
		if ( empty( $message ) || empty( $data['email'] ) ) {
			return;
		}
		$business = NHAF_Chatbot_Settings::get( 'business_name', get_bloginfo( 'name' ) );
		$subject  = sprintf(
			/* translators: %s: business name */
			__( 'Your safari enquiry with %s', 'nhaf-safari-chatbot' ),
			$business
		);
		$body = $message . "\n\n" . __( 'Your reference: ', 'nhaf-safari-chatbot' ) . $data['reference'];
		wp_mail( $data['email'], $subject, $body );
	}

	/**
	 * Build an affiliate booking URL with tracking params.
	 *
	 * @param array $data Lead data.
	 * @return string
	 */
	private static function build_affiliate_url( $data ) {
		$base   = NHAF_Chatbot_Settings::get( 'affiliate_base_url', 'https://www.safari.com/book' );
		$aff_id = NHAF_Chatbot_Settings::get( 'affiliate_id', '' );

		$args = array(
			'ref'         => $data['reference'],
			'destination' => $data['destination'],
			'utm_source'  => 'nomad_horizons',
			'utm_medium'  => 'chatbot',
		);
		if ( ! empty( $aff_id ) ) {
			$args['a'] = $aff_id;
		}

		return esc_url_raw( add_query_arg( array_filter( $args ), $base ) );
	}

	/**
	 * Retrieve leads for the admin table.
	 *
	 * @param int $limit  Max rows.
	 * @param int $offset Offset.
	 * @return array
	 */
	public static function get_leads( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'nhaf_chatbot_leads';
		$limit  = absint( $limit );
		$offset = absint( $offset );
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset ),
			ARRAY_A
		);
	}

	/**
	 * Update a lead status.
	 *
	 * @param int    $id     Lead ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'nhaf_chatbot_leads';
		$allowed = array( 'new', 'contacted', 'converted', 'closed' );
		$status  = in_array( $status, $allowed, true ) ? $status : 'new';
		return false !== $wpdb->update( $table, array( 'status' => $status ), array( 'id' => absint( $id ) ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Stream all leads as a CSV download.
	 */
	public static function export_csv() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nhaf-safari-chatbot' ) );
		}
		check_admin_referer( 'nhaf_export_leads' );

		$leads = self::get_leads( 10000, 0 );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=safari-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Reference', 'Name', 'Email', 'Phone', 'Travelers', 'Destination', 'Month', 'Message', 'Status', 'Created' ) );
		foreach ( $leads as $lead ) {
			fputcsv(
				$out,
				array(
					$lead['id'], $lead['reference'], $lead['name'], $lead['email'], $lead['phone'],
					$lead['travelers'], $lead['destination'], $lead['preferred_month'],
					$lead['message'], $lead['status'], $lead['created_at'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
