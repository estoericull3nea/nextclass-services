/**
 * Next Class Services: booking service editor behavior.
 */
( function () {
	'use strict';

	var form = document.getElementById( 'ncs-service-form' );
	if ( ! form ) {
		return;
	}

	var methodSelect = document.getElementById( 'ncs-cfg-method' );
	var modeSelect = document.getElementById( 'ncs-cfg-external-custom-mode' );

	function refresh() {
		var method = methodSelect ? methodSelect.value : 'native';
		var mode = modeSelect ? modeSelect.value : 'url';

		Array.prototype.forEach.call( form.querySelectorAll( '[data-show-method]' ), function ( el ) {
			var allowed = el.getAttribute( 'data-show-method' ).split( ' ' );
			el.hidden = allowed.indexOf( method ) === -1;
		} );

		Array.prototype.forEach.call( form.querySelectorAll( '[data-show-custom-mode]' ), function ( el ) {
			var wants = el.getAttribute( 'data-show-custom-mode' );
			var isEmbed = method === 'custom' && mode === 'embed';
			var methodHidden = el.hasAttribute( 'data-show-method' ) && el.getAttribute( 'data-show-method' ).split( ' ' ).indexOf( method ) === -1;
			if ( methodHidden ) {
				el.hidden = true;
			} else {
				el.hidden = wants === 'embed' ? ! isEmbed : isEmbed;
			}
		} );
	}

	if ( methodSelect ) {
		methodSelect.addEventListener( 'change', refresh );
	}
	if ( modeSelect ) {
		modeSelect.addEventListener( 'change', refresh );
	}
	refresh();

	// Custom form fields repeater.
	var list = document.getElementById( 'ncs-custom-fields' );
	var add = document.getElementById( 'ncs-add-custom-field' );
	var tpl = document.getElementById( 'ncs-custom-template' );
	var counter = list ? list.children.length + 100 : 100;

	if ( add && tpl && list ) {
		add.addEventListener( 'click', function () {
			var html = tpl.innerHTML.replace( /__i__/g, String( counter++ ) );
			var holder = document.createElement( 'div' );
			holder.innerHTML = html;
			var row = holder.firstElementChild;
			list.appendChild( row );
			var input = row.querySelector( 'input[type="text"]' );
			if ( input ) {
				input.focus();
			}
			form.dispatchEvent( new Event( 'ncs:change' ) );
		} );
	}

	form.addEventListener( 'click', function ( e ) {
		var remove = e.target.closest( '[data-ncs-remove-row]' );
		if ( remove ) {
			var row = remove.closest( '.ncs-custom-row' );
			if ( row ) {
				row.parentNode.removeChild( row );
				form.dispatchEvent( new Event( 'ncs:change' ) );
			}
		}
	} );
}() );
