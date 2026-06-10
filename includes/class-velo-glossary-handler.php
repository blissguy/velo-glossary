<?php
/**
 * Handler for creating links to the glossary on the frontend.
 */

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_Handler {
	private $glossary;
	private $processed;
	private $content;
	private $context_post_id;

	public function __construct() {
		$this->glossary = new Velo_Glossary();

		// Hooks
		// 20 to run after do_shortcode (12) and O2 filters (15)
		add_filter( 'the_content', array( $this, 'glossary_links' ), 20 );
		add_filter( 'comment_text', array( $this, 'glossary_comment_links' ), 20, 2 );
	}

	/**
	 * Create the link for the glossary item for the frontend.
	 *
	 * @param array $matches The matches coming from the regular expression.
	 * @return string HTML fragment representing this glossary term.
	 */
	public function glossary_item_hovercard( $matches ) {
		$found_text = $matches[1];

		$glossary_item = $this->glossary->get_active_item( $found_text, $this->context_post_id );
		if ( ! $glossary_item ) {
			return $matches[0];
		}

		if ( ! empty( $this->processed[ strtolower( $found_text ) ] ) ) {
			return $matches[0];
		}
		$this->processed[ strtolower( $found_text ) ] = true;

		// TinyMCE wraps everything in p tags on save, so let's replace them with line breaks
		$desc_html = preg_replace( '/<p>/', '', $glossary_item->description );
		$desc_html = preg_replace( '/<\/p>/', '<br />', $desc_html );
		$desc_html = preg_replace( "/\n/", '<br />', $desc_html );
		$desc_html = preg_replace( '/(<br \/>)+/', '<br />', $desc_html );
		global $allowedtags;
		$desc_html = wp_kses( $desc_html, $allowedtags );

		// Add edit link.
		is_multisite() && switch_to_blog( $glossary_item->site );
		if ( current_user_can( 'edit_post', $glossary_item->id ) ) {
			$edit_link = get_edit_post_link( $glossary_item->id );
			if ( $edit_link ) {
				$desc_html .= '<br><a href="' . esc_url( $edit_link ) . '">' . esc_html_x( 'Edit Entry', 'glossary entry edit link', 'velo-glossary' ) . '</a>';
			}
		}
		is_multisite() && restore_current_blog();

		$term_html = "<span class='glossary-item-header velo-glossary__header'>" . esc_html( $glossary_item->name ) . "</span> <span class='glossary-item-description velo-glossary__description'>$desc_html</span>";

		$replacement = sprintf(
			// NOTE: When altering this HTML, please update the relevant code in Velo_Glossary_Handler::link_glossary_terms().
			"<span tabindex='0' class='glossary-item-container velo-glossary__term'>%s<span class='glossary-item-hidden-content velo-glossary__hidden-content'>%s</span></span>",
			esc_html( $found_text ),
			$term_html
		);

		return $replacement;
	}

	/**
	 * Parses and links glossary item within a string. Run on the_content.
	 *
	 * @param string $content The content.
	 * @return string The linked content.
	 */
	public function glossary_links( $content ) {
		if ( ! Velo_Glossary_Settings::should_process_content() ) {
			return $content;
		}

		$post    = get_post();
		$post_id = $post instanceof WP_Post ? $post->ID : null;

		return $this->link_glossary_terms( $content, $post_id );
	}

	/**
	 * Parses and links glossary items within a comment string.
	 *
	 * @param string     $content The comment text.
	 * @param WP_Comment $comment The comment object.
	 * @return string The linked comment text.
	 */
	public function glossary_comment_links( $content, $comment = null ) {
		if ( ! Velo_Glossary_Settings::should_process_comment( $comment ) ) {
			return $content;
		}

		$comment = get_comment( $comment );
		$post_id = $comment instanceof WP_Comment ? $comment->comment_post_ID : null;

		return $this->link_glossary_terms( $content, $post_id );
	}

	/**
	 * Link glossary terms within a string.
	 *
	 * @param string $content The content.
	 * @param int|null $post_id Current content post ID.
	 * @return string The linked content.
	 */
	protected function link_glossary_terms( $content, $post_id = null ) {
		if ( Velo_Glossary_Settings::should_limit_terms_to_associations() && ! $post_id ) {
			return $content;
		}

		$regex = $this->glossary->get_item_names_regex( $post_id );
		if ( ! $regex ) {
			return $content;
		}

		// Used for creating a unique ID to keep track of replacements in a single post/comment
		$this->content         = $content;
		$this->processed       = array();
		$this->context_post_id = $post_id;
		$textarr               = wp_html_split( $content );

		$ignore_elements  = array( 'code', '/code', 'a', '/a', 'pre', '/pre', 'dt', '/dt', 'option', '/option' );
		$ignore_elements  = array_merge( $ignore_elements, Velo_Glossary_Settings::get_excluded_tags() );
		$excluded_classes = Velo_Glossary_Settings::get_excluded_classes();
		$inside_block     = array();

		// Close tags carry no class attribute, so class-excluded wrappers are
		// tracked by tag name with a same-name depth counter instead of the stack.
		$class_skip_tag   = '';
		$class_skip_depth = 0;

		foreach ( $textarr as &$element ) {
			$tag_name   = '';
			$is_end_tag = false;

			if ( 0 === strpos( $element, '<' ) ) {
				$offset = 1;

				if ( 1 === strpos( $element, '/' ) ) {
					$offset     = 2;
					$is_end_tag = true;
				}

				preg_match( '/^.+(\b|\n|$)/U', substr( $element, $offset ), $matches );
				$tag_name = $matches ? $matches[0] : '';
			}

			if ( $class_skip_depth > 0 ) {
				if ( $tag_name === $class_skip_tag ) {
					$class_skip_depth += $is_end_tag ? -1 : 1;
				}

				continue;
			}

			if ( $tag_name ) {
				if ( in_array( $tag_name, $ignore_elements, true ) ) {
					if ( ! $is_end_tag ) {
						array_unshift( $inside_block, $tag_name );
					} elseif ( $inside_block && $tag_name === $inside_block[0] ) {
						array_shift( $inside_block );
					}

					continue;
				}

				// Start a class-excluded zone. Also skips the Glossary item
				// container span, for when the_content is run over the_content.
				if (
					! $is_end_tag
					&& ! in_array( $tag_name, Velo_Glossary_Settings::VOID_ELEMENTS, true )
					&& (
						( $excluded_classes && $this->element_has_classes( $element, $excluded_classes ) )
						|| ( 'span' === $tag_name && $this->element_has_class( $element, 'glossary-item-container' ) )
					)
				) {
					$class_skip_tag   = $tag_name;
					$class_skip_depth = 1;

					continue;
				}
			}

			// Skip any links that will be auto-generated by make_clickable()
			// three strpos() are faster than one preg_match() here. If we need to check for more protocols, preg_match() would probably be better
			if ( strpos( $element, 'http://' ) !== false || strpos( $element, 'https://' ) !== false || strpos( $element, 'www.' ) !== false ) {
				continue;
			}

			if ( empty( $inside_block ) ) {
				$element = preg_replace_callback( $regex, array( $this, 'glossary_item_hovercard' ), $element );
			}
		}

		$this->context_post_id = null;

		return join( $textarr );
	}

	/**
	 * Determine whether an HTML element has a class token.
	 *
	 * @param string $element HTML element fragment.
	 * @param string $class_name Class name to search for.
	 * @return bool
	 */
	protected function element_has_class( $element, $class_name ) {
		$class_name = preg_quote( $class_name, '/' );

		return (bool) preg_match( '/\sclass=(["\'])(?=[^"\']*\b' . $class_name . '\b)[^"\']*\1/i', $element );
	}

	/**
	 * Determine whether an HTML element has any of the given class tokens.
	 *
	 * @param string $element HTML element fragment.
	 * @param array  $class_names Class names to search for.
	 * @return bool
	 */
	protected function element_has_classes( $element, $class_names ) {
		foreach ( $class_names as $class_name ) {
			if ( $this->element_has_class( $element, $class_name ) ) {
				return true;
			}
		}

		return false;
	}
}
