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
					slotWrap.appendChild( h( 'h3', { 'class': 'ncs-subheading', text: I18N.chooseTime || 'Choose a time' } ) );
					slotWrap.appendChild( h( 'p', { 'class': 'ncs-note ncs-slots-hint', text: I18N.selectDateFirst || 'Select date first' } ) );
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
						text: slot.label,
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
			renderSlots();
			return box;
		}

		/* ---------- native booking flow ---------- */

		/** Decorative inline icons; labels remain readable without SVG support. */
		function bookingIcon( name ) {
			var paths = {
				calendar: [ 'M8 2v4M16 2v4M3 10h18', 'M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z' ],
				clock: [ 'M12 8v4l3 2', 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' ],
				user: [ 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z', 'M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2' ],
				mail: [ 'M3 6l9 7 9-7', 'M3 5h18v14H3Z' ],
				globe: [ 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM3 12h18', 'M12 3a18 18 0 0 1 0 18 18 18 0 0 1 0-18Z' ],
				edit: [ 'm15 5 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15Z' ],
				check: [ 'm5 12 4 4L19 6' ],
				back: [ 'M19 12H5m6-6-6 6 6 6' ],
				video: [ 'M15 9l6-4v14l-6-4', 'M3 5h12v14H3Z' ],
				link: [ 'M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-2 2', 'M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l2-2' ]
			};
			var svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
			var attrs = { viewBox: '0 0 24 24', width: '20', height: '20', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.7', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false', 'class': 'ncs-booking-icon' };
			Object.keys( attrs ).forEach( function ( key ) { svg.setAttribute( key, attrs[key] ); } );
			( paths[name] || paths.check ).forEach( function ( data ) {
				var path = document.createElementNS( 'http://www.w3.org/2000/svg', 'path' );
				path.setAttribute( 'd', data ); svg.appendChild( path );
			} );
			return svg;
		}

		function reviewButtonLabel( button, text, icon ) {
			button.textContent = '';
			button.appendChild( bookingIcon( icon || 'check' ) );
			button.appendChild( h( 'span', { text: text } ) );
		}

		function summaryList( rows ) {
			var list = h( 'dl', { 'class': 'ncs-booking-facts' } );
			rows.forEach( function ( row ) {
				if ( row[1] ) {
					list.appendChild( h( 'div', {}, h( 'dt', {}, row[2] ? bookingIcon( row[2] ) : null, h( 'span', { text: row[0] } ) ), h( 'dd', { text: row[1] } ) ) );
				}
			} );
			return list;
		}

		function bookingCard( appointment, icons ) {
			return h( 'section', { 'class': 'ncs-booking-card ncs-appointment-card', 'aria-label': I18N.appointmentDetails || 'Appointment details' },
				h( 'p', { 'class': 'ncs-booking-kicker' }, icons ? bookingIcon( 'calendar' ) : null, h( 'span', { text: I18N.yourAppointment || 'Your appointment' } ) ),
				h( 'h3', { 'class': 'ncs-booking-card-title', text: appointment.service } ),
				h( 'p', { 'class': 'ncs-booking-date' }, h( 'span', { text: appointment.date } ) ),
				h( 'p', { 'class': 'ncs-booking-time' }, icons ? bookingIcon( 'clock' ) : null, h( 'span', { text: appointment.time } ) ),
				appointment.timezone ? h( 'p', { 'class': 'ncs-meta' }, icons ? bookingIcon( 'globe' ) : null, h( 'span', { text: appointment.timezone } ) ) : null
			);
		}

		function statusBadge( status, label ) {
			return h( 'span', { 'class': 'ncs-booking-status' + ( status === 'cancelled' ? ' is-cancelled' : '' ), text: label || status } );
		}

		function calendarNotice( state, email, icons ) {
			if ( state === 'off' || state === 'cancelled' || state === 'awaiting_approval' || ! state ) {
				return null;
			}
			return h( 'div', { 'class': 'ncs-booking-notice' },
				h( 'h3', { 'class': 'ncs-booking-card-title' }, icons ? bookingIcon( 'mail' ) : null, h( 'span', { text: state === 'pending' ? ( I18N.calendarPendingTitle || 'Calendar invitation is being prepared' ) : ( I18N.calendarReadyTitle || 'Your calendar invitation' ) } ) ),
				h( 'p', { text: state === 'pending' ? ( I18N.calendarPending || 'Your booking is saved. Your calendar invitation and Meet link are still being prepared. Check your private booking page shortly.' ) : ( I18N.calendarReady || 'Check your inbox for the Google Calendar invitation. You may need to accept it to add the event to your calendar.' ) } ),
				email ? h( 'p', { 'class': 'ncs-meta', text: email } ) : null
			);
		}

		function meetButton( url, icons ) {
			return url ? h( 'a', { href: url, 'class': 'ncs-btn ncs-btn-primary', target: '_blank', rel: 'noopener noreferrer' }, icons ? bookingIcon( 'video' ) : null, h( 'span', { text: I18N.joinMeet || 'Join Google Meet' } ) ) : null;
		}

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
				wrap.classList.toggle( 'ncs-review', screen === 'confirm' );
				if ( screen !== 'confirm' ) {
					wrap.appendChild( serviceHeader( s ) );
				}
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
						input = h( 'textarea', { id: id, name: f.key, rows: 4, placeholder: f.placeholder || '', 'class': 'ncs-input', 'aria-describedby': errId } );
					} else if ( f.type === 'select' ) {
						input = h( 'select', { id: id, name: f.key, 'class': 'ncs-select', 'aria-describedby': errId }, h( 'option', { value: '', text: f.placeholder || I18N.choose || 'Choose an option' } ) );
						( f.options || [] ).forEach( function ( o ) {
							input.appendChild( h( 'option', { value: o, text: o } ) );
						} );
					} else {
						input = h( 'input', {
							id: id, name: f.key, 'class': 'ncs-input', 'aria-describedby': errId,
							placeholder: f.placeholder || '',
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
				wrap.appendChild( h( 'p', { 'class': 'ncs-step-label', text: stepLabel( 3 ) } ) );
				var progress = h( 'ol', { 'class': 'ncs-review-progress', 'aria-label': I18N.bookingProgress || 'Booking progress' } );
				[ I18N.chooseTime || 'Choose a time', I18N.yourDetails || 'Your details', I18N.confirmTitle || 'Review and confirm' ].forEach( function ( label, index ) {
					progress.appendChild( h( 'li', { 'aria-current': index === 2 ? 'step' : null }, h( 'span', { 'class': 'ncs-review-step-number', 'aria-hidden': 'true' }, index < 2 ? bookingIcon( 'check' ) : '3' ), h( 'span', { text: label } ) ) );
				} );
				wrap.appendChild( progress );
				wrap.appendChild( h( 'div', { 'class': 'ncs-review-header' }, h( 'span', { 'class': 'ncs-review-heading-icon' }, bookingIcon( 'check' ) ), h( 'div', {},
					h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: I18N.confirmTitle || 'Review and confirm' } ),
					h( 'p', { 'class': 'ncs-lead-text', text: I18N.reviewHelp || 'Check your appointment and contact details before you confirm.' } ) ) ) );
				var grid = h( 'div', { 'class': 'ncs-booking-grid' } );
				var appointment = bookingCard( { service: s.name, date: longDate( chosen.date ), time: chosen.label, timezone: s.tzLabel }, true );
				var editTime = h( 'button', { type: 'button', 'class': 'ncs-booking-edit', 'aria-label': I18N.editTime || 'Edit date and time', title: I18N.editTime || 'Edit date and time', onclick: function () {
					screen = 'pick'; draw(); focusEl( wrap.querySelector( '.ncs-step-label' ) );
				} } );
				editTime.appendChild( bookingIcon( 'edit' ) );
				appointment.appendChild( editTime );
				var rows = [];
				s.fields.forEach( function ( f ) {
					if ( values[ f.key ] ) {
						rows.push( [ f.label, values[ f.key ], f.key === 'email' ? 'mail' : ( f.key === 'name' ? 'user' : null ) ] );
					}
				} );
				var editDetails = h( 'button', { type: 'button', 'class': 'ncs-booking-edit', 'aria-label': I18N.editDetails || 'Edit details', title: I18N.editDetails || 'Edit details', onclick: function () {
					screen = 'details'; draw(); focusEl( wrap.querySelector( '[data-ncs-focus]' ) );
				} } );
				editDetails.appendChild( bookingIcon( 'edit' ) );
				grid.appendChild( appointment );
				grid.appendChild( h( 'section', { 'class': 'ncs-booking-card' }, h( 'h3', { 'class': 'ncs-booking-card-title ncs-review-details-title' }, bookingIcon( 'user' ), h( 'span', { text: I18N.yourDetails || 'Your details' } ) ), summaryList( rows ), editDetails ) );
				wrap.appendChild( grid );
				if ( s.calendarSync ) {
					wrap.appendChild( h( 'div', { 'class': 'ncs-booking-notice ncs-review-invitation' }, bookingIcon( 'video' ), h( 'p', { text: I18N.invitationReview || 'Our team will review your request first. After approval, your name and email will be used to send a Google Calendar invitation with a Meet link.' } ) ) );
				}
				var msg = h( 'p', { 'class': 'ncs-error', role: 'alert', hidden: ! serverErrors, text: serverErrors || '' } );
				wrap.appendChild( msg );
				var hp = h( 'div', { 'class': 'ncs-hp', 'aria-hidden': 'true' }, h( 'input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off' } ) );
				wrap.appendChild( hp );
				var confirm = h( 'button', { type: 'button', 'class': 'ncs-btn ncs-btn-primary', text: s.buttonText && s.buttonText !== 'Book a time' ? s.buttonText : ( I18N.confirmBooking || 'Confirm booking' ) } );
				var confirmLabel = confirm.textContent;
				reviewButtonLabel( confirm, confirmLabel );
				var back = h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.back || 'Back', onclick: function () {
					screen = 'details';
					draw();
				} } );
				reviewButtonLabel( back, I18N.back || 'Back', 'back' );
				confirm.addEventListener( 'click', function () {
					msg.hidden = true;
					confirm.disabled = true;
					back.disabled = true;
					editTime.disabled = true;
					editDetails.disabled = true;
					wrap.setAttribute( 'aria-busy', 'true' );
					reviewButtonLabel( confirm, I18N.working || 'Working...', 'clock' );
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
						wrap.removeAttribute( 'aria-busy' );
						if ( d.ok ) {
							NCS.ga( 'booking_complete', { item_id: s.id, item_name: s.name, method: 'native' } );
							done( d );
							return;
						}
						confirm.disabled = false;
						back.disabled = editTime.disabled = editDetails.disabled = false;
						reviewButtonLabel( confirm, confirmLabel );
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
						wrap.removeAttribute( 'aria-busy' );
						back.disabled = editTime.disabled = editDetails.disabled = false;
						confirm.disabled = false;
						reviewButtonLabel( confirm, confirmLabel );
						msg.hidden = false;
						msg.textContent = I18N.network || 'Something went wrong. Please try again.';
					} );
				} );
				wrap.appendChild( h( 'div', { 'class': 'ncs-actions ncs-booking-footer' }, back, confirm ) );
			}

			function done( d ) {
				wrap.innerHTML = '';
				wrap.classList.remove( 'ncs-review' );
				wrap.classList.add( 'ncs-booking-received' );
				var a = d.appointment || {};
				wrap.appendChild( h( 'div', { 'class': 'ncs-booking-hero' },
					h( 'span', { 'class': 'ncs-received-heading-icon' }, bookingIcon( a.status === 'pending' ? 'clock' : 'check' ) ),
					h( 'p', { 'class': 'ncs-booking-kicker', text: d.preview ? ( I18N.preview || 'Preview' ) : ( I18N.bookingSaved || 'Booking saved' ) } ),
					h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: I18N.bookingDone || 'Booking received' } ),
					statusBadge( a.status, a.statusLabel )
				) );
				wrap.appendChild( h( 'p', { 'class': 'ncs-lead-text', text: d.message } ) );
				var next = h( 'section', { 'class': 'ncs-booking-card' }, h( 'h3', { 'class': 'ncs-booking-card-title' }, bookingIcon( 'calendar' ), h( 'span', { text: I18N.nextSteps || 'What happens next' } ) ) );
				if ( a.status === 'pending' ) {
					next.appendChild( h( 'p', { text: I18N.pendingBooking || 'Your booking is awaiting approval. Keep your private link to check its status.' } ) );
				}
				add( next, calendarNotice( d.calendarState, d.email, true ) );
				if ( d.calendarState === 'off' || ! d.calendarState ) {
					next.appendChild( h( 'p', { text: I18N.keepBookingLink || 'Keep your private booking link so you can view your appointment and available changes.' } ) );
				}
				var nextActions = h( 'div', { 'class': 'ncs-actions' } );
				if ( d.manageUrl ) {
					nextActions.appendChild( h( 'a', { 'class': d.meetUrl ? 'ncs-btn' : 'ncs-btn ncs-btn-primary', href: d.manageUrl }, bookingIcon( 'calendar' ), h( 'span', { text: I18N.manageTitle || 'Manage your booking' } ) ) );
				}
				add( nextActions, meetButton( d.meetUrl, true ) );
				next.appendChild( nextActions );
				wrap.appendChild( h( 'div', { 'class': 'ncs-booking-grid' }, bookingCard( a, true ), next ) );
				if ( d.preview ) {
					wrap.appendChild( h( 'p', { 'class': 'ncs-note', text: I18N.previewDone || 'This is a preview, so nothing was saved.' } ) );
				}
				if ( d.manageUrl ) {
					var linkId = uid( 'private-link' );
					var link = h( 'input', { id: linkId, type: 'url', readonly: true, 'class': 'ncs-input', value: d.manageUrl } );
					var copyResult = h( 'p', { 'class': 'ncs-meta', role: 'status', 'aria-live': 'polite' } );
					var copy = h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.copyLink || 'Copy link', onclick: function () {
						function manualCopy() { link.focus(); link.select(); copyResult.textContent = I18N.copyManually || 'Select and copy this link to keep it.'; }
						if ( navigator.clipboard && navigator.clipboard.writeText ) {
							navigator.clipboard.writeText( d.manageUrl ).then( function () { copyResult.textContent = I18N.linkCopied || 'Link copied.'; }, manualCopy );
						} else { manualCopy(); }
					} } );
					reviewButtonLabel( copy, I18N.copyLink || 'Copy link', 'link' );
					wrap.appendChild( h( 'section', { 'class': 'ncs-booking-card ncs-private-link' }, h( 'label', { 'for': linkId }, bookingIcon( 'link' ), h( 'span', { text: I18N.privateLink || 'Your private booking link' } ) ),
						h( 'p', { 'class': 'ncs-meta', text: I18N.privateLinkHelp || 'Save this link and keep it private. Anyone with the link can manage this booking.' } ), h( 'div', { 'class': 'ncs-booking-link-row' }, link, copy ), copyResult ) );
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

			function loadManage( notice ) {
				NCS.api( 'POST', 'booking/manage', { token: token, website: '' } ).then( function ( res ) {
					var d = res.data || {};
					if ( ! d.ok ) {
						show( h( 'div', { 'class': 'ncs-panel' }, h( 'p', { 'class': 'ncs-error', role: 'alert', 'data-ncs-focus': '', text: d.message || I18N.bookingNotFound || 'We could not find that booking.' } ) ), true );
						return;
					}
					drawManage( d, notice );
				} ).catch( function () {
					show( h( 'div', { 'class': 'ncs-panel' },
						notice ? h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: notice } ) : null,
						h( 'p', { 'class': 'ncs-error', role: 'alert', text: I18N.network || 'Something went wrong. Please try again.' } ),
						h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.refreshBooking || 'Refresh booking details', onclick: function () { loadManage( notice ); } } )
					), true );
				} );
			}
			loadManage();

			function summary( d ) {
				var a = d.appointment;
				var card = bookingCard( a );
				card.appendChild( statusBadge( a.status, a.statusLabel ) );
				return card;
			}

			function drawManage( d, notice ) {
				var a = d.appointment;
				var panel = h( 'div', { 'class': 'ncs-panel ncs-manage' },
					h( 'div', { 'class': 'ncs-booking-hero' }, h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: I18N.manageTitle || 'Manage your booking' } ), statusBadge( a.status, a.statusLabel ) ),
					h( 'p', { 'class': 'ncs-lead-text', text: I18N.manageHelp || 'Your appointment details and available changes, all in one place.' } )
				);
				if ( notice ) { panel.appendChild( h( 'p', { 'class': 'ncs-booking-notice', role: 'status', text: notice } ) ); }
				var details = h( 'section', { 'class': 'ncs-booking-card' }, h( 'h3', { 'class': 'ncs-booking-card-title', text: I18N.yourDetails || 'Your details' } ), summaryList( [ [ I18N.name || 'Name', a.name ], [ I18N.email || 'Email', a.email ] ] ) );
				add( details, calendarNotice( a.calendarState, '' ) );
				if ( a.active ) { add( details, meetButton( a.meetUrl ) ); }
				if ( a.calendarState === 'pending' ) {
					details.appendChild( h( 'button', { type: 'button', 'class': 'ncs-booking-edit', text: I18N.refreshBooking || 'Refresh booking details', onclick: function ( event ) { event.currentTarget.disabled = true; loadManage(); } } ) );
				}
				panel.appendChild( h( 'div', { 'class': 'ncs-booking-grid' }, bookingCard( a ), details ) );
				var actions = h( 'div', { 'class': 'ncs-booking-grid ncs-manage-actions' } );
				if ( d.appointment.active ) {
					if ( d.canReschedule ) {
						actions.appendChild( h( 'section', { 'class': 'ncs-booking-card' }, h( 'h3', { 'class': 'ncs-booking-card-title', text: I18N.needDifferentTime || 'Need a different time?' } ), h( 'p', { text: I18N.rescheduleHelp || 'Choose another available slot. Your booking stays in place until the new time is confirmed.' } ), h( 'button', { type: 'button', 'class': 'ncs-btn', text: I18N.rescheduleBooking || 'Reschedule', onclick: function () {
							drawReschedule( d );
						} } ) ) );
					} else if ( d.rescheduleReason ) {
						panel.appendChild( h( 'p', { 'class': 'ncs-note', text: d.rescheduleReason } ) );
					}
					if ( d.canCancel ) {
						actions.appendChild( h( 'section', { 'class': 'ncs-booking-card' }, h( 'h3', { 'class': 'ncs-booking-card-title', text: I18N.cantMakeIt || 'Can\'t make it?' } ), h( 'p', { text: I18N.cancelHelp || 'Cancel your appointment and release this time. You will review the cancellation first.' } ), h( 'button', { type: 'button', 'class': 'ncs-btn ncs-booking-cancel', text: I18N.cancelBooking || 'Cancel booking', onclick: function () {
							drawCancel( d );
						} } ) ) );
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
							loadManage( r.message );
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
				var moving = false;
				var msg = h( 'p', { 'class': 'ncs-error', role: 'alert', hidden: true } );
				var confirm = h( 'button', { type: 'button', 'class': 'ncs-btn ncs-btn-primary', text: I18N.confirmMove || 'Confirm new time', disabled: true } );
				var pick = picker( s, token, function ( p ) {
					chosen = p;
					confirm.disabled = moving;
				}, null );
				confirm.addEventListener( 'click', function () {
					if ( ! chosen || moving ) {
						return;
					}
					confirm.disabled = true;
					moving = true;
					NCS.api( 'POST', 'booking/reschedule', { token: token, date: chosen.date, time: chosen.time, website: '' } ).then( function ( res ) {
						var r = res.data || {};
						if ( r.ok ) {
							loadManage( r.message );
						} else {
							moving = false;
							confirm.disabled = false;
							msg.hidden = false;
							msg.textContent = r.message || I18N.network;
						}
					} ).catch( function () {
						moving = false;
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
