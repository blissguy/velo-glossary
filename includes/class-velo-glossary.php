<?php

/**
 * Helper class for accessing the glossary DB.
 */

defined( 'ABSPATH' ) || exit;

class Velo_Glossary {
	const ASSOCIATED_POST_META = '_velo_glossary_associated_post_id';
	const RELATED_TERM_META    = '_velo_glossary_related_term_id';
	const TAG_TAXONOMY         = 'velo_glossary_tag';

	private $cache_group   = 'velo-glossary';
	private $cache_version = 4;

	/**
	 * Construct the Glossary object.
	 */
	public function __construct() {
		// Clear the cache upon Glossary item being updated. Items on sub-sites will be cleared in an hour or so.
		add_action( 'save_post_glossary', array( $this, 'clear_cache' ), 20 );
	}

	/**
	 * Load an item from the Glossary by name.
	 *
	 * @param string $name
	 *
	 * @return false|object The glossary item or false if none exist.
	 */
	public function get_active_item( $name, $post_id = null ) {
		$case_sensitive = $this->name_is_case_sensitive( $name );

		$item = array_filter(
			$this->get_active_items( $post_id ),
			function( $item ) use ( $name, $case_sensitive ) {
				if ( $case_sensitive && $item->name === $name ) {
					return true;
				} elseif ( ! $case_sensitive && 0 === strcasecmp( $item->name, $name ) ) {
					return true;
				} elseif ( $item->alternatives ) {
					if ( $case_sensitive && in_array( $name, $item->alternatives, true ) ) {
						return true;
					} elseif ( ! $case_sensitive && in_array( strtolower( $name ), array_map( 'strtolower', $item->alternatives ), true ) ) {
						return true;
					}
				}

				return false;
			}
		);

		return array_shift( $item ) ?: false;
	}

	/**
	 * Get all item names from the glossary.
	 *
	 * @return array
	 */
	public function get_active_item_names( $post_id = null ) {
		$items = $this->get_active_items( $post_id );

		$names = array_values( wp_list_pluck( $items, 'name' ) );

		// Retrieve and flatten the list of alternative names for the glossary items.
		$alternatives = array_values( wp_list_pluck( $items, 'alternatives' ) );
		if ( $alternatives ) {
			$alternatives = call_user_func_array( 'array_merge', $alternatives );
		}

		return array_merge( $names, $alternatives );
	}

	/**
	 * Get all glossary items.
	 *
	 * @return array
	 */
	public function get_active_items( $post_id = null ) {
		$items = $this->get_all_active_items();

		if ( null === $post_id || ! Velo_Glossary_Settings::should_limit_terms_to_associations() ) {
			return $items;
		}

		return $this->filter_items_for_post(
			$items,
			$post_id,
			Velo_Glossary_Settings::should_include_unassociated_terms()
		);
	}

	/**
	 * Get glossary items associated with a post.
	 *
	 * @param int  $post_id Current content post ID.
	 * @param bool $include_unassociated Whether unassociated terms should act as global terms.
	 * @return array
	 */
	public function get_active_items_for_post( $post_id, $include_unassociated = false ) {
		return $this->filter_items_for_post( $this->get_all_active_items(), $post_id, $include_unassociated );
	}

	/**
	 * Get all glossary items without display-context filtering.
	 *
	 * @return array
	 */
	protected function get_all_active_items() {
		$cache_key = "items-v{$this->cache_version}";
		if ( ! $items = wp_cache_get( $cache_key, $this->cache_group ) ) {
			$items = array();
			if ( is_multisite() && ! is_main_site() ) {
				// Fetch any from the main parent site first.
				switch_to_blog( get_main_site_id() );

				$items = $this->get_all_active_items();

				restore_current_blog();
			}

			$posts = get_posts(
				array(
					'post_type'   => 'glossary',
					'post_status' => 'publish',
					'numberposts' => -1,
				)
			);
			foreach ( $posts as $post ) {
				$item                               = $this->post_to_glossary_item( $post );
				$items[ strtolower( $item->name ) ] = $item;
			}

			if ( $items ) {
				wp_cache_set( $cache_key, $items, $this->cache_group, HOUR_IN_SECONDS );
			}
		}

		return $items;
	}

	/**
	 * Map a Post object into a Glossary item.
	 *
	 * @param WP_Post $post The Post object
	 *
	 * @return object A Glossary item object.
	 */
	protected function post_to_glossary_item( $post ) {
		return (object) array(
			'id'                  => $post->ID,
			'site'                => get_current_blog_id(),
			'name'                => trim( $post->post_title ),
			'description'         => trim( $post->post_content ),
			'alternatives'        => get_post_meta( $post->ID, 'alternatives', true ) ?: array(),
			'associated_post_ids' => self::get_associated_post_ids( $post->ID ),
		);
	}

