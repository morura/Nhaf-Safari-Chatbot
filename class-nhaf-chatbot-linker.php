<?php
/**
 * Auto-link destinations and countries in chatbot responses.
 *
 * @package NHAF_Safari_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NHAF_Chatbot_Linker
 */
class NHAF_Chatbot_Linker {

	/**
	 * Apply destination links to a chatbot response.
	 *
	 * Detects mentions of countries, regions, and safari attractions
	 * and replaces them with affiliate-tracked Safari.com links (if configured).
	 *
	 * Example: "Kenya is famous for the Maasai Mara" becomes
	 *   "<a href='https://www.safari.com/destinations/kenya?a=MR9'>Kenya</a> is famous for the
	 *   <a href='...'>Maasai Mara</a>"
	 *
	 * @param string $html Bot response HTML.
	 * @return string HTML with destination links inserted.
	 */
	public static function apply_destination_links( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		$destinations = self::get_linkable_destinations();
		if ( empty( $destinations ) ) {
			return $html;
		}

		/**
		 * Allow customization of which destinations get linked.
		 *
		 * @param array $destinations Associative array: display name => url slug.
		 * @param string $html Original HTML.
		 */
		$destinations = apply_filters( 'nhaf_chatbot_linkable_destinations', $destinations, $html );

		return self::link_destinations_in_html( $html, $destinations );
	}

	/**
	 * Get the list of destinations that should be auto-linked.
	 *
	 * @return array Associative array: destination name => safari.com path.
	 */
	private static function get_linkable_destinations() {
		// Common African countries and safari destinations.
		// The key is what appears in text; value is the Safari.com path slug.
		$destinations = array(
			'Kenya'          => 'kenya',
			'Tanzania'       => 'tanzania',
			'South Africa'   => 'south-africa',
			'Botswana'       => 'botswana',
			'Zimbabwe'       => 'zimbabwe',
			'Namibia'        => 'namibia',
			'Uganda'         => 'uganda',
			'Rwanda'         => 'rwanda',
			'Zambia'         => 'zambia',
			'Malawi'         => 'malawi',
			'Ethiopia'       => 'ethiopia',
			'Madagascar'     => 'madagascar',

			'Serengeti'      => 'destinations/tanzania',
			'Maasai Mara'    => 'destinations/kenya',
			'Ngorongoro'     => 'destinations/tanzania',
			'Kruger'         => 'destinations/south-africa',
			'Okavango Delta' => 'destinations/botswana',
			'Timbavati'      => 'destinations/south-africa',
			'Sabi Sands'     => 'destinations/south-africa',
			'Kalahari'       => 'destinations/botswana',
			'Victoria Falls' => 'destinations/zimbabwe',
		);

		/**
		 * Filter destinations to auto-link.
		 *
		 * @param array $destinations Destination name => slug pairs.
		 */
		return apply_filters( 'nhaf_chatbot_destination_links', $destinations );
	}

	/**
	 * Replace destination names with affiliate-tracked links in HTML.
	 *
	 * Uses word-boundary regex to avoid partial matches.
	 *
	 * @param string $html Input HTML.
	 * @param array  $destinations Destination name => slug map.
	 * @return string HTML with links inserted.
	 */
	private static function link_destinations_in_html( $html, $destinations ) {
		$base_url = NHAF_Chatbot_Settings::get( 'affiliate_base_url', 'https://www.safari.com' );
		$aff_id   = NHAF_Chatbot_Settings::get( 'affiliate_id', '' );

		foreach ( $destinations as $name => $slug ) {
			// Skip if already linked to avoid nested links.
			if ( false !== strpos( $html, '<a href=' ) && false !== strpos( $html, '>' . $name . '</a>' ) ) {
				continue;
			}

			$link = self::build_destination_link( $base_url, $slug, $aff_id, $name );

			// Word-boundary match to avoid partial word replacement.
			// e.g., "Serengeti" won't match "Serengetis" but will match "the Serengeti"
			$pattern = '/\b' . preg_quote( $name, '/' ) . '\b/i';
			$html    = preg_replace(
				$pattern,
				$link,
				$html,
				-1, // replace all
				$count
			);

			// Limit replacements per destination to avoid over-linking.
			if ( $count > 5 ) {
				break;
			}
		}

		return $html;
	}

	/**
	 * Build a destination affiliate link.
	 *
	 * @param string $base_url Base Safari.com URL.
	 * @param string $slug     Path slug (e.g., 'kenya', 'destinations/botswana').
	 * @param string $aff_id   Affiliate ID (or empty).
	 * @param string $text     Display text.
	 * @return string HTML anchor tag.
	 */
	private static function build_destination_link( $base_url, $slug, $aff_id, $text ) {
		$url = untrailingslashit( $base_url ) . '/' . trim( $slug, '/' );

		if ( ! empty( $aff_id ) ) {
			$url = add_query_arg( 'a', urlencode( $aff_id ), $url );
		}

		$url  = esc_url( $url );
		$text = esc_html( $text );

		return sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', $url, $text );
	}
}
