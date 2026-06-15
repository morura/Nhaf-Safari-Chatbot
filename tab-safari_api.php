<?php
/**
 * Admin view: Safari.com API Integration tab.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$use_finder = (int) NHAF_Chatbot_Settings::get( 'use_finder_token', 1 );
$api_base   = NHAF_Chatbot_Settings::get( 'safari_api_base', 'https://api.safari.com/v1' );
$endpoints  = (array) NHAF_Chatbot_Settings::get( 'safari_endpoints', array( 'destinations', 'experiences', 'safaris' ) );
$has_token  = '' !== (string) NHAF_Chatbot_Settings::get( 'safari_api_token', '' );

$all_endpoints = array(
	'destinations' => __( 'Destinations', 'nhaf-safari-chatbot' ),
	'experiences'  => __( 'Experiences', 'nhaf-safari-chatbot' ),
	'safaris'      => __( 'Safaris', 'nhaf-safari-chatbot' ),
);

NHAF_Chatbot_Admin::form_open( 'safari_api' );
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Token source', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="use_finder_token" value="1" <?php checked( $use_finder, 1 ); ?> />
				<?php esc_html_e( 'Reuse the Safari.com token from the Safari Finder plugin when available.', 'nhaf-safari-chatbot' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="safari_api_token"><?php esc_html_e( 'API token (this plugin)', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<input type="password" name="safari_api_token" id="safari_api_token" value="" autocomplete="new-password" class="regular-text" />
			<?php if ( $has_token ) : ?><p class="description"><?php esc_html_e( 'A token is stored. Leave blank to keep it.', 'nhaf-safari-chatbot' ); ?></p><?php endif; ?>
			<p class="description"><?php esc_html_e( 'Used as a fallback, or when the reuse option above is disabled.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="safari_api_base"><?php esc_html_e( 'API base URL', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="url" name="safari_api_base" id="safari_api_base" value="<?php echo esc_attr( $api_base ); ?>" class="regular-text" /></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Endpoints to use', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<fieldset>
				<?php foreach ( $all_endpoints as $key => $label ) : ?>
					<label style="display:inline-block;margin:0 12px 6px 0;">
						<input type="checkbox" name="safari_endpoints[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $endpoints, true ) ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Test connection', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<button type="button" class="button" id="nhaf-test-safari"><?php esc_html_e( 'Test API connection', 'nhaf-safari-chatbot' ); ?></button>
			<span class="nhaf-test-result" id="nhaf-test-safari-result"></span>
			<p class="description"><?php esc_html_e( 'Save your changes before testing.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
</table>
<?php
NHAF_Chatbot_Admin::form_close();
