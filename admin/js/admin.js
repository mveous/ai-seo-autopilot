/**
 * AI Autopilot SEO Assistant — admin dashboard/settings behavior.
 * No build step: plain ES2017, loaded only on the plugin's own screens.
 */
( function () {
	'use strict';

	var config = window.aasa || {};

	function setScoreRings() {
		document.querySelectorAll( '.aasa-score-ring' ).forEach( function ( el ) {
			var score = parseInt( el.getAttribute( 'data-score' ), 10 ) || 0;
			el.style.setProperty( '--score', Math.max( 0, Math.min( 100, score ) ) );
		} );
	}

	function restRequest( path, method, body ) {
		return fetch( config.restUrl + path, {
			method: method || 'GET',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			credentials: 'same-origin',
			body: body ? JSON.stringify( body ) : undefined,
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				return response.json().then( function ( data ) {
					throw new Error( ( data && data.message ) || config.i18n.genericError );
				} );
			}
			return response.json();
		} );
	}

	function initRunScan() {
		var button = document.getElementById( 'aasa-run-scan' );
		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( ! window.confirm( config.i18n.confirmRunScan ) ) {
				return;
			}

			button.disabled = true;
			var originalText = button.innerHTML;
			button.innerHTML = '<span class="dashicons dashicons-update" style="animation:spin 1s linear infinite;"></span> …';

			restRequest( '/scan', 'POST', {} )
				.then( function () {
					window.location.reload();
				} )
				.catch( function ( error ) {
					window.alert( error.message || config.i18n.genericError );
					button.disabled = false;
					button.innerHTML = originalText;
				} );
		} );
	}

	function initFixIssue() {
		document.querySelectorAll( '.aasa-fix-issue' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var cell = button.parentNode;
				var originalText = button.innerHTML;

				button.disabled = true;
				button.innerHTML = '<span class="dashicons dashicons-update" style="animation:spin 1s linear infinite;"></span> ' + config.i18n.fixing;

				restRequest( '/issues/' + button.getAttribute( 'data-issue-id' ) + '/fix', 'POST', {} )
					.then( function ( response ) {
						var data = response.data || {};
						var label = 'title' === data.field ? config.i18n.newTitle : config.i18n.newDescription;

						var status = document.createElement( 'span' );
						status.className = 'aasa-issue-fixed';
						status.textContent = '✓ ' + config.i18n.fixed;

						var value = document.createElement( 'span' );
						value.className = 'description';
						value.textContent = label + ' ' + ( data.value || '' );

						cell.textContent = '';
						cell.appendChild( status );
						cell.appendChild( document.createElement( 'br' ) );
						cell.appendChild( value );
						cell.closest( 'tr' ).classList.add( 'is-fixed' );
					} )
					.catch( function ( error ) {
						window.alert( error.message || config.i18n.genericError );
						button.disabled = false;
						button.innerHTML = originalText;
					} );
			} );
		} );
	}

	function initSettingsToast() {
		var toast = document.getElementById( 'aasa-toast' );
		if ( ! toast ) {
			return;
		}

		var url = new URL( window.location.href );
		url.searchParams.delete( 'status' );
		url.searchParams.delete( 'message' );
		window.history.replaceState( {}, '', url );

		function dismiss() {
			toast.classList.add( 'is-hidden' );
			setTimeout( function () { toast.remove(); }, 300 );
		}

		toast.querySelector( '.aasa-toast__close' ).addEventListener( 'click', dismiss );
		setTimeout( dismiss, 10000 );
	}

	function initRemoveApiKey() {
		document.querySelectorAll( '.aasa-remove-key' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				if ( ! window.confirm( config.i18n.confirmRemoveKey ) ) {
					return;
				}

				var body = new URLSearchParams( {
					action: 'aasa_delete_api_key',
					_wpnonce: config.settingsNonce,
					provider: button.dataset.provider,
				} );

				button.disabled = true;

				fetch( config.adminPostUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function () {
						var url = new URL( window.location.href );
						url.searchParams.set( 'status', 'deleted' );
						window.location.href = url.toString();
					} )
					.catch( function () { button.disabled = false; } );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		setScoreRings();
		initRunScan();
		initFixIssue();
		initSettingsToast();
		initRemoveApiKey();
	} );
} )();
