/**
 * Filename: duplicate-finder.js
 * Author: Krafty Sprouts Media, LLC
 * Created: 14/11/2025
 * Version: 1.2.0
 * Last Modified: 14/11/2025
 * Description: Admin interactions for the Medialytic duplicate image finder.
 */

( function ( $ ) {
	'use strict';

	const config = window.medialyticDuplicateFinder || {};

	if ( ! config.ajaxUrl || ! config.nonce ) {
		return;
	}

	let dismissedGroups = [];
	let lastScanPayload = {};

	const selectors = {
		scanButton: '#medialytic-dif-scan-btn',
		loadCacheButton: '#medialytic-dif-load-cache-btn',
		clearCacheButton: '#medialytic-dif-clear-cache-btn',
		deleteSelectedButton: '#medialytic-dif-delete-selected-btn',
		resultsContainer: '#medialytic-dif-results',
		status: '#medialytic-dif-scan-status',
		group: '.medialytic-duplicate-group',
		groupDismissButton: '.medialytic-dismiss-group',
		imageItem: '.medialytic-image-item',
		imageCheckbox: '.medialytic-image-checkbox',
		deleteSingle: '.medialytic-delete-single',
		body: 'body',
	};

	const ajaxActions = {
		scan: 'medialytic_scan_duplicate_images',
		save: 'medialytic_save_duplicate_scan',
		get: 'medialytic_get_duplicate_scan',
		clear: 'medialytic_clear_duplicate_scan',
		delete: 'medialytic_delete_duplicate_images',
	};

	function updateStatus( message ) {
		$( selectors.status ).html( message );
	}

	function renderGroups( data, fromCache ) {
		const resultsContainer = $( selectors.resultsContainer );
		resultsContainer.empty();

		if ( ! data.groups?.length ) {
			resultsContainer.html( '<p>' + config.strings.noDuplicates + '</p>' );
			updateStatus( config.strings.noDuplicatesFound );
			return;
		}

		$( selectors.deleteSelectedButton ).show();

		let displayedCount = 0;

		data.groups.forEach( ( group, index ) => {
			if ( fromCache && dismissedGroups.includes( index ) ) {
				return;
			}

			displayedCount++;

			const groupElement = $( '<div/>' )
				.addClass( 'medialytic-duplicate-group' )
				.attr( 'data-group-id', index );

			const header = $( '<div/>' ).addClass( 'medialytic-group-header' );
			header.append( $( '<h3/>' ).text( config.strings.groupTitle.replace( '%s', group.base_name ) ) );

			const dismissButton = $( '<button/>' )
				.addClass( 'button button-small medialytic-dismiss-group' )
				.attr( 'data-group-id', index )
				.text( config.strings.dismissGroup );

			header.append( dismissButton );
			groupElement.append( header );

			const grid = $( '<div/>' ).addClass( 'medialytic-images-grid' );

			group.images.forEach( ( image, imageIndex ) => {
				const isOriginal = imageIndex === 0;
				const item = $( '<div/>' )
					.addClass( 'medialytic-image-item' )
					.addClass( isOriginal ? 'original' : 'duplicate' )
					.attr( 'data-id', image.id );

				const checkbox = $( '<input/>' )
					.attr( 'type', 'checkbox' )
					.addClass( 'medialytic-image-checkbox' )
					.attr( 'data-id', image.id );

				const badge = $( '<span/>' )
					.addClass( 'medialytic-badge' )
					.addClass( isOriginal ? 'original' : 'duplicate' )
					.text( isOriginal ? config.strings.original : config.strings.copy.replace( '%d', imageIndex ) );

				const thumb = $( '<img/>' )
					.attr( 'src', image.thumb )
					.attr( 'alt', image.title );

				const info = $( '<div/>' ).addClass( 'medialytic-image-info' );
				info.append( $( '<strong/>' ).text( config.strings.title + image.title ) );
				info.append( $( '<span/>' ).text( config.strings.filename + image.filename ) );
				info.append( $( '<br/>' ) );
				info.append( $( '<span/>' ).text( config.strings.size + image.size ) );

				const deleteButton = $( '<button/>' )
					.addClass( 'button button-small medialytic-delete-single' )
					.attr( 'data-id', image.id )
					.text( config.strings.deleteSingle );

				item.append( checkbox, badge, thumb, info, deleteButton );
				grid.append( item );
			} );

			groupElement.append( grid );
			resultsContainer.append( groupElement );
		} );

		const status = fromCache
			? config.strings.loadedFromCache.replace( '%d', displayedCount )
			: config.strings.foundGroups.replace( '%d', data.total );

		updateStatus( status );
	}

	function saveResults() {
		$.post( config.ajaxUrl, {
			action: ajaxActions.save,
			nonce: config.nonce,
			results: JSON.stringify( lastScanPayload ),
			dismissed: dismissedGroups,
		} );
	}

	function loadCachedResults() {
		updateStatus( config.strings.loadingCache );

		$.post( config.ajaxUrl, {
			action: ajaxActions.get,
			nonce: config.nonce,
		} ).done( ( response ) => {
			if ( ! response.success ) {
				updateStatus( config.strings.noCache );
				return;
			}

			try {
				const payload = JSON.parse( response.data.results );
				lastScanPayload = payload;
				dismissedGroups = response.data.dismissed || [];
				renderGroups( payload, true );

				if ( response.data.timestamp ) {
					const timestamp = new Date( response.data.timestamp );
					updateStatus(
						config.strings.cacheTimestamp.replace( '%s', timestamp.toLocaleString() )
					);
				}
			} catch ( error ) {
				updateStatus( config.strings.cacheParseError );
			}
		} );
	}

	function scanDuplicates() {
		const button = $( selectors.scanButton );
		button.prop( 'disabled', true );
		updateStatus( config.strings.scanning );
		$( selectors.resultsContainer ).empty();

		$.post( config.ajaxUrl, {
			action: ajaxActions.scan,
			nonce: config.nonce,
		} )
			.done( ( response ) => {
				if ( response.success ) {
					lastScanPayload = response.data;
					dismissedGroups = [];
					renderGroups( response.data, false );
					saveResults();
				} else {
					updateStatus( config.strings.error.replace( '%s', response.data ) );
				}
			} )
			.fail( () => {
				updateStatus( config.strings.genericError );
			} )
			.always( () => button.prop( 'disabled', false ) );
	}

	function clearCacheAndRescan() {
		if ( ! window.confirm( config.strings.confirmClear ) ) {
			return;
		}

		$.post( config.ajaxUrl, {
			action: ajaxActions.clear,
			nonce: config.nonce,
		} ).always( () => {
			dismissedGroups = [];
			scanDuplicates();
		} );
	}

	function toggleSelection( checkbox ) {
		const item = checkbox.closest( selectors.imageItem );
		if ( checkbox.is( ':checked' ) ) {
			item.addClass( 'selected' );
		} else {
			item.removeClass( 'selected' );
		}
	}

	function deleteImages( ids ) {
		if ( ! ids.length ) {
			window.alert( config.strings.noSelection );
			return;
		}

		if ( ! window.confirm( config.strings.confirmDelete.replace( '%d', ids.length ) ) ) {
			return;
		}

		$.post( config.ajaxUrl, {
			action: ajaxActions.delete,
			nonce: config.nonce,
			image_ids: ids,
		} )
			.done( ( response ) => {
				if ( response.success ) {
					window.alert( response.data.message );
					ids.forEach( ( id ) => {
						const item = $( `${ selectors.imageItem }[data-id="${ id }"]` );
						const group = item.closest( selectors.group );

						item.fadeOut( 200, () => {
							item.remove();
							if ( group.find( selectors.imageItem ).length <= 1 ) {
								group.fadeOut( 200, () => {
									group.remove();
									updateGroupCount();
								} );
							} else {
								updateGroupCount();
							}
						} );
					} );
				} else {
					window.alert( config.strings.error.replace( '%s', response.data ) );
				}
			} )
			.fail( () => {
				window.alert( config.strings.genericError );
			} );
	}

	function updateGroupCount() {
		const remaining = $( selectors.group ).length;

		if ( remaining === 0 ) {
			updateStatus( config.strings.allGroupsCleared );
			$( selectors.deleteSelectedButton ).hide();
		} else {
			updateStatus( config.strings.foundGroups.replace( '%d', remaining ) );
		}
	}

	function handleDismissGroup( groupId ) {
		if ( dismissedGroups.includes( groupId ) ) {
			return;
		}

		dismissedGroups.push( groupId );

		const group = $( `${ selectors.group }[data-group-id="${ groupId }"]` );

		group.fadeOut( 300, () => {
			group.remove();
			updateGroupCount();
			saveResults();
		} );
	}

	$( () => {
		$( window ).on( 'load', loadCachedResults );

		$( selectors.scanButton ).on( 'click', scanDuplicates );
		$( selectors.loadCacheButton ).on( 'click', loadCachedResults );
		$( selectors.clearCacheButton ).on( 'click', clearCacheAndRescan );

		$( selectors.deleteSelectedButton ).on( 'click', () => {
			const ids = [];
			$( `${ selectors.imageCheckbox }:checked` ).each( function getId() {
				ids.push( $( this ).data( 'id' ) );
			} );

			deleteImages( ids );
		} );

		$( document ).on( 'change', selectors.imageCheckbox, function onChange() {
			toggleSelection( $( this ) );
		} );

		$( document ).on( 'click', selectors.imageItem, function onClick( event ) {
			if ( $( event.target ).hasClass( 'medialytic-delete-single' ) ) {
				return;
			}

			const checkbox = $( this ).find( selectors.imageCheckbox );
			checkbox.prop( 'checked', ! checkbox.is( ':checked' ) );
			toggleSelection( checkbox );
		} );

		$( document ).on( 'click', selectors.deleteSingle, function onDelete( event ) {
			event.preventDefault();
			event.stopPropagation();
			const id = $( this ).data( 'id' );
			deleteImages( [ id ] );
		} );

		$( document ).on( 'click', selectors.groupDismissButton, function onDismiss( event ) {
			event.preventDefault();
			event.stopPropagation();

			const groupId = parseInt( $( this ).data( 'group-id' ), 10 );
			handleDismissGroup( groupId );
		} );
	} );
}( jQuery ) );

