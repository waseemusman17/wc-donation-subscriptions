/**
 * WooCommerce Donation Subscriptions - frontend behaviour.
 *
 * Relies on the `wcDonationSubscriptions` object localized via
 * wp_localize_script() in the main plugin file.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var settings = window.wcDonationSubscriptions || {};
		var minAmount = parseFloat( settings.minAmount ) || 0;
		var $popup = $( '#wcdsDonationPopup' );
		var $popupMessage = $( '#wcdsDonationPopupMessage' );
		var $amountField = $( '#donation_amount' );

		if ( ! $popup.length || ! $amountField.length ) {
			return;
		}

		function formatMessage( template, amount ) {
			return ( template || '' ).replace( '%s', amount.toFixed( 2 ) );
		}

		function showDonationPopup( message ) {
			$popupMessage.text( message );
			$popup.fadeIn( 200 );
			$( 'body' ).addClass( 'wcds-popup-open' );
		}

		function hideDonationPopup() {
			$popup.fadeOut( 200 );
			$( 'body' ).removeClass( 'wcds-popup-open' );
		}

		$( document ).on( 'click', '#wcdsDonationPopupOk', hideDonationPopup );

		$popup.on( 'click', function ( e ) {
			if ( e.target === this ) {
				hideDonationPopup();
			}
		} );

		$( document ).on( 'keydown', function ( e ) {
			if ( 27 === e.keyCode && $popup.is( ':visible' ) ) {
				hideDonationPopup();
			}
		} );

		$amountField.on( 'blur', function () {
			var amount = parseFloat( $( this ).val() ) || 0;
			if ( amount < minAmount ) {
				showDonationPopup( formatMessage( settings.belowMinMessage, minAmount ) );
				$( this ).val( minAmount.toFixed( 2 ) );
			}
		} );

		$( document ).on( 'submit', 'form.cart', function ( e ) {
			var amount = parseFloat( $amountField.val() ) || 0;
			if ( amount < minAmount ) {
				e.preventDefault();
				showDonationPopup( formatMessage( settings.submitBelowMinMessage, minAmount ) );
				return false;
			}
		} );
	} );
} )( jQuery );
