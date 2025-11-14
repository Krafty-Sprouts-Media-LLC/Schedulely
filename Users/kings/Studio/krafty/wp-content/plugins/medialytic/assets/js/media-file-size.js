/* global medialyticMediaSize */
( function ( $ ) {
	'use strict';

	const settings = window.medialyticMediaSize || {};
	window.medialyticMediaSizeVariants = window.medialyticMediaSizeVariants || {};

	function request( action, data = {} ) {
		return fetch( settings.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams(
				Object.assign(
					{
						action,
						nonce: settings.nonce,
					},
					data
				)
			),
		} ).then( ( response ) => response.json() );
	}

	function renderSummary( totalHuman, tooltip ) {
		const content = '(' + totalHuman + ')' + ( tooltip ? '<span class="tooltiptext">' + tooltip + '</span>' : '' );
		let container = document.querySelector( '.medialytic-media-size-summary' );

		if ( container ) {
			container.innerHTML = content;
		} else {
			const span = document.createElement( 'span' );
			span.className = 'medialytic-media-size-summary';
			span.innerHTML = content;

			const heading = document.querySelector( 'h1, h2' );
			if ( heading ) {
				heading.appendChild( span );
			}
		}
	}

	function toggleButtonLoading( button, isLoading ) {
		if ( isLoading ) {
			button.dataset.originalText = button.textContent;
			button.innerHTML = '<div class="medialytic-media-size-button-loading"><div></div><div></div><div></div><div></div></div>';
			button.disabled = true;
		} else {
			button.textContent = button.dataset.originalText || settings.strings.indexMedia;
			button.disabled = false;
		}
	}

	function injectIndexButton() {
		if ( document.querySelector( '.medialytic-index-media' ) ) {
			return;
		}

		const button = document.createElement( 'button' );
		button.className = 'page-title-action medialytic-index-media';
		button.textContent = settings.strings.indexMedia;

		const header = document.querySelector( 'hr.wp-header-end' ) || document.querySelector( '.page-title-action' )?.parentElement;
		if ( header ) {
			header.parentNode.insertBefore( button, header );
		}

		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			runIndex( button, false );
		} );
	}

	function injectReindexMenu() {
		const submenu = document.querySelector( '#menu-media ul' );
		if ( ! submenu || submenu.querySelector( '.medialytic-reindex-media' ) ) {
			return;
		}

		const li = document.createElement( 'li' );
		const a = document.createElement( 'a' );
		a.href = '#';
		a.textContent = settings.strings.reindexMedia;
		a.className = 'medialytic-reindex-media';
		li.appendChild( a );
		submenu.appendChild( li );

		a.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			runIndex( a, true );
		} );
	}

	function runIndex( trigger, isReindex ) {
		toggleButtonLoading( trigger, true );

		request( 'medialytic_media_size_index', { reindex: isReindex ? 1 : 0 } )
			.then( ( response ) => {
				if ( response.success ) {
					if ( response.data.message ) {
						window.wp?.notices?.success( response.data.message ) || alert( response.data.message );
					}

					if ( Array.isArray( response.data.html ) ) {
						response.data.html.forEach( ( item ) => {
							const row = document.querySelector( '#post-' + item.attachment_id );
							if ( row ) {
								const cell = row.querySelector( '.medialytic_media_file_size' );
								if ( cell ) {
									cell.innerHTML = item.html;
								}
							}
						} );
					}
					refreshSummary();
					if ( response.data.variants ) {
						window.medialyticMediaSizeVariants = Object.assign(
							window.medialyticMediaSizeVariants || {},
							response.data.variants
						);
					}
					initVariantButtons();
				} else {
					const message = response?.data?.body || settings.strings.indexError;
					window.wp?.notices?.error( message ) || alert( message );
				}
			} )
			.catch( () => {
				window.wp?.notices?.error( settings.strings.indexError ) || alert( settings.strings.indexError );
			} )
			.finally( () => toggleButtonLoading( trigger, false ) );
	}

	function refreshSummary() {
		request( 'medialytic_media_size_index_count' ).then( ( response ) => {
			if ( response.success && response.data.TotalMLSize ) {
				renderSummary( response.data.TotalMLSize, response.data.TotalMLSize_Title );
			}
		} );
	}

	let variantsHandlerBound = false;
	function initVariantButtons() {
		if ( variantsHandlerBound ) {
			return;
		}

		$( document ).on( 'click', '.medialytic-media-size-variants-button', function ( event ) {
			event.preventDefault();
			const attachmentId = this.dataset.attachmentId;
			const data = ( window.medialyticMediaSizeVariants || {} )[ attachmentId ];
			if ( ! data ) {
				return;
			}

			const overlay = document.createElement( 'div' );
			overlay.className = 'medialytic-media-size-modal-overlay';
			const modal = document.createElement( 'div' );
			modal.className = 'medialytic-media-size-modal';
			overlay.appendChild( modal );

			data.sort( ( a, b ) => a.width - b.width );

			data.forEach( ( variant ) => {
				modal.innerHTML += `
					<div class="medialytic-media-size-modal-card">
						<span class="preview">
							${ variant.width }<br>x<br>${ variant.height }
							<a href="${ variant.filename }" target="_blank" rel="noopener">View</a>
						</span>
						<span class="filename">${ variant.filename.split( /[\\/]/ ).pop() }</span>
						<span class="size-name">${ variant.size }</span>
						<span>${ variant.filesize_hr }</span>
					</div>
				`;
			} );

			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					overlay.remove();
				}
			} );

			document.body.appendChild( overlay );
		} );

		variantsHandlerBound = true;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! document.querySelector( '.wp-list-table.media' ) ) {
			return;
		}

		injectIndexButton();
		injectReindexMenu();
		refreshSummary();
		initVariantButtons();
	} );
}( jQuery ) );

