<?php
/**
 * Shortcodes.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Shortcodes
 */
class NHAF_Chatbot_Shortcodes {

	/**
	 * Register shortcodes.
	 */
	public static function init() {
		add_shortcode( 'nhaf_chatbot_button', array( __CLASS__, 'render_button' ) );
		add_shortcode( 'nhaf_chatbot_embed', array( __CLASS__, 'render_embed' ) );
	}

	/**
	 * [nhaf_chatbot_button] - just the trigger bubble.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_button( $atts ) {
		$atts = shortcode_atts( array( 'label' => __( 'Chat with Safari AI', 'nhaf-safari-chatbot' ) ), $atts, 'nhaf_chatbot_button' );

		// Ensure assets load even if the global widget is excluded on this page.
		NHAF_Chatbot_Public::force_enqueue();

		return sprintf(
			'<button type="button" class="nhaf-chatbot-trigger nhaf-inline-trigger" data-nhaf-open="1">%s</button>',
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [nhaf_chatbot_embed] - render the chat window inline.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_embed( $atts ) {
		$atts = shortcode_atts( array( 'height' => '520' ), $atts, 'nhaf_chatbot_embed' );

		NHAF_Chatbot_Public::force_enqueue();

		ob_start();
		?>
		<div class="nhaf-chatbot-embed" style="height:<?php echo esc_attr( absint( $atts['height'] ) ); ?>px;" data-nhaf-embed="1"></div>
		<?php
		return ob_get_clean();
	}
}
