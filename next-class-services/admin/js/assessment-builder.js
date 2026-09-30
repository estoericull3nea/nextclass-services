/**
 * Next Class Services: assessment builder.
 * Questions, conditional logic, programs, result categories and lead fields.
 * State is serialized into hidden inputs and sanitized again on the server.
 */
( function () {
	'use strict';

	var dataEl = document.getElementById( 'ncs-builder-data' );
	var form = document.getElementById( 'ncs-assessment-form' );
	if ( ! dataEl || ! form ) {
		return;
	}
	var data = JSON.parse( dataEl.textContent );
	var t = data.i18n;

	var state = {
		questions: data.questions || [],
		results: data.results || [],
		programs: data.programs || [],
		leadFields: data.leadFields || []
	};
	var open = { question: null, result: null, program: null, field: null };

	var out = {
		questions: document.getElementById( 'ncs-questions-json' ),
		results: document.getElementById( 'ncs-results-json' ),
		programs: document.getElementById( 'ncs-programs-json' ),
		leadFields: document.getElementById( 'ncs-leadfields-json' )
	};

	/* ---------- helpers ---------- */

	function uid( prefix ) {
		return prefix + Math.random().toString( 36 ).slice( 2, 10 );
	}

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
			append( el, arguments[ i ] );
		}
		return el;
	}

	function append( el, child ) {
		if ( child === null || child === undefined || child === false ) {
			return;
		}
		if ( Array.isArray( child ) ) {
			child.forEach( function ( c ) {
				append( el, c );
			} );
		} else if ( typeof child === 'string' || typeof child === 'number' ) {
			el.appendChild( document.createTextNode( String( child ) ) );
		} else {
			el.appendChild( child );
		}
	}

	function sync() {
		out.questions.value = JSON.stringify( state.questions );
		out.results.value = JSON.stringify( state.results );
		out.programs.value = JSON.stringify( state.programs );
		out.leadFields.value = JSON.stringify( state.leadFields );
		updateSummary();
		renderRangeCheck();
	}

	function changed() {
		sync();
		form.dispatchEvent( new Event( 'ncs:change' ) );
	}

	var fieldCounter = 0;
	var examples = {};
	examples[ t.text ] = 'e.g. How often does your team hold check-ins?';
	examples[ t.help ] = 'e.g. Think about the last three months.';
	examples[ t.section ] = 'e.g. Communication';
	examples[ t.minLabel ] = 'e.g. Never';
	examples[ t.maxLabel ] = 'e.g. Always';
	examples[ t.numMin ] = 'e.g. 0';
	examples[ t.numMax ] = 'e.g. 100';
	examples[ t.value ] = 'e.g. Yes';
	examples[ t.resultTitle ] = 'e.g. Strong Foundation';
	examples[ t.minScore ] = 'e.g. 0';
	examples[ t.maxScoreLabel ] = 'e.g. 25';
	examples[ t.resultDesc ] = 'e.g. Your team communicates well and has solid habits.';
	examples[ t.resultRec ] = 'e.g. Keep building on your strengths with regular coaching.';
	examples[ t.ctaLabel ] = 'e.g. Book a consultation';
	examples[ t.ctaUrl ] = 'https://yourdomain.com/book/';
	examples[ t.cta2Label ] = 'e.g. Learn more';
	examples[ t.cta2Url ] = 'https://yourdomain.com/programs/';
	examples[ t.programName ] = 'e.g. Leadership Essentials';
	examples[ t.programDesc ] = 'e.g. A 6 week program for new managers.';
	examples[ t.programUrl ] = 'https://yourdomain.com/programs/leadership/';
	examples[ t.programCta ] = 'e.g. View program';
	examples[ t.label ] = 'e.g. Work email';
	examples[ t.fieldPlaceholder ] = 'e.g. name@company.com';
	examples[ t.fieldOptions ] = 'e.g. Option one\nOption two';

	function labelled( label, control, help ) {
		var id = 'ncs-b-' + ( ++fieldCounter );
		control.id = id;
		if ( examples[ label ] && ! control.getAttribute( 'placeholder' ) && control.tagName !== 'SELECT' ) {
			control.setAttribute( 'placeholder', examples[ label ] );
		}
		return h( 'div', { 'class': 'ncs-field' },
			h( 'label', { 'for': id, text: label } ),
			control,
			help ? h( 'p', { 'class': 'ncs-help', text: help } ) : null
		);
	}

	function textInput( value, onInput, attrs ) {
		var a = attrs || {};
		a.type = a.type || 'text';
		a.value = value === undefined || value === null ? '' : value;
		a.oninput = function ( e ) {
			onInput( e.target.value );
		};
		return h( 'input', a );
	}

	function numberInput( value, onInput, attrs ) {
		var a = attrs || {};
		a.type = 'number';
		a.step = a.step || 'any';
		a.value = value === undefined || value === null ? '' : value;
		a.oninput = function ( e ) {
			onInput( e.target.value === '' ? '' : Number( e.target.value ) );
		};
		return h( 'input', a );
	}

	function textArea( value, onInput, rows ) {
		return h( 'textarea', {
			rows: rows || 3,
			value: value || '',
			oninput: function ( e ) {
				onInput( e.target.value );
			}
		} );
	}

	function checkbox( label, checked, onChange ) {
		var id = 'ncs-b-' + ( ++fieldCounter );
		return h( 'div', { 'class': 'ncs-field ncs-field-check' },
			h( 'label', { 'for': id },
				h( 'input', {
					type: 'checkbox',
					id: id,
					checked: checked,
					onchange: function ( e ) {
						onChange( e.target.checked );
					}
				} ),
				' ' + label
			)
		);
	}

	function select( options, value, onChange ) {
		var sel = h( 'select', {
			onchange: function ( e ) {
				onChange( e.target.value );
			}
		} );
		options.forEach( function ( o ) {
			sel.appendChild( h( 'option', { value: o[ 0 ], text: o[ 1 ], selected: String( o[ 0 ] ) === String( value ) } ) );
		} );
		return sel;
	}

	function btn( label, onClick, cls, extra ) {
		var a = extra || {};
		a.type = 'button';
		a[ 'class' ] = 'ncs-btn ncs-btn-small' + ( cls ? ' ' + cls : '' );
		a.text = label;
		a.onclick = onClick;
		return h( 'button', a );
	}

	function move( list, from, to ) {
		if ( to < 0 || to >= list.length || from === to ) {
			return;
		}
		var item = list.splice( from, 1 )[ 0 ];
		list.splice( to, 0, item );
	}

	/**
	 * Drag and drop reordering (mouse). Keyboard users use the move buttons.
	 */
	function makeSortable( container, list, onDone ) {
		var dragIndex = null;
		Array.prototype.forEach.call( container.children, function ( item, index ) {
			var handle = item.querySelector( '.ncs-drag' );
			if ( ! handle ) {
				return;
			}
			handle.addEventListener( 'mousedown', function () {
				item.setAttribute( 'draggable', 'true' );
			} );
			handle.addEventListener( 'mouseup', function () {
				item.removeAttribute( 'draggable' );
			} );
			item.addEventListener( 'dragstart', function ( e ) {
				dragIndex = index;
				item.classList.add( 'is-dragging' );
				if ( e.dataTransfer ) {
					e.dataTransfer.effectAllowed = 'move';
					e.dataTransfer.setData( 'text/plain', String( index ) );
				}
			} );
			item.addEventListener( 'dragend', function () {
				item.classList.remove( 'is-dragging' );
				item.removeAttribute( 'draggable' );
				Array.prototype.forEach.call( container.children, function ( c ) {
					c.classList.remove( 'is-over' );
				} );
			} );
			item.addEventListener( 'dragover', function ( e ) {
				if ( dragIndex === null ) {
					return;
				}
				e.preventDefault();
				item.classList.add( 'is-over' );
			} );
			item.addEventListener( 'dragleave', function () {
				item.classList.remove( 'is-over' );
			} );
			item.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				if ( dragIndex === null ) {
					return;
				}
				move( list, dragIndex, index );
				dragIndex = null;
				onDone();
			} );
		} );
	}

	function itemHeader( opts ) {
		var actions = h( 'div', { 'class': 'ncs-item-actions' } );
		actions.appendChild( btn( opts.isOpen ? t.close : t.edit, opts.onToggle, '', { 'aria-expanded': opts.isOpen ? 'true' : 'false' } ) );
		actions.appendChild( btn( t.moveUp, opts.onUp, '', { disabled: opts.first } ) );
		actions.appendChild( btn( t.moveDown, opts.onDown, '', { disabled: opts.last } ) );
		if ( opts.onDuplicate ) {
			actions.appendChild( btn( t.duplicate, opts.onDuplicate ) );
		}
		actions.appendChild( btn( t.delete, opts.onDelete, 'ncs-btn-danger' ) );
		return h( 'div', { 'class': 'ncs-item-head' },
			h( 'span', { 'class': 'ncs-drag', title: t.dragHandle, 'aria-hidden': 'true', text: '::' } ),
			h( 'div', { 'class': 'ncs-item-summary' }, opts.summary ),
			actions
		);
	}

	/* ---------- scoring helpers (mirror PHP) ---------- */

	function isChoice( type ) {
		return [ 'single', 'multiple', 'dropdown', 'yesno' ].indexOf( type ) !== -1;
	}

	function isScorable( type ) {
		return isChoice( type ) || type === 'rating';
	}

	function isScored( q ) {
		if ( isChoice( q.type ) ) {
			return ( q.options || [] ).some( function ( o ) {
				return Number( o.score ) !== 0 && ! isNaN( Number( o.score ) );
			} );
		}
		return q.type === 'rating' && !! q.scored;
	}

	function questionMax( q ) {
		if ( ! q.enabled || ! isScored( q ) ) {
			return 0;
		}
		var w = q.weight === undefined || q.weight === '' ? 1 : Number( q.weight );
		var max = 0;
		if ( q.type === 'rating' ) {
			max = Number( q.scale_max ) || 0;
		} else if ( q.type === 'multiple' ) {
			( q.options || [] ).forEach( function ( o ) {
				if ( Number( o.score ) > 0 ) {
					max += Number( o.score );
				}
			} );
		} else {
			( q.options || [] ).forEach( function ( o ) {
				max = Math.max( max, Number( o.score ) || 0 );
			} );
		}
		return max * w;
	}

	function totalMax() {
		var sum = 0;
		state.questions.forEach( function ( q ) {
			sum += questionMax( q );
		} );
		return Math.round( sum * 100 ) / 100;
	}

	function updateSummary() {
		var el = document.getElementById( 'ncs-score-summary' );
		if ( el ) {
			el.textContent = t.scoreSummary.replace( '%s', String( totalMax() ) );
		}
	}

	/* ---------- questions ---------- */

	var qRoot = document.getElementById( 'ncs-questions-root' );

	function newQuestion() {
		return {
			id: uid( 'q' ),
			type: 'single',
			text: '',
			help: '',
			section: '',
			required: true,
			enabled: true,
			placeholder: '',
			options: [
				{ id: uid( 'o' ), label: 'Option 1', score: 0 },
				{ id: uid( 'o' ), label: 'Option 2', score: 0 }
			],
			scored: false,
			weight: 1,
			scale_min: 1,
			scale_max: 5,
			min_label: '',
			max_label: '',
			reverse: false,
			num_min: '',
			num_max: '',
			logic: { enabled: false, action: 'show', match: 'all', conditions: [] }
		};
	}

	function applyType( q, type ) {
		q.type = type;
		if ( type === 'yesno' ) {
			var yes = 0, no = 0;
			( q.options || [] ).forEach( function ( o ) {
				if ( o.id === 'yes' ) { yes = o.score; }
				if ( o.id === 'no' ) { no = o.score; }
			} );
			q.options = [ { id: 'yes', label: t.yes, score: yes }, { id: 'no', label: t.no, score: no } ];
		} else if ( isChoice( type ) ) {
			q.options = ( q.options || [] ).filter( function ( o ) {
				return o.id !== 'yes' && o.id !== 'no';
			} );
			while ( q.options.length < 2 ) {
				q.options.push( { id: uid( 'o' ), label: 'Option ' + ( q.options.length + 1 ), score: 0 } );
			}
		}
	}

	function cleanLogic() {
		var seen = {};
		state.questions.forEach( function ( q ) {
			q.logic = q.logic || { enabled: false, action: 'show', match: 'all', conditions: [] };
			q.logic.conditions = ( q.logic.conditions || [] ).filter( function ( c ) {
				return seen[ c.question ];
			} );
			if ( ! q.logic.conditions.length ) {
				q.logic.enabled = false;
			}
			seen[ q.id ] = true;
		} );
	}

	function questionTitle( q, i ) {
		return q.text ? q.text : t.untitled + ' (' + ( i + 1 ) + ')';
	}

	function renderQuestions() {
		qRoot.innerHTML = '';
		cleanLogic();
		if ( ! state.questions.length ) {
			qRoot.appendChild( h( 'p', { 'class': 'ncs-empty-inline', text: t.noQuestions } ) );
		}
		var list = h( 'div', { 'class': 'ncs-items' } );
		state.questions.forEach( function ( q, i ) {
			list.appendChild( questionItem( q, i ) );
		} );
		qRoot.appendChild( list );
		makeSortable( list, state.questions, function () {
			changed();
			renderQuestions();
		} );
		qRoot.appendChild( h( 'p', null, btn( t.addQuestion, function () {
			var q = newQuestion();
			state.questions.push( q );
			open.question = q.id;
			changed();
			renderQuestions();
		}, 'ncs-btn-primary' ) ) );
		sync();
	}

	function questionItem( q, i ) {
		var isOpen = open.question === q.id;
		var titleEl = h( 'strong', { 'class': 'ncs-item-title', text: questionTitle( q, i ) } );
		var summary = [
			h( 'span', { 'class': 'ncs-item-index', text: String( i + 1 ) } ),
			titleEl,
			h( 'span', { 'class': 'ncs-item-meta' },
				data.types[ q.type ],
				' | ' + ( q.required ? t.required : t.optional ),
				! q.enabled ? ' | ' + t.disabled : '',
				isScored( q ) ? ' | ' + t.badgeScored : '',
				q.logic && q.logic.enabled ? ' | ' + t.logicBadge : ''
			)
		];
		var item = h( 'div', { 'class': 'ncs-item' + ( q.enabled ? '' : ' is-disabled' ), 'data-id': q.id } );
		item.appendChild( itemHeader( {
			isOpen: isOpen,
			first: i === 0,
			last: i === state.questions.length - 1,
			summary: summary,
			onToggle: function () {
				open.question = isOpen ? null : q.id;
				renderQuestions();
			},
			onUp: function () {
				move( state.questions, i, i - 1 );
				changed();
				renderQuestions();
			},
			onDown: function () {
				move( state.questions, i, i + 1 );
				changed();
				renderQuestions();
			},
			onDuplicate: function () {
				var copy = JSON.parse( JSON.stringify( q ) );
				copy.id = uid( 'q' );
				if ( copy.type !== 'yesno' ) {
					copy.options.forEach( function ( o ) {
						o.id = uid( 'o' );
					} );
				}
				state.questions.splice( i + 1, 0, copy );
				open.question = copy.id;
				changed();
				renderQuestions();
			},
			onDelete: function () {
				if ( ! window.confirm( t.confirmDelete ) ) {
					return;
				}
				var removed = state.questions.splice( i, 1 )[ 0 ];
				state.questions.forEach( function ( other ) {
					other.logic.conditions = ( other.logic.conditions || [] ).filter( function ( c ) {
						return c.question !== removed.id;
					} );
				} );
				changed();
				renderQuestions();
			}
		} ) );

		if ( isOpen ) {
			item.appendChild( questionBody( q, i, titleEl ) );
		}
		return item;
	}

	function questionBody( q, index, titleEl ) {
		var body = h( 'div', { 'class': 'ncs-item-body' } );
		var types = Object.keys( data.types ).map( function ( k ) {
			return [ k, data.types[ k ] ];
		} );

		body.appendChild( h( 'div', { 'class': 'ncs-cols' },
			labelled( t.type, select( types, q.type, function ( v ) {
				applyType( q, v );
				changed();
				renderQuestions();
			} ) ),
			labelled( t.text, textInput( q.text, function ( v ) {
				q.text = v;
				titleEl.textContent = questionTitle( q, index );
				changed();
			} ) )
		) );
		body.appendChild( h( 'div', { 'class': 'ncs-cols' },
			labelled( t.help, textInput( q.help, function ( v ) {
				q.help = v;
				changed();
			} ) ),
			labelled( t.section, textInput( q.section, function ( v ) {
				q.section = v;
				changed();
			} ) )
		) );
		body.appendChild( h( 'div', { 'class': 'ncs-inline-checks' },
			checkbox( t.required, q.required, function ( v ) {
				q.required = v;
				changed();
			} ),
			checkbox( t.enabled, q.enabled, function ( v ) {
				q.enabled = v;
				changed();
				renderQuestions();
			} )
		) );

		if ( isChoice( q.type ) ) {
			body.appendChild( optionsEditor( q ) );
		}
		if ( q.type === 'rating' ) {
			body.appendChild( h( 'div', { 'class': 'ncs-cols' },
				labelled( t.scaleMin, numberInput( q.scale_min, function ( v ) {
					q.scale_min = v === '' ? 1 : v;
					changed();
				}, { min: 0, max: 19, step: 1 } ) ),
				labelled( t.scaleMax, numberInput( q.scale_max, function ( v ) {
					q.scale_max = v === '' ? 5 : v;
					changed();
				}, { min: 1, max: 20, step: 1 } ) )
			) );
			body.appendChild( h( 'div', { 'class': 'ncs-cols' },
				labelled( t.minLabel, textInput( q.min_label, function ( v ) {
					q.min_label = v;
					changed();
				} ) ),
				labelled( t.maxLabel, textInput( q.max_label, function ( v ) {
					q.max_label = v;
					changed();
				} ) )
			) );
			body.appendChild( h( 'div', { 'class': 'ncs-inline-checks' },
				checkbox( t.scored, q.scored, function ( v ) {
					q.scored = v;
					changed();
					renderQuestions();
				} ),
				checkbox( t.reverse, q.reverse, function ( v ) {
					q.reverse = v;
					changed();
				} )
			) );
		}
		if ( q.type === 'number' ) {
			body.appendChild( h( 'div', { 'class': 'ncs-cols' },
				labelled( t.numMin, numberInput( q.num_min, function ( v ) {
					q.num_min = v;
					changed();
				} ) ),
				labelled( t.numMax, numberInput( q.num_max, function ( v ) {
					q.num_max = v;
					changed();
				} ) )
			) );
		}
		if ( q.type === 'number' || q.type === 'short_text' || q.type === 'long_text' ) {
			body.appendChild( labelled( t.placeholder, textInput( q.placeholder, function ( v ) {
				q.placeholder = v;
				changed();
			} ) ) );
		}
		if ( isScorable( q.type ) ) {
			body.appendChild( labelled( t.weight, numberInput( q.weight, function ( v ) {
				q.weight = v === '' ? 1 : v;
				changed();
			}, { min: 0, max: 100, step: 'any' } ) ) );
		}

		body.appendChild( logicEditor( q, index ) );
		return body;
	}

	function optionsEditor( q ) {
		var wrap = h( 'div', { 'class': 'ncs-subblock' }, h( 'h3', { 'class': 'ncs-subtitle', text: t.options } ) );
		var fixed = q.type === 'yesno';
		var list = h( 'div', { 'class': 'ncs-option-list' } );
		q.options.forEach( function ( o, oi ) {
			var row = h( 'div', { 'class': 'ncs-option-row' } );
			if ( fixed ) {
				row.appendChild( h( 'span', { 'class': 'ncs-option-fixed', text: o.label } ) );
			} else {
				row.appendChild( h( 'div', { 'class': 'ncs-field' },
					h( 'label', { 'class': 'screen-reader-text', text: t.optionLabel } ),
					textInput( o.label, function ( v ) {
						o.label = v;
						changed();
					}, { placeholder: t.optionLabel, 'aria-label': t.optionLabel } )
				) );
			}
			row.appendChild( h( 'div', { 'class': 'ncs-field ncs-field-narrow' },
				h( 'label', { 'class': 'screen-reader-text', text: t.score } ),
				numberInput( o.score, function ( v ) {
					o.score = v === '' ? 0 : v;
					changed();
					updateSummary();
				}, { placeholder: t.score, 'aria-label': t.score + ': ' + o.label } )
			) );
			if ( ! fixed ) {
				row.appendChild( btn( t.moveUp, function () {
					move( q.options, oi, oi - 1 );
					changed();
					renderQuestions();
				}, '', { disabled: oi === 0 } ) );
				row.appendChild( btn( t.moveDown, function () {
					move( q.options, oi, oi + 1 );
					changed();
					renderQuestions();
				}, '', { disabled: oi === q.options.length - 1 } ) );
				row.appendChild( btn( t.removeOption, function () {
					if ( q.options.length <= 2 ) {
						return;
					}
					q.options.splice( oi, 1 );
					changed();
					renderQuestions();
				}, 'ncs-btn-danger', { disabled: q.options.length <= 2 } ) );
			}
			list.appendChild( row );
		} );
		wrap.appendChild( list );
		if ( ! fixed ) {
			wrap.appendChild( btn( t.addOption, function () {
				q.options.push( { id: uid( 'o' ), label: 'Option ' + ( q.options.length + 1 ), score: 0 } );
				changed();
				renderQuestions();
			} ) );
		}
		return wrap;
	}

	function earlierQuestions( index ) {
		return state.questions.slice( 0, index );
	}

	function logicEditor( q, index ) {
		var logic = q.logic;
		var wrap = h( 'div', { 'class': 'ncs-subblock' }, h( 'h3', { 'class': 'ncs-subtitle', text: t.logic } ) );
		var earlier = earlierQuestions( index );

		wrap.appendChild( checkbox( t.logicEnable, logic.enabled, function ( v ) {
			logic.enabled = v;
			if ( v && ! logic.conditions.length && earlier.length ) {
				logic.conditions.push( { question: earlier[ earlier.length - 1 ].id, operator: 'equals', value: '' } );
			}
			changed();
			renderQuestions();
		} ) );
		if ( ! earlier.length ) {
			wrap.appendChild( h( 'p', { 'class': 'ncs-help', text: t.logicNone } ) );
			return wrap;
		}
		if ( ! logic.enabled ) {
			return wrap;
		}

		wrap.appendChild( h( 'div', { 'class': 'ncs-cols' },
			labelled( t.logicAction, select( [ [ 'show', t.logicShow ], [ 'hide', t.logicHide ] ], logic.action, function ( v ) {
				logic.action = v;
				changed();
			} ) ),
			labelled( t.logicMatch, select( [ [ 'all', t.matchAll ], [ 'any', t.matchAny ] ], logic.match, function ( v ) {
				logic.match = v;
				changed();
			} ) )
		) );

		var qOptions = [ [ '', t.chooseQuestion ] ].concat( earlier.map( function ( e, ei ) {
			return [ e.id, ( ei + 1 ) + '. ' + ( e.text || t.untitled ) ];
		} ) );
		var opOptions = Object.keys( data.operators ).map( function ( k ) {
			return [ k, data.operators[ k ] ];
		} );

		logic.conditions.forEach( function ( c, ci ) {
			var ref = null;
			earlier.forEach( function ( e ) {
				if ( e.id === c.question ) {
					ref = e;
				}
			} );
			var valueControl;
			if ( ref && isChoice( ref.type ) ) {
				var opts = [ [ '', t.chooseQuestion ] ].concat( ref.options.map( function ( o ) {
					return [ o.id, o.label ];
				} ) );
				valueControl = select( opts, c.value, function ( v ) {
					c.value = v;
					changed();
				} );
			} else {
				valueControl = textInput( c.value, function ( v ) {
					c.value = v;
					changed();
				} );
			}
			var row = h( 'div', { 'class': 'ncs-condition-row' },
				labelled( t.question, select( qOptions, c.question, function ( v ) {
					c.question = v;
					c.value = '';
					changed();
					renderQuestions();
				} ) ),
				labelled( t.operator, select( opOptions, c.operator, function ( v ) {
					c.operator = v;
					changed();
				} ) ),
				labelled( t.value, valueControl ),
				btn( t.removeOption, function () {
					logic.conditions.splice( ci, 1 );
					if ( ! logic.conditions.length ) {
						logic.enabled = false;
					}
					changed();
					renderQuestions();
				}, 'ncs-btn-danger' )
			);
			wrap.appendChild( row );
		} );
		if ( logic.conditions.length < 10 ) {
			wrap.appendChild( btn( t.addCondition, function () {
				logic.conditions.push( { question: earlier[ earlier.length - 1 ].id, operator: 'equals', value: '' } );
				changed();
				renderQuestions();
			} ) );
		}
		return wrap;
	}

	/* ---------- programs ---------- */

	var pRoot = document.getElementById( 'ncs-programs-root' );

	function renderPrograms() {
		pRoot.innerHTML = '';
		if ( ! state.programs.length ) {
			pRoot.appendChild( h( 'p', { 'class': 'ncs-empty-inline', text: t.noProgramsYet } ) );
		}
		var list = h( 'div', { 'class': 'ncs-items' } );
		state.programs.forEach( function ( p, i ) {
			var isOpen = open.program === p.id;
			var titleEl = h( 'strong', { 'class': 'ncs-item-title', text: p.name || t.untitledProgram } );
			var item = h( 'div', { 'class': 'ncs-item', 'data-id': p.id } );
			item.appendChild( itemHeader( {
				isOpen: isOpen,
				first: i === 0,
				last: i === state.programs.length - 1,
				summary: [ h( 'span', { 'class': 'ncs-item-index', text: String( i + 1 ) } ), titleEl ],
				onToggle: function () {
					open.program = isOpen ? null : p.id;
					renderPrograms();
				},
				onUp: function () {
					move( state.programs, i, i - 1 );
					changed();
					renderPrograms();
				},
				onDown: function () {
					move( state.programs, i, i + 1 );
					changed();
					renderPrograms();
				},
				onDelete: function () {
					if ( ! window.confirm( t.confirmDelete ) ) {
						return;
					}
					state.programs.splice( i, 1 );
					state.results.forEach( function ( r ) {
						r.programs = ( r.programs || [] ).filter( function ( id ) {
							return id !== p.id;
						} );
					} );
					changed();
					renderPrograms();
					renderResults();
				}
			} ) );
			if ( isOpen ) {
				item.appendChild( h( 'div', { 'class': 'ncs-item-body' },
					h( 'div', { 'class': 'ncs-cols' },
						labelled( t.programName, textInput( p.name, function ( v ) {
							p.name = v;
							titleEl.textContent = v || t.untitledProgram;
							changed();
							renderResults();
						} ) ),
						labelled( t.programUrl, textInput( p.url, function ( v ) {
							p.url = v;
							changed();
						}, { type: 'url', placeholder: 'https://' } ) )
					),
					labelled( t.programDesc, textArea( p.description, function ( v ) {
						p.description = v;
						changed();
					}, 3 ) ),
					labelled( t.programCta, textInput( p.cta_label, function ( v ) {
						p.cta_label = v;
						changed();
					} ) )
				) );
			}
			list.appendChild( item );
		} );
		pRoot.appendChild( list );
		makeSortable( list, state.programs, function () {
			changed();
			renderPrograms();
		} );
		pRoot.appendChild( h( 'p', null, btn( t.addProgram, function () {
			var p = { id: uid( 'p' ), name: '', description: '', url: '', cta_label: '' };
			state.programs.push( p );
			open.program = p.id;
			changed();
			renderPrograms();
			renderResults();
		}, 'ncs-btn-primary' ) ) );
	}

	/* ---------- results ---------- */

	var rRoot = document.getElementById( 'ncs-results-root' );
	var basisEl = document.getElementById( 'ncs-cfg-range-basis' );

	function renderResults() {
		rRoot.innerHTML = '';
		if ( ! state.results.length ) {
			rRoot.appendChild( h( 'p', { 'class': 'ncs-empty-inline', text: t.noResults } ) );
		}
		var list = h( 'div', { 'class': 'ncs-items' } );
		state.results.forEach( function ( r, i ) {
			var isOpen = open.result === r.id;
			var titleEl = h( 'strong', { 'class': 'ncs-item-title', text: r.title || t.untitledResult } );
			var rangeEl = h( 'span', { 'class': 'ncs-item-meta', text: r.min + ' to ' + r.max } );
			var item = h( 'div', { 'class': 'ncs-item', 'data-id': r.id } );
			item.appendChild( itemHeader( {
				isOpen: isOpen,
				first: i === 0,
				last: i === state.results.length - 1,
				summary: [ h( 'span', { 'class': 'ncs-item-index', text: String( i + 1 ) } ), titleEl, rangeEl ],
				onToggle: function () {
					open.result = isOpen ? null : r.id;
					renderResults();
				},
				onUp: function () {
					move( state.results, i, i - 1 );
					changed();
					renderResults();
				},
				onDown: function () {
					move( state.results, i, i + 1 );
					changed();
					renderResults();
				},
				onDelete: function () {
					if ( ! window.confirm( t.confirmDelete ) ) {
						return;
					}
					state.results.splice( i, 1 );
					changed();
					renderResults();
				}
			} ) );
			if ( isOpen ) {
				item.appendChild( resultBody( r, titleEl, rangeEl ) );
			}
			list.appendChild( item );
		} );
		rRoot.appendChild( list );
		makeSortable( list, state.results, function () {
			changed();
			renderResults();
		} );
		rRoot.appendChild( h( 'p', null, btn( t.addResult, function () {
			var last = state.results.length ? state.results[ state.results.length - 1 ] : null;
			var start = last ? Number( last.max ) + 1 : 0;
			var r = {
				id: uid( 'r' ), title: '', min: start, max: start + 25, description: '', recommendation: '',
				programs: [], cta_label: '', cta_url: '', cta2_label: '', cta2_url: ''
			};
			state.results.push( r );
			open.result = r.id;
			changed();
			renderResults();
		}, 'ncs-btn-primary' ) ) );
		renderRangeCheck();
	}

	function resultBody( r, titleEl, rangeEl ) {
		var body = h( 'div', { 'class': 'ncs-item-body' } );
		var updateRange = function () {
			rangeEl.textContent = r.min + ' to ' + r.max;
			changed();
		};
		body.appendChild( h( 'div', { 'class': 'ncs-cols ncs-cols-3' },
			labelled( t.resultTitle, textInput( r.title, function ( v ) {
				r.title = v;
				titleEl.textContent = v || t.untitledResult;
				changed();
			} ) ),
			labelled( t.minScore, numberInput( r.min, function ( v ) {
				r.min = v === '' ? 0 : v;
				updateRange();
			} ) ),
			labelled( t.maxScoreLabel, numberInput( r.max, function ( v ) {
				r.max = v === '' ? 0 : v;
				updateRange();
			} ) )
		) );
		body.appendChild( labelled( t.resultDesc, textArea( r.description, function ( v ) {
			r.description = v;
			changed();
		}, 3 ) ) );
		body.appendChild( labelled( t.resultRec, textArea( r.recommendation, function ( v ) {
			r.recommendation = v;
			changed();
		}, 3 ) ) );

		var progBlock = h( 'fieldset', { 'class': 'ncs-fieldset' }, h( 'legend', { text: t.resultPrograms } ) );
		if ( ! state.programs.length ) {
			progBlock.appendChild( h( 'p', { 'class': 'ncs-help', text: t.noPrograms } ) );
		}
		state.programs.forEach( function ( p ) {
			progBlock.appendChild( checkbox( p.name || t.untitledProgram, ( r.programs || [] ).indexOf( p.id ) !== -1, function ( v ) {
				r.programs = ( r.programs || [] ).filter( function ( id ) {
					return id !== p.id;
				} );
				if ( v ) {
					r.programs.push( p.id );
				}
				changed();
			} ) );
		} );
		body.appendChild( progBlock );

		body.appendChild( h( 'div', { 'class': 'ncs-cols' },
			labelled( t.ctaLabel, textInput( r.cta_label, function ( v ) {
				r.cta_label = v;
				changed();
			} ) ),
			labelled( t.ctaUrl, textInput( r.cta_url, function ( v ) {
				r.cta_url = v;
				changed();
			}, { type: 'url', placeholder: 'https://' } ) )
		) );
		body.appendChild( h( 'div', { 'class': 'ncs-cols' },
			labelled( t.cta2Label, textInput( r.cta2_label, function ( v ) {
				r.cta2_label = v;
				changed();
			} ) ),
			labelled( t.cta2Url, textInput( r.cta2_url, function ( v ) {
				r.cta2_url = v;
				changed();
			}, { type: 'url', placeholder: 'https://' } ) )
		) );
		return body;
	}

	function renderRangeCheck() {
		var el = document.getElementById( 'ncs-range-check' );
		if ( ! el ) {
			return;
		}
		el.innerHTML = '';
		var msgs = [];
		var raw = basisEl && basisEl.value === 'raw';
		var top = raw ? totalMax() : 100;
		if ( ! state.results.length ) {
			el.appendChild( h( 'p', { 'class': 'ncs-range-note', text: t.rangeEmpty } ) );
			return;
		}
		var list = state.results.map( function ( r ) {
			return { title: r.title || t.untitledResult, min: Number( r.min ), max: Number( r.max ) };
		} );
		list.forEach( function ( r ) {
			if ( r.min > r.max ) {
				msgs.push( t.rangeInvalid.replace( '%s', r.title ) );
			}
		} );
		if ( ! msgs.length ) {
			list.sort( function ( a, b ) {
				return a.min - b.min;
			} );
			if ( list[ 0 ].min > 0 ) {
				msgs.push( t.rangeStart.replace( '%s', String( list[ 0 ].min ) ) );
			}
			for ( var i = 1; i < list.length; i++ ) {
				if ( list[ i ].min <= list[ i - 1 ].max ) {
					msgs.push( t.rangeOverlap.replace( '%1$s', list[ i - 1 ].title ).replace( '%2$s', list[ i ].title ) );
				} else if ( list[ i ].min - list[ i - 1 ].max > 1 ) {
					msgs.push( t.rangeGap.replace( '%1$s', String( list[ i - 1 ].max ) ).replace( '%2$s', String( list[ i ].min ) ) );
				}
			}
			if ( list[ list.length - 1 ].max < top ) {
				msgs.push( t.rangeEnd.replace( '%s', String( list[ list.length - 1 ].max ) ) );
			}
		}
		if ( ! msgs.length ) {
			el.appendChild( h( 'p', { 'class': 'ncs-range-note ncs-range-ok' }, h( 'strong', { text: t.rangeCheck + ': ' } ), t.rangeOk ) );
		} else {
			var ul = h( 'ul', { 'class': 'ncs-range-note ncs-range-bad' } );
			msgs.forEach( function ( m ) {
				ul.appendChild( h( 'li', null, h( 'strong', { text: t.rangeCheck + ': ' } ), m ) );
			} );
			el.appendChild( ul );
		}
	}

	if ( basisEl ) {
		basisEl.addEventListener( 'change', renderRangeCheck );
	}

	/* ---------- lead fields ---------- */

	var lRoot = document.getElementById( 'ncs-leadfields-root' );

	function renderLeadFields() {
		lRoot.innerHTML = '';
		var list = h( 'div', { 'class': 'ncs-items' } );
		state.leadFields.forEach( function ( f, i ) {
			var isEmail = f.key === 'email';
			var isOpen = open.field === f.key;
			var titleEl = h( 'strong', { 'class': 'ncs-item-title', text: f.label } );
			var item = h( 'div', { 'class': 'ncs-item' + ( f.enabled ? '' : ' is-disabled' ), 'data-id': f.key } );
			item.appendChild( itemHeader( {
				isOpen: isOpen,
				first: i === 0,
				last: i === state.leadFields.length - 1,
				summary: [
					h( 'span', { 'class': 'ncs-item-index', text: String( i + 1 ) } ),
					titleEl,
					h( 'span', { 'class': 'ncs-item-meta', text: ( f.enabled ? t.enabled : t.disabled ) + ' | ' + ( f.required ? t.required : t.optional ) + ( f.custom ? ' | ' + t.customField : '' ) } )
				],
				onToggle: function () {
					open.field = isOpen ? null : f.key;
					renderLeadFields();
				},
				onUp: function () {
					move( state.leadFields, i, i - 1 );
					changed();
					renderLeadFields();
				},
				onDown: function () {
					move( state.leadFields, i, i + 1 );
					changed();
					renderLeadFields();
				},
				onDelete: function () {
					if ( ! f.custom ) {
						f.enabled = false;
						changed();
						renderLeadFields();
						return;
					}
					if ( ! window.confirm( t.confirmDelete ) ) {
						return;
					}
					state.leadFields.splice( i, 1 );
					changed();
					renderLeadFields();
				}
			} ) );
			if ( isOpen ) {
				var body = h( 'div', { 'class': 'ncs-item-body' } );
				body.appendChild( h( 'div', { 'class': 'ncs-cols' },
					labelled( t.label, textInput( f.label, function ( v ) {
						f.label = v;
						titleEl.textContent = v;
						changed();
					} ) ),
					labelled( t.fieldPlaceholder, textInput( f.placeholder, function ( v ) {
						f.placeholder = v;
						changed();
					} ) )
				) );
				body.appendChild( h( 'div', { 'class': 'ncs-inline-checks' },
					isEmail ? h( 'p', { 'class': 'ncs-help', text: t.emailLocked } ) : [
						checkbox( t.enabled, f.enabled, function ( v ) {
							f.enabled = v;
							changed();
							renderLeadFields();
						} ),
						checkbox( t.required, f.required, function ( v ) {
							f.required = v;
							changed();
							renderLeadFields();
						} )
					]
				) );
				if ( f.custom ) {
					body.appendChild( labelled( t.fieldType, select( [ [ 'text', t.typeText ], [ 'textarea', t.typeTextarea ], [ 'select', t.typeSelect ] ], f.type, function ( v ) {
						f.type = v;
						changed();
						renderLeadFields();
					} ) ) );
				}
				if ( f.type === 'select' ) {
					body.appendChild( labelled( t.fieldOptions, textArea( ( f.options || [] ).join( '\n' ), function ( v ) {
						f.options = v.split( /\r?\n/ ).map( function ( s ) {
							return s.trim();
						} ).filter( Boolean );
						changed();
					}, 4 ) ) );
				}
				item.appendChild( body );
			}
			list.appendChild( item );
		} );
		lRoot.appendChild( list );
		makeSortable( list, state.leadFields, function () {
			changed();
			renderLeadFields();
		} );
		lRoot.appendChild( h( 'p', null, btn( t.addCustomField, function () {
			var f = { key: 'custom_' + uid( '' ).slice( 0, 6 ), label: t.customField, placeholder: '', type: 'text', options: [], required: false, enabled: true, custom: true };
			state.leadFields.push( f );
			open.field = f.key;
			changed();
			renderLeadFields();
		} ) ) );
	}

	/* ---------- init ---------- */

	form.addEventListener( 'submit', sync );
	renderQuestions();
	renderPrograms();
	renderResults();
	renderLeadFields();
	sync();
}() );
