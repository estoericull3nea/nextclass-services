/**
 * Next Class Services: booking experience.
 * External embeds (Calendly, TidyCal, custom) and the native scheduler.
 * The server re-checks availability before saving. Nothing here is trusted.
 */
( function () {
	'use strict';

	var NCS = window.NCS;
	var I18N = ( window.ncsPublic && window.ncsPublic.i18n ) || {};
	var LANG = document.documentElement.lang || undefined;

	function h( tag, props ) {
		var el = document.createElement( tag );
		var i;
		props = props || {};
		Object.keys( props ).forEach( function ( key ) {
			var val = props[ key ];
			if ( val === null || val === undefined || val === false ) {
				return;
			}
			if ( key === 'class' ) {
				el.className = val;
			} else if ( key === 'text' ) {
				el.textContent = val;
			} else if ( key === 'html' ) {
				el.innerHTML = val;
			} else if ( key.indexOf( 'on' ) === 0 && typeof val === 'function' ) {
				el.addEventListener( key.slice( 2 ), val );
			} else if ( key === 'value' ) {
				el.value = val;
			} else if ( key === 'checked' || key === 'selected' || key === 'disabled' || key === 'hidden' ) {
				el[ key ] = !! val;
			} else {
				el.setAttribute( key, val === true ? '' : val );
			}
		} );
		for ( i = 2; i < arguments.length; i++ ) {
			add( el, arguments[ i ] );
		}
		return el;
	}

	function add( el, child ) {
		if ( child === null || child === undefined || child === false ) {
			return;
		}
		if ( Array.isArray( child ) ) {
			child.forEach( function ( c ) {
				add( el, c );
			} );
		} else if ( typeof child === 'string' || typeof child === 'number' ) {
			el.appendChild( document.createTextNode( String( child ) ) );
		} else {
			el.appendChild( child );
		}
	}

	function pad( n ) {
		return n < 10 ? '0' + n : String( n );
	}

	function ymd( y, m, d ) {
		return y + '-' + pad( m ) + '-' + pad( d );
	}

	function longDate( date ) {
		var p = date.split( '-' );
		var d = new Date( Number( p[ 0 ] ), Number( p[ 1 ] ) - 1, Number( p[ 2 ] ) );
		try {
			return d.toLocaleDateString( LANG, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' } );
		} catch ( e ) {
			return date;
		}
	}

	function monthTitle( y, m ) {
		try {
			return new Date( y, m - 1, 1 ).toLocaleDateString( LANG, { month: 'long', year: 'numeric' } );
		} catch ( e ) {
			return y + '-' + pad( m );
		}
	}

	function getToken() {
		var m = /[?&]ncs_manage=([a-f0-9]{40})(?:&|#|$)/.exec( window.location.search + '' );
		return m ? m[ 1 ] : '';
	}

	function App( root ) {
		var cfgEl = root.querySelector( '.ncs-config' );
		if ( ! cfgEl ) {
			return;
		}
		var cfg = JSON.parse( cfgEl.textContent );
		var app = root.querySelector( '.ncs-app' );
		var restOpts = cfg.preview ? { restNonce: cfg.restNonce } : {};
		var idCounter = 0;

		function uid( p ) {
			idCounter += 1;
			return 'ncs-b-' + p + '-' + idCounter;
		}

		function focusEl( el ) {
			if ( el ) {
				el.setAttribute( 'tabindex', '-1' );
				el.focus();
			}
		}

		function show( view, focus ) {
			app.innerHTML = '';
			view.classList.add( 'ncs-view' );
			app.appendChild( view );
			if ( focus ) {
				focusEl( view.querySelector( '[data-ncs-focus]' ) || view );
			}
		}

		function serviceById( id ) {
			var found = null;
			cfg.services.forEach( function ( s ) {
				if ( Number( s.id ) === Number( id ) ) {
					found = s;
				}
			} );
			return found;
		}

		function track( service, event, meta ) {
			NCS.track( event, service.id, meta || service.method, { item_name: service.name, method: service.method }, !! service.preview, false );
		}

		function serviceHeader( s ) {
			var head = h( 'div', { 'class': 'ncs-service-head' }, h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: s.name } ) );
			if ( s.showDuration && s.duration ) {
				head.appendChild( h( 'p', { 'class': 'ncs-meta', text: ( I18N.minutes || '%d minutes' ).replace( '%d', String( s.duration ) ) } ) );
			}
			if ( s.description ) {
				head.appendChild( h( 'div', { 'class': 'ncs-rich', html: s.description } ) );
			}
			return head;
		}

		/* ---------- external booking ---------- */

		function renderExternal( s ) {
			var wrap = h( 'div', { 'class': 'ncs-panel ncs-external' }, serviceHeader( s ) );
			var interacted = false;
			var signalled = false;
			var link = h( 'a', {
				'class': 'ncs-btn ncs-btn-primary',
				href: s.url,
				target: '_blank',
				rel: 'noopener noreferrer',
				text: s.mode === 'embed' ? ( I18N.openBooking || 'Open the booking page in a new tab' ) : ( s.buttonText || 'Book a time' ),
				onclick: function () {
					track( s, 'booking_external_click', s.method + '_link' );
				}
			} );

			if ( s.mode === 'embed' && s.embedUrl ) {
				var frame = h( 'iframe', {
					'class': 'ncs-embed',
					src: s.embedUrl,
					title: ( I18N.embedTitle || 'Booking calendar' ) + ': ' + s.name,
					loading: 'lazy',
					referrerpolicy: 'strict-origin-when-cross-origin',
					sandbox: 'allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation',
					style: 'height:' + Number( s.height || 700 ) + 'px'
				} );
				wrap.appendChild( frame );
				wrap.appendChild( h( 'p', { 'class': 'ncs-embed-help' }, ( I18N.embedHelp || 'Having trouble loading the calendar?' ) + ' ', link ) );

				// Interaction inside a cross-origin iframe moves focus to it.
				window.addEventListener( 'blur', function () {
					setTimeout( function () {
						if ( ! interacted && document.activeElement === frame ) {
							interacted = true;
							track( s, 'booking_external_click', s.method + '_embed' );
						}
					}, 0 );
				} );

				// Calendly reports progress from the visitor's browser. These are signals only.
				if ( s.method === 'calendly' ) {
					window.addEventListener( 'message', function ( e ) {
						if ( e.origin !== 'https://calendly.com' || e.source !== frame.contentWindow || ! e.data || typeof e.data.event !== 'string' ) {
							return;
						}
						if ( e.data.event === 'calendly.date_and_time_selected' && ! interacted ) {
							interacted = true;
							track( s, 'booking_external_click', 'calendly_time_selected' );
						}
						if ( e.data.event === 'calendly.event_scheduled' && ! signalled ) {
							signalled = true;
							track( s, 'booking_external_signal', 'calendly_event_scheduled' );
						}
					} );
				}
			} else {
				wrap.appendChild( h( 'p', { 'class': 'ncs-actions' }, link ) );
			}
			return wrap;
		}

		/* ---------- native picker ---------- */

		/**
		 * Date and time picker.
		 *
		 * @param {Object}   s       Service.
		 * @param {string}   token   Management token when rescheduling.
		 * @param {Function} onPick  Called with { date, time, label }.
		 * @param {Function} onFirst Called once on the first date choice.
		 */
		function picker( s, token, onPick, onFirst ) {
			var now = new Date();
			var minY = now.getFullYear();
			var minM = now.getMonth() + 1;
			var st = { y: minY, m: minM, days: null, date: '', time: '', slots: null, first: true, loadingMonth: false };
			var box = h( 'div', { 'class': 'ncs-picker' } );
			var calWrap = h( 'div', { 'class': 'ncs-cal-wrap' } );
			var slotWrap = h( 'div', { 'class': 'ncs-slots-wrap', 'aria-live': 'polite' } );
			box.appendChild( calWrap );
			box.appendChild( slotWrap );

			function loadMonth() {
				st.loadingMonth = true;
				renderCal();
				NCS.api( 'GET', 'booking/availability', { service: s.id, month: st.y + '-' + pad( st.m ), token: token }, restOpts ).then( function ( res ) {
					st.loadingMonth = false;
					st.days = res.data && res.data.days ? res.data.days : {};
					renderCal();
				} ).catch( function () {
					st.loadingMonth = false;
					st.days = {};
					renderCal();
				} );
			}

			function shiftMonth( delta ) {
				st.m += delta;
				if ( st.m > 12 ) {
					st.m = 1;
					st.y += 1;
				}
				if ( st.m < 1 ) {
					st.m = 12;
					st.y -= 1;
				}
				st.days = null;
				loadMonth();
			}

			function renderCal() {
				calWrap.innerHTML = '';
				var atMin = st.y === minY && st.m === minM;
				var atMax = ( st.y * 12 + st.m ) >= ( minY * 12 + minM + 24 );
				var titleId = uid( 'cal' );
				var nav = h( 'div', { 'class': 'ncs-cal-nav' },
					h( 'button', { type: 'button', 'class': 'ncs-btn ncs-btn-small', text: I18N.prevMonth || 'Previous month', disabled: atMin || st.loadingMonth, onclick: function () {
						shiftMonth( -1 );
					} } ),
					h( 'h3', { 'class': 'ncs-cal-title', id: titleId, text: monthTitle( st.y, st.m ) } ),
					h( 'button', { type: 'button', 'class': 'ncs-btn ncs-btn-small', text: I18N.nextMonth || 'Next month', disabled: atMax || st.loadingMonth, onclick: function () {
						shiftMonth( 1 );
					} } )
				);
				calWrap.appendChild( nav );
				if ( st.days === null ) {
					calWrap.appendChild( h( 'p', { 'class': 'ncs-loading', text: I18N.loading || 'Loading...' } ) );
					return;
				}

				var start = Number( I18N.startOfWeek || 0 ); // 0 Sunday.
				var names = ( I18N.weekdays || [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ] ).slice();
				// names is Monday first. Rotate to the site week start.
				var offset = start === 0 ? 6 : start - 1;
				names = names.slice( offset ).concat( names.slice( 0, offset ) );

				var table = h( 'table', { 'class': 'ncs-cal', 'aria-labelledby': titleId } );
				var headRow = h( 'tr' );
				names.forEach( function ( n ) {
					headRow.appendChild( h( 'th', { scope: 'col', text: n } ) );
				} );
				table.appendChild( h( 'thead', null, headRow ) );

				var first = new Date( st.y, st.m - 1, 1 );
				var lead = ( first.getDay() - start + 7 ) % 7;
				var daysInMonth = new Date( st.y, st.m, 0 ).getDate();
				var body = h( 'tbody' );
				var row = h( 'tr' );
				var i;
				var any = false;
				for ( i = 0; i < lead; i++ ) {
					row.appendChild( h( 'td', { 'class': 'ncs-cal-empty' } ) );
				}
				for ( i = 1; i <= daysInMonth; i++ ) {
					( function ( day ) {
						var date = ymd( st.y, st.m, day );
						var count = Number( st.days[ date ] || 0 );
						var label = longDate( date ) + ', ' + ( count > 0 ? count + ' ' + ( I18N.available || 'available' ) : ( I18N.unavailable || 'unavailable' ) );
						if ( count > 0 ) {
							any = true;
						}
						var selected = st.date === date;
						var b = h( 'button', {
							type: 'button',
							'class': 'ncs-cal-day' + ( count > 0 ? ' is-available' : ' is-unavailable' ) + ( selected ? ' is-selected' : '' ),
							disabled: count < 1,
							'aria-label': label + ( selected ? ', ' + ( I18N.selected || 'selected' ) : '' ),
							'aria-pressed': selected ? 'true' : 'false',
							onclick: function () {
								pickDate( date );
							}
						}, h( 'span', { text: String( day ) } ) );
						row.appendChild( h( 'td', null, b ) );
						if ( ( lead + day ) % 7 === 0 ) {
							body.appendChild( row );
							row = h( 'tr' );
						}
					}( i ) );
				}
				if ( row.children.length ) {
					body.appendChild( row );
				}
				table.appendChild( body );
				calWrap.appendChild( table );
				if ( ! any ) {
					calWrap.appendChild( h( 'p', { 'class': 'ncs-note', text: I18N.noDates || 'No dates are available this month.' } ) );
				}
			}

			function pickDate( date ) {
				if ( st.first ) {
					st.first = false;
					if ( onFirst ) {
						onFirst();
					}
				}
				st.date = date;
				st.time = '';
				st.slots = null;
				renderCal();
				renderSlots();
				NCS.api( 'GET', 'booking/slots', { service: s.id, date: date, token: token }, restOpts ).then( function ( res ) {
					if ( st.date !== date ) {
						return;
					}
					st.slots = res.data && res.data.slots ? res.data.slots : [];
					renderSlots();
				} ).catch( function () {
					st.slots = [];
					renderSlots();
				} );
			}

			function renderSlots() {
				slotWrap.innerHTML = '';
				if ( ! st.date ) {
					return;
				}
				slotWrap.appendChild( h( 'h3', { 'class': 'ncs-subheading', text: ( I18N.chooseTime || 'Choose a time' ) + ': ' + longDate( st.date ) } ) );
				if ( st.slots === null ) {
					slotWrap.appendChild( h( 'p', { 'class': 'ncs-loading', text: I18N.loading || 'Loading...' } ) );
					return;
				}
				if ( ! st.slots.length ) {
					slotWrap.appendChild( h( 'p', { 'class': 'ncs-note', text: I18N.noSlots || 'No times are available on this date.' } ) );
					return;
				}
				var list = h( 'div', { 'class': 'ncs-slots', role: 'group', 'aria-label': I18N.chooseTime || 'Choose a time' } );
				st.slots.forEach( function ( slot ) {
					var selected = st.time === slot.time;
					list.appendChild( h( 'button', {
						type: 'button',
						'class': 'ncs-slot' + ( selected ? ' is-selected' : '' ),
						'aria-pressed': selected ? 'true' : 'false',
						text: slot.label + ( selected ? ' (' + ( I18N.selected || 'selected' ) + ')' : '' ),
						onclick: function () {
							st.time = slot.time;
							st.label = slot.label;
							renderSlots();
							onPick( { date: st.date, time: slot.time, label: slot.label } );
							var again = slotWrap.querySelector( '.is-selected' );
							if ( again ) {
								again.focus();
							}
						}
					} ) );
				} );
				slotWrap.appendChild( list );
			}

			loadMonth();
			return box;
		}

		/* ---------- native booking flow ---------- */

		function renderNative( s ) {
			var chosen = null;
			var values = {};
			var screen = 'pick';
			var wrap = h( 'div', { 'class': 'ncs-panel ncs-native' } );
			var started = false;
			track( s, 'booking_view' );

			function stepLabel( n ) {
				return ( I18N.step || 'Step %1 of %2' ).replace( '%1', String( n ) ).replace( '%2', '3' );
			}

			function tzNote() {
				return s.showTimezone && s.tzLabel ? h( 'p', { 'class': 'ncs-meta', text: ( I18N.timesShown || 'Times are shown in %s.' ).replace( '%s', s.tzLabel ) } ) : null;
			}

			function draw() {
				wrap.innerHTML = '';
				wrap.appendChild( serviceHeader( s ) );
				if ( screen === 'pick' ) {
					drawPick();
				} else if ( screen === 'details' ) {
					drawDetails();
				} else if ( screen === 'confirm' ) {
					drawConfirm();
				}
			}

			var pickerEl = null;
			var contBtn = null;
			function drawPick() {
				wrap.appendChild( h( 'p', { 'class': 'ncs-step-label', text: stepLabel( 1 ) + ': ' + ( I18N.chooseDate || 'Choose a date' ) } ) );
				add( wrap, tzNote() );
				if ( ! pickerEl ) {
					pickerEl = picker( s, '', function ( pick ) {
						chosen = pick;
						if ( contBtn ) {
							contBtn.disabled = false;
						}
					}, function () {
						if ( ! started ) {
							started = true;
							track( s, 'booking_start' );
						}
					} );
				}
				wrap.appendChild( pickerEl );
				contBtn = h( 'button', {
					type: 'button', 'class': 'ncs-btn ncs-btn-primary', text: I18N.continue || 'Continue', disabled: ! chosen,
					onclick: function () {
						screen = 'details';
						draw();
						focusEl( wrap.querySelector( '[data-ncs-step]' ) );
					}
				} );
				wrap.appendChild( h( 'div', { 'class': 'ncs-actions' }, contBtn ) );
			}

			function drawDetails() {
				var form = h( 'form', { 'class': 'ncs-form', novalidate: true, 'data-ncs-step': '' } );
				form.appendChild( h( 'p', { 'class': 'ncs-step-label', 'data-ncs-focus': '', text: stepLabel( 2 ) + ': ' + ( I18N.yourDetails || 'Your details' ) } ) );
				var refs = {};
				s.fields.forEach( function ( f ) {
					var id = uid( 'f' );
					var errId = id + '-err';
					var input;
					if ( f.type === 'textarea' ) {
						input = h( 'textarea', { id: id, name: f.key, rows: 4, 'class': 'ncs-input', 'aria-describedby': errId } );
					} else if ( f.type === 'select' ) {
						input = h( 'select', { id: id, name: f.key, 'class': 'ncs-select', 'aria-describedby': errId }, h( 'option', { value: '', text: I18N.choose || 'Choose an option' } ) );
						( f.options || [] ).forEach( function ( o ) {
							input.appendChild( h( 'option', { value: o, text: o } ) );
						} );
					} else {
						input = h( 'input', {
							id: id, name: f.key, 'class': 'ncs-input', 'aria-describedby': errId,
							type: f.type === 'email' ? 'email' : ( f.type === 'tel' ? 'tel' : 'text' ),
							autocomplete: { name: 'name', email: 'email', phone: 'tel', company: 'organization', job_title: 'organization-title' }[ f.key ] || 'off'
						} );
					}
					input.value = values[ f.key ] || '';
					if ( f.required ) {
						input.setAttribute( 'aria-required', 'true' );
					}
					input.addEventListener( 'input', function () {
						values[ f.key ] = input.value;
					} );
					input.addEventListener( 'change', function () {
						values[ f.key ] = input.value;
					} );
					var err = h( 'p', { 'class': 'ncs-error', id: errId, role: 'alert', hidden: true } );
					refs[ f.key ] = { input: input, err: err };
					form.appendChild( h( 'div', { 'class': 'ncs-field' },
						h( 'label', { 'for': id }, f.label, f.required ? h( 'span', { 'class': 'ncs-required', text: ' ' + ( I18N.requiredMark || '(required)' ) } ) : null ),
						input, err ) );
				} );
				wrap.appendChild( form );
				drawDetailsButtons( form, refs );
			}

			function setErr( ref, msg ) {
				ref.err.hidden = ! msg;
				ref.err.textContent = msg || '';
				if ( msg ) {
					ref.input.setAttribute( 'aria-invalid', 'true' );
				} else {
					ref.input.removeAttribute( 'aria-invalid' );
				}
			}

			function drawDetailsButtons( form, refs ) {
				var back = h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.back || 'Back', onclick: function () {
					screen = 'pick';
					draw();
				} } );
				var next = h( 'button', { type: 'submit', 'class': 'ncs-btn ncs-btn-primary', text: I18N.continue || 'Continue' } );
				form.appendChild( h( 'div', { 'class': 'ncs-actions' }, back, next ) );
				form.addEventListener( 'submit', function ( e ) {
					e.preventDefault();
					var bad = null;
					s.fields.forEach( function ( f ) {
						var v = ( values[ f.key ] || '' ).trim();
						var msg = '';
						if ( f.required && v === '' ) {
							msg = f.label + ' is required.';
						} else if ( f.type === 'email' && v !== '' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( v ) ) {
							msg = 'Please enter a valid email address.';
						}
						setErr( refs[ f.key ], msg );
						if ( msg && ! bad ) {
							bad = refs[ f.key ].input;
						}
					} );
					if ( bad ) {
						bad.focus();
						return;
					}
					screen = 'confirm';
					draw();
					focusEl( wrap.querySelector( '[data-ncs-focus]' ) );
				} );
			}

			function drawConfirm( serverErrors ) {
				wrap.appendChild( h( 'p', { 'class': 'ncs-step-label', 'data-ncs-focus': '', text: stepLabel( 3 ) + ': ' + ( I18N.confirmTitle || 'Review and confirm' ) } ) );
				var dl = h( 'dl', { 'class': 'ncs-summary' } );
				var rows = [
					[ I18N.service || 'Service', s.name ],
					[ I18N.when || 'When', longDate( chosen.date ) + ', ' + chosen.label ],
					[ 'Timezone', s.tzLabel || '' ]
				];
				s.fields.forEach( function ( f ) {
					if ( values[ f.key ] ) {
						rows.push( [ f.label, values[ f.key ] ] );
					}
				} );
				rows.forEach( function ( r ) {
					if ( r[ 1 ] ) {
						dl.appendChild( h( 'dt', { text: r[ 0 ] } ) );
						dl.appendChild( h( 'dd', { text: r[ 1 ] } ) );
					}
				} );
				wrap.appendChild( dl );
				var msg = h( 'p', { 'class': 'ncs-error', role: 'alert', hidden: ! serverErrors, text: serverErrors || '' } );
				wrap.appendChild( msg );
				var hp = h( 'div', { 'class': 'ncs-hp', 'aria-hidden': 'true' }, h( 'input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off' } ) );
				wrap.appendChild( hp );
				var confirm = h( 'button', { type: 'button', 'class': 'ncs-btn ncs-btn-primary', text: s.buttonText && s.buttonText !== 'Book a time' ? s.buttonText : ( I18N.confirmBooking || 'Confirm booking' ) } );
				var back = h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.back || 'Back', onclick: function () {
					screen = 'details';
					draw();
				} } );
				confirm.addEventListener( 'click', function () {
					msg.hidden = true;
					confirm.disabled = true;
					confirm.textContent = I18N.working || 'Working...';
					var body = {
						service: s.id,
						date: chosen.date,
						time: chosen.time,
						fields: values,
						website: hp.firstChild.value,
						page_url: window.location.href.split( '#' )[ 0 ]
					};
					if ( s.preview ) {
						body.preview = true;
						body.preview_nonce = s.previewNonce;
					}
					NCS.api( 'POST', 'booking/create', body, restOpts ).then( function ( res ) {
						var d = res.data || {};
						if ( d.ok ) {
							NCS.ga( 'booking_complete', { item_id: s.id, item_name: s.name, method: 'native' } );
							done( d );
							return;
						}
						confirm.disabled = false;
						confirm.textContent = I18N.confirmBooking || 'Confirm booking';
						msg.hidden = false;
						msg.textContent = d.message || ( I18N.network || 'Something went wrong. Please try again.' );
						if ( d.code === 'slot_taken' ) {
							// Force a fresh availability lookup.
							pickerEl = null;
							chosen = null;
							screen = 'pick';
							draw();
							var note = h( 'p', { 'class': 'ncs-error', role: 'alert', text: d.message } );
							wrap.insertBefore( note, wrap.children[ 1 ] || null );
						} else if ( d.code === 'validation' ) {
							screen = 'details';
							draw();
						}
					} ).catch( function () {
						confirm.disabled = false;
						confirm.textContent = I18N.confirmBooking || 'Confirm booking';
						msg.hidden = false;
						msg.textContent = I18N.network || 'Something went wrong. Please try again.';
					} );
				} );
				wrap.appendChild( h( 'div', { 'class': 'ncs-actions' }, back, confirm ) );
			}

			function done( d ) {
				wrap.innerHTML = '';
				wrap.appendChild( h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: I18N.bookingDone || 'Booking received' } ) );
				wrap.appendChild( h( 'p', { 'class': 'ncs-lead-text', text: d.message } ) );
				var a = d.appointment || {};
				var dl = h( 'dl', { 'class': 'ncs-summary' } );
				[ [ I18N.service || 'Service', a.service ], [ I18N.when || 'When', ( a.date || '' ) + ( a.time ? ', ' + a.time : '' ) ], [ 'Timezone', a.timezone ], [ I18N.status || 'Status', a.statusLabel ] ].forEach( function ( r ) {
					if ( r[ 1 ] ) {
						dl.appendChild( h( 'dt', { text: r[ 0 ] } ) );
						dl.appendChild( h( 'dd', { text: r[ 1 ] } ) );
					}
				} );
				wrap.appendChild( dl );
				if ( d.preview ) {
					wrap.appendChild( h( 'p', { 'class': 'ncs-note', text: I18N.previewDone || 'This is a preview, so nothing was saved.' } ) );
				}
				if ( d.manageUrl ) {
					wrap.appendChild( h( 'p', { 'class': 'ncs-note' }, ( I18N.manageLink || 'Save this private link to cancel or reschedule your booking:' ) + ' ', h( 'a', { href: d.manageUrl, text: I18N.manageTitle || 'Manage your booking' } ) ) );
				}
				focusEl( wrap.querySelector( '[data-ncs-focus]' ) );
				if ( d.redirect && ! d.preview ) {
					setTimeout( function () {
						window.location.href = d.redirect;
					}, 2500 );
				}
			}

			draw();
			return wrap;
		}

		/* ---------- manage flow ---------- */

		function renderManage( token ) {
			var wrap = h( 'div', { 'class': 'ncs-panel ncs-manage' }, h( 'p', { 'class': 'ncs-loading', text: I18N.loading || 'Loading...' } ) );
			show( wrap, false );

			NCS.api( 'POST', 'booking/manage', { token: token, website: '' } ).then( function ( res ) {
				var d = res.data || {};
				if ( ! d.ok ) {
					show( h( 'div', { 'class': 'ncs-panel' }, h( 'p', { 'class': 'ncs-error', role: 'alert', 'data-ncs-focus': '', text: d.message || I18N.bookingNotFound || 'We could not find that booking.' } ) ), true );
					return;
				}
				drawManage( d );
			} ).catch( function () {
				show( h( 'div', { 'class': 'ncs-panel' }, h( 'p', { 'class': 'ncs-error', role: 'alert', text: I18N.network || 'Something went wrong. Please try again.' } ) ), false );
			} );

			function summary( d ) {
				var a = d.appointment;
				var dl = h( 'dl', { 'class': 'ncs-summary' } );
				[ [ I18N.service || 'Service', a.service ], [ I18N.when || 'When', a.date + ', ' + a.time ], [ 'Timezone', a.timezone ], [ I18N.status || 'Status', a.statusLabel ] ].forEach( function ( r ) {
					dl.appendChild( h( 'dt', { text: r[ 0 ] } ) );
					dl.appendChild( h( 'dd', { text: r[ 1 ] } ) );
				} );
				return dl;
			}

			function drawManage( d ) {
				var panel = h( 'div', { 'class': 'ncs-panel ncs-manage' },
					h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: I18N.manageTitle || 'Manage your booking' } ),
					summary( d )
				);
				var actions = h( 'div', { 'class': 'ncs-actions' } );
				if ( d.appointment.active ) {
					if ( d.canReschedule ) {
						actions.appendChild( h( 'button', { type: 'button', 'class': 'ncs-btn ncs-btn-primary', text: I18N.rescheduleBooking || 'Reschedule', onclick: function () {
							drawReschedule( d );
						} } ) );
					} else if ( d.rescheduleReason ) {
						panel.appendChild( h( 'p', { 'class': 'ncs-note', text: d.rescheduleReason } ) );
					}
					if ( d.canCancel ) {
						actions.appendChild( h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.cancelBooking || 'Cancel booking', onclick: function () {
							drawCancel( d );
						} } ) );
					} else if ( d.cancelReason ) {
						panel.appendChild( h( 'p', { 'class': 'ncs-note', text: d.cancelReason } ) );
					}
				}
				panel.appendChild( actions );
				show( panel, true );
			}

			function drawCancel( d ) {
				var msg = h( 'p', { 'class': 'ncs-error', role: 'alert', hidden: true } );
				var yes = h( 'button', { type: 'button', 'class': 'ncs-btn ncs-btn-primary', text: I18N.yesCancel || 'Yes, cancel booking' } );
				yes.addEventListener( 'click', function () {
					yes.disabled = true;
					NCS.api( 'POST', 'booking/cancel', { token: token, website: '' } ).then( function ( res ) {
						var r = res.data || {};
						if ( r.ok ) {
							show( h( 'div', { 'class': 'ncs-panel' }, h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: r.message } ) ), true );
						} else {
							yes.disabled = false;
							msg.hidden = false;
							msg.textContent = r.message || I18N.network;
						}
					} ).catch( function () {
						yes.disabled = false;
						msg.hidden = false;
						msg.textContent = I18N.network || 'Something went wrong.';
					} );
				} );
				show( h( 'div', { 'class': 'ncs-panel' },
					h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: I18N.confirmCancel || 'Are you sure you want to cancel this booking?' } ),
					summary( d ), msg,
					h( 'div', { 'class': 'ncs-actions' },
						h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.keepBooking || 'Keep booking', onclick: function () {
							drawManage( d );
						} } ),
						yes
					)
				), true );
			}

			function drawReschedule( d ) {
				var s = d.service;
				var chosen = null;
				var msg = h( 'p', { 'class': 'ncs-error', role: 'alert', hidden: true } );
				var confirm = h( 'button', { type: 'button', 'class': 'ncs-btn ncs-btn-primary', text: I18N.confirmMove || 'Confirm new time', disabled: true } );
				var pick = picker( s, token, function ( p ) {
					chosen = p;
					confirm.disabled = false;
				}, null );
				confirm.addEventListener( 'click', function () {
					if ( ! chosen ) {
						return;
					}
					confirm.disabled = true;
					NCS.api( 'POST', 'booking/reschedule', { token: token, date: chosen.date, time: chosen.time, website: '' } ).then( function ( res ) {
						var r = res.data || {};
						if ( r.ok ) {
							var a = r.appointment || {};
							show( h( 'div', { 'class': 'ncs-panel' },
								h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: r.message } ),
								h( 'p', { text: ( a.date || '' ) + ', ' + ( a.time || '' ) + ' (' + ( a.timezone || '' ) + ')' } )
							), true );
						} else {
							confirm.disabled = false;
							msg.hidden = false;
							msg.textContent = r.message || I18N.network;
						}
					} ).catch( function () {
						confirm.disabled = false;
						msg.hidden = false;
						msg.textContent = I18N.network || 'Something went wrong.';
					} );
				} );
				show( h( 'div', { 'class': 'ncs-panel' },
					h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: I18N.newTime || 'Choose a new time' } ),
					s.showTimezone && s.tzLabel ? h( 'p', { 'class': 'ncs-meta', text: ( I18N.timesShown || 'Times are shown in %s.' ).replace( '%s', s.tzLabel ) } ) : null,
					pick, msg,
					h( 'div', { 'class': 'ncs-actions' },
						h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.back || 'Back', onclick: function () {
							drawManage( d );
						} } ),
						confirm
					)
				), true );
			}
		}

		/* ---------- start ---------- */

		function renderService( s ) {
			return s.method === 'native' ? renderNative( s ) : renderExternal( s );
		}

		var token = getToken();
		var hasNative = cfg.services.some( function ( s ) {
			return s.method === 'native';
		} );
		if ( token && hasNative ) {
			renderManage( token );
			return;
		}

		if ( cfg.services.length === 1 ) {
			var only = cfg.services[ 0 ];
			var view = renderService( only );
			show( view, false );
			if ( only.method !== 'native' ) {
				track( only, 'booking_view' );
			}
			return;
		}

		// Several services: let the visitor choose.
		var holder = h( 'div', { 'class': 'ncs-panel ncs-chooser' } );
		var list = h( 'ul', { 'class': 'ncs-service-list' } );
		var stage = h( 'div', { 'class': 'ncs-service-stage', 'aria-live': 'polite' } );
		cfg.services.forEach( function ( s ) {
			list.appendChild( h( 'li', null, h( 'button', {
				type: 'button', 'class': 'ncs-service-btn', 'aria-pressed': 'false',
				onclick: function ( e ) {
					Array.prototype.forEach.call( list.querySelectorAll( 'button' ), function ( b ) {
						b.setAttribute( 'aria-pressed', 'false' );
						b.classList.remove( 'is-selected' );
					} );
					e.currentTarget.setAttribute( 'aria-pressed', 'true' );
					e.currentTarget.classList.add( 'is-selected' );
					stage.innerHTML = '';
					var v = renderService( s );
					stage.appendChild( v );
					if ( s.method !== 'native' ) {
						track( s, 'booking_view' );
					}
				}
			}, h( 'strong', { text: s.name } ), s.showDuration && s.duration ? h( 'span', { 'class': 'ncs-meta', text: ' ' + ( I18N.minutes || '%d minutes' ).replace( '%d', String( s.duration ) ) } ) : null ) ) );
		} );
		holder.appendChild( h( 'h2', { 'class': 'ncs-heading', text: I18N.chooseService || 'Choose a service' } ) );
		holder.appendChild( list );
		holder.appendChild( stage );
		show( holder, false );
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-ncs-booking]' ), function ( root ) {
			if ( root.getAttribute( 'data-ncs-ready' ) ) {
				return;
			}
			root.setAttribute( 'data-ncs-ready', '1' );
			try {
				App( root );
			} catch ( e ) {
				if ( window.console && window.console.error ) {
					window.console.error( 'Next Class Services:', e );
				}
				var app = root.querySelector( '.ncs-app' );
				if ( app ) {
					app.textContent = '';
				}
			}
		} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
}() );
