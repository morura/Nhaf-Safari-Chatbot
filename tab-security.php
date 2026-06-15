<?php
/**
 * Admin view: Security & Rate Limiting tab.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$per_min     = (int) NHAF_Chatbot_Settings::get( 'rate_limit_per_min', 20 );
$per_day     = (int) NHAF_Chatbot_Settings::get( 'daily_limit_per_ip', 200 );
$max_len     = (int) NHAF_Chatbot_Settings::get( 'max_message_length', 1000 );
$blocklist   = (array) NHAF_Chatbot_Settings::get( 'ip_blocklist', array() );
$rc_enabled  = (int) NHAF_Chatbot_Settings::get( 'recaptcha_enabled', 0 );
$rc_version  = NHAF_Chatbot_Settings::get( 'recaptcha_version', 'v3' );
$rc_site     = NHAF_Chatbot_Settings::get( 'recaptcha_site_key', '' );
$has_secret  = '' !== (string) NHAF_Chatbot_Settings::get( 'recaptcha_secret', '' );
$delete_data = (int) NHAF_Chatbot_Settings::get( 'delete_data_on_uninstall', 0 );

NHAF_Chatbot_Admin::form_open( 'security' );
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="rate_limit_per_min"><?php esc_html_e( 'Messages per minute (per IP)', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="number" min="1" max="120" name="rate_limit_per_min" id="rate_limit_per_min" value="<?php echo esc_attr( $per_min ); ?>" class="small-text" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="daily_limit_per_ip"><?php esc_html_e( 'Messages per day (per IP)', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="number" min="10" max="10000" name="daily_limit_per_ip" id="daily_limit_per_ip" value="<?php echo esc_attr( $per_day ); ?>" class="small-text" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="max_message_length"><?php esc_html_e( 'Max message length', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="number" min="50" max="5000" name="max_message_length" id="max_message_length" value="<?php echo esc_attr( $max_len ); ?>" class="small-text" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="ip_blocklist"><?php esc_html_e( 'IP blocklist', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<textarea name="ip_blocklist" id="ip_blocklist" rows="4" class="large-text code" placeholder="203.0.113.4&#10;198.51.100.7"><?php echo esc_textarea( implode( "\n", $blocklist ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'One IP per line (or comma separated). Blocked IPs cannot use the chatbot.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'reCAPTCHA', 'nhaf-safari-chatbot' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Enable reCAPTCHA', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="recaptcha_enabled" value="1" <?php checked( $rc_enabled, 1 ); ?> />
				<?php esc_html_e( 'Verify chat messages with Google reCAPTCHA.', 'nhaf-safari-chatbot' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="recaptcha_version"><?php esc_html_e( 'Version', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<select name="recaptcha_version" id="recaptcha_version">
				<option value="v3" <?php selected( $rc_version, 'v3' ); ?>>v3 (invisible)</option>
				<option value="v2" <?php selected( $rc_version, 'v2' ); ?>>v2</option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="recaptcha_site_key"><?php esc_html_e( 'Site key', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="text" name="recaptcha_site_key" id="recaptcha_site_key" value="<?php echo esc_attr( $rc_site ); ?>" class="regular-text" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="recaptcha_secret"><?php esc_html_e( 'Secret key', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<input type="password" name="recaptcha_secret" id="recaptcha_secret" value="" autocomplete="new-password" class="regular-text" />
			<?php if ( $has_secret ) : ?><p class="description"><?php esc_html_e( 'A secret is stored. Leave blank to keep it.', 'nhaf-safari-chatbot' ); ?></p><?php endif; ?>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Data', 'nhaf-safari-chatbot' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Remove data on uninstall', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $delete_data, 1 ); ?> />
				<?php esc_html_e( 'Delete all chatbot tables, leads, knowledge base and settings when the plugin is uninstalled.', 'nhaf-safari-chatbot' ); ?>
			</label>
		</td>
	</tr>
</table>
<?php
NHAF_Chatbot_Admin::form_close();
