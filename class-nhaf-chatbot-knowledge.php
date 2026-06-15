<?php
/**
 * Knowledge sources: crawling, web search, Safari.com API and context assembly.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Knowledge
 */
class NHAF_Chatbot_Knowledge {

	const POST_TYPE = 'nhaf_chatbot_knowledge';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'nhaf_chatbot_recrawl_event', array( __CLASS__, 'run_scheduled_crawl' ) );
		add_action( 'update_option_' . NHAF_CHATBOT_OPTION_KEY, array( __CLASS__, 'maybe_reschedule_crawl' ), 10, 0 );
	}

	/**
	 * Register the internal knowledge custom post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Chatbot Knowledge', 'nhaf-safari-chatbot' ),
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'rewrite'      => false,
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
			)
		);
	}

	/**
	 * Ensure the recrawl cron event matches the configured interval.
	 */
	public static function maybe_reschedule_crawl() {
		NHAF_Chatbot_Settings::flush_cache();
		$interval = NHAF_Chatbot_Settings::get( 'recrawl_interval', 'weekly' );

		$timestamp = wp_next_scheduled( 'nhaf_chatbot_recrawl_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nhaf_chatbot_recrawl_event' );
		}

		$recurrence_map = array(
			'daily'   => 'daily',
			'weekly'  => 'weekly',
			'monthly' => 'nhaf_monthly',
		);
		$recurrence = isset( $recurrence_map[ $interval ] ) ? $recurrence_map[ $interval ] : 'weekly';

		if ( ! wp_next_scheduled( 'nhaf_chatbot_recrawl_event' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, 'nhaf_chatbot_recrawl_event' );
		}
	}

	/**
	 * Add a custom monthly cron schedule.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_schedule( $schedules ) {
		$schedules['nhaf_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once Monthly (Safari Chatbot)', 'nhaf-safari-chatbot' ),
		);
		return $schedules;
	}

	/**
	 * Crawl a list of URLs and store sanitised content.
	 *
	 * @param array $urls URLs to crawl. Falls back to configured list.
	 * @return array Summary { crawled, failed, skipped }.
	 */
	public static function crawl( $urls = array() ) {
		global $wpdb;

		if ( empty( $urls ) ) {
			$urls = (array) NHAF_Chatbot_Settings::get( 'crawl_urls', array() );
		}

		$table   = $wpdb->prefix . 'nhaf_chatbot_knowledge';
		$summary = array( 'crawled' => 0, 'failed' => 0, 'skipped' => 0 );

		foreach ( $urls as $url ) {
			$url = esc_url_raw( trim( $url ) );
			if ( empty( $url ) || ! wp_http_validate_url( $url ) ) {
				$summary['skipped']++;
				continue;
			}

			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => 30,
					'user-agent' => 'NomadHorizonsSafariBot/1.0 (+' . home_url() . ')',
					'headers'    => array( 'Accept' => 'text/html' ),
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				$summary['failed']++;
				NHAF_Chatbot_Security::log( 'Crawl failed for ' . $url );
				continue;
			}

			$html    = wp_remote_retrieve_body( $response );
			$content = self::extract_text( $html );
			$hash    = md5( $content );

			// Skip if unchanged.
			$existing_hash = $wpdb->get_var( $wpdb->prepare( "SELECT content_hash FROM {$table} WHERE url = %s", $url ) ); // phpcs:ignore WordPress.DB.PreparedSQL

			if ( $existing_hash === $hash ) {
				// Just bump the timestamp.
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array( 'last_crawled' => current_time( 'mysql' ), 'status' => 'indexed' ),
					array( 'url' => $url ),
					array( '%s', '%s' ),
					array( '%s' )
				);
				$summary['skipped']++;
				continue;
			}

			$data = array(
				'url'          => $url,
				'content'      => $content,
				'content_hash' => $hash,
				'last_crawled' => current_time( 'mysql' ),
				'status'       => 'indexed',
			);

			if ( null !== $existing_hash ) {
				$wpdb->update( $table, $data, array( 'url' => $url ), array( '%s', '%s', '%s', '%s', '%s' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				$wpdb->insert( $table, $data, array( '%s', '%s', '%s', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}

			// Mirror to the CPT for easy management.
			self::upsert_cpt( $url, $content );

			$summary['crawled']++;
		}

		/**
		 * Fires after a crawl run completes.
		 *
		 * @param array $summary Counts of crawled/failed/skipped URLs.
		 */
		do_action( 'nhaf_chatbot_on_crawl_complete', $summary );

		return $summary;
	}

	/**
	 * Scheduled crawl callback.
	 */
	public static function run_scheduled_crawl() {
		self::crawl();
	}

	/**
	 * Store/refresh a knowledge CPT entry.
	 *
	 * @param string $url     Source URL.
	 * @param string $content Sanitised content.
	 */
	private static function upsert_cpt( $url, $content ) {
		$existing = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'meta_key'    => '_nhaf_source_url',
				'meta_value'  => $url,
				'numberposts' => 1,
				'fields'      => 'ids',
				'post_status' => 'any',
			)
		);

		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => wp_trim_words( $content, 8, '…' ) . ' — ' . wp_parse_url( $url, PHP_URL_HOST ),
			'post_content' => wp_kses_post( $content ),
		);

		if ( ! empty( $existing ) ) {
			$postarr['ID'] = (int) $existing[0];
			wp_update_post( $postarr );
		} else {
			$post_id = wp_insert_post( $postarr );
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_nhaf_source_url', $url );
			}
		}
	}

	/**
	 * Convert HTML to clean plain text, trimmed to a sane length.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private static function extract_text( $html ) {
		// Drop scripts/styles/nav/footer.
		$html = preg_replace( '#<(script|style|noscript|nav|footer|header|svg)[^>]*>.*?</\1>#is', ' ', $html );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( $text );
		// Cap to ~8000 chars to keep prompts manageable.
		if ( mb_strlen( $text ) > 8000 ) {
			$text = mb_substr( $text, 0, 8000 );
		}
		return $text;
	}

	/**
	 * Clear all stored knowledge.
	 */
	public static function clear() {
		global $wpdb;
		$table = $wpdb->prefix . 'nhaf_chatbot_knowledge';
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB

		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'numberposts' => -1,
				'fields'      => 'ids',
				'post_status' => 'any',
			)
		);
		foreach ( $posts as $pid ) {
			wp_delete_post( $pid, true );
		}
	}

	/**
	 * List indexed entries for the admin screen.
	 *
	 * @return array
	 */
	public static function list_entries() {
		global $wpdb;
		$table = $wpdb->prefix . 'nhaf_chatbot_knowledge';
		return $wpdb->get_results( "SELECT id, url, last_crawled, status, CHAR_LENGTH(content) AS length FROM {$table} ORDER BY last_crawled DESC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Assemble grounding context for a user query from all enabled sources.
	 *
	 * @param string $query User message.
	 * @return string
	 */
	public static function build_context( $query ) {
		$pieces = array();

		// 1. Stored knowledge (simple keyword relevance).
		$kb = self::search_stored_knowledge( $query );
		if ( ! empty( $kb ) ) {
			$pieces[] = "Indexed Safari.com content:\n" . $kb;
		}

		// 2. Real-time web search.
		if ( NHAF_Chatbot_Settings::get( 'enable_web_search' ) ) {
			$search = self::web_search( $query );
			if ( ! empty( $search ) ) {
				$pieces[] = "Live web search results (safari.com):\n" . $search;
			}
		}

		// 3. Safari.com API.
		$api = self::query_safari_api( $query );
		if ( ! empty( $api ) ) {
			$pieces[] = "Safari.com API data:\n" . $api;
		}

		/**
		 * Filter the assembled knowledge sources/context.
		 *
		 * @param array  $pieces Context fragments.
		 * @param string $query  The user query.
		 */
		$pieces = apply_filters( 'nhaf_chatbot_knowledge_sources', $pieces, $query );

		$context = implode( "\n\n", array_filter( $pieces ) );

		// Keep total context bounded.
		if ( mb_strlen( $context ) > 12000 ) {
			$context = mb_substr( $context, 0, 12000 );
		}
		return $context;
	}

	/**
	 * Naive relevance search over stored content.
	 *
	 * @param string $query User query.
	 * @return string
	 */
	private static function search_stored_knowledge( $query ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nhaf_chatbot_knowledge';

		$terms = array_filter( explode( ' ', preg_replace( '/[^a-z0-9 ]/i', ' ', $query ) ), function ( $t ) {
			return mb_strlen( $t ) > 3;
		} );
		if ( empty( $terms ) ) {
			return '';
		}

		$like = '%' . $wpdb->esc_like( $terms[0] ) . '%';
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			$wpdb->prepare(
				"SELECT url, content FROM {$table} WHERE status = 'indexed' AND content LIKE %s LIMIT 3",
				$like
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return '';
		}

		$out = '';
		foreach ( $rows as $row ) {
			$out .= '[' . $row['url'] . "]\n" . mb_substr( $row['content'], 0, 1500 ) . "\n\n";
		}
		return trim( $out );
	}

	/**
	 * Perform a live web search via SerpAPI or Google Custom Search.
	 *
	 * @param string $query User query.
	 * @return string
	 */
	private static function web_search( $query ) {
		$provider = NHAF_Chatbot_Settings::get( 'search_provider', 'serpapi' );
		$api_key  = NHAF_Chatbot_Settings::get( 'search_api_key' );
		$count    = (int) NHAF_Chatbot_Settings::get( 'search_results_count', 5 );
		$count    = max( 3, min( 10, $count ) );

		if ( empty( $api_key ) ) {
			return '';
		}

		$scoped = 'site:safari.com ' . $query;

		if ( 'google_cse' === $provider ) {
			$cx  = NHAF_Chatbot_Settings::get( 'search_cse_id' );
			$url = add_query_arg(
				array(
					'key' => $api_key,
					'cx'  => $cx,
					'q'   => rawurlencode( $scoped ),
					'num' => $count,
				),
				'https://www.googleapis.com/customsearch/v1'
			);
		} else {
			$url = add_query_arg(
				array(
					'engine' => 'google',
					'q'      => rawurlencode( $scoped ),
					'num'    => $count,
					'api_key' => $api_key,
				),
				'https://serpapi.com/search'
			);
		}

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			NHAF_Chatbot_Security::log( 'Web search failed.' );
			return '';
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$results = array();

		if ( 'google_cse' === $provider && ! empty( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$results[] = '- ' . $item['title'] . ': ' . ( $item['snippet'] ?? '' ) . ' (' . $item['link'] . ')';
			}
		} elseif ( ! empty( $data['organic_results'] ) ) {
			foreach ( $data['organic_results'] as $item ) {
				$results[] = '- ' . ( $item['title'] ?? '' ) . ': ' . ( $item['snippet'] ?? '' ) . ' (' . ( $item['link'] ?? '' ) . ')';
			}
		}

		return implode( "\n", array_slice( $results, 0, $count ) );
	}

	/**
	 * Query the Safari.com API (reusing the Finder plugin token if available).
	 *
	 * @param string $query User query.
	 * @return string
	 */
	private static function query_safari_api( $query ) {
		$token = self::get_safari_token();
		if ( empty( $token ) ) {
			return '';
		}

		$base      = rtrim( NHAF_Chatbot_Settings::get( 'safari_api_base', 'https://api.safari.com/v1' ), '/' );
		$endpoints = (array) NHAF_Chatbot_Settings::get( 'safari_endpoints', array( 'destinations' ) );

		// Pick the most relevant single endpoint by keyword to limit calls.
		$endpoint = 'destinations';
		if ( false !== stripos( $query, 'experience' ) && in_array( 'experiences', $endpoints, true ) ) {
			$endpoint = 'experiences';
		} elseif ( ( false !== stripos( $query, 'safari' ) || false !== stripos( $query, 'tour' ) ) && in_array( 'safaris', $endpoints, true ) ) {
			$endpoint = 'safaris';
		}

		if ( ! in_array( $endpoint, $endpoints, true ) ) {
			return '';
		}

		$response = wp_remote_get(
			$base . '/' . $endpoint,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data ) ) {
			return '';
		}

		// Summarise the first few items into compact text.
		$items = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( is_array( $data ) ? $data : array() );
		$lines = array();
		$i     = 0;
		foreach ( $items as $item ) {
			if ( $i++ >= 8 ) {
				break;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name = $item['name'] ?? ( $item['title'] ?? '' );
			$desc = $item['description'] ?? ( $item['summary'] ?? '' );
			$lines[] = '- ' . trim( $name . ': ' . wp_trim_words( wp_strip_all_tags( (string) $desc ), 30 ) );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Resolve the Safari.com API token, reusing the Finder plugin's if configured.
	 *
	 * @return string
	 */
	public static function get_safari_token() {
		if ( NHAF_Chatbot_Settings::get( 'use_finder_token' ) ) {
			// Preferred: ask the Safari Finder plugin directly when it is active.
			// Guarded with reflection + try/catch so differences in the Finder
			// plugin's API (instance vs static, visibility, signature) can
			// never fatal this site.
			$token = self::token_from_finder_class();
			if ( ! empty( $token ) && is_string( $token ) ) {
				return $token;
			}

			// Fallback: read the token the Finder plugin stored in options.
			$finder = get_option( 'nhaf_safari_finder_settings' );
			if ( is_array( $finder ) && ! empty( $finder['api_token'] ) ) {
				// The Finder plugin stores its token encrypted the same way.
				$token = $finder['api_token'];
				if ( 0 === strpos( (string) $token, 'enc::' ) ) {
					$token = NHAF_Chatbot_Settings::decrypt( $token );
				}
				return $token;
			}
			// Alternative known option name.
			$alt = get_option( 'nhaf_safari_api_token' );
			if ( $alt ) {
				return is_string( $alt ) && 0 === strpos( $alt, 'enc::' ) ? NHAF_Chatbot_Settings::decrypt( $alt ) : $alt;
			}
		}

		return NHAF_Chatbot_Settings::get( 'safari_api_token' );
	}

	/**
	 * Safely obtain a token from the Safari Finder plugin's get_token()
	 * regardless of whether it is static or an instance method.
	 *
	 * @return string Token, or empty string when unavailable.
	 */
	private static function token_from_finder_class() {
		if ( ! class_exists( 'NHAF_Safari_Finder_Plugin' ) ) {
			return '';
		}

		try {
			$ref = new ReflectionClass( 'NHAF_Safari_Finder_Plugin' );

			if ( ! $ref->hasMethod( 'get_token' ) ) {
				return '';
			}

			$method = $ref->getMethod( 'get_token' );
			if ( ! $method->isPublic() || $method->getNumberOfRequiredParameters() > 0 ) {
				return '';
			}

			// Static method: call directly.
			if ( $method->isStatic() ) {
				$token = NHAF_Safari_Finder_Plugin::get_token();
				return is_string( $token ) ? $token : '';
			}

			// Instance method: locate a public static singleton accessor.
			foreach ( array( 'instance', 'get_instance', 'getInstance' ) as $accessor ) {
				if ( ! $ref->hasMethod( $accessor ) ) {
					continue;
				}
				$acc = $ref->getMethod( $accessor );
				if ( ! $acc->isStatic() || ! $acc->isPublic() || $acc->getNumberOfRequiredParameters() > 0 ) {
					continue;
				}
				$instance = call_user_func( array( 'NHAF_Safari_Finder_Plugin', $accessor ) );
				if ( is_object( $instance ) ) {
					$token = $instance->get_token();
					return is_string( $token ) ? $token : '';
				}
			}
		} catch ( Throwable $e ) {
			NHAF_Chatbot_Security::log( 'Safari Finder token lookup failed: ' . $e->getMessage() );
		}

		return '';
	}
}
