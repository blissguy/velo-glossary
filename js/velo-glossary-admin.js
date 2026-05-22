( function( $ ) {
	function getSelectedIds( $list ) {
		return $list
			.find( 'input[name="velo_glossary_associated_post_ids[]"]' )
			.map( function() {
				return $( this ).val();
			} )
			.get();
	}

	function updateEmptyState( $list ) {
		$( '.velo-glossary-associated-content-empty' ).toggleClass( 'hidden', $list.children().length > 0 );
	}

	function addAssociatedContent( $list, item ) {
		if ( $list.find( '[data-post-id="' + item.id + '"]' ).length ) {
			return;
		}

		var $item = $( '<li />', {
			class: 'velo-glossary-associated-content-item',
			'data-post-id': item.id
		} );

		$( '<span />', {
			class: 'velo-glossary-associated-content-title',
			text: item.title
		} ).appendTo( $item );

		$( '<span />', {
			class: 'velo-glossary-associated-content-meta',
			text: item.meta
		} ).appendTo( $item );

		$( '<button />', {
			type: 'button',
			class: 'button-link-delete velo-glossary-associated-content-remove',
			text: item.removeLabel || veloGlossaryAdmin.strings.remove
		} ).appendTo( $item );

		$( '<input />', {
			type: 'hidden',
			name: 'velo_glossary_associated_post_ids[]',
			value: item.id
		} ).appendTo( $item );

		$list.append( $item );
		updateEmptyState( $list );
	}

	$( function() {
		var $search = $( '#velo-glossary-associated-content-search' );
		var $list = $( '#velo-glossary-associated-content-list' );

		if ( ! $search.length || ! $list.length ) {
			return;
		}

		$search.autocomplete( {
			minLength: 2,
			source: function( request, response ) {
				$.getJSON( veloGlossaryAdmin.ajaxUrl, {
					action: 'velo_glossary_search_content',
					nonce: veloGlossaryAdmin.nonce,
					term: request.term,
					selected: getSelectedIds( $list )
				} )
					.done( function( data ) {
						response( $.isArray( data ) ? data : [] );
					} )
					.fail( function() {
						response( [] );
					} );
			},
			focus: function( event ) {
				event.preventDefault();
			},
			select: function( event, ui ) {
				event.preventDefault();
				addAssociatedContent( $list, ui.item );
				$search.val( '' );
			}
		} );

		$list.on( 'click', '.velo-glossary-associated-content-remove', function() {
			$( this ).closest( '.velo-glossary-associated-content-item' ).remove();
			updateEmptyState( $list );
		} );
	} );
}( jQuery ) );
