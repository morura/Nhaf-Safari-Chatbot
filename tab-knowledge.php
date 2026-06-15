<?php
/**
 * Admin view: Knowledge Base tab.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$recrawl      = NHAF_Chatbot_Settings::get( 'recrawl_interval', 'weekly' );
$crawl_urls   = (array) NHAF_Chatbot_Settings::get( 'crawl_urls', array() );
$web_search   = (int) NHAF_Chatbot_Settings::get( 'enable_web_search', 0 );
$search_prov  = NHAF_Chatbot_Settings::get( 'search_provider', 'serpapi' );
$cse_id       = NHAF_Chatbot_Settings::get( 'search_cse_id', '' );
$results_cnt  = (int) NHAF_Chatbot_Settings::get( 'search_results_count', 5 );
$has_searchk  = '' !== (string) NHAF_Chatbot_Settings::get( 'search_api_key', '' );

$entries = NHAF_Chatbot_Knowledge::list_entries();

NHAF_Chatbot_Admin::form_open( 'knowledge' );
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="crawl_urls"><?php esc_html_e( 'URLs to crawl', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<textarea name="crawl_urls" id="crawl_urls" rows="5" class="large-text code" placeholder="https://www.safari.com/destinations&#10;https://www.safari.com/experiences"><?php echo esc_textarea( implode( "\n", $crawl_urls ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'One URL per line. These pages are fetched and indexed for context.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="recrawl_interval"><?php esc_html_e( 'Recrawl schedule', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<select name="recrawl_interval" id="recrawl_interval">
				<option value="daily" <?php selected( $recrawl, 'daily' ); ?>><?php esc_html_e( 'Daily', 'nhaf-safari-chatbot' ); ?></option>
				<option value="weekly" <?php selected( $recrawl, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'nhaf-safari-chatbot' ); ?></option>
				<option value="monthly" <?php selected( $recrawl, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'nhaf-safari-chatbot' ); ?></option>
			</select>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Real-time web search', 'nhaf-safari-chatbot' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Enable web search', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="enable_web_search" value="1" <?php checked( $web_search, 1 ); ?> />
				<?php esc_html_e( 'Query a search API (scoped to safari.com) and add results to the model context.', 'nhaf-safari-chatbot' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="search_provider"><?php esc_html_e( 'Search provider', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<select name="search_provider" id="search_provider">
				<option value="serpapi" <?php selected( $search_prov, 'serpapi' ); ?>><?php esc_html_e( 'SerpAPI', 'nhaf-safari-chatbot' ); ?></option>
				<option value="google_cse" <?php selected( $search_prov, 'google_cse' ); ?>><?php esc_html_e( 'Google Custom Search', 'nhaf-safari-chatbot' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="search_api_key"><?php esc_html_e( 'Search API key', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<input type="password" name="search_api_key" id="search_api_key" value="" autocomplete="new-password" class="regular-text" />
			<?php if ( $has_searchk ) : ?><p class="description"><?php esc_html_e( 'A key is stored. Leave blank to keep it.', 'nhaf-safari-chatbot' ); ?></p><?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="search_cse_id"><?php esc_html_e( 'Google CSE ID', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<input type="text" name="search_cse_id" id="search_cse_id" value="<?php echo esc_attr( $cse_id ); ?>" class="regular-text" />
			<p class="description"><?php esc_html_e( 'Only required for Google Custom Search.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="search_results_count"><?php esc_html_e( 'Results to include', 'nhaf-safari-chatbot' ); ?></label></th>
		<td><input type="number" min="3" max="10" name="search_results_count" id="search_results_count" value="<?php echo esc_attr( $results_cnt ); ?>" class="small-text" /></td>
	</tr>
</table>
<?php
NHAF_Chatbot_Admin::form_close();
?>

<hr />

<h2><?php esc_html_e( 'Indexed content', 'nhaf-safari-chatbot' ); ?></h2>
<p>
	<button type="button" class="button button-primary" id="nhaf-run-crawl"><?php esc_html_e( 'Start new crawl', 'nhaf-safari-chatbot' ); ?></button>
	<button type="button" class="button button-secondary" id="nhaf-clear-kb"><?php esc_html_e( 'Clear knowledge base', 'nhaf-safari-chatbot' ); ?></button>
	<span class="nhaf-test-result" id="nhaf-crawl-result"></span>
</p>

<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'URL', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Characters', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Status', 'nhaf-safari-chatbot' ); ?></th>
			<th><?php esc_html_e( 'Last crawled', 'nhaf-safari-chatbot' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $entries ) ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No content indexed yet. Add URLs above and start a crawl.', 'nhaf-safari-chatbot' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $entries as $e ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( $e['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $e['url'] ); ?></a></td>
					<td><?php echo esc_html( number_format_i18n( (int) $e['length'] ) ); ?></td>
					<td><?php echo esc_html( $e['status'] ); ?></td>
					<td><?php echo esc_html( $e['last_crawled'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
