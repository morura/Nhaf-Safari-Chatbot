<?php
/**
 * Admin view: Lead Management tab.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notify_email   = NHAF_Chatbot_Settings::get( 'lead_notify_email', '' );
$autoresponder  = NHAF_Chatbot_Settings::get( 'autoresponder', '' );
$affiliate_base = NHAF_Chatbot_Settings::get( 'affiliate_base_url', '' );
$affiliate_id   = NHAF_Chatbot_Settings::get( 'affiliate_id', '' );

$leads    = NHAF_Chatbot_Leads::get_leads( 200, 0 );
$statuses = array(
	'new'       => __( 'New', 'nhaf-safari-chatbot' ),
	'contacted' => __( 'Contacted', 'nhaf-safari-chatbot' ),
	'converted' => __( 'Converted', 'nhaf-safari-chatbot' ),
	'closed'    => __( 'Closed', 'nhaf-safari-chatbot' ),
);

$export_url = wp_nonce_url(
	add_query_arg( 'action', 'nhaf_export_leads', admin_url( 'admin-post.php' ) ),
	'nhaf_export_leads'
);

NHAF_Chatbot_Admin::form_open( 'leads' );
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="lead_notify_email"><?php esc_html_e( 'Notification email', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<input type="email" name="lead_notify_email" id="lead_notify_email" value="<?php echo esc_attr( $notify_email ); ?>" class="regular-text" />
			<p class="description"><?php esc_html_e( 'New booking enquiries are sent here.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="autoresponder"><?php esc_html_e( 'Auto-responder message', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><textarea name="autoresponder" id="autoresponder" rows="3" class="large-text"><?php echo esc_textarea( $autoresponder ); ?></textarea></td>
	</tr>
	<tr>
		<th scope="row"><label for="affiliate_base_url"><?php esc_html_e( 'Affiliate base URL', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="url" name="affiliate_base_url" id="affiliate_base_url" value="<?php echo esc_attr( $affiliate_base ); ?>" class="regular-text" placeholder="https://www.safari.com/book" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="affiliate_id"><?php esc_html_e( 'Affiliate ID', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="text" name="affiliate_id" id="affiliate_id" value="<?php echo esc_attr( $affiliate_id ); ?>" class="regular-text" /></td>
	</tr>
</table>
<?php
NHAF_Chatbot_Admin::form_close();
?>

<hr />

<h2><?php esc_html_e( 'Leads', 'nhaf-safari-chatbot' ); ?>
	<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'nhaf-safari-chatbot' ); ?></a>
</h2>

<table class="widefat striped nhaf-leads-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Date', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Reference', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Name', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Email', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Destination', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Travelers', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Month', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Status', 'nhaf-safari-chatbot' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $leads ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No leads yet.', 'nhaf-safari-chatbot' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $leads as $lead ) : ?>
				<tr>
					<td><?php echo esc_html( $lead['created_at'] ); ?></td>
					<td><?php echo esc_html( isset( $lead['reference'] ) ? $lead['reference'] : '' ); ?></td>
					<td><?php echo esc_html( $lead['name'] ); ?></td>
					<td><a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a></td>
					<td><?php echo esc_html( $lead['destination'] ); ?></td>
					<td><?php echo esc_html( $lead['travelers'] ); ?></td>
					<td><?php echo esc_html( $lead['preferred_month'] ); ?></td>
					<td>
						<select class="nhaf-lead-status" data-lead-id="<?php echo esc_attr( $lead['id'] ); ?>">
							<?php foreach ( $statuses as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $lead['status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
