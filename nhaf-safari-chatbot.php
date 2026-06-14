<?php
/**
 * Plugin Name:       Nomad Horizons Safari AI Chatbot
 * Plugin URI:        https://nomadhorizonsafrica.com/safari-ai-chatbot
 * Description:        AI-powered chatbot that answers visitor questions about African safaris using Safari.com affiliate data and LLM integration. Provides instant answers and booking guidance.
 * Version:           1.0.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Nomad Horizons Africa
 * Author URI:        https://nomadhorizonsafrica.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nhaf-safari-chatbot
 * Domain Path:       /languages
 *
 * @package NHAF_Safari_Chatbot
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current plugin version.
 */
define( 'NHAF_CHATBOT_VERSION', '1.0.3' );

/**
 * Plugin path / URL constants.
 */
define( 'NHAF_CHATBOT_FILE', __FILE__ );
define( 'NHAF_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'NHAF_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
define( 'NHAF_CHATBOT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Option / table key prefixes.
 */
define( 'NHAF_CHATBOT_OPTION_KEY', 'nhaf_chatbot_settings' );
define( 'NHAF_CHATBOT_DB_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 */
function nhaf_chatbot_activate() {
	require_once NHAF_CHATBOT_PATH . 'includes/class-nhaf-chatbot-activator.php';
	NHAF_Chatbot_Activator::activate();
}
register_activation_hook( __FILE__, 'nhaf_chatbot_activate' );

/**
 * The code that runs during plugin deactivation.
 */
function nhaf_chatbot_deactivate() {
	require_once NHAF_CHATBOT_PATH . 'includes/class-nhaf-chatbot-deactivator.php';
	NHAF_Chatbot_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'nhaf_chatbot_deactivate' );

/**
 * PSR-style lightweight autoloader for plugin classes.
 *
 * Maps NHAF_Chatbot_Foo_Bar => class-nhaf-chatbot-foo-bar.php searching
 * the includes/, admin/, public/ and widgets/ directories.
 *
 * @param string $class_name Fully qualified class name being loaded.
 */
function nhaf_chatbot_autoloader( $class_name ) {
	if ( 0 !== strpos( $class_name, 'NHAF_Chatbot' ) ) {
		return;
	}

	$file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

	$directories = array(
		NHAF_CHATBOT_PATH . 'includes/',
		NHAF_CHATBOT_PATH . 'admin/',
		NHAF_CHATBOT_PATH . 'public/',
		NHAF_CHATBOT_PATH . 'widgets/',
	);

	foreach ( $directories as $directory ) {
		$path = $directory . $file_name;
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
}
spl_autoload_register( 'nhaf_chatbot_autoloader' );

/**
 * Begins execution of the plugin.
 */
function nhaf_chatbot_run() {
	$plugin = NHAF_Chatbot_Core::instance();
	$plugin->run();
}
add_action( 'plugins_loaded', 'nhaf_chatbot_run', 20 );

/**
 * Load translations.
 */
function nhaf_chatbot_load_textdomain() {
	load_plugin_textdomain( 'nhaf-safari-chatbot', false, dirname( NHAF_CHATBOT_BASENAME ) . '/languages' );
}
add_action( 'init', 'nhaf_chatbot_load_textdomain' );
