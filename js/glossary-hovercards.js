jQuery( document ).ready( function ( $ ) {
	var tooltips = [];

	$( '.glossary-item-container' ).each( function( index, element ) {

		var tooltipContent = $( element ).find( '.glossary-item-hidden-content' ).detach().html();

		var tooltip = tippy(element, {
			content: tooltipContent,
			allowHTML: true,
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
