/**
 * Nomad Horizons Safari AI Chatbot — admin scripts.
 *
 * @package NHAF_Safari_Chatbot
 */

/* global jQuery, nhafAdmin */
( function ( $ ) {
	'use strict';

	$( function () {
		// Color pickers.
		if ( $.fn.wpColorPicker ) {
			$( '.nhaf-color-field' ).wpColorPicker();
		}

		// Provider panel toggling on the LLM tab.
		var $providerSelect = $( '#llm_provider' );

		function togglePanels() {
			var provider = $providerSelect.val();
			$( '.nhaf-provider-panel' ).removeClass( 'is-active' )
				.filter( '[data-provider="' + provider + '"]' )
				.addClass( 'is-active' );
		}

		if ( $providerSelect.length ) {
			togglePanels();
			$providerSelect.on( 'change', togglePanels );
		}

		/**
		 * Run an admin AJAX action and report into a result element.
		 *
		 * @param {string} action   AJAX action name.
		 * @param {Object} data     Extra request data.
		 * @param {jQuery} $button  Button to disable while running.
		 * @param {jQuery} $result  Result span.
		 * @param {string} busyText Text shown while running.
		 */
		function runAjax( action, data, $button, $result, busyText ) {
			$button.prop( 'disabled', true );
			$result.removeClass( 'is-success is-error' ).text( busyText );

			$.post(
				nhafAdmin.ajaxUrl,
				$.extend( { action: action, nonce: nhafAdmin.nonce }, data || {} )
			)
				.done( function ( res ) {
					var ok  = res && res.success;
					var msg = ( res && res.data && res.data.message ) ? res.data.message : ( ok ? nhafAdmin.i18n.success : nhafAdmin.i18n.failed );
					$result.toggleClass( 'is-success', ok ).toggleClass( 'is-error', ! ok ).text( msg );
				} )
				.fail( function () {
					$result.addClass( 'is-error' ).text( nhafAdmin.i18n.failed );
				} )
				.always( function () {
					$button.prop( 'disabled', false );
				} );
		}

		// Test LLM connection.
		$( '#nhaf-test-llm' ).on( 'click', function () {
			runAjax( 'nhaf_test_llm', {}, $( this ), $( '#nhaf-test-llm-result' ), nhafAdmin.i18n.testing );
		} );

		// Test Safari.com API.
		$( '#nhaf-test-safari' ).on( 'click', function () {
			runAjax( 'nhaf_test_safari_api', {}, $( this ), $( '#nhaf-test-safari-result' ), nhafAdmin.i18n.testing );
		} );

		// Run crawl now.
		$( '#nhaf-run-crawl' ).on( 'click', function () {
			runAjax( 'nhaf_run_crawl', {}, $( this ), $( '#nhaf-crawl-result' ), nhafAdmin.i18n.crawling );
		} );

		// Clear knowledge base (confirm first).
		$( '#nhaf-clear-kb' ).on( 'click', function () {
			if ( ! window.confirm( nhafAdmin.i18n.confirmClear ) ) {
				return;
			}
			runAjax( 'nhaf_clear_kb', {}, $( this ), $( '#nhaf-crawl-result' ), nhafAdmin.i18n.testing );
		} );

		// Lead status changes.
		$( document ).on( 'change', '.nhaf-lead-status', function () {
			var $select = $( this );
			$select.prop( 'disabled', true );
			$.post(
				nhafAdmin.ajaxUrl,
				{
					action: 'nhaf_update_lead_status',
					nonce: nhafAdmin.nonce,
					lead_id: $select.data( 'lead-id' ),
					status: $select.val()
				}
			).always( function () {
				$select.prop( 'disabled', false );
			} );
		} );
	} );
}( jQuery ) );
