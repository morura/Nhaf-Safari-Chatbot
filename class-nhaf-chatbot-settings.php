<?php
/**
 * Centralised settings access with transparent encryption for secret fields.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Settings
 */
class NHAF_Chatbot_Settings {

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Fields that must be stored encrypted at rest.
	 *
	 * @var array
	 */
	private static $secret_fields = array(
		'openai_api_key',
		'anthropic_api_key',
		'search_api_key',
		'safari_api_token',
		'recaptcha_secret',
	);

	/**
	 * Retrieve the full settings array (secrets decrypted).
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$raw = get_option( NHAF_CHATBOT_OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		foreach ( self::$secret_fields as $field ) {
			if ( isset( $raw[ $field ] ) && '' !== $raw[ $field ] ) {
				$raw[ $field ] = self::decrypt( $raw[ $field ] );
			}
		}

		self::$cache = $raw;
		return $raw;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist a settings array, encrypting secret fields.
	 *
	 * @param array $settings Settings to merge and save.
	 */
	public static function save( array $settings ) {
		$current = self::all();
		$merged  = array_merge( $current, $settings );

		$to_store = $merged;
		foreach ( self::$secret_fields as $field ) {
			if ( isset( $to_store[ $field ] ) && '' !== $to_store[ $field ] ) {
				$to_store[ $field ] = self::encrypt( $to_store[ $field ] );
			}
		}

		update_option( NHAF_CHATBOT_OPTION_KEY, $to_store );
		self::$cache = $merged;
	}

	/**
	 * Whether a field is treated as a secret.
	 *
	 * @param string $field Field key.
	 * @return bool
	 */
	public static function is_secret( $field ) {
		return in_array( $field, self::$secret_fields, true );
	}

	/**
	 * Build the per-site encryption key.
	 *
	 * @return string 32-byte key.
	 */
	private static function get_key() {
		$salt = get_option( 'nhaf_chatbot_enc_salt' );
		if ( ! $salt ) {
			$salt = wp_generate_password( 64, true, true );
			update_option( 'nhaf_chatbot_enc_salt', $salt );
		}
		// Mix in WP secret keys so the key is not derivable from the DB alone.
		$material = $salt . ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' );
		return hash( 'sha256', $material, true );
	}

	/**
	 * Encrypt a string with AES-256-CBC.
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string Base64 encoded payload, or the original on failure.
	 */
	public static function encrypt( $plaintext ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $plaintext;
		}
		$key    = self::get_key();
		$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv     = openssl_random_pseudo_bytes( $iv_len );
		$cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return $plaintext;
		}
		return 'enc::' . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a value produced by encrypt().
	 *
	 * @param string $payload Stored value.
	 * @return string
	 */
	public static function decrypt( $payload ) {
		if ( ! is_string( $payload ) || 0 !== strpos( $payload, 'enc::' ) ) {
			// Not encrypted (e.g. legacy plaintext) - return as-is.
			return $payload;
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw    = base64_decode( substr( $payload, 5 ) );
		$key    = self::get_key();
		$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
		if ( strlen( $raw ) <= $iv_len ) {
			return '';
		}
		$iv     = substr( $raw, 0, $iv_len );
		$cipher = substr( $raw, $iv_len );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return ( false === $plain ) ? '' : $plain;
	}

	/**
	 * Clear the runtime cache (useful after external updates).
	 */
	public static function flush_cache() {
		self::$cache = null;
	}
}
