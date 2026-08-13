<?php
/**
 * Bricks Builder integration.
 *
 * Bricks elements store and render their own markup, and only the Post Content
 * element runs the_content. Terms written directly into a Bricks element are
 * therefore never linked, so the rendered element output is filtered here
 * instead.
 *
 * Only outermost elements are processed. Bricks renders children first and
 * embeds their output in the parent, so the outermost element carries the whole
 * subtree in one string. That is what the excluded tag and excluded class rules
 * need: a wrapper's class only excludes the text inside it when the wrapper and
 * the text are scanned together, exactly as they are under the_content.
 */

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_Bricks {
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
	 * Link glossary terms in the rendered output of a Bricks element.
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

		if ( ! $this->should_process_element( $element_instance ) ) {
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
	protected function should_process_element( $element_instance ) {
		$should_process = $this->is_outermost_element( $element_instance ) && $this->is_content_area();

		/**
		 * Filter whether Velo Glossary links terms in a Bricks element's output.
		 *
		 * Only outermost elements are processed by default. Opting a nested element
		 * in means its text is scanned without its ancestors, so excluded class names
		 * on a wrapper around it no longer apply, and its parent scans the same text
		 * again afterwards.
		 *
		 * @param bool   $should_process Whether to link terms in this element.
		 * @param object $element_instance Bricks element instance being rendered.
		 */
		return (bool) apply_filters( 'velo_glossary_bricks_process_element', $should_process, $element_instance );
	}

	/**
	 * Determine whether an element sits at the top of its render area.
	 *
	 * @param object $element_instance Bricks element instance.
	 * @return bool
	 */
	protected function is_outermost_element( $element_instance ) {
		if ( ! isset( $element_instance->element ) || ! is_array( $element_instance->element ) ) {
			return false;
		}

		return empty( $element_instance->element['parent'] );
	}

	/**
	 * Determine whether Bricks is rendering the content area.
	 *
	 * Header and footer templates carry navigation and other chrome that repeats on
	 * every page, so terms are not linked there.
	 *
	 * @return bool
	 */
	protected function is_content_area() {
		if ( ! class_exists( '\Bricks\Frontend' ) ) {
			return false;
		}

		return 'content' === \Bricks\Frontend::$area;
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
