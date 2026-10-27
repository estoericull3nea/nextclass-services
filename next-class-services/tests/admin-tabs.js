/* Run with node tests/admin-tabs.js. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const path = require( 'node:path' );
const panels = {};
const tabs = [ 'booking', 'notifications', 'privacy' ].map( ( key, i ) => {
	const classes = new Set( i === 0 ? [ 'is-active' ] : [] );
	const panelId = 'panel-' + key;
	panels[panelId] = { id: panelId, hidden: i !== 0 };
	return {
		attrs: { 'data-tab': key, 'aria-controls': panelId }, listeners: {},
		classList: { toggle( name, active ) { if ( active ) { classes.add( name ); } else { classes.delete( name ); } } },
		getAttribute( name ) { return this.attrs[name]; }, setAttribute( name, value ) { this.attrs[name] = value; },
		addEventListener( name, fn ) { this.listeners[name] = fn; }, focus() { this.focused = true; }
	};
} );
const list = { classList: { contains: name => name === 'ncs-settings-tabs' }, querySelectorAll: () => tabs, querySelector: () => tabs[0] };
const root = { querySelector: () => null, querySelectorAll: selector => selector === '.ncs-tabs' ? [ list ] : [], addEventListener() {} };
const window = { location: { href: 'https://example.test/wp-admin/admin.php?page=ncs-settings&other=keep#ncs-google-calendar' }, addEventListener() {}, history: { state: { keep: true }, replaceState( state, title, url ) { this.state = state; window.location.href = url; } } };
const document = { readyState: 'complete', querySelector: () => root, querySelectorAll: () => [], addEventListener() {}, getElementById: id => id === 'ncs-google-calendar' ? { closest: () => panels['panel-booking'] } : panels[id] || null };
vm.runInNewContext( fs.readFileSync( path.join( __dirname, '../admin/js/admin.js' ), 'utf8' ), { window, document, URL } );
assert.ok( window.location.href.endsWith( '#ncs-google-calendar' ), 'Initialization preserves deep links' );
tabs[2].listeners.click();
let url = new URL( window.location.href );
assert.equal( url.searchParams.get( 'tab' ), 'privacy' );
assert.equal( url.searchParams.get( 'page' ), 'ncs-settings' );
assert.equal( url.searchParams.get( 'other' ), 'keep' );
assert.equal( url.hash, '', 'Remove an anchor belonging to another tab' );
assert.equal( panels['panel-privacy'].hidden, false );
assert.equal( panels['panel-booking'].hidden, true );
assert.equal( window.history.state.keep, true );
tabs[2].listeners.keydown( { key: 'ArrowLeft', preventDefault() {} } );
url = new URL( window.location.href );
assert.equal( url.searchParams.get( 'tab' ), 'notifications', 'Keyboard navigation also updates the URL' );
assert.equal( tabs[1].focused, true );
console.log( 'Settings tab URL checks passed: mouse, keyboard, existing parameters and deep links.' );
