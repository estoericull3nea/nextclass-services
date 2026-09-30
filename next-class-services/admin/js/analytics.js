/**
 * Next Class Services: analytics screen. Shows custom date inputs when needed.
 */
( function () {
	'use strict';

	var select = document.getElementById( 'ncs-range' );
	if ( ! select ) {
		return;
	}
	var custom = document.querySelectorAll( '[data-custom-range]' );

	function toggle() {
		var show = select.value === 'custom';
		Array.prototype.forEach.call( custom, function ( el ) {
			el.hidden = ! show;
		} );
	}

	select.addEventListener( 'change', toggle );
	toggle();
}() );
