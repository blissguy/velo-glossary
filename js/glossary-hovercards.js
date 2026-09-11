( function () {
	var SELECTOR = '.glossary-item-container';

	// The card currently on screen, so opening another can close it.
	var openCard = null;

	/**
	 * Attach a hovercard to one term.
	 *
	 * @param {Element} element The term wrapper.
	 */
	function createHovercard( element ) {
		// tippy stores the instance on the element, so an already wired term is
		// skipped without adding an attribute of our own. Anything written into the
		// DOM here can end up saved into the page: opening the builder on a rendered
		// page is how empty hovercards get baked into an element in the first place.
		if ( element._tippy ) {
			return;
		}

		var hidden  = element.querySelector( '.glossary-item-hidden-content' );
		var content = hidden ? hidden.innerHTML : '';

		if ( hidden ) {
			hidden.parentNode.removeChild( hidden );
		}

		// A term with no definition is left over markup, from a page saved after this
		// script had already stripped it. Tippy would attach an empty card that never
		// appears, leaving a word that looks interactive and does nothing.
		if ( ! content ) {
			element.classList.remove( 'glossary-item-container', 'velo-glossary__term' );
			element.removeAttribute( 'tabindex' );

			return;
		}

		tippy( element, {
			content: content,
			allowHTML: true,
			// Tippy appends an interactive tippy to the reference's parent unless
			// appendTo says otherwise, which puts the card inside the paragraph, menu
			// or toggle holding the term. It then inherits that element's overflow
			// clipping and width, so cards inside a collapsed menu or a narrow inline
			// wrapper are clipped away or squeezed into an unreadable sliver.
			appendTo: function () {
				return document.body;
			},
			arrow: true,
			theme: 'velo-glossary',
			delay: [ 0, 500 ],
			onCreate: function ( instance ) {
				var box     = instance.popper.querySelector( '.tippy-box' );
				var content = instance.popper.querySelector( '.tippy-content' );

				instance.popper.classList.add( 'velo-glossary__tippy-popper' );

				if ( box ) {
					box.classList.add( 'velo-glossary__tippy-box' );
				}

				if ( content ) {
					content.classList.add( 'velo-glossary__tippy-content' );
				}
			},
			onShow: function ( instance ) {
				// Only one definition at a time. tippy.hideAll would be the obvious
				// way, but it only reaches instances it has already mounted, and a
				// card mounts a frame or more after it is shown. Two cards opened in
				// quick succession both stay up.
				if ( openCard && openCard !== instance && ! openCard.state.isDestroyed ) {
					openCard.hide();
				}

				openCard = instance;
			},
			onHidden: function ( instance ) {
				if ( openCard === instance ) {
					openCard = null;
				}
			},
			interactive: true,
			popperOptions: {
				modifiers: [
					{
						name: 'preventOverflow',
						options: {
							rootBoundary: 'viewport'
						}
					}
				]
			},
			zIndex: 99999
		} );
	}

	/**
	 * Attach hovercards to every term in a subtree.
	 *
	 * Safe to call repeatedly. Terms that already have a card are left alone.
	 *
	 * @param {Element|Document} [root] Subtree to scan. Defaults to the document.
	 */
	function initHovercards( root ) {
		var scope = root || document;

		if ( scope.nodeType !== 1 && scope.nodeType !== 9 ) {
			return;
		}

		if ( scope.nodeType === 1 && scope.matches( SELECTOR ) ) {
			createHovercard( scope );
		}

		Array.prototype.forEach.call( scope.querySelectorAll( SELECTOR ), createHovercard );
	}

	/**
	 * Wire terms that arrive after page load.
	 *
	 * Answers posted to a Q&A, notification lists and anything else rendered by
	 * script carry their own term markup, and a one-shot pass at ready leaves those
	 * words underlined with nothing behind them. Only added nodes are examined, so
	 * an animating page is not rescanned on every frame.
	 */
	function watchForTerms() {
		if ( typeof MutationObserver !== 'function' ) {
			return;
		}

		var pending = [];
		var timer   = null;

		var flush = function () {
			var roots = pending;

			pending = [];
			timer   = null;

			roots.forEach( initHovercards );
		};

		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				Array.prototype.forEach.call( mutation.addedNodes, function ( node ) {
					if ( node.nodeType !== 1 ) {
						return;
					}

					if ( node.matches( SELECTOR ) || node.querySelector( SELECTOR ) ) {
						pending.push( node );
					}
				} );
			} );

			if ( pending.length && null === timer ) {
				timer = window.setTimeout( flush, 100 );
			}
		} ).observe( document.body, { childList: true, subtree: true } );
	}

	// Exposed so a script that renders terms can wire them straight away rather
	// than waiting for the observer, and so a subtree can be passed in directly.
	window.veloGlossary = window.veloGlossary || {};
	window.veloGlossary.initHovercards = initHovercards;

	var start = function () {
		initHovercards();
		watchForTerms();
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
