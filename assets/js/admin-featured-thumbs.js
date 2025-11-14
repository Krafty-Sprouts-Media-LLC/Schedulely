/**
 * Filename: admin-featured-thumbs.js
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.5.0
 * Last Modified: 14/11/2025
 * Description: Inline featured image management for the Medialytic list-table column.
 */

( function ( $, wp ) {
	if ( ! wp || ! wp.media || ! wp.ajax ) {
		return;
	}

	const settings = window.medialyticFeaturedThumbs || {};
	const state = {
		frame: null,
		currentPostId: null,
		currentNonce: null,
	};

	function toggleSpinner( $cell, isVisible ) {
		const $spinner = $cell.find( '.spinner' );
		if ( isVisible ) {
			$spinner.addClass( 'is-active' );
		} else {
			$spinner.removeClass( 'is-active' );
		}
	}

	function refreshCell( postId, html ) {
		const cell = document.querySelector( `[data-medialytic-thumb="${ postId }"]` );
		if ( cell && html ) {
			cell.outerHTML = html;
		}
	}

	function sendRequest( attachmentId ) {
		if ( ! state.currentPostId || ! state.currentNonce ) {
			return;
		}

		const $cell = $( `[data-medialytic-thumb="${ state.currentPostId }"]` );
		toggleSpinner( $cell, true );

		wp.ajax
			.post( 'medialytic_set_featured_image', {
				post_id: state.currentPostId,
				attachment_id: attachmentId,
				nonce: state.currentNonce,
			} )
			.done( ( response ) => {
				if ( response && response.html ) {
					refreshCell( state.currentPostId, response.html );
				}
			} )
			.fail( () => {
				window.alert( settings.l10n?.error || 'Unable to update the featured image.' );
			} )
			.always( () => {
				toggleSpinner( $cell, false );
			} );
	}

	function ensureFrame() {
		if ( state.frame ) {
			return state.frame;
		}

		state.frame = wp.media( {
			title: settings.l10n?.title || 'Select featured image',
			button: {
				text: settings.l10n?.button || 'Use featured image',
			},
			library: {
				type: 'image',
			},
			multiple: false,
		} );

		state.frame.on( 'select', () => {
			const attachment = state.frame.state().get( 'selection' ).first();
			if ( attachment ) {
				sendRequest( attachment.get( 'id' ) );
			}
		} );

		return state.frame;
	}

	function handleToggle( event ) {
		event.preventDefault();

		const $button = $( event.currentTarget );
		const $cell = $button.closest( '.medialytic-thumb-cell' );

		state.currentPostId = parseInt( $button.data( 'postId' ), 10 );
		state.currentNonce = $cell.data( 'nonce' );

		const frame = ensureFrame();
		frame.open();
	}

	function handleRemove( event ) {
		event.preventDefault();

		const $button = $( event.currentTarget );
		const $cell = $button.closest( '.medialytic-thumb-cell' );

		state.currentPostId = parseInt( $button.data( 'postId' ), 10 );
		state.currentNonce = $cell.data( 'nonce' );

		if ( window.confirm( settings.l10n?.remove || 'Remove featured image?' ) ) {
			sendRequest( 0 );
		}
	}

	$( document ).on( 'click', '.medialytic-featured-image-toggle', handleToggle );
	$( document ).on( 'click', '.medialytic-featured-image-remove', handleRemove );
}( jQuery, window.wp ) );

