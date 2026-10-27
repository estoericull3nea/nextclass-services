/** Settings actions, inline feedback and Google OAuth popup. */
( function () {
	'use strict';
	function init() {
	var root = document.querySelector( '.ncs-admin' );
	var feedback = document.getElementById( 'ncs-settings-feedback' );
	if ( ! root || ! feedback ) {
		return;
	}
	var config = window.ncsAdmin || {};
	var i18n = config.i18n || {};
	var google = document.getElementById( 'ncs-google-calendar' );
	var busy = false;
	var popup = null;
	var popupTimer = null;
	var activeButton = null;
	var activeLabel = '';
	var state = {
		cleanupEnabled: !! root.querySelector( '[data-ncs-cleanup]:not(:disabled)' ),
		google: google ? { connected: google.getAttribute( 'data-connected' ) === '1' } : null
	};

	function showNotices( notices ) {
		feedback.textContent = '';
		notices.forEach( function ( notice ) {
			var item = document.createElement( 'p' );
			item.className = 'ncs-notice ncs-notice-' + ( [ 'success', 'error', 'warning', 'info' ].indexOf( notice.type ) !== -1 ? notice.type : 'info' );
			item.textContent = notice.message;
			feedback.appendChild( item );
		} );
		feedback.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
	}

	function updateControls() {
		Array.prototype.forEach.call( root.querySelectorAll( '[data-ncs-settings-ajax] button[type="submit"], button[form="ncs-settings-form"]' ), function ( button ) {
			var disabled = busy;
			if ( button.hasAttribute( 'data-ncs-cleanup' ) ) {
				disabled = disabled || ! state.cleanupEnabled;
			}
			var action = button.getAttribute( 'data-ncs-google-action' );
			if ( action && state.google ) {
				disabled = disabled || ( action === 'connect' ? state.google.connected : ! state.google.connected );
			}
			button.disabled = disabled;
		} );
	}

	function updateState( next ) {
		if ( ! next ) {
			return;
		}
		state = next;
		if ( google && state.google ) {
			google.setAttribute( 'data-connected', state.google.connected ? '1' : '0' );
			var badge = google.querySelector( '[data-ncs-google-badge]' );
			badge.textContent = state.google.connected ? ( i18n.connected || 'Connected' ) : ( i18n.notConnected || 'Not connected' );
			badge.classList.toggle( 'is-connected', state.google.connected );
			badge.classList.toggle( 'is-disconnected', ! state.google.connected );
			var test = google.querySelector( '[data-ncs-google-test-result]' );
			var result = state.google.test || {};
			test.hidden = ! result.message;
			test.textContent = result.message ? result.date + ': ' + result.message : '';
			var secret = google.querySelector( '[name="client_secret"]' );
			var help = document.getElementById( secret.id + '-help' );
			if ( help ) {
				help.textContent = state.google.secretSaved ? i18n.secretSaved : i18n.secretEmpty;
			}
		}
		updateControls();
	}

	function setBusy( value, button ) {
		busy = value;
		if ( value ) {
			activeButton = button;
			if ( button ) {
				activeLabel = button.textContent;
				button.textContent = i18n.working || 'Working...';
			}
		} else if ( activeButton ) {
			activeButton.textContent = activeLabel;
			activeButton = null;
		}
		feedback.setAttribute( 'aria-busy', value ? 'true' : 'false' );
		updateControls();
	}

	function request( data ) {
		return fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } ).then( function ( response ) {
			return response.json();
		} );
	}

	function displayResponse( response ) {
		var data = response.data || {};
		updateState( data.state );
		showNotices( data.notices || [ { type: response.success ? 'success' : 'error', message: data.message || i18n.ajaxError || 'The request could not be completed.' } ] );
	}

	function refreshStatus() {
		var data = new FormData();
		data.set( 'action', 'ncs_google_status' );
		data.set( '_ncs_nonce', google.getAttribute( 'data-status-nonce' ) );
		return request( data ).then( function ( response ) {
			if ( ! response.success ) {
				throw new Error( response.data && response.data.message || i18n.ajaxError );
			}
			updateState( response.data.state );
			return state.google.connected;
		} );
	}

	function stopPopup( notice ) {
		window.clearInterval( popupTimer );
		popupTimer = null;
		popup = null;
		setBusy( false );
		if ( notice ) {
			showNotices( [ notice ] );
		}
	}

	window.addEventListener( 'message', function ( event ) {
		if ( ! popup || event.source !== popup || event.origin !== window.location.origin || ! event.data || event.data.type !== 'ncs-google-oauth' ) {
			return;
		}
		var result = event.data;
		refreshStatus().then( function () {
			stopPopup( { type: result.success ? 'success' : 'error', message: result.message } );
		} ).catch( function ( error ) {
			stopPopup( { type: 'error', message: error.message } );
		} );
	} );

	function watchPopup() {
		var checking = false;
		var started = Date.now();
		popupTimer = window.setInterval( function () {
			if ( checking || ! popup ) {
				return;
			}
			checking = true;
			var closed = popup.closed;
			refreshStatus().then( function ( connected ) {
				if ( connected ) {
					if ( popup && ! popup.closed ) {
						popup.close();
					}
					stopPopup( { type: 'success', message: i18n.connected || 'Connected' } );
				} else if ( closed || Date.now() - started > 10 * 60 * 1000 ) {
					stopPopup( { type: 'info', message: i18n.oauthClosed || 'The Google window was closed. Connection status has been refreshed.' } );
				}
			} ).catch( function ( error ) {
				if ( closed ) {
					stopPopup( { type: 'error', message: error.message } );
				}
			} ).then( function () { checking = false; } );
		}, 2500 );
	}

	Array.prototype.forEach.call( root.querySelectorAll( 'form[data-ncs-settings-ajax]' ), function ( form ) {
		form.ncsRevision = 0;
		form.addEventListener( 'input', function () { form.ncsRevision++; } );
		form.addEventListener( 'change', function () { form.ncsRevision++; } );
	} );
	[ 'input', 'change' ].forEach( function ( type ) {
		root.addEventListener( type, function ( event ) {
			var form = event.target.form;
			if ( form && form.hasAttribute( 'data-ncs-settings-ajax' ) && ! form.contains( event.target ) ) {
				form.ncsRevision++;
			}
		} );
	} );

	// Reveal the category containing an invalid control before browser validation focuses it.
	var invalidField = null;
	root.addEventListener( 'invalid', function ( event ) {
		if ( ! event.target.form || event.target.form.id !== 'ncs-settings-form' ) {
			return;
		}
		if ( invalidField ) {
			event.preventDefault();
			return;
		}
		invalidField = event.target;
		var panel = event.target.closest( '[role="tabpanel"]' );
		if ( panel && panel.hidden ) {
			document.getElementById( panel.getAttribute( 'aria-labelledby' ) ).click();
		}
		window.setTimeout( function () { invalidField = null; }, 0 );
	}, true );

	root.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( ! form.matches( 'form[data-ncs-settings-ajax]' ) || event.defaultPrevented ) {
			return;
		}
		event.preventDefault();
		if ( busy ) {
			return;
		}
		var data = new FormData( form );
		var action = data.get( 'action' );
		var connect = action === 'ncs_google_connect';
		if ( connect ) {
			popup = window.open( 'about:blank', 'ncs-google-authorization', 'popup,width=600,height=760' );
			if ( ! popup ) {
				showNotices( [ { type: 'error', message: i18n.popupBlocked } ] );
				return;
			}
		}
		var revision = form.ncsRevision;
		var button = event.submitter || form.querySelector( 'button[type="submit"]' );
		setBusy( true, button );
		request( data ).then( function ( response ) {
			if ( connect && response.success && response.data.authUrl ) {
				popup.location.href = response.data.authUrl;
				showNotices( [ { type: 'info', message: i18n.oauthWaiting } ] );
				watchPopup();
				return;
			}
			if ( connect && popup ) {
				popup.close();
				popup = null;
			}
			displayResponse( response );
			if ( response.success && revision === form.ncsRevision ) {
				form.dispatchEvent( new Event( 'ncs:saved' ) );
				if ( action === 'ncs_save_settings' ) {
					Array.prototype.forEach.call( form.elements, function ( input ) {
						var match = /^s\[([^\]]+)\]$/.exec( input.name );
						if ( ! match || ! Object.prototype.hasOwnProperty.call( state.settings, match[1] ) ) {
							return;
						}
						if ( input.type === 'checkbox' ) {
							input.checked = !! state.settings[ match[1] ];
						} else {
							input.value = state.settings[ match[1] ];
						}
					} );
				}
				if ( action === 'ncs_google_save' ) {
					form.querySelector( '[name="client_secret"]' ).value = '';
					form.querySelector( '[name="client_id"]' ).value = state.google.clientId;
					form.querySelector( '[name="calendar_id"]' ).value = state.google.calendarId;
				}
			}
			setBusy( false );
		} ).catch( function () {
			if ( connect && popup ) {
				popup.close();
				popup = null;
			}
			setBusy( false );
			showNotices( [ { type: 'error', message: i18n.ajaxError || 'The request could not be completed.' } ] );
		} );
	} );
	}
	// Register after shared admin behavior so cleanup confirmation runs before AJAX.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
