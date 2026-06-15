<?php
/**
 * "Safari AI Chat" sidebar widget.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Widget
 */
class NHAF_Chatbot_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'nhaf_chatbot_widget',
			__( 'Safari AI Chat', 'nhaf-safari-chatbot' ),
			array( 'description' => __( 'Displays a Safari AI chat trigger button.', 'nhaf-safari-chatbot' ) )
		);
	}

	/**
	 * Front-end display.
	 *
	 * @param array $args     Widget args.
	 * @param array $instance Settings.
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Plan Your Safari', 'nhaf-safari-chatbot' );
		$label = ! empty( $instance['label'] ) ? $instance['label'] : __( 'Chat with Safari AI', 'nhaf-safari-chatbot' );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput

		echo do_shortcode( '[nhaf_chatbot_button label="' . esc_attr( $label ) . '"]' );

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Back-end form.
	 *
	 * @param array $instance Settings.
	 * @return void
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$label = isset( $instance['label'] ) ? $instance['label'] : __( 'Chat with Safari AI', 'nhaf-safari-chatbot' );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'nhaf-safari-chatbot' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'label' ) ); ?>"><?php esc_html_e( 'Button label:', 'nhaf-safari-chatbot' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'label' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'label' ) ); ?>" type="text" value="<?php echo esc_attr( $label ); ?>" />
		</p>
		<?php
	}

	/**
	 * Sanitise settings on save.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
			'label' => sanitize_text_field( $new_instance['label'] ?? '' ),
		);
	}
}
