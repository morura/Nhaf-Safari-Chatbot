<?php
/**
 * Admin view: LLM Configuration tab.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$provider      = NHAF_Chatbot_Settings::get( 'llm_provider', 'openai' );
$openai_model  = NHAF_Chatbot_Settings::get( 'openai_model', 'gpt-3.5-turbo' );
$openai_temp   = NHAF_Chatbot_Settings::get( 'openai_temperature', 0.5 );
$openai_tokens = (int) NHAF_Chatbot_Settings::get( 'openai_max_tokens', 600 );
$anthr_model   = NHAF_Chatbot_Settings::get( 'anthropic_model', 'claude-3-haiku-20240307' );
$anthr_temp    = NHAF_Chatbot_Settings::get( 'anthropic_temperature', 0.5 );
$ollama_ep     = NHAF_Chatbot_Settings::get( 'ollama_endpoint', 'http://localhost:11434' );
$ollama_model  = NHAF_Chatbot_Settings::get( 'ollama_model', 'llama2' );
$system_prompt = NHAF_Chatbot_Settings::get( 'system_prompt', '' );

$has_openai = '' !== (string) NHAF_Chatbot_Settings::get( 'openai_api_key', '' );
$has_anthr  = '' !== (string) NHAF_Chatbot_Settings::get( 'anthropic_api_key', '' );

$stored_hint = __( 'A key is stored. Leave blank to keep it; enter a new value to replace.', 'nhaf-safari-chatbot' );

NHAF_Chatbot_Admin::form_open( 'llm' );
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="llm_provider"><?php esc_html_e( 'LLM provider', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<select name="llm_provider" id="llm_provider" class="nhaf-provider-select">
				<option value="openai" <?php selected( $provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'nhaf-safari-chatbot' ); ?></option>
				<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>><?php esc_html_e( 'Anthropic Claude', 'nhaf-safari-chatbot' ); ?></option>
				<option value="ollama" <?php selected( $provider, 'ollama' ); ?>><?php esc_html_e( 'Ollama (local)', 'nhaf-safari-chatbot' ); ?></option>
			</select>
		</td>
	</tr>
</table>

<div class="nhaf-provider-panel" data-provider="openai">
	<h2><?php esc_html_e( 'OpenAI', 'nhaf-safari-chatbot' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="openai_api_key"><?php esc_html_e( 'API key', 'nhaf-safari-chatbot' ); ?></label></th>
			<td>
				<input type="password" name="openai_api_key" id="openai_api_key" value="" autocomplete="new-password" class="regular-text" placeholder="sk-…" />
				<?php if ( $has_openai ) : ?><p class="description"><?php echo esc_html( $stored_hint ); ?></p><?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="openai_model"><?php esc_html_e( 'Model', 'nhaf-safari-chatbot' ); ?></label></th>
			<td>
				<select name="openai_model" id="openai_model">
					<?php foreach ( array( 'gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'gpt-4o', 'gpt-4o-mini' ) as $m ) : ?>
						<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $openai_model, $m ); ?>><?php echo esc_html( $m ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="openai_temperature"><?php esc_html_e( 'Temperature', 'nhaf-safari-chatbot' ); ?></label></th>
			<td><input type="number" step="0.1" min="0" max="1" name="openai_temperature" id="openai_temperature" value="<?php echo esc_attr( $openai_temp ); ?>" class="small-text" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="openai_max_tokens"><?php esc_html_e( 'Max tokens', 'nhaf-safari-chatbot' ); ?></label></th>
			<td><input type="number" min="50" max="4000" name="openai_max_tokens" id="openai_max_tokens" value="<?php echo esc_attr( $openai_tokens ); ?>" class="small-text" /></td>
		</tr>
	</table>
</div>

<div class="nhaf-provider-panel" data-provider="anthropic">
	<h2><?php esc_html_e( 'Anthropic Claude', 'nhaf-safari-chatbot' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="anthropic_api_key"><?php esc_html_e( 'API key', 'nhaf-safari-chatbot' ); ?></label></th>
			<td>
				<input type="password" name="anthropic_api_key" id="anthropic_api_key" value="" autocomplete="new-password" class="regular-text" placeholder="sk-ant-…" />
				<?php if ( $has_anthr ) : ?><p class="description"><?php echo esc_html( $stored_hint ); ?></p><?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="anthropic_model"><?php esc_html_e( 'Model', 'nhaf-safari-chatbot' ); ?></label></th>
			<td>
				<select name="anthropic_model" id="anthropic_model">
					<?php
					$anthr_models = array(
						'claude-3-haiku-20240307'  => 'Claude 3 Haiku',
						'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
						'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
					);
					foreach ( $anthr_models as $val => $label ) :
						?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $anthr_model, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="anthropic_temperature"><?php esc_html_e( 'Temperature', 'nhaf-safari-chatbot' ); ?></label></th>
			<td><input type="number" step="0.1" min="0" max="1" name="anthropic_temperature" id="anthropic_temperature" value="<?php echo esc_attr( $anthr_temp ); ?>" class="small-text" /></td>
		</tr>
	</table>
</div>

<div class="nhaf-provider-panel" data-provider="ollama">
	<h2><?php esc_html_e( 'Ollama (local)', 'nhaf-safari-chatbot' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="ollama_endpoint"><?php esc_html_e( 'Endpoint URL', 'nhaf-safari-chatbot' ); ?></label></th>
			<td><input type="url" name="ollama_endpoint" id="ollama_endpoint" value="<?php echo esc_attr( $ollama_ep ); ?>" class="regular-text" placeholder="http://localhost:11434" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="ollama_model"><?php esc_html_e( 'Model name', 'nhaf-safari-chatbot' ); ?></label></th>
			<td><input type="text" name="ollama_model" id="ollama_model" value="<?php echo esc_attr( $ollama_model ); ?>" class="regular-text" placeholder="llama2" /></td>
		</tr>
	</table>
</div>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="system_prompt"><?php esc_html_e( 'System prompt', 'nhaf-safari-chatbot' ); ?></label></th>
		<td>
			<textarea name="system_prompt" id="system_prompt" rows="6" class="large-text code"><?php echo esc_textarea( $system_prompt ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Instructions sent to the model on every request.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Test connection', 'nhaf-safari-chatbot' ); ?></th>
		<td>
			<button type="button" class="button" id="nhaf-test-llm"><?php esc_html_e( 'Send test message', 'nhaf-safari-chatbot' ); ?></button>
			<span class="nhaf-test-result" id="nhaf-test-llm-result"></span>
			<p class="description"><?php esc_html_e( 'Save your changes first, then test the active provider.', 'nhaf-safari-chatbot' ); ?></p>
		</td>
	</tr>
</table>
<?php
NHAF_Chatbot_Admin::form_close();
