jQuery( document ).ready( function ( $ ) {
	var tooltips = [];

	$( '.glossary-item-container' ).each( function( index, element ) {

		var tooltipContent = $( element ).find( '.glossary-item-hidden-content' ).detach().html();

		// A term with no definition is left over markup: the builder was opened on a
		// page this script had already stripped, and the bare wrapper got saved into
		// the element. Tippy would attach an empty card that never appears, leaving a
		// word that looks interactive and does nothing.
		if ( ! tooltipContent ) {
			element.classList.remove( 'glossary-item-container', 'velo-glossary__term' );
			element.removeAttribute( 'tabindex' );

			return;
		}

		var tooltip = tippy(element, {
			content: tooltipContent,
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
			delay: [0, 500],
			onCreate: function(instance) {
				var box = instance.popper.querySelector( '.tippy-box' );
				var content = instance.popper.querySelector( '.tippy-content' );

				instance.popper.classList.add( 'velo-glossary__tippy-popper' );

				if ( box ) {
					box.classList.add( 'velo-glossary__tippy-box' );
				}

				if ( content ) {
					content.classList.add( 'velo-glossary__tippy-content' );
				}
			},
			onShow: function(instance) {
				for (var i = 0; i < tooltips.length; i++) {
					if ( instance !== tooltips[i] ) {
						tooltips[i].hide();
					}
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
		});

		hoverintent(
			element,
			function() {
				tooltip.show();
			},
			function() {
				// do nothing, allow tippy.js to close itself.
				// this allows for hoverIntent to trigger it
				// but not to close it if the mouse is over the tooltip
			}
		);

		tooltips.push(tooltip);
	} );
} );
