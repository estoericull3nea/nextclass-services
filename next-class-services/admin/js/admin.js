/**
 * Next Class Services: shared admin behavior.
 * Tabs, copy buttons, confirmation prompts and unsaved change warning.
 */
( function () {
	'use strict';

	var i18n = ( window.ncsAdmin && window.ncsAdmin.i18n ) || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var root = document.querySelector( '.ncs-admin' );
		if ( ! root ) {
			return;
		}

		// Copy shortcode buttons.
		root.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-ncs-copy]' );
			if ( ! btn ) {
				return;
			}
			var text = btn.getAttribute( 'data-ncs-copy' );
			var done = function () {
				var old = btn.textContent;
				btn.textContent = i18n.copied || 'Copied';
				setTimeout( function () {
					btn.textContent = old;
				}, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done, function () {
					fallbackCopy( btn, text, done );
				} );
			} else {
				fallbackCopy( btn, text, done );
			}
		} );

		function fallbackCopy( btn, text, done ) {
			var input = btn.parentNode.querySelector( 'input' );
			if ( input ) {
				input.focus();
				input.select();
				try {
					document.execCommand( 'copy' );
					done();
				} catch ( err ) {
					/* Selection is left in place for manual copy. */
				}
			}
		}

		// Confirmation on destructive links and buttons.
		root.addEventListener( 'click', function ( e ) {
			var el = e.target.closest( '[data-ncs-confirm]' );
			if ( el && ! window.confirm( el.getAttribute( 'data-ncs-confirm' ) || i18n.confirmDelete ) ) {
				e.preventDefault();
			}
		} );
		root.addEventListener( 'submit', function ( e ) {
			var form = e.target;
			var msg = form.getAttribute( 'data-ncs-confirm-submit' );
			if ( msg && ! window.confirm( msg ) ) {
				e.preventDefault();
			}
		} );

		// Tabs.
		var tabLists = root.querySelectorAll( '.ncs-tabs' );
		Array.prototype.forEach.call( tabLists, function ( list ) {
			var tabs = Array.prototype.slice.call( list.querySelectorAll( '[role="tab"]' ) );
			var hidden = document.getElementById( 'ncs-current-tab' );

			function select( tab, focus ) {
				tabs.forEach( function ( t ) {
					var on = t === tab;
					t.classList.toggle( 'is-active', on );
					t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					t.setAttribute( 'tabindex', on ? '0' : '-1' );
					var panel = document.getElementById( t.getAttribute( 'aria-controls' ) );
					if ( panel ) {
						panel.hidden = ! on;
					}
				} );
				if ( hidden ) {
					hidden.value = tab.getAttribute( 'data-tab' );
				}
				if ( focus ) {
					tab.focus();
				}
			}

			tabs.forEach( function ( tab, i ) {
				tab.addEventListener( 'click', function () {
					select( tab, false );
				} );
				tab.addEventListener( 'keydown', function ( e ) {
					var next = null;
					if ( e.key === 'ArrowRight' ) {
						next = tabs[ ( i + 1 ) % tabs.length ];
					} else if ( e.key === 'ArrowLeft' ) {
						next = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
					} else if ( e.key === 'Home' ) {
						next = tabs[ 0 ];
					} else if ( e.key === 'End' ) {
						next = tabs[ tabs.length - 1 ];
					}
					if ( next ) {
						e.preventDefault();
						select( next, true );
					}
				} );
			} );
			var active = list.querySelector( '.is-active' ) || tabs[ 0 ];
			if ( active ) {
				select( active, false );
			}
		} );

		// Unsaved changes warning.
		var form = root.querySelector( '[data-ncs-warn-unsaved]' );
		if ( form ) {
			var dirty = false;
			var flag = document.getElementById( 'ncs-dirty' );
			var mark = function () {
				dirty = true;
				if ( flag ) {
					flag.hidden = false;
				}
			};
			form.addEventListener( 'input', mark );
			form.addEventListener( 'change', mark );
			form.addEventListener( 'ncs:change', mark );
			form.addEventListener( 'submit', function () {
				dirty = false;
			} );
			window.addEventListener( 'beforeunload', function ( e ) {
				if ( dirty ) {
					e.preventDefault();
					e.returnValue = '';
				}
			} );
		}
	} );
}() );
