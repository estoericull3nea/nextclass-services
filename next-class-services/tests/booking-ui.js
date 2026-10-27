/* Mock DOM and API regression checks. No calendar or email requests leave this process. */
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const assert = require( 'node:assert/strict' );
const path = require( 'node:path' );
class Element {
	constructor( tag = 'div' ) { this.tagName = tag.toUpperCase(); this.children = []; this.attrs = {}; this.listeners = {}; this.value = ''; this.disabled = false; this.hidden = false; this._text = ''; this.parentNode = null; }
	set textContent( value ) { this._text = String( value ); this.children = []; }
	get textContent() { return this._text + this.children.map( c => c.textContent ).join( '' ); }
	set innerHTML( value ) { assert.equal( value, '' ); this.children = []; this._text = ''; }
	get firstChild() { return this.children[0]; }
	get className() { return this.attrs.class || ''; }
	set className( value ) { this.attrs.class = value; }
	get classList() { return { add: name => { this.className += ' ' + name; }, remove: name => { this.className = this.className.split( ' ' ).filter( value => value !== name ).join( ' ' ); }, contains: name => this.className.split( ' ' ).includes( name ), toggle: ( name, active ) => { this.className = this.className.split( ' ' ).filter( value => value !== name ).join( ' ' ) + ( active ? ' ' + name : '' ); } }; }
	appendChild( node ) { if ( node.parentNode ) { node.parentNode.children = node.parentNode.children.filter( c => c !== node ); } this.children.push( node ); node.parentNode = this; return node; }
	insertBefore( node, before ) { const index = this.children.indexOf( before ); if ( index === -1 ) { return this.appendChild( node ); } this.children.splice( index, 0, node ); node.parentNode = this; }
	setAttribute( key, value ) { this.attrs[key] = String( value ); }
	getAttribute( key ) { return this.attrs[key] ?? null; }
	removeAttribute( key ) { delete this.attrs[key]; }
	addEventListener( name, fn ) { ( this.listeners[name] ||= [] ).push( fn ); }
	fire( name ) { for ( const fn of this.listeners[name] || [] ) { fn( { target: this, currentTarget: this, preventDefault() {} } ); } }
	click() { if ( ! this.disabled ) { this.fire( 'click' ); } }
	focus() {}
	select() {}
	matches( selector ) {
		const tag = /^[a-z][a-z0-9]*/i.exec( selector );
		if ( tag && this.tagName !== tag[0].toUpperCase() ) { return false; }
		for ( const match of selector.split( '[' )[0].matchAll( /\.([\w-]+)/g ) ) { if ( ! this.classList.contains( match[1] ) ) { return false; } }
		for ( const match of selector.matchAll( /\[([\w-]+)(?:="([^"]*)")?\]/g ) ) { if ( ! ( match[1] in this.attrs ) || ( match[2] !== undefined && this.attrs[match[1]] !== match[2] ) ) { return false; } }
		return true;
	}
	querySelectorAll( selector ) { const out = []; for ( const child of this.children ) { if ( child.matches( selector ) ) { out.push( child ); } out.push( ...child.querySelectorAll( selector ) ); } return out; }
	querySelector( selector ) { return this.querySelectorAll( selector )[0] || null; }
}
function setup( manage = false ) {
	const root = new Element(); root.setAttribute( 'data-ncs-booking', '' );
	const cfg = new Element( 'script' ); cfg.className = 'ncs-config';
	const fields = [ { key: 'name', label: 'Name', type: 'text', required: true, placeholder: 'Your full name' }, { key: 'email', label: 'Email', type: 'email', required: true, placeholder: 'you@example.com' }, { key: 'notes', label: 'Notes', type: 'textarea', placeholder: 'Your agenda' }, { key: 'custom_team', label: 'Team', type: 'select', placeholder: 'Choose your team', options: [ 'People', 'Operations' ] } ];
	const service = { id: 1, name: 'HR consultation', method: 'native', fields, tzLabel: 'Asia/Shanghai', calendarSync: true };
	cfg.textContent = JSON.stringify( { services: [ service ] } ); root.appendChild( cfg );
	const app = new Element(); app.className = 'ncs-app'; root.appendChild( app );
	const calls = []; let status = 'confirmed'; let changed = false;
	function appointment() { return { service: service.name, date: changed ? 'November 4, 2026' : 'November 3, 2026', time: '10:00 AM', timezone: 'Asia/Shanghai', name: 'Alex Client', email: 'alex@example.test', status, statusLabel: status === 'confirmed' ? 'Confirmed' : 'Cancelled', active: status === 'confirmed', calendarState: status === 'confirmed' ? 'ready' : 'cancelled', meetUrl: status === 'confirmed' ? 'https://meet.google.com/abc-defg-hij' : '' }; }
	const window = { location: { href: 'https://example.test/book', search: manage ? '?ncs_manage=' + 'a'.repeat( 40 ) : '' }, ncsPublic: { i18n: {} }, console: { error( ...args ) { throw new Error( args.join( ' ' ) ); } }, NCS: { ga() {}, track() {}, api( method, route, body ) {
		calls.push( { route, body } ); let data = { ok: true };
		if ( route === 'booking/availability' ) { data.days = {}; for ( let day = 1; day <= 31; day++ ) { data.days[body.month + '-' + String( day ).padStart( 2, '0' )] = 2; } }
		if ( route === 'booking/slots' ) { data.slots = [ { time: '10:00', label: '10:00 AM' } ]; }
		if ( route === 'booking/create' ) { data = { ok: true, message: 'Booking received.', appointment: appointment(), email: 'alex@example.test', calendarState: 'ready', meetUrl: appointment().meetUrl, manageUrl: 'https://example.test/book?ncs_manage=' + 'a'.repeat( 40 ) }; }
		if ( route === 'booking/cancel' ) { status = 'cancelled'; data.message = 'Cancelled.'; }
		if ( route === 'booking/reschedule' ) { changed = true; data.message = 'Rescheduled.'; }
		if ( route === 'booking/manage' ) { data = { ok: true, service, appointment: appointment(), canCancel: status === 'confirmed', canReschedule: status === 'confirmed' }; }
		return Promise.resolve( { data } );
	} } };
	const document = { readyState: 'complete', documentElement: { lang: 'en' }, querySelectorAll: () => [ root ], createElement: tag => new Element( tag ), createElementNS: ( ns, tag ) => new Element( tag ), createTextNode: text => { const node = new Element( '#text' ); node.textContent = text; return node; } };
	vm.runInNewContext( fs.readFileSync( path.join( __dirname, '../public/js/booking.js' ), 'utf8' ), { document, window, navigator: {}, setTimeout() {} } );
	const button = text => { const result = app.querySelectorAll( 'button' ).find( el => el.textContent === text || el.getAttribute( 'aria-label' ) === text ); assert.ok( result, 'Find button: ' + text ); return result; };
	return { app, calls, button };
}
const flush = () => new Promise( resolve => setImmediate( resolve ) );
async function chooseTime( ui ) { await flush(); ui.app.querySelector( '.ncs-cal-day.is-available' ).click(); await flush(); ui.app.querySelector( '.ncs-slot' ).click(); }
( async () => {
	let ui = setup(); await chooseTime( ui ); ui.button( 'Continue' ).click();
	assert.equal( ui.app.querySelector( '[name="name"]' ).getAttribute( 'placeholder' ), 'Your full name' );
	assert.equal( ui.app.querySelector( 'textarea' ).getAttribute( 'placeholder' ), 'Your agenda' );
	assert.equal( ui.app.querySelector( 'select' ).firstChild.textContent, 'Choose your team' );
	for ( const [ name, value ] of [ [ 'name', 'Alex Client' ], [ 'email', 'alex@example.test' ] ] ) { const input = ui.app.querySelector( '[name="' + name + '"]' ); input.value = value; input.fire( 'input' ); }
	ui.app.querySelector( 'form' ).fire( 'submit' );
	assert.ok( ui.app.textContent.includes( 'Review and confirm' ) );
	assert.equal( ui.button( 'Edit details' ).textContent, '', 'Edit actions are icon-only with accessible labels' );
	assert.ok( ui.app.querySelectorAll( 'svg[aria-hidden="true"]' ).length >= 6, 'Review icons are decorative and retain text labels' );
	assert.equal( ui.app.querySelector( '[aria-current="step"]' ).textContent, '3Review and confirm', 'Progress indicates the current step' );
	assert.equal( ui.app.querySelectorAll( '.ncs-booking-card' ).length >= 2, true );
	ui.button( 'Edit details' ).click(); assert.equal( ui.app.querySelector( '[name="email"]' ).value, 'alex@example.test', 'Editing preserves entered values' );
	ui.app.querySelector( 'form' ).fire( 'submit' ); ui.button( 'Confirm booking' ).click(); await flush();
	assert.ok( ui.app.textContent.includes( 'What happens next' ) && ui.app.textContent.includes( 'Your private booking link' ) );
	assert.ok( ui.app.querySelector( '.ncs-booking-received' ) && ui.app.querySelector( '.ncs-received-heading-icon' ), 'Confirmation uses a distinct icon-led completion layout' );
	assert.ok( ui.app.querySelectorAll( 'svg[aria-hidden="true"]' ).length >= 7, 'Confirmation icons remain decorative with text labels' );
	assert.ok( ui.app.querySelector( 'a[href="https://meet.google.com/abc-defg-hij"]' ), 'Meet CTA appears after booking' );
	ui.button( 'Copy link' ).click(); assert.ok( ui.app.textContent.includes( 'Select and copy' ), 'Clipboard fallback remains usable' );
	assert.equal( ui.calls.filter( call => call.route === 'booking/create' ).length, 1 );

	ui = setup( true ); await flush(); assert.ok( ui.app.textContent.includes( 'Need a different time?' ) );
	assert.ok( ui.app.querySelectorAll( 'svg[aria-hidden="true"]' ).length >= 8, 'Management uses decorative icons alongside readable labels' );
	ui.button( 'Reschedule' ).click(); await chooseTime( ui ); ui.button( 'Confirm new time' ).click();
	assert.equal( ui.button( 'Rescheduling...' ).disabled, true, 'Disable confirmation while rescheduling' );
	assert.equal( ui.button( 'Rescheduling...' ).getAttribute( 'aria-busy' ), 'true', 'Expose rescheduling loading state to assistive technology' );
	await flush();
	assert.ok( ui.app.textContent.includes( 'November 4, 2026' ) && ui.app.textContent.includes( 'Rescheduled.' ), 'Reschedule refreshes the management summary' );
	ui.button( 'Cancel booking' ).click(); ui.button( 'Yes, cancel booking' ).click(); await flush();
	assert.ok( ui.app.textContent.includes( 'Cancelled' ) );
	assert.equal( ui.app.querySelectorAll( 'a[href="https://meet.google.com/abc-defg-hij"]' ).length, 0, 'Cancelled booking has no join link' );
	assert.equal( ui.app.querySelector( '.ncs-manage-actions' ).querySelectorAll( 'button' ).length, 0, 'Cancelled booking has no active actions' );
	console.log( 'Booking UI checks passed: placeholders, review edits, confirmation, copy fallback, reschedule and cancellation.' );
} )().catch( error => { console.error( error ); process.exitCode = 1; } );
