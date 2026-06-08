( function( $ ) {
	function getSelectedIds( $list, inputName ) {
		return $list
			.find( 'input[name="' + inputName + '[]"]' )
			.map( function() {
				return $( this ).val();
			} )
			.get();
	}

	function updateEmptyState( $list, emptySelector ) {
		$( emptySelector ).toggleClass( 'hidden', $list.children().length > 0 );
	}

	function addPickerItem( config, item ) {
		var $list = config.$list;

		if ( $list.find( '[data-post-id="' + item.id + '"]' ).length ) {
			return;
		}

		var $item = $( '<li />', {
			class: config.itemClass,
			'data-post-id': item.id
		} );

		$( '<span />', {
			class: config.titleClass,
			text: item.title
		} ).appendTo( $item );

		$( '<span />', {
			class: config.metaClass,
			text: item.meta
		} ).appendTo( $item );

		$( '<button />', {
			type: 'button',
			class: 'button-link-delete ' + config.removeClass,
			text: item.removeLabel || veloGlossaryAdmin.strings.remove
		} ).appendTo( $item );

		$( '<input />', {
			type: 'hidden',
			name: config.inputName + '[]',
			value: item.id
		} ).appendTo( $item );

		$list.append( $item );
		updateEmptyState( $list, config.emptySelector );
	}

	function setupPicker( config ) {
		var $search = $( config.searchSelector );
		var $list = $( config.listSelector );

		if ( ! $search.length || ! $list.length ) {
			return;
		}

		config.$list = $list;

		$search.autocomplete( {
			minLength: 2,
			source: function( request, response ) {
				var data = {
					action: config.action,
					nonce: veloGlossaryAdmin.nonce,
					term: request.term,
					selected: getSelectedIds( $list, config.inputName )
				};

				if ( config.currentPostId ) {
					data.currentPostId = config.currentPostId;
				}

				$.getJSON( veloGlossaryAdmin.ajaxUrl, data )
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
				addPickerItem( config, ui.item );
				$search.val( '' );
			}
		} );

		$list.on( 'click', '.' + config.removeClass, function() {
			$( this ).closest( '.' + config.itemClass ).remove();
			updateEmptyState( $list, config.emptySelector );
		} );
	}

	$( function() {
		setupPicker( {
			action: 'velo_glossary_search_content',
			searchSelector: '#velo-glossary-associated-content-search',
			listSelector: '#velo-glossary-associated-content-list',
			emptySelector: '.velo-glossary-associated-content-empty',
			inputName: 'velo_glossary_associated_post_ids',
			itemClass: 'velo-glossary-associated-content-item',
			titleClass: 'velo-glossary-associated-content-title',
			metaClass: 'velo-glossary-associated-content-meta',
			removeClass: 'velo-glossary-associated-content-remove'
		} );

		setupPicker( {
			action: 'velo_glossary_search_terms',
			searchSelector: '#velo-glossary-related-terms-search',
			listSelector: '#velo-glossary-related-terms-list',
			emptySelector: '.velo-glossary-related-terms-empty',
			inputName: 'velo_glossary_related_term_ids',
			itemClass: 'velo-glossary-related-terms-item',
			titleClass: 'velo-glossary-related-terms-title',
			metaClass: 'velo-glossary-related-terms-meta',
			removeClass: 'velo-glossary-related-terms-remove',
			currentPostId: $( '#velo-glossary-related-terms-search' ).data( 'currentPostId' )
		} );
	} );
}( jQuery ) );
