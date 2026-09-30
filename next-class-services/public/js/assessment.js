/**
 * Next Class Services: assessment experience.
 * Multi-step questions with conditional logic, lead capture and results.
 * Scoring and validation are repeated on the server. Nothing here is trusted.
 */
( function () {
	'use strict';

	var NCS = window.NCS;
	var I18N = ( window.ncsPublic && window.ncsPublic.i18n ) || {};

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

	/* ---------- conditional logic (mirrors PHP) ---------- */

	function isEmptyAnswer( v ) {
		if ( Array.isArray( v ) ) {
			return v.length === 0;
		}
		return v === undefined || v === null || String( v ).trim() === '';
	}

	function compare( op, answer, target ) {
		target = String( target );
		var empty = isEmptyAnswer( answer );
		function equals() {
			if ( Array.isArray( answer ) ) {
				return answer.length === 1 && String( answer[ 0 ] ) === target;
			}
			if ( empty ) {
				return false;
			}
			if ( ! isNaN( parseFloat( answer ) ) && isFinite( answer ) && ! isNaN( parseFloat( target ) ) && isFinite( target ) ) {
				return parseFloat( answer ) === parseFloat( target );
			}
			return String( answer ).toLowerCase() === target.toLowerCase();
		}
		function contains() {
			if ( Array.isArray( answer ) ) {
				return answer.map( String ).indexOf( target ) !== -1;
			}
			if ( empty || target === '' ) {
				return false;
			}
			return String( answer ).toLowerCase().indexOf( target.toLowerCase() ) !== -1;
		}
		function numeric() {
			return ! Array.isArray( answer ) && ! empty && isFinite( answer ) && isFinite( target ) && target !== '';
		}
		switch ( op ) {
			case 'equals': return equals();
			case 'not_equals': return ! equals();
			case 'contains': return contains();
			case 'not_contains': return ! contains();
			case 'gt': return numeric() && parseFloat( answer ) > parseFloat( target );
			case 'lt': return numeric() && parseFloat( answer ) < parseFloat( target );
		}
		return false;
	}

	function visibleMap( questions, answers ) {
		var visible = {};
		questions.forEach( function ( q ) {
			var logic = q.logic;
			if ( ! logic || ! logic.enabled || ! logic.conditions || ! logic.conditions.length ) {
				visible[ q.id ] = true;
				return;
			}
			var results = logic.conditions.map( function ( c ) {
				var a = visible[ c.question ] ? answers[ c.question ] : null;
				if ( isEmptyAnswer( a ) ) {
					a = null;
				}
				return compare( c.operator, a, c.value );
			} );
			var met = logic.match === 'any' ? results.indexOf( true ) !== -1 : results.indexOf( false ) === -1;
			if ( logic.action === 'hide' ) {
				met = ! met;
			}
			if ( met ) {
				visible[ q.id ] = true;
			}
		} );
		return visible;
	}

	/* ---------- app ---------- */

	function App( root ) {
		var cfgEl = root.querySelector( '.ncs-config' );
		if ( ! cfgEl ) {
			return;
		}
		var cfg = JSON.parse( cfgEl.textContent );
		var app = root.querySelector( '.ncs-app' );
		var startedAt = Date.now();
		var restOpts = cfg.preview ? { restNonce: cfg.restNonce } : {};
		var uidCounter = 0;

		var state = {
			phase: cfg.showIntro ? 'intro' : 'questions',
			answers: {},
			lead: {},
			consent: { privacy: false, marketing: false },
			currentFirst: cfg.questions.length ? cfg.questions[ 0 ].id : null,
			started: false,
			busy: false
		};

		function uid( p ) {
			uidCounter += 1;
			return 'ncs-' + cfg.id + '-' + p + '-' + uidCounter + '-' + Math.random().toString( 36 ).slice( 2, 6 );
		}

		function track( event, meta ) {
			NCS.track( event, cfg.id, meta || '', { item_name: cfg.title }, cfg.preview, false );
		}

		function visible() {
			return visibleMap( cfg.questions, state.answers );
		}

		function steps() {
			var vis = visible();
			var list = cfg.questions.filter( function ( q ) {
				return vis[ q.id ];
			} );
			var result = [];
			list.forEach( function ( q ) {
				var last = result[ result.length - 1 ];
				if ( cfg.layout === 'grouped' && last && last[ 0 ].section === q.section ) {
					last.push( q );
				} else {
					result.push( [ q ] );
				}
			} );
			return result;
		}

		function currentStepIndex( all ) {
			var i;
			var idx;
			var order = cfg.questions.map( function ( q ) {
				return q.id;
			} );
			var pos = order.indexOf( state.currentFirst );
			for ( i = 0; i < all.length; i++ ) {
				idx = order.indexOf( all[ i ][ all[ i ].length - 1 ].id );
				if ( idx >= pos ) {
					return i;
				}
			}
			return Math.max( 0, all.length - 1 );
		}

		function progressPercent( index, total ) {
			var totalSteps = total + ( cfg.lead.enabled ? 1 : 0 );
			return totalSteps ? Math.min( 100, Math.round( ( ( index + 1 ) / totalSteps ) * 100 ) ) : 0;
		}

		function focusTarget( el ) {
			if ( ! el ) {
				return;
			}
			el.setAttribute( 'tabindex', '-1' );
			el.focus( { preventScroll: false } );
		}

		function render( focus ) {
			app.innerHTML = '';
			var view;
			if ( state.phase === 'intro' ) {
				view = renderIntro();
			} else if ( state.phase === 'questions' ) {
				view = renderQuestions();
			} else if ( state.phase === 'lead' ) {
				view = renderLead();
			} else {
				view = renderResult();
			}
			view.classList.add( 'ncs-view' );
			app.appendChild( view );
			if ( focus ) {
				var target = view.querySelector( '[data-ncs-focus]' );
				focusTarget( target || view );
			}
		}

		/* --- intro --- */

		function renderIntro() {
			var wrap = h( 'div', { 'class': 'ncs-panel ncs-intro' } );
			wrap.appendChild( h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: cfg.title } ) );
			if ( cfg.description ) {
				wrap.appendChild( h( 'div', { 'class': 'ncs-rich', html: cfg.description } ) );
			}
			if ( cfg.intro ) {
				wrap.appendChild( h( 'div', { 'class': 'ncs-rich', html: cfg.intro } ) );
			}
			wrap.appendChild( h( 'div', { 'class': 'ncs-actions' },
				h( 'button', {
					type: 'button',
					'class': 'ncs-btn ncs-btn-primary',
					text: cfg.text.start,
					onclick: function () {
						state.phase = 'questions';
						render( true );
					}
				} )
			) );
			return wrap;
		}

		/* --- progress --- */

		function progressBar( percent, label ) {
			if ( ! cfg.progress ) {
				return null;
			}
			return h( 'div', { 'class': 'ncs-progress' },
				h( 'div', {
					'class': 'ncs-progress-track',
					role: 'progressbar',
					'aria-valuemin': '0',
					'aria-valuemax': '100',
					'aria-valuenow': String( percent ),
					'aria-label': label
				}, h( 'div', { 'class': 'ncs-progress-fill', style: 'width:' + percent + '%' } ) ),
				h( 'p', { 'class': 'ncs-progress-text' }, label, cfg.percentage ? ' (' + percent + '%)' : '' )
			);
		}

		/* --- questions --- */

		function renderQuestions() {
			var all = steps();
			if ( ! all.length ) {
				state.phase = cfg.lead.enabled ? 'lead' : 'result';
				return cfg.lead.enabled ? renderLead() : submitNow();
			}
			var index = currentStepIndex( all );
			var step = all[ index ];
			state.currentFirst = step[ 0 ].id;
			var isLast = index === all.length - 1;
			var total = all.length;
			var percent = progressPercent( index, total );
			var label = ( I18N.step || 'Step %1 of %2' ).replace( '%1', String( index + 1 ) ).replace( '%2', String( total + ( cfg.lead.enabled ? 1 : 0 ) ) );

			var form = h( 'form', { 'class': 'ncs-panel ncs-step', novalidate: true, 'aria-label': cfg.title } );
			add( form, progressBar( percent, label ) );
			if ( cfg.layout === 'grouped' && step[ 0 ].section ) {
				form.appendChild( h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: step[ 0 ].section } ) );
			}
			var blocks = {};
			step.forEach( function ( q, qi ) {
				var block = questionBlock( q, cfg.layout !== 'grouped' || ! step[ 0 ].section ? qi === 0 : false );
				blocks[ q.id ] = block;
				form.appendChild( block );
			} );

			var stepError = h( 'p', { 'class': 'ncs-error ncs-step-error', role: 'alert', hidden: true } );
			form.appendChild( stepError );

			var prev = h( 'button', {
				type: 'button',
				'class': 'ncs-btn',
				text: cfg.text.prev,
				disabled: index === 0 && ! cfg.showIntro,
				onclick: function () {
					if ( index === 0 ) {
						state.phase = 'intro';
						render( true );
						return;
					}
					state.currentFirst = all[ index - 1 ][ 0 ].id;
					render( true );
				}
			} );
			if ( index === 0 && ! cfg.showIntro ) {
				prev.hidden = true;
			}
			var next = h( 'button', {
				type: 'submit',
				'class': 'ncs-btn ncs-btn-primary',
				text: isLast && ! cfg.lead.enabled ? cfg.text.submit : cfg.text.next
			} );
			form.appendChild( h( 'div', { 'class': 'ncs-actions' }, prev, next ) );

			// Live logic inside a grouped step.
			form.addEventListener( 'ncs-answer', function () {
				var vis = visible();
				step.forEach( function ( q ) {
					if ( blocks[ q.id ] ) {
						blocks[ q.id ].hidden = ! vis[ q.id ];
					}
				} );
			} );

			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var vis = visible();
				var firstBad = null;
				step.forEach( function ( q ) {
					if ( ! vis[ q.id ] ) {
						return;
					}
					var msg = validateQuestion( q );
					showError( blocks[ q.id ], msg );
					if ( msg && ! firstBad ) {
						firstBad = blocks[ q.id ];
					}
				} );
				if ( firstBad ) {
					stepError.hidden = false;
					stepError.textContent = I18N.fix || 'Please answer the highlighted question.';
					var focusable = firstBad.querySelector( 'input, select, textarea' );
					if ( focusable ) {
						focusable.focus();
					}
					return;
				}
				stepError.hidden = true;
				// Re-evaluate the flow with the final answers of this step.
				var fresh = steps();
				var lastId = step[ step.length - 1 ].id;
				var order = cfg.questions.map( function ( q ) {
					return q.id;
				} );
				var after = null;
				fresh.forEach( function ( s ) {
					if ( ! after && order.indexOf( s[ 0 ].id ) > order.indexOf( lastId ) ) {
						after = s;
					}
				} );
				if ( after ) {
					state.currentFirst = after[ 0 ].id;
					render( true );
				} else if ( cfg.lead.enabled ) {
					state.phase = 'lead';
					render( true );
				} else {
					submitAssessment();
				}
			} );
			return form;
		}

		function showError( block, msg ) {
			var err = block.querySelector( '.ncs-error' );
			if ( ! err ) {
				return;
			}
			err.hidden = ! msg;
			err.textContent = msg || '';
			block.classList.toggle( 'has-error', !! msg );
			Array.prototype.forEach.call( block.querySelectorAll( 'input, select, textarea' ), function ( input ) {
				if ( msg ) {
					input.setAttribute( 'aria-invalid', 'true' );
				} else {
					input.removeAttribute( 'aria-invalid' );
				}
			} );
		}

		function validateQuestion( q ) {
			var v = state.answers[ q.id ];
			if ( q.required && isEmptyAnswer( v ) ) {
				return ( I18N.required || 'This question is required.' );
			}
			if ( q.type === 'number' && ! isEmptyAnswer( v ) ) {
				if ( isNaN( Number( v ) ) ) {
					return 'Please enter a valid number.';
				}
				if ( q.num_min !== '' && q.num_min !== null && Number( v ) < Number( q.num_min ) ) {
					return 'Please enter a number of ' + q.num_min + ' or more.';
				}
				if ( q.num_max !== '' && q.num_max !== null && Number( v ) > Number( q.num_max ) ) {
					return 'Please enter a number of ' + q.num_max + ' or less.';
				}
			}
			return '';
		}

		function answerChanged( q, value ) {
			state.answers[ q.id ] = value;
			if ( ! state.started ) {
				state.started = true;
				track( 'assessment_start' );
			}
			var stepEl = app.querySelector( '.ncs-step' );
			if ( stepEl ) {
				stepEl.dispatchEvent( new Event( 'ncs-answer' ) );
			}
		}

		function questionBlock( q, headingFirst ) {
			var idBase = uid( 'q' );
			var helpId = idBase + '-help';
			var errId = idBase + '-err';
			var describedBy = ( q.help ? helpId + ' ' : '' ) + errId;
			var fs = h( 'fieldset', { 'class': 'ncs-question ncs-question-' + q.type, 'data-qid': q.id } );
			var legend = h( 'legend', { 'class': 'ncs-question-text' }, q.text );
			if ( headingFirst ) {
				legend.setAttribute( 'data-ncs-focus', '' );
			}
			if ( q.required ) {
				legend.appendChild( h( 'span', { 'class': 'ncs-required', text: ' ' + ( I18N.requiredMark || '(required)' ) } ) );
			}
			fs.appendChild( legend );
			if ( q.help ) {
				fs.appendChild( h( 'p', { 'class': 'ncs-help', id: helpId, text: q.help } ) );
			}
			var value = state.answers[ q.id ];
			var control;
			var onChange = function ( v ) {
				answerChanged( q, v );
				showError( fs, '' );
			};

			switch ( q.type ) {
				case 'single':
				case 'yesno':
					control = h( 'div', { 'class': 'ncs-choices' + ( q.type === 'yesno' ? ' ncs-choices-inline' : '' ) } );
					q.options.forEach( function ( o ) {
						var id = uid( 'o' );
						control.appendChild( h( 'div', { 'class': 'ncs-choice' },
							h( 'input', {
								type: 'radio', id: id, name: idBase, value: o.id, checked: value === o.id, 'aria-describedby': describedBy,
								onchange: function ( e ) {
									onChange( e.target.value );
								}
							} ),
							h( 'label', { 'for': id, text: o.label } )
						) );
					} );
					break;
				case 'multiple':
					control = h( 'div', { 'class': 'ncs-choices' } );
					q.options.forEach( function ( o ) {
						var id = uid( 'o' );
						control.appendChild( h( 'div', { 'class': 'ncs-choice' },
							h( 'input', {
								type: 'checkbox', id: id, name: idBase, value: o.id, checked: Array.isArray( value ) && value.indexOf( o.id ) !== -1, 'aria-describedby': describedBy,
								onchange: function () {
									var picked = [];
									Array.prototype.forEach.call( control.querySelectorAll( 'input:checked' ), function ( i ) {
										picked.push( i.value );
									} );
									onChange( picked );
								}
							} ),
							h( 'label', { 'for': id, text: o.label } )
						) );
					} );
					break;
				case 'dropdown':
					var sid = uid( 's' );
					control = h( 'select', {
						id: sid, 'class': 'ncs-select', 'aria-describedby': describedBy,
						onchange: function ( e ) {
							onChange( e.target.value );
						}
					}, h( 'option', { value: '', text: I18N.choose || 'Choose an option' } ) );
					q.options.forEach( function ( o ) {
						control.appendChild( h( 'option', { value: o.id, text: o.label, selected: value === o.id } ) );
					} );
					break;
				case 'rating':
					control = h( 'div', { 'class': 'ncs-rating-wrap' } );
					var row = h( 'div', { 'class': 'ncs-rating' } );
					var n;
					for ( n = Number( q.scale_min ); n <= Number( q.scale_max ); n++ ) {
						( function ( num ) {
							var id = uid( 'r' );
							row.appendChild( h( 'div', { 'class': 'ncs-rating-item' },
								h( 'input', {
									type: 'radio', id: id, name: idBase, value: String( num ), checked: String( value ) === String( num ), 'aria-describedby': describedBy,
									onchange: function ( e ) {
										onChange( e.target.value );
									}
								} ),
								h( 'label', { 'for': id, text: String( num ) } )
							) );
						}( n ) );
					}
					control.appendChild( row );
					if ( q.min_label || q.max_label ) {
						control.appendChild( h( 'div', { 'class': 'ncs-rating-labels' },
							h( 'span', { text: q.min_label } ),
							h( 'span', { text: q.max_label } )
						) );
					}
					break;
				case 'number':
					var nid = uid( 'n' );
					control = h( 'input', {
						type: 'number', id: nid, 'class': 'ncs-input', value: value || '', placeholder: q.placeholder, step: 'any', 'aria-describedby': describedBy,
						min: q.num_min !== '' ? q.num_min : null, max: q.num_max !== '' ? q.num_max : null,
						oninput: function ( e ) {
							onChange( e.target.value );
						}
					} );
					break;
				case 'long_text':
					control = h( 'textarea', {
						id: uid( 't' ), 'class': 'ncs-input', rows: 5, placeholder: q.placeholder, maxlength: '5000', 'aria-describedby': describedBy,
						oninput: function ( e ) {
							onChange( e.target.value );
						}
					} );
					control.value = value || '';
					break;
				default:
					control = h( 'input', {
						type: 'text', id: uid( 't' ), 'class': 'ncs-input', value: value || '', placeholder: q.placeholder, maxlength: '255', 'aria-describedby': describedBy,
						oninput: function ( e ) {
							onChange( e.target.value );
						}
					} );
			}
			fs.appendChild( control );
			fs.appendChild( h( 'p', { 'class': 'ncs-error', id: errId, role: 'alert', hidden: true } ) );
			return fs;
		}

		/* --- lead --- */

		function renderLead() {
			var form = h( 'form', { 'class': 'ncs-panel ncs-lead', novalidate: true, 'aria-label': cfg.lead.heading || cfg.title } );
			var stepsCount = steps().length + 1;
			add( form, progressBar( progressPercent( stepsCount - 1, stepsCount - 1 ), ( I18N.step || 'Step %1 of %2' ).replace( '%1', String( stepsCount ) ).replace( '%2', String( stepsCount ) ) ) );
			form.appendChild( h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: cfg.lead.heading || cfg.title } ) );
			if ( cfg.lead.intro ) {
				form.appendChild( h( 'p', { 'class': 'ncs-lead-intro', text: cfg.lead.intro } ) );
			}
			var errors = h( 'p', { 'class': 'ncs-error ncs-step-error', role: 'alert', hidden: true } );
			var fieldEls = {};
			cfg.lead.fields.forEach( function ( f ) {
				var id = uid( 'f' );
				var errId = id + '-err';
				var input;
				var common = {
					id: id, name: f.key, 'aria-describedby': errId, placeholder: f.placeholder || null, 'class': 'ncs-input',
					autocomplete: autocompleteFor( f.key )
				};
				if ( f.type === 'textarea' ) {
					input = h( 'textarea', common );
					input.rows = 4;
				} else if ( f.type === 'select' ) {
					input = h( 'select', { id: id, name: f.key, 'aria-describedby': errId, 'class': 'ncs-select' }, h( 'option', { value: '', text: I18N.choose || 'Choose an option' } ) );
					( f.options || [] ).forEach( function ( o ) {
						input.appendChild( h( 'option', { value: o, text: o } ) );
					} );
				} else {
					common.type = f.type === 'tel' ? 'tel' : ( f.type === 'email' ? 'email' : 'text' );
					input = h( 'input', common );
				}
				input.value = state.lead[ f.key ] || '';
				if ( f.required ) {
					input.setAttribute( 'aria-required', 'true' );
				}
				input.addEventListener( 'input', function () {
					state.lead[ f.key ] = input.value;
				} );
				input.addEventListener( 'change', function () {
					state.lead[ f.key ] = input.value;
				} );
				var err = h( 'p', { 'class': 'ncs-error', id: errId, role: 'alert', hidden: true } );
				fieldEls[ f.key ] = { input: input, err: err };
				form.appendChild( h( 'div', { 'class': 'ncs-field' },
					h( 'label', { 'for': id }, f.label, f.required ? h( 'span', { 'class': 'ncs-required', text: ' ' + ( I18N.requiredMark || '(required)' ) } ) : null ),
					input,
					err
				) );
			} );

			var consentEls = {};
			[ 'privacy', 'marketing' ].forEach( function ( type ) {
				var c = cfg.lead[ type ];
				if ( ! c || ! c.enabled ) {
					return;
				}
				var id = uid( 'c' );
				var errId = id + '-err';
				var box = h( 'input', {
					type: 'checkbox', id: id, 'aria-describedby': errId, checked: !! state.consent[ type ],
					onchange: function ( e ) {
						state.consent[ type ] = e.target.checked;
					}
				} );
				var label = h( 'label', { 'for': id, html: c.text } );
				if ( c.required ) {
					label.appendChild( h( 'span', { 'class': 'ncs-required', text: ' ' + ( I18N.requiredMark || '(required)' ) } ) );
				}
				// Links inside consent text must not toggle the checkbox when clicked.
				Array.prototype.forEach.call( label.querySelectorAll( 'a' ), function ( a ) {
					a.setAttribute( 'target', '_blank' );
					a.setAttribute( 'rel', 'noopener noreferrer' );
				} );
				var err = h( 'p', { 'class': 'ncs-error', id: errId, role: 'alert', hidden: true } );
				consentEls[ type ] = { box: box, err: err };
				form.appendChild( h( 'div', { 'class': 'ncs-field ncs-consent' }, box, label, err ) );
			} );
			if ( cfg.lead.privacyUrl ) {
				form.appendChild( h( 'p', { 'class': 'ncs-privacy-link' },
					h( 'a', { href: cfg.lead.privacyUrl, target: '_blank', rel: 'noopener noreferrer', text: I18N.privacy || 'Read our privacy policy' } )
				) );
			}

			// Honeypot: real visitors never see or fill this field.
			var hp = h( 'input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true' } );
			form.appendChild( h( 'div', { 'class': 'ncs-hp', 'aria-hidden': 'true' }, hp ) );

			form.appendChild( errors );
			var submit = h( 'button', { type: 'submit', 'class': 'ncs-btn ncs-btn-primary', text: cfg.text.leadSubmit } );
			var back = h( 'button', {
				type: 'button', 'class': 'ncs-btn', text: cfg.text.prev,
				onclick: function () {
					state.phase = 'questions';
					var all = steps();
					state.currentFirst = all.length ? all[ all.length - 1 ][ 0 ].id : state.currentFirst;
					render( true );
				}
			} );
			form.appendChild( h( 'div', { 'class': 'ncs-actions' }, back, submit ) );

			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				if ( state.busy ) {
					return;
				}
				var bad = null;
				Object.keys( fieldEls ).forEach( function ( key ) {
					var f = cfg.lead.fields.filter( function ( x ) {
						return x.key === key;
					} )[ 0 ];
					var val = ( state.lead[ key ] || '' ).trim();
					var msg = '';
					if ( f.required && val === '' ) {
						msg = f.label + ' is required.';
					} else if ( f.type === 'email' && val !== '' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( val ) ) {
						msg = 'Please enter a valid email address.';
					}
					setFieldError( fieldEls[ key ], msg );
					if ( msg && ! bad ) {
						bad = fieldEls[ key ].input;
					}
				} );
				Object.keys( consentEls ).forEach( function ( type ) {
					var msg = cfg.lead[ type ].required && ! state.consent[ type ] ? ( I18N.tick || 'Please tick this box to continue.' ) : '';
					setFieldError( { input: consentEls[ type ].box, err: consentEls[ type ].err }, msg );
					if ( msg && ! bad ) {
						bad = consentEls[ type ].box;
					}
				} );
				if ( bad ) {
					errors.hidden = false;
					errors.textContent = I18N.fix2 || 'Please fix the highlighted fields.';
					bad.focus();
					return;
				}
				errors.hidden = true;
				submitAssessment( { hp: hp.value, fieldEls: fieldEls, consentEls: consentEls, errors: errors, submit: submit } );
			} );
			return form;
		}

		function autocompleteFor( key ) {
			var map = {
				first_name: 'given-name', last_name: 'family-name', full_name: 'name', email: 'email', phone: 'tel', company: 'organization', job_title: 'organization-title'
			};
			return map[ key ] || 'off';
		}

		function setFieldError( refs, msg ) {
			refs.err.hidden = ! msg;
			refs.err.textContent = msg || '';
			if ( msg ) {
				refs.input.setAttribute( 'aria-invalid', 'true' );
			} else {
				refs.input.removeAttribute( 'aria-invalid' );
			}
		}

		/* --- submit --- */

		function collectAnswers() {
			var vis = visible();
			var out = {};
			cfg.questions.forEach( function ( q ) {
				if ( vis[ q.id ] && ! isEmptyAnswer( state.answers[ q.id ] ) ) {
					out[ q.id ] = state.answers[ q.id ];
				}
			} );
			return out;
		}

		function submitNow() {
			submitAssessment();
			return h( 'div', { 'class': 'ncs-panel' }, h( 'p', { 'class': 'ncs-loading', text: I18N.working || 'Working...' } ) );
		}

		function submitAssessment( ctx ) {
			if ( state.busy ) {
				return;
			}
			state.busy = true;
			if ( ctx && ctx.submit ) {
				ctx.submit.disabled = true;
				ctx.submit.textContent = I18N.working || 'Working...';
			}
			var body = {
				id: cfg.id,
				answers: collectAnswers(),
				lead: state.lead,
				consent: state.consent,
				website: ctx ? ctx.hp : '',
				elapsed: Date.now() - startedAt,
				page_url: window.location.href.split( '#' )[ 0 ]
			};
			if ( cfg.preview ) {
				body.preview = true;
				body.preview_nonce = cfg.previewNonce;
			}
			NCS.api( 'POST', 'assessment/submit', body, restOpts ).then( function ( res ) {
				state.busy = false;
				var data = res.data;
				if ( data && data.ok ) {
					state.result = data.result;
					state.phase = 'result';
					NCS.ga( 'assessment_complete', { item_id: cfg.id, item_name: cfg.title } );
					if ( state.lead.email ) {
						NCS.ga( 'lead_captured', { item_id: cfg.id, item_name: cfg.title } );
					}
					render( true );
					track( 'result_view' );
					return;
				}
				handleError( res, ctx );
			} ).catch( function () {
				state.busy = false;
				handleError( { status: 0, data: {} }, ctx );
			} );
		}

		function handleError( res, ctx ) {
			var data = res.data || {};
			var message = data.message || ( I18N.network || 'Something went wrong. Please check your connection and try again.' );
			if ( data.code === 'validation' && data.errors ) {
				if ( data.errors.answers ) {
					// Take the visitor back to the first problem question.
					var firstId = Object.keys( data.errors.answers )[ 0 ];
					state.phase = 'questions';
					state.currentFirst = firstId;
					render( true );
					var block = app.querySelector( '[data-qid="' + firstId + '"]' );
					if ( block ) {
						showError( block, data.errors.answers[ firstId ] );
					}
					return;
				}
				if ( ctx && data.errors.lead ) {
					Object.keys( data.errors.lead ).forEach( function ( key ) {
						if ( ctx.fieldEls[ key ] ) {
							setFieldError( ctx.fieldEls[ key ], data.errors.lead[ key ] );
						}
					} );
				}
				if ( ctx && data.errors.consent ) {
					Object.keys( data.errors.consent ).forEach( function ( type ) {
						if ( ctx.consentEls[ type ] ) {
							setFieldError( { input: ctx.consentEls[ type ].box, err: ctx.consentEls[ type ].err }, data.errors.consent[ type ] );
						}
					} );
				}
			}
			if ( ctx && ctx.errors ) {
				ctx.errors.hidden = false;
				ctx.errors.textContent = message;
				ctx.submit.disabled = false;
				ctx.submit.textContent = cfg.text.leadSubmit;
			} else {
				// Failure without a lead form: show a retry panel.
				app.innerHTML = '';
				var panel = h( 'div', { 'class': 'ncs-panel ncs-view' },
					h( 'p', { 'class': 'ncs-error', role: 'alert', text: message } ),
					h( 'div', { 'class': 'ncs-actions' }, h( 'button', {
						type: 'button', 'class': 'ncs-btn ncs-btn-primary', text: I18N.retry || 'Try again',
						onclick: function () {
							submitAssessment();
						}
					} ) )
				);
				app.appendChild( panel );
			}
		}

		/* --- result --- */

		function renderResult() {
			var r = state.result || {};
			var wrap = h( 'div', { 'class': 'ncs-panel ncs-result' } );
			wrap.appendChild( h( 'p', { 'class': 'ncs-eyebrow', text: r.heading || '' } ) );
			wrap.appendChild( h( 'h2', { 'class': 'ncs-heading', 'data-ncs-focus': '', text: r.title || r.assessment || cfg.title } ) );
			var stats = [];
			if ( r.score ) {
				stats.push( h( 'div', { 'class': 'ncs-stat' }, h( 'span', { 'class': 'ncs-stat-value', text: fmt( r.score.raw ) + ' / ' + fmt( r.score.max ) } ), h( 'span', { 'class': 'ncs-stat-label', text: I18N.score || 'Score' } ) ) );
			}
			if ( r.percentage !== null && r.percentage !== undefined ) {
				stats.push( h( 'div', { 'class': 'ncs-stat' }, h( 'span', { 'class': 'ncs-stat-value', text: r.percentage + '%' } ), h( 'span', { 'class': 'ncs-stat-label', text: I18N.percent || 'Percentage' } ) ) );
			}
			if ( stats.length ) {
				wrap.appendChild( h( 'div', { 'class': 'ncs-stats' }, stats ) );
			}
			if ( r.description ) {
				wrap.appendChild( h( 'div', { 'class': 'ncs-rich', html: r.description } ) );
			}
			if ( r.recommendation ) {
				wrap.appendChild( h( 'div', { 'class': 'ncs-recommendation' },
					h( 'h3', { 'class': 'ncs-subheading', text: I18N.rec || 'Our recommendation' } ),
					h( 'div', { 'class': 'ncs-rich', html: r.recommendation } )
				) );
			}
			if ( r.programs && r.programs.length ) {
				var list = h( 'ul', { 'class': 'ncs-programs' } );
				r.programs.forEach( function ( p ) {
					var li = h( 'li', { 'class': 'ncs-program' }, h( 'h4', { 'class': 'ncs-program-name', text: p.name } ) );
					if ( p.description ) {
						li.appendChild( h( 'div', { 'class': 'ncs-rich', html: p.description } ) );
					}
					if ( p.url ) {
						li.appendChild( h( 'a', { 'class': 'ncs-btn', href: p.url, text: p.cta_label } ) );
					}
					list.appendChild( li );
				} );
				wrap.appendChild( h( 'div', { 'class': 'ncs-programs-wrap' }, h( 'h3', { 'class': 'ncs-subheading', text: I18N.programs || 'Recommended programs' } ), list ) );
			}
			if ( r.cta || r.cta2 ) {
				var actions = h( 'div', { 'class': 'ncs-actions' } );
				if ( r.cta ) {
					actions.appendChild( h( 'a', { 'class': 'ncs-btn ncs-btn-primary', href: r.cta.url, text: r.cta.label } ) );
				}
				if ( r.cta2 ) {
					actions.appendChild( h( 'a', { 'class': 'ncs-btn', href: r.cta2.url, text: r.cta2.label } ) );
				}
				wrap.appendChild( actions );
			}
			if ( r.completion && ! r.title ) {
				wrap.appendChild( h( 'div', { 'class': 'ncs-rich', html: r.completion } ) );
			}
			return wrap;
		}

		function fmt( n ) {
			return String( Math.round( Number( n ) * 100 ) / 100 );
		}

		/* --- start --- */

		render( false );
		track( 'assessment_view' );
	}

	function init() {
		var roots = document.querySelectorAll( '[data-ncs-assessment]' );
		Array.prototype.forEach.call( roots, function ( root ) {
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
