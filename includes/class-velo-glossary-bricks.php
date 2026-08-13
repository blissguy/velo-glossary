<?php
/**
 * Bricks Builder integration.
 *
 * Bricks text elements store and render their own markup, and only the Post
 * Content element runs the_content. Terms written directly into a text element
 * are therefore never linked, so the rendered element output is filtered here
 * instead.
 */

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_Bricks {
	/**
	 * Bricks element names processed by default.
	 *
	 * Leaf prose elements only. Layout elements re-render their children's markup
	 * as part of their own output, so processing them would walk the same text
	 * twice.
	 */
	const DEFAULT_ELEMENTS = array( 'text', 'text-basic' );

	/**
	 * Shared handler used for the frontend content filters.
	 *
	 * @var Velo_Glossary_Handler
	 */
	private $handler;

	/**
	 * @param Velo_Glossary_Handler $handler Shared handler instance.
	 */
	public function __construct( Velo_Glossary_Handler $handler ) {
		$this->handler = $handler;

		add_filter( 'bricks/frontend/render_element', array( $this, 'link_element_terms' ), 20, 2 );
	}

	/**
	 * Link glossary terms in the rendered output of a supported Bricks element.
	 *
	 * @param string $html Rendered element HTML.
	 * @param object $element_instance Bricks element instance.
	 * @return string
	 */
	public function link_element_terms( $html, $element_instance = null ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $html;
		}

		// The builder saves what it renders. Injecting hovercard markup there bakes
		// it into the element, and the frontend script strips the definition out of
		// that copy on first load, leaving an empty hovercard behind.
		if ( $this->is_builder_request() ) {
			return $html;
		}

		if ( ! $this->is_supported_element( $element_instance ) ) {
			return $html;
		}

		if ( ! Velo_Glossary_Settings::should_process_content() ) {
			return $html;
		}

		$post    = get_post();
		$post_id = $post instanceof WP_Post ? $post->ID : null;

		return $this->handler->link_fragment( $html, $post_id );
	}

	/**
	 * Determine whether an element's output should be processed.
	 *
	 * @param object $element_instance Bricks element instance.
	 * @return bool
	 */
	protected function is_supported_element( $element_instance ) {
		$element_name = isset( $element_instance->name ) ? (string) $element_instance->name : '';
		if ( '' === $element_name ) {
			return false;
		}

		/**
		 * Filter the Bricks element names Velo Glossary links terms in.
		 *
		 * Adding layout elements that render nested children is not supported: their
		 * output already contains the processed markup of every element inside them.
		 *
		 * @param array  $element_names Bricks element names.
		 * @param object $element_instance Bricks element instance being rendered.
		 */
		$element_names = apply_filters( 'velo_glossary_bricks_elements', self::DEFAULT_ELEMENTS, $element_instance );

		return in_array( $element_name, (array) $element_names, true );
	}

	/**
	 * Determine whether the current render is coming from the builder.
	 *
	 * @return bool
	 */
	protected function is_builder_request() {
		if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			return true;
		}

		return function_exists( 'bricks_is_builder_call' ) && bricks_is_builder_call();
	}
}
