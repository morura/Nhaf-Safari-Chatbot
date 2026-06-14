<?php
/**
 * Uninstall handler for Nomad Horizons Safari AI Chatbot.
 *
 * Data is only removed when the admin opted in via
 * Settings → Safari AI Chatbot → Security & Rate Limiting → "Remove data on uninstall".
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$nhaf_settings = get_option( 'nhaf_chatbot_settings' );

if ( empty( $nhaf_settings['delete_data_on_uninstall'] ) ) {
	return; // Preserve all data.
}

global $wpdb;

// Drop custom tables.
foreach ( array( 'nhaf_chatbot_leads', 'nhaf_chatbot_conversations', 'nhaf_chatbot_knowledge' ) as $nhaf_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $nhaf_table ); // phpcs:ignore WordPress.DB
}

// Remove knowledge CPT posts.
$nhaf_posts = get_posts(
	array(
		'post_type'      => 'nhaf_chatbot_knowledge',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'suppress_filters' => true,
	)
);
foreach ( $nhaf_posts as $nhaf_post_id ) {
	wp_delete_post( $nhaf_post_id, true );
}

// Remove options.
delete_option( 'nhaf_chatbot_settings' );
delete_option( 'nhaf_chatbot_enc_salt' );
delete_option( 'nhaf_chatbot_db_version' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'nhaf_chatbot_recrawl_event' );
wp_clear_scheduled_hook( 'nhaf_chatbot_monthly_summary' );

// Remove plugin transients (sessions, caches, rate counters).
$wpdb->query( // phpcs:ignore WordPress.DB
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_nhaf\\_%' OR option_name LIKE '\\_transient\\_timeout\\_nhaf\\_%'"
);
