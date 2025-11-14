/**
 * Filename: image-title-alt.js
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.7.0
 * Last Modified: 14/11/2025
 * Description: Handles inline optimization of attachment titles/alt text within the media UI.
 */

( function ( $ ) {
	'use strict';

	const config = window.medialyticImageMeta || {};

	function setButtonState( $button, state ) {
		const original = $button.data( 'original-text' ) || $button.text();
		if ( ! $button.data( 'original-text' ) ) {
			$button.data( 'original-text', original );
		}

		if ( 'loading' === state ) {
			$button.prop( 'disabled', true ).text( config.messages?.updating || 'Updating…' );
		} else if ( 'success' === state ) {
			$button.text( config.messages?.success || 'Updated!' );
		} else if ( 'error' === state ) {
			$button.text( config.messages?.error || 'Error' );
		} else {
			$button.prop( 'disabled', false ).text( original );
		}
	}

	function pushFeedback( $button, message, isError ) {
		const $feedback = $button.closest( '.medialytic-image-meta-feedback' );
		if ( ! $feedback.length ) {
			return;
		}

		const cssClass = isError ? 'notice-error' : 'notice-success';
		$feedback.html( '<div class="notice ' + cssClass + '"><p>' + message + '</p></div>' );
	}

	function sendRequest( attachmentId, $button ) {
		setButtonState( $button, 'loading' );

		$.post(
			config.ajaxUrl,
			{
				action: 'medialytic_image_title_alt',
				post_id: attachmentId,
				fields: config.fields || [],
				capitalization: config.capitalization || 'ucwords',
				nonce: config.nonce,
			}
		)
			.done( function ( response ) {
				if ( response && response.success ) {
					setButtonState( $button, 'success' );
					pushFeedback( $button, response.data || '', false );
					setTimeout( function () {
						window.location.reload();
					}, 200 );
				} else {
					setButtonState( $button, 'error' );
					pushFeedback( $button, response?.data || ( config.messages?.error || 'Unable to update.' ), true );
					setTimeout( function () {
						setButtonState( $button, 'reset' );
					}, 2000 );
				}
			} )
			.fail( function () {
				setButtonState( $button, 'error' );
				pushFeedback( $button, config.messages?.error || 'Unable to update.', true );
				setTimeout( function () {
					setButtonState( $button, 'reset' );
				}, 2000 );
			} );
	}

	$( document ).on( 'click', '.medialytic-image-meta-trigger', function ( event ) {
		event.preventDefault();

		const $button = $( this );
		const attachmentId = parseInt( $button.data( 'postId' ), 10 );
		if ( ! attachmentId ) {
			return;
		}

		if ( $button.prop( 'disabled' ) ) {
			return;
		}

		sendRequest( attachmentId, $button );
	} );
}( jQuery ) );

