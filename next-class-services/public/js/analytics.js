/**
 * Next Class Services: shared frontend helpers.
 * REST client with nonce refresh, first-party event beacons and optional GA4 events.
 * Google Analytics is never installed by this plugin. Events are sent only when gtag exists.
 */
( function () {
	'use strict';

	var cfg = window.ncsPublic || {};
	var GA_NAMES = {
		assessment_view: 'nc_assessment_viewed',
		assessment_start: 'nc_assessment_started',
		assessment_complete: 'nc_assessment_completed',
		lead_captured: 'nc_lead_captured',
		result_view: 'nc_result_viewed',
		booking_view: 'nc_booking_viewed',
		booking_start: 'nc_booking_started',
		booking_complete: 'nc_booking_completed',
		booking_external_click: 'nc_booking_external_click'
	};

	function ga( key, params ) {
		var name = GA_NAMES[ key ];
		if ( ! cfg.ga4 || ! name ) {
			return;
		}
		try {
			if ( typeof window.gtag === 'function' ) {
				window.gtag( 'event', name, params || {} );
			}
		} catch ( e ) {
			/* GA4 problems must never affect the page. */
		}
	}

	function url( path ) {
		return ( cfg.restUrl || '/wp-json/ncs/v1/' ) + path;
	}

	function refreshNonce() {
		return window.fetch( url( 'nonce' ), { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( j ) {
				if ( j && j.nonce ) {
					cfg.nonce = j.nonce;
				}
				return cfg.nonce;
			} );
	}

	/**
	 * Call the plugin REST API.
	 *
	 * @param {string} method GET or POST.
	 * @param {string} path   Route below ncs/v1.
	 * @param {Object} body   Params (query string for GET, JSON for POST).
	 * @param {Object} opts   restNonce: X-WP-Nonce value for authenticated preview requests.
	 * @param {boolean} retried Internal.
	 * @return {Promise<{status:number,data:Object}>}
	 */
	function api( method, path, body, opts, retried ) {
		opts = opts || {};
		var headers = { 'X-NCS-Nonce': cfg.nonce || '' };
		var init = { method: method, credentials: 'same-origin', headers: headers };
		var target = url( path );
		if ( opts.restNonce ) {
			headers[ 'X-WP-Nonce' ] = opts.restNonce;
		}
		if ( method === 'GET' ) {
			var q = [];
			Object.keys( body || {} ).forEach( function ( k ) {
				if ( body[ k ] !== undefined && body[ k ] !== null && body[ k ] !== '' ) {
					q.push( encodeURIComponent( k ) + '=' + encodeURIComponent( body[ k ] ) );
				}
			} );
			target += ( target.indexOf( '?' ) === -1 ? '?' : '&' ) + q.join( '&' );
			init.cache = 'no-store';
		} else {
			headers[ 'Content-Type' ] = 'application/json';
			init.body = JSON.stringify( body || {} );
		}
		return window.fetch( target, init ).then( function ( r ) {
			return r.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( r.status === 403 && data && data.code === 'bad_nonce' && ! retried ) {
					return refreshNonce().then( function () {
						return api( method, path, body, opts, true );
					} );
				}
				return { status: r.status, data: data || {} };
			} );
		} );
	}

	/**
	 * Track a client reported event.
	 *
	 * @param {string} event     Event key (for example assessment_view).
	 * @param {number} objectId  Assessment or service id.
	 * @param {string} meta      Short context.
	 * @param {Object} gaParams  Extra GA4 params.
	 * @param {boolean} preview  Skip everything in admin previews.
	 * @param {boolean} serverSide When true only GA4 fires (server already recorded it).
	 */
	function track( event, objectId, meta, gaParams, preview, serverSide ) {
		if ( preview ) {
			return;
		}
		var params = gaParams || {};
		params.item_id = objectId;
		ga( event, params );
		if ( serverSide || ! cfg.analytics ) {
			return;
		}
		try {
			api( 'POST', 'event', { event: event, id: objectId, meta: meta || '' } ).catch( function () {} );
		} catch ( e ) {
			/* Tracking failures are silent. */
		}
	}

	window.NCS = {
		api: api,
		track: track,
		ga: ga
	};
}() );
