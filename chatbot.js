/* global nhafChatbot, grecaptcha */
( function () {
	'use strict';

	if ( typeof nhafChatbot === 'undefined' ) {
		return;
	}

	var STORAGE_KEY = 'nhaf_chat_session';
	var HISTORY_KEY = 'nhaf_chat_history';

	var state = {
		open: false,
		sessionId: null,
		busy: false,
		messages: []
	};

	/* ---------- Utilities ---------- */

	function uuid() {
		return 'sess-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 10 );
	}

	function loadSession() {
		try {
			state.sessionId = localStorage.getItem( STORAGE_KEY ) || uuid();
			localStorage.setItem( STORAGE_KEY, state.sessionId );
			var raw = localStorage.getItem( HISTORY_KEY );
			state.messages = raw ? JSON.parse( raw ) : [];
		} catch ( e ) {
			state.sessionId = uuid();
			state.messages = [];
		}
	}

	function persistHistory() {
		try {
			localStorage.setItem( HISTORY_KEY, JSON.stringify( state.messages.slice( -20 ) ) );
		} catch ( e ) {}
	}

	function clearSession() {
		state.messages = [];
		state.sessionId = uuid();
		try {
			localStorage.setItem( STORAGE_KEY, state.sessionId );
			localStorage.removeItem( HISTORY_KEY );
		} catch ( e ) {}
	}

	function el( tag, cls, html ) {
		var node = document.createElement( tag );
		if ( cls ) { node.className = cls; }
		if ( html !== undefined ) { node.innerHTML = html; }
		return node;
	}

	function escapeText( str ) {
		var d = document.createElement( 'div' );
		d.textContent = str;
		return d.innerHTML;
	}

	/**
	 * Minimal, safe markdown: escapes first, then supports
	 * **bold**, [text](https://link), and "- item" lists.
	 */
	function renderMarkdown( str ) {
		var safe  = escapeText( str || '' );
		var lines = safe.split( /\r?\n/ );
		var out   = [];
		var list  = [];

		function flushList() {
			if ( list.length ) {
				out.push( '<ul>' + list.map( function ( li ) { return '<li>' + li + '</li>'; } ).join( '' ) + '</ul>' );
				list = [];
			}
		}

		lines.forEach( function ( line ) {
			var m = line.match( /^\s*[-*]\s+(.+)$/ );
			if ( m ) {
				list.push( m[ 1 ] );
			} else {
				flushList();
				if ( line.trim() ) { out.push( line ); }
			}
		} );
		flushList();

		var html = out.join( '<br>' );
		html = html.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
		html = html.replace( /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener nofollow">$1</a>' );
		return html;
	}

	/** Inline-only markdown for server-rendered HTML (bold + links). */
	function inlineMarkdown( html ) {
		html = String( html || '' );
		html = html.replace( /\*\*([^*<]+)\*\*/g, '<strong>$1</strong>' );
		return html;
	}

	/* ---------- reCAPTCHA ---------- */

	function withRecaptcha( action, cb ) {
		var rc = nhafChatbot.recaptcha;
		if ( ! rc || ! rc.enabled || ! rc.siteKey || typeof grecaptcha === 'undefined' ) {
			cb( '' );
			return;
		}
		if ( rc.version === 'v3' ) {
			grecaptcha.ready( function () {
				grecaptcha.execute( rc.siteKey, { action: action } ).then( function ( token ) {
					cb( token );
				} ).catch( function () { cb( '' ); } );
			} );
		} else {
			// v2: token would be collected from a rendered checkbox; fall back to empty.
			cb( '' );
		}
	}

	/* ---------- API ---------- */

	function api( path, body ) {
		return fetch( nhafChatbot.restUrl + path, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nhafChatbot.nonce
			},
			body: JSON.stringify( body )
		} ).then( function ( res ) {
			return res.json().then( function ( data ) {
				return { ok: res.ok, status: res.status, data: data };
			} );
		} );
	}

	/**
	 * Call the API with up to 3 attempts; retries network errors and
	 * 429/5xx responses with exponential backoff (500ms, 1s).
	 */
	function apiRetry( path, body, attempt ) {
		attempt = attempt || 1;
		return api( path, body ).then( function ( resp ) {
			if ( attempt < 3 && ( resp.status === 429 || resp.status >= 500 ) ) {
				return delayRetry( path, body, attempt );
			}
			return resp;
		} ).catch( function ( err ) {
			if ( attempt < 3 ) {
				return delayRetry( path, body, attempt );
			}
			throw err;
		} );
	}

	function delayRetry( path, body, attempt ) {
		return new Promise( function ( resolve ) {
			setTimeout( resolve, 500 * Math.pow( 2, attempt - 1 ) );
		} ).then( function () {
			return apiRetry( path, body, attempt + 1 );
		} );
	}

	/* ---------- Rendering ---------- */

	var refs = {};

	function buildWidget( root, embedded ) {
		root.classList.add( 'nhaf-widget' );
		if ( embedded ) { root.classList.add( 'nhaf-embedded' ); }
		refs.root = root;

		// Trigger bubble (floating mode). The chat window itself is built
		// lazily on first open to keep initial page work minimal.
		if ( ! embedded ) {
			refs.trigger = el( 'button', 'nhaf-chatbot-trigger', '<span class="nhaf-bubble-icon" aria-hidden="true">💬</span>' );
			refs.trigger.setAttribute( 'aria-label', nhafChatbot.i18n.openChat || 'Open safari chat' );
			refs.trigger.setAttribute( 'aria-expanded', 'false' );
			refs.trigger.addEventListener( 'click', toggleOpen );
			root.appendChild( refs.trigger );
			return;
		}

		buildWindow( root, true );
	}

	function buildWindow( root, embedded ) {
		// Window.
		refs.window = el( 'div', 'nhaf-window' + ( embedded ? ' nhaf-open' : '' ) );
		refs.window.setAttribute( 'role', 'dialog' );
		refs.window.setAttribute( 'aria-label', ( nhafChatbot.businessName || '' ) + ' Safari AI chat' );

		var header = el( 'div', 'nhaf-header' );
		header.appendChild( el( 'div', 'nhaf-title', escapeText( nhafChatbot.businessName ) + ' &middot; Safari AI' ) );

		var actions = el( 'div', 'nhaf-header-actions' );
		var clearBtn = el( 'button', 'nhaf-icon-btn', '↺' );
		clearBtn.title = nhafChatbot.i18n.clear;
		clearBtn.setAttribute( 'aria-label', nhafChatbot.i18n.clear );
		clearBtn.addEventListener( 'click', onClear );
		actions.appendChild( clearBtn );

		if ( ! embedded ) {
			var closeBtn = el( 'button', 'nhaf-icon-btn', '✕' );
			closeBtn.title = 'Close';
			closeBtn.setAttribute( 'aria-label', 'Close chat' );
			closeBtn.addEventListener( 'click', toggleOpen );
			actions.appendChild( closeBtn );
		}
		header.appendChild( actions );
		refs.window.appendChild( header );

		// Messages area.
		refs.messages = el( 'div', 'nhaf-messages' );
		refs.messages.setAttribute( 'role', 'log' );
		refs.messages.setAttribute( 'aria-live', 'polite' );
		refs.window.appendChild( refs.messages );

		// Form mount point (booking).
		refs.formMount = el( 'div', 'nhaf-form-mount' );
		refs.window.appendChild( refs.formMount );

		// Input row.
		var inputRow = el( 'div', 'nhaf-input-row' );
		refs.input = el( 'textarea', 'nhaf-input' );
		refs.input.rows = 1;
		refs.input.placeholder = nhafChatbot.i18n.placeholder;
		refs.input.setAttribute( 'aria-label', nhafChatbot.i18n.placeholder );
		refs.input.maxLength = nhafChatbot.maxLength || 1000;
		refs.input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				onSend();
			}
		} );
		var sendBtn = el( 'button', 'nhaf-send', escapeText( nhafChatbot.i18n.send ) );
		sendBtn.setAttribute( 'aria-label', nhafChatbot.i18n.send );
		sendBtn.addEventListener( 'click', onSend );

		inputRow.appendChild( refs.input );
		inputRow.appendChild( sendBtn );
		refs.window.appendChild( inputRow );

		root.appendChild( refs.window );

		// Restore prior messages or show welcome + quick replies.
		if ( state.messages.length ) {
			state.messages.forEach( function ( m ) { appendBubble( m.role, m.content, false ); } );
		} else {
			appendBubble( 'assistant', renderMarkdown( nhafChatbot.welcome ), false );
			renderQuickReplies();
		}
		scrollToBottom();
	}

	function renderQuickReplies() {
		var replies = nhafChatbot.quickReplies || [];
		if ( ! replies.length || refs.messages.querySelector( '.nhaf-quick-replies' ) ) { return; }

		var wrap = el( 'div', 'nhaf-quick-replies' );
		replies.forEach( function ( text ) {
			var btn = el( 'button', 'nhaf-quick-reply', escapeText( text ) );
			btn.type = 'button';
			btn.addEventListener( 'click', function () {
				wrap.parentNode && wrap.parentNode.removeChild( wrap );
				refs.input.value = text;
				onSend();
			} );
			wrap.appendChild( btn );
		} );
		refs.messages.appendChild( wrap );
	}

	function appendBubble( role, html, store ) {
		var bubble = el( 'div', 'nhaf-msg nhaf-msg-' + ( role === 'user' ? 'user' : 'bot' ) );
		bubble.innerHTML = html;
		refs.messages.appendChild( bubble );
		if ( store !== false ) {
			state.messages.push( { role: role, content: html } );
			persistHistory();
		}
		scrollToBottom();
		return bubble;
	}

	function showTyping() {
		refs.typing = el( 'div', 'nhaf-msg nhaf-msg-bot nhaf-typing', '<span></span><span></span><span></span>' );
		refs.messages.appendChild( refs.typing );
		scrollToBottom();
	}

	function hideTyping() {
		if ( refs.typing && refs.typing.parentNode ) {
			refs.typing.parentNode.removeChild( refs.typing );
		}
		refs.typing = null;
	}

	function scrollToBottom() {
		if ( refs.messages ) {
			refs.messages.scrollTop = refs.messages.scrollHeight;
		}
	}

	function toggleOpen() {
		// Lazy build: the chat window is created on first open.
		if ( ! refs.window && refs.root ) {
			buildWindow( refs.root, false );
		}
		state.open = ! state.open;
		refs.window.classList.toggle( 'nhaf-open', state.open );
		if ( refs.trigger ) {
			refs.trigger.classList.toggle( 'nhaf-hidden', state.open );
			refs.trigger.setAttribute( 'aria-expanded', state.open ? 'true' : 'false' );
		}
		if ( state.open ) {
			setTimeout( function () { refs.input && refs.input.focus(); }, 50 );
			scrollToBottom();
		}
	}

	/* ---------- Handlers ---------- */

	function onClear() {
		clearSession();
		refs.messages.innerHTML = '';
		refs.formMount.innerHTML = '';
		appendBubble( 'assistant', renderMarkdown( nhafChatbot.welcome ), false );
		renderQuickReplies();
	}

	function onSend() {
		if ( state.busy ) { return; }
		var text = ( refs.input.value || '' ).trim();
		if ( ! text ) { return; }

		appendBubble( 'user', escapeText( text ), true );
		refs.input.value = '';
		state.busy = true;
		showTyping();

		withRecaptcha( 'chat', function ( token ) {
			apiRetry( '/chat', {
				message: text,
				session_id: state.sessionId,
				page_url: window.location.href,
				recaptcha_token: token
			} ).then( function ( resp ) {
				hideTyping();
				state.busy = false;
				if ( ! resp.ok || ! resp.data ) {
					appendBubble( 'assistant', escapeText(
						( resp.data && resp.data.message ) ? resp.data.message : nhafChatbot.i18n.error
					), false );
					return;
				}
				if ( resp.data.session_id ) { state.sessionId = resp.data.session_id; }
				appendBubble( 'assistant', inlineMarkdown( resp.data.reply ), true );
				if ( resp.data.show_form ) {
					renderBookingForm();
				}
			} ).catch( function () {
				hideTyping();
				state.busy = false;
				appendBubble( 'assistant', escapeText( nhafChatbot.i18n.error ), false );
			} );
		} );
	}

	function renderBookingForm() {
		if ( refs.formMount.querySelector( '.nhaf-booking-form' ) ) { return; }
		var t = nhafChatbot.i18n;
		var form = el( 'div', 'nhaf-booking-form' );
		form.innerHTML =
			'<div class="nhaf-form-title">' + escapeText( t.bookTitle ) + '</div>' +
			'<input type="text" name="name" placeholder="' + escapeText( t.name ) + ' *" />' +
			'<input type="email" name="email" placeholder="' + escapeText( t.email ) + ' *" />' +
			'<input type="text" name="phone" placeholder="' + escapeText( t.phone ) + '" />' +
			'<input type="number" name="travelers" min="1" placeholder="' + escapeText( t.travelers ) + '" />' +
			'<input type="text" name="destination" placeholder="' + escapeText( t.destination ) + '" />' +
			'<input type="text" name="preferred_month" placeholder="' + escapeText( t.month ) + '" />' +
			'<button type="button" class="nhaf-form-submit">' + escapeText( t.submit ) + '</button>' +
			'<div class="nhaf-form-msg" aria-live="polite"></div>';
		refs.formMount.appendChild( form );

		form.querySelector( '.nhaf-form-submit' ).addEventListener( 'click', function () {
			submitBooking( form );
		} );
		scrollToBottom();
	}

	function submitBooking( form ) {
		var msgBox = form.querySelector( '.nhaf-form-msg' );
		var get = function ( n ) { var f = form.querySelector( '[name="' + n + '"]' ); return f ? f.value.trim() : ''; };

		var payload = {
			name: get( 'name' ),
			email: get( 'email' ),
			phone: get( 'phone' ),
			travelers: get( 'travelers' ),
			destination: get( 'destination' ),
			preferred_month: get( 'preferred_month' )
		};

		if ( ! payload.name || ! payload.email ) {
			msgBox.textContent = nhafChatbot.i18n.error + ' (' + nhafChatbot.i18n.name + ' / ' + nhafChatbot.i18n.email + ')';
			return;
		}

		var btn = form.querySelector( '.nhaf-form-submit' );
		btn.disabled = true;

		withRecaptcha( 'lead', function ( token ) {
			payload.recaptcha_token = token;
			apiRetry( '/lead', payload ).then( function ( resp ) {
				btn.disabled = false;
				if ( resp.ok && resp.data && resp.data.success ) {
					form.parentNode.removeChild( form );
					var html = escapeText( resp.data.message ) +
						'<br><strong>' + escapeText( resp.data.reference ) + '</strong>';
					if ( resp.data.affiliate_url ) {
						html += '<br><a class="nhaf-cta" target="_blank" rel="noopener nofollow" href="' +
							encodeURI( resp.data.affiliate_url ) + '">' +
							escapeText( nhafChatbot.i18n.bookCta ) + '</a>';
					}
					appendBubble( 'assistant', html, true );
				} else {
					msgBox.textContent = ( resp.data && resp.data.message ) ? resp.data.message : nhafChatbot.i18n.error;
				}
			} ).catch( function () {
				btn.disabled = false;
				msgBox.textContent = nhafChatbot.i18n.error;
			} );
		} );
	}

	/* ---------- Init ---------- */

	function init() {
		loadSession();

		var root = document.getElementById( 'nhaf-chatbot-root' );
		if ( root ) {
			buildWidget( root, false );
		}

		// Embedded instances via shortcode.
		document.querySelectorAll( '[data-nhaf-embed="1"]' ).forEach( function ( node ) {
			buildWidget( node, true );
		} );

		// Inline trigger buttons.
		document.querySelectorAll( '[data-nhaf-open="1"]' ).forEach( function ( node ) {
			node.addEventListener( 'click', function () {
				if ( ! state.open ) { toggleOpen(); }
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