	/**
	 * Get all glossary items as a regex.
	 *
	 * @return false|string The Regex.
	 */
	public function get_item_names_regex( $post_id = null ) {
		$item_names = $this->get_active_item_names( $post_id );
		if ( ! $item_names ) {
			return false;
		}

		$item_names = array_filter( array_unique( $item_names ) );

		// Sort long -> short so that the longer items match first.
		usort(
			$item_names,
			function( $a, $b ) {
				return ( strlen( $a ) < strlen( $b ) ) ? 1 : -1;
			}
		);

		$regex = implode(
			'|',
			array_map(
				function( $name ) {
					return preg_quote( $name, '/' );
				},
				$item_names
			)
		);

		return "/\b($regex)(?![^<]*>|[.]\w)\b/i";
	}

	/**
	 * Clear the Glossary item cache.
	 */
	public function clear_cache() {
		wp_cache_delete( "items-v{$this->cache_version}", $this->cache_group );
	}

	/**
	 * Get content post IDs associated with a glossary entry.
	 *
	 * @param int $glossary_id Glossary post ID.
	 * @return array
	 */
	public static function get_associated_post_ids( $glossary_id ) {
		$post_ids = get_post_meta( $glossary_id, self::ASSOCIATED_POST_META, false );
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids );

		return array_values( array_unique( $post_ids ) );
	}

	/**
	 * Replace all associated content meta rows for a glossary entry.
	 *
	 * @param int   $glossary_id Glossary post ID.
	 * @param array $post_ids Associated content post IDs.
	 */
	public static function set_associated_post_ids( $glossary_id, $post_ids ) {
		$glossary_id = absint( $glossary_id );
		$post_ids    = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );

		delete_post_meta( $glossary_id, self::ASSOCIATED_POST_META );

		foreach ( $post_ids as $post_id ) {
			add_post_meta( $glossary_id, self::ASSOCIATED_POST_META, $post_id, false );
		}
	}

	/**
	 * Get glossary entry IDs related to another glossary entry.
	 *
	 * @param int $glossary_id Glossary post ID.
	 * @return array
	 */
	public static function get_related_term_ids( $glossary_id ) {
		$term_ids = get_post_meta( $glossary_id, self::RELATED_TERM_META, false );
		$term_ids = array_map( 'absint', $term_ids );
		$term_ids = array_filter(
			$term_ids,
			function( $term_id ) use ( $glossary_id ) {
				return $term_id && $term_id !== absint( $glossary_id ) && 'glossary' === get_post_type( $term_id );
			}
		);

		return array_values( array_unique( $term_ids ) );
	}

	/**
	 * Save related term IDs and mirror the relationship on the related terms.
	 *
	 * @param int   $glossary_id Glossary post ID.
	 * @param array $new_related_ids Related glossary term IDs.
	 */
	public static function set_related_term_ids( $glossary_id, $new_related_ids ) {
		$glossary_id     = absint( $glossary_id );
		$new_related_ids = array_values( array_unique( array_map( 'absint', $new_related_ids ) ) );
		$old_related_ids = self::get_related_term_ids( $glossary_id );
		$all_related_ids = array_values( array_unique( array_merge( $old_related_ids, $new_related_ids ) ) );

		self::replace_related_term_meta( $glossary_id, $new_related_ids );

		foreach ( $all_related_ids as $related_id ) {
			$related_ids = self::get_related_term_ids( $related_id );

			if ( in_array( $related_id, $new_related_ids, true ) ) {
				$related_ids[] = $glossary_id;
			} else {
				$related_ids = array_diff( $related_ids, array( $glossary_id ) );
			}

			self::replace_related_term_meta( $related_id, $related_ids );
		}
	}

	/**
	 * Replace all related term meta rows for a glossary entry.
	 *
	 * @param int   $glossary_id Glossary post ID.
	 * @param array $related_ids Related glossary term IDs.
	 */
	public static function replace_related_term_meta( $glossary_id, $related_ids ) {
		$glossary_id = absint( $glossary_id );
		$related_ids = array_filter(
			array_values( array_unique( array_map( 'absint', $related_ids ) ) ),
			function( $related_id ) use ( $glossary_id ) {
				return $related_id && $related_id !== $glossary_id && 'glossary' === get_post_type( $related_id );
			}
		);

		delete_post_meta( $glossary_id, self::RELATED_TERM_META );

		foreach ( $related_ids as $related_id ) {
			add_post_meta( $glossary_id, self::RELATED_TERM_META, $related_id, false );
		}
	}

	/**
	 * Filter glossary items down to items available for a content post.
	 *
	 * @param array $items All active glossary items.
	 * @param int   $post_id Current content post ID.
	 * @param bool  $include_unassociated Whether unassociated terms should act as global terms.
	 * @return array
	 */
	protected function filter_items_for_post( $items, $post_id, $include_unassociated ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return array();
		}

		return array_filter(
			$items,
			function( $item ) use ( $post_id, $include_unassociated ) {
				if ( empty( $item->associated_post_ids ) ) {
					return $include_unassociated;
				}

				return in_array( $post_id, $item->associated_post_ids, true );
			}
		);
	}

	/**
	 * Determine if a name should be case sensitive
	 */
	public function name_is_case_sensitive( $name ) {
		return strlen( $name ) <= 3;
	}
}
