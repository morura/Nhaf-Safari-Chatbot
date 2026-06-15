<?php
/**
 * Admin view: General settings tab.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled         = (int) NHAF_Chatbot_Settings::get( 'enabled', 1 );
$position        = NHAF_Chatbot_Settings::get( 'widget_position', 'bottom-right' );
$offset_x        = (int) NHAF_Chatbot_Settings::get( 'offset_x', 20 );
$offset_y        = (int) NHAF_Chatbot_Settings::get( 'offset_y', 20 );
$welcome         = NHAF_Chatbot_Settings::get( 'welcome_message', '' );
$color_primary   = NHAF_Chatbot_Settings::get( 'color_primary', '#0b6b3a' );
$color_secondary = NHAF_Chatbot_Settings::get( 'color_secondary', '#f4a300' );
$color_text      = NHAF_Chatbot_Settings::get( 'color_text', '#1a1a1a' );
$business_name   = NHAF_Chatbot_Settings::get( 'business_name', '' );
$contact_email   = NHAF_Chatbot_Settings::get( 'contact_email', '' );
$excluded        = (array) NHAF_Chatbot_Settings::get( 'excluded_post_types', array() );

$post_types = get_post_types( array( 'public' => true ), 'objects' );

NHAF_Chatbot_Admin::form_open( 'general' );
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Enable chatbot', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="enabled" value="1" <?php checked( $enabled, 1 ); ?> />
				<?php esc_html_e( 'Display the floating chat widget across the site.', 'nhaf-safari-chatbot' ); ?>
			</label>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="widget_position"><?php esc_html_e( 'Widget position', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<select name="widget_position" id="widget_position">
				<option value="bottom-right" <?php selected( $position, 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'nhaf-safari-chatbot' ); ?></option>
				<option value="bottom-left" <?php selected( $position, 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'nhaf-safari-chatbot' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Corner where the chat bubble appears.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Offset (px)', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<label>X <input type="number" name="offset_x" value="<?php echo esc_attr( $offset_x ); ?>" min="0" max="200" class="small-text" /></label>
			&nbsp;
			<label>Y <input type="number" name="offset_y" value="<?php echo esc_attr( $offset_y ); ?>" min="0" max="200" class="small-text" /></label>
			<p class="description"><?php esc_html_e( 'Distance from the chosen corner.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="welcome_message"><?php esc_html_e( 'Welcome message', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<textarea name="welcome_message" id="welcome_message" rows="3" class="large-text"><?php echo esc_textarea( $welcome ); ?></textarea>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Colors', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<p>
				<label><?php esc_html_e( 'Primary', 'nhaf-safari-chatbot' ); ?><br />
				<input type="text" name="color_primary" value="<?php echo esc_attr( $color_primary ); ?>" class="nhaf-color-field" data-default-color="#0b6b3a" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Secondary', 'nhaf-safari-chatbot' ); ?><br />
				<input type="text" name="color_secondary" value="<?php echo esc_attr( $color_secondary ); ?>" class="nhaf-color-field" data-default-color="#f4a300" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Text', 'nhaf-safari-chatbot' ); ?><br />
				<input type="text" name="color_text" value="<?php echo esc_attr( $color_text ); ?>" class="nhaf-color-field" data-default-color="#1a1a1a" /></label>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="business_name"><?php esc_html_e( 'Business name', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="text" name="business_name" id="business_name" value="<?php echo esc_attr( $business_name ); ?>" class="regular-text" /></td>
	</tr>

	<tr>
		<th scope="row"><label for="contact_email"><?php esc_html_e( 'Contact email', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="email" name="contact_email" id="contact_email" value="<?php echo esc_attr( $contact_email ); ?>" class="regular-text" /></td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Exclude post types', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<fieldset>
				<?php foreach ( $post_types as $pt ) : ?>
					<label style="display:inline-block;margin:0 12px 6px 0;">
						<input type="checkbox" name="excluded_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $excluded, true ) ); ?> />
						<?php echo esc_html( $pt->labels->singular_name ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="description"><?php esc_html_e( 'The widget will be hidden on single views of the selected types.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
</table>
<?php
NHAF_Chatbot_Admin::form_close();
