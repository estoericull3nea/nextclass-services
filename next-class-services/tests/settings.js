/* Run with node tests/settings.js. Browser-independent Settings AJAX regressions. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const path = require( 'node:path' );

function element( attrs = {} ) {
	return {
		attrs, disabled: false, textContent: 'Action', hidden: false, children: [], listeners: {},
		classList: { toggle() {} },
		getAttribute( key ) { return this.attrs[key] ?? null; },
		setAttribute( key, value ) { this.attrs[key] = value; },
		hasAttribute( key ) { return key in this.attrs; },
		addEventListener( key, fn ) { this.listeners[key] = fn; },
		appendChild( child ) { this.children.push( child ); },
		scrollIntoView() {}
	};
}

function setup( connected = false ) {
	const feedback = element();
	const main = element();
	const cleanup = element( { 'data-ncs-cleanup': '' } );
	const buttons = {};
	const forms = {};
	const allButtons = [ cleanup ];
	const secret = Object.assign( element(), { id: 'client-secret', value: 'typed-secret', name: 'client_secret' } );
	const client = Object.assign( element(), { value: 'client', name: 'client_id' } );
	const calendar = Object.assign( element(), { value: 'primary', name: 'calendar_id' } );
	const radius = Object.assign( element(), { name: 's[frontend_radius]', type: 'number', value: '7' } );
	for ( const action of [ 'ncs_save_settings', 'ncs_google_save', 'ncs_google_connect', 'ncs_google_disconnect', 'ncs_google_test' ] ) {
		const form = action === 'ncs_save_settings' ? main : element();
		form.attrs['data-ncs-settings-ajax'] = '';
		form.contains = () => false;
		const short = action.replace( 'ncs_google_', '' );
		const button = element( [ 'connect', 'disconnect', 'test' ].includes( short ) ? { 'data-ncs-google-action': short } : {} );
		button.disabled = short === 'connect' ? connected : [ 'disconnect', 'test' ].includes( short ) && ! connected;
		form.fields = new Map( [ [ 'action', action ], [ '_ncs_nonce', 'test-nonce' ] ] );
		form.matches = () => true;
		form.querySelector = selector => selector.includes( 'client_secret' ) ? secret : selector.includes( 'client_id' ) ? client : selector.includes( 'calendar_id' ) ? calendar : button;
		form.querySelectorAll = () => [ radius ];
		form.elements = [ radius ];
		form.dispatched = [];
		form.dispatchEvent = event => form.dispatched.push( event.type );
		forms[action] = form;
		buttons[action] = button;
		allButtons.push( button );
	}
	const badge = element();
	const test = element();
	const help = element();
	const google = element( { 'data-connected': connected ? '1' : '0', 'data-status-nonce': 'status-nonce' } );
	google.querySelector = selector => selector.includes( 'badge' ) ? badge : selector.includes( 'result' ) ? test : secret;
	const root = element();
	root.querySelector = () => cleanup;
	root.querySelectorAll = selector => selector.startsWith( 'form' ) ? Object.values( forms ) : allButtons;
	const window = element();
	window.ncsAdmin = { ajaxUrl: '/admin-ajax.php', i18n: { connected: 'Connected', notConnected: 'Not connected', ajaxError: 'Request failed', popupBlocked: 'Popup blocked' } };
	window.location = { origin: 'https://example.test' };
	window.open = () => null;
	window.setInterval = callback => { window.poll = callback; return 1; };
	window.clearInterval = () => {};
	const document = {
		querySelector: () => root,
		getElementById: id => id === 'ncs-settings-feedback' ? feedback : id === 'ncs-google-calendar' ? google : help,
		createElement: () => element()
	};
	const requests = [];
	const responses = [];
	class FormDataMock extends Map {
		constructor( form ) { super( form ? form.fields : [] ); }
	}
	vm.runInNewContext( fs.readFileSync( path.join( __dirname, '../admin/js/settings.js' ), 'utf8' ), {
		window, document, FormData: FormDataMock, Event: class { constructor( type ) { this.type = type; } },
		fetch: ( url, options ) => { requests.push( { url, options } ); return Promise.resolve( { json: () => Promise.resolve( responses.shift() ) } ); }
	} );
	function submit( action ) {
		const event = { target: forms[action], submitter: buttons[action], defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
		root.listeners.submit( event );
		assert.equal( event.defaultPrevented, true, 'AJAX submission must prevent page navigation' );
	}
	const state = flag => ( { cleanupEnabled: true, settings: { frontend_radius: 10 }, google: { connected: flag, secretSaved: true, clientId: 'saved-client', calendarId: 'primary', test: {} } } );
	return { root, forms, buttons, radius, secret, feedback, badge, requests, responses, submit, state, window };
}
const flush = () => new Promise( resolve => setImmediate( resolve ) );

( async () => {
	let ui = setup();
	ui.root.listeners.submit( { target: ui.forms.ncs_save_settings, defaultPrevented: true } );
	assert.equal( ui.requests.length, 0, 'Honor a cancelled confirmation before making an AJAX request' );
	ui.responses.push( { success: true, data: { state: ui.state( false ), notices: [ { type: 'success', message: 'Saved' } ] } } );
	ui.submit( 'ncs_save_settings' );
	assert.equal( ui.buttons.ncs_save_settings.disabled, true, 'Prevent duplicate submissions' );
	await flush();
	assert.equal( ui.forms.ncs_save_settings.dispatched[0], 'ncs:saved' );
	assert.equal( ui.radius.value, 10, 'Show canonical saved value' );
	assert.equal( ui.buttons.ncs_google_test.disabled, true );
	assert.equal( ui.buttons.ncs_google_disconnect.disabled, true );
	assert.equal( ui.buttons.ncs_google_connect.disabled, false );
	assert.equal( ui.requests[0].options.body.get( '_ncs_nonce' ), 'test-nonce' );

	ui = setup();
	ui.responses.push( { success: false, data: { message: 'Session expired' } } );
	ui.submit( 'ncs_save_settings' );
	await flush();
	assert.equal( ui.forms.ncs_save_settings.dispatched.length, 0, 'Failed saves must retain unsaved state' );
	assert.equal( ui.feedback.children[0].textContent, 'Session expired' );
	assert.equal( ui.buttons.ncs_save_settings.disabled, false );

	ui = setup();
	ui.responses.push( { success: true, data: { state: ui.state( false ), notices: [] } } );
	ui.submit( 'ncs_save_settings' );
	ui.forms.ncs_save_settings.listeners.input();
	ui.radius.value = '12';
	await flush();
	assert.equal( ui.forms.ncs_save_settings.dispatched.length, 0, 'Editing during a save remains unsaved' );
	assert.equal( ui.radius.value, '12', 'Do not overwrite new edits with an older response' );

	ui = setup();
	ui.responses.push( { success: true, data: { state: ui.state( false ), notices: [] } } );
	ui.submit( 'ncs_save_settings' );
	ui.root.listeners.input( { target: { form: ui.forms.ncs_save_settings } } );
	await flush();
	assert.equal( ui.forms.ncs_save_settings.dispatched.length, 0, 'Track new edits in fields associated with the shared form outside its DOM tree' );

	ui = setup( true );
	ui.responses.push( { success: true, data: { state: ui.state( false ), notices: [] } } );
	ui.submit( 'ncs_google_disconnect' );
	await flush();
	assert.equal( ui.badge.textContent, 'Not connected' );
	assert.equal( ui.buttons.ncs_google_connect.disabled, false );
	assert.equal( ui.buttons.ncs_google_disconnect.disabled, true );
	assert.equal( ui.buttons.ncs_google_test.disabled, true );

	ui = setup();
	ui.submit( 'ncs_google_connect' );
	assert.equal( ui.requests.length, 0, 'Do not start OAuth when the popup is blocked' );
	assert.equal( ui.feedback.children[0].textContent, 'Popup blocked' );

	ui = setup();
	const popup = { closed: false, location: {}, close() { this.closed = true; } };
	ui.window.open = () => popup;
	ui.responses.push( { success: true, data: { authUrl: 'https://accounts.google.com/test' } } );
	ui.submit( 'ncs_google_connect' );
	await flush();
	assert.equal( popup.location.href, 'https://accounts.google.com/test' );
	ui.window.listeners.message( { source: popup, origin: 'https://evil.example', data: { type: 'ncs-google-oauth' } } );
	assert.equal( ui.requests.length, 1, 'Ignore OAuth messages from other origins' );
	ui.responses.push( { success: true, data: { state: ui.state( true ) } } );
	ui.window.listeners.message( { source: popup, origin: 'https://example.test', data: { type: 'ncs-google-oauth', success: true, message: 'Connected' } } );
	await flush();
	assert.equal( ui.badge.textContent, 'Connected' );
	assert.equal( ui.buttons.ncs_google_connect.disabled, true );
	assert.equal( ui.buttons.ncs_google_disconnect.disabled, false );
	assert.equal( ui.buttons.ncs_google_test.disabled, false );
	console.log( 'Settings AJAX UI regressions passed: saving, failures, concurrent edits, connection states and OAuth.' );
} )().catch( error => { console.error( error ); process.exitCode = 1; } );
