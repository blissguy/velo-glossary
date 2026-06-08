<?php
/**
 * Abilities API integration for Velo Glossary.
 *
 * @package Velo_Glossary
 */

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_Abilities {
	const CATEGORY = 'velo-glossary';

	/**
	 * Post statuses agents may assign through the save ability.
	 *
	 * @var array
	 */
	protected static array $editable_statuses = array( 'publish', 'draft', 'pending', 'private' );

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the Velo Glossary ability category.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Velo Glossary', 'velo-glossary' ),
				'description' => __( 'Abilities for reading and managing Velo Glossary terms.', 'velo-glossary' ),
			)
		);
	}

	/**
	 * Register glossary abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'velo-glossary/list-terms',
			array(
				'label'               => __( 'List glossary terms', 'velo-glossary' ),
				'description'         => __( 'Searches and lists glossary terms with optional status, tag, association, and related-term filters.', 'velo-glossary' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'list_terms' ),
				'permission_callback' => array( $this, 'can_read_terms' ),
				'input_schema'        => $this->get_list_input_schema(),
				'meta'                => $this->get_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'velo-glossary/get-term',
			array(
				'label'               => __( 'Get glossary term', 'velo-glossary' ),
				'description'         => __( 'Retrieves one glossary term by ID, including alternatives, tags, associated content IDs, and related term IDs.', 'velo-glossary' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'get_term' ),
				'permission_callback' => array( $this, 'can_read_term' ),
				'input_schema'        => $this->get_id_input_schema(),
				'meta'                => $this->get_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'velo-glossary/save-term',
			array(
				'label'               => __( 'Create or update glossary term', 'velo-glossary' ),
				'description'         => __( 'Creates a new glossary term or updates an existing term. Supports definition, status, alternative terms, tags, associated content IDs, and related term IDs.', 'velo-glossary' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'save_term' ),
				'permission_callback' => array( $this, 'can_save_term' ),
				'input_schema'        => $this->get_save_input_schema(),
				'meta'                => $this->get_meta( false, false, true ),
			)
		);

		wp_register_ability(
			'velo-glossary/trash-term',
			array(
				'label'               => __( 'Trash glossary term', 'velo-glossary' ),
				'description'         => __( 'Moves a glossary term to the trash. This does not permanently delete the term.', 'velo-glossary' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'trash_term' ),
				'permission_callback' => array( $this, 'can_trash_term' ),
				'input_schema'        => $this->get_id_input_schema(),
				'meta'                => $this->get_meta( false, true, true ),
			)
		);
	}

	/**
	 * List glossary terms.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function list_terms( array $input ) {
		$input = wp_parse_args(
			$input,
			array(
				'search'                => '',
				'status'                => 'any',
				'tag'                   => '',
				'associated_content_id' => 0,
				'related_term_id'       => 0,
				'page'                  => 1,
				'per_page'              => 20,
			)
		);

		$query_args = array(
			'post_type'      => 'glossary',
			'post_status'    => $this->normalize_list_status( $input['status'] ),
			'posts_per_page' => max( 1, min( 100, absint( $input['per_page'] ) ) ),
			'paged'          => max( 1, absint( $input['page'] ) ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( '' !== trim( (string) $input['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( '' !== trim( (string) $input['tag'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Agents need to filter glossary terms by the plugin's native tag taxonomy.
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => Velo_Glossary::TAG_TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( $input['tag'] ),
				),
			);
		}

		$meta_query = array();
		if ( ! empty( $input['associated_content_id'] ) ) {
			$meta_query[] = array(
				'key'     => Velo_Glossary::ASSOCIATED_POST_META,
				'value'   => absint( $input['associated_content_id'] ),
				'compare' => '=',
			);
		}

		if ( ! empty( $input['related_term_id'] ) ) {
			$meta_query[] = array(
				'key'     => Velo_Glossary::RELATED_TERM_META,
				'value'   => absint( $input['related_term_id'] ),
				'compare' => '=',
			);
		}

		if ( $meta_query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Relationship lookups are stored as repeatable post meta for builder-friendly WP_Query support.
			$query_args['meta_query'] = $meta_query;
		}

		$query = new WP_Query( $query_args );

		return array(
			'terms'       => array_map( array( $this, 'format_term' ), $query->posts ),
			'total'       => absint( $query->found_posts ),
			'total_pages' => absint( $query->max_num_pages ),
			'page'        => absint( $query_args['paged'] ),
			'per_page'    => absint( $query_args['posts_per_page'] ),
		);
	}

	/**
	 * Retrieve one glossary term.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_term( array $input ) {
		$post = $this->get_glossary_post_from_input( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->format_term( $post );
	}

	/**
	 * Create or update a glossary term.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function save_term( array $input ) {
		$post_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		$is_new  = ! $post_id;

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || 'glossary' !== $post->post_type ) {
				return $this->error( 'invalid_id', __( 'The id must reference an existing glossary term.', 'velo-glossary' ) );
			}
		}

		$title = isset( $input['term'] ) ? sanitize_text_field( $input['term'] ) : '';
		if ( $is_new && '' === $title ) {
			return $this->error( 'missing_term', __( 'A term is required when creating a glossary entry.', 'velo-glossary' ) );
		}

		$postarr = array(
			'post_type' => 'glossary',
		);

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
		} else {
			$postarr['post_author'] = get_current_user_id();
			$postarr['post_status'] = 'draft';
		}

		if ( '' !== $title ) {
			$postarr['post_title'] = $title;
		}

		if ( array_key_exists( 'definition', $input ) ) {
			$postarr['post_content'] = wp_kses_post( (string) $input['definition'] );
		}

		if ( array_key_exists( 'status', $input ) && '' !== (string) $input['status'] ) {
			$status = sanitize_key( $input['status'] );
			if ( ! in_array( $status, self::$editable_statuses, true ) ) {
				return $this->error( 'invalid_status', __( 'Status must be publish, draft, pending, or private.', 'velo-glossary' ) );
			}

			$publish_cap = $this->get_post_type_cap( 'publish_posts' );
			if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $publish_cap ) ) {
				return $this->error( 'invalid_status', __( 'You are not allowed to publish glossary terms.', 'velo-glossary' ) );
			}

			$postarr['post_status'] = $status;
		}

		$result = $post_id ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = absint( $result );

		if ( array_key_exists( 'alternative_terms', $input ) ) {
			update_post_meta( $post_id, 'alternatives', $this->sanitize_string_list( $input['alternative_terms'], 2 ) );
		}

		if ( array_key_exists( 'tags', $input ) ) {
			$tag_result = $this->set_tags( $post_id, $input['tags'] );
			if ( is_wp_error( $tag_result ) ) {
				return $tag_result;
			}
		}

		if ( array_key_exists( 'associated_content_ids', $input ) ) {
			$associated_ids = $this->validate_associated_content_ids( $input['associated_content_ids'] );
			if ( is_wp_error( $associated_ids ) ) {
				return $associated_ids;
			}
			Velo_Glossary::set_associated_post_ids( $post_id, $associated_ids );
		}

		if ( array_key_exists( 'related_term_ids', $input ) ) {
			$related_ids = $this->validate_related_term_ids( $input['related_term_ids'], $post_id );
			if ( is_wp_error( $related_ids ) ) {
				return $related_ids;
			}
			Velo_Glossary::set_related_term_ids( $post_id, $related_ids );
		}

		return $this->format_term( get_post( $post_id ) );
	}

	/**
	 * Trash one glossary term.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function trash_term( array $input ) {
		$post = $this->get_glossary_post_from_input( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( 'trash' !== $post->post_status ) {
			$trashed = wp_trash_post( $post->ID );
			if ( ! $trashed instanceof WP_Post ) {
				return $this->error( 'term_data_unavailable', __( 'The glossary term could not be moved to the trash.', 'velo-glossary' ) );
			}
			$post = get_post( $post->ID );
		}

		return $this->format_term( $post );
	}

	/**
	 * Permission callback for listing glossary terms.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_terms( array $input = array() ): bool {
		return current_user_can( $this->get_post_type_cap( 'edit_posts' ) );
	}

	/**
	 * Permission callback for reading one glossary term.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_term( array $input ): bool {
		$post_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( ! $post_id ) {
			return current_user_can( $this->get_post_type_cap( 'edit_posts' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'glossary' !== $post->post_type ) {
			return current_user_can( $this->get_post_type_cap( 'edit_posts' ) );
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Permission callback for creating or updating a glossary term.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_save_term( array $input ): bool {
		$post_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || 'glossary' !== $post->post_type ) {
				return current_user_can( $this->get_post_type_cap( 'edit_posts' ) );
			}

			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( $this->get_post_type_cap( 'edit_posts' ) );
	}

	/**
	 * Permission callback for trashing a glossary term.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_trash_term( array $input ): bool {
		$post_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( ! $post_id ) {
			return current_user_can( $this->get_post_type_cap( 'edit_posts' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'glossary' !== $post->post_type ) {
			return current_user_can( $this->get_post_type_cap( 'edit_posts' ) );
		}

		return current_user_can( 'delete_post', $post_id );
	}

	/**
	 * Format one glossary term for ability output.
	 *
	 * @param WP_Post|null $post Glossary post.
	 * @return array
	 */
	protected function format_term( ?WP_Post $post ): array {
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$alternatives = get_post_meta( $post->ID, 'alternatives', true );
		$alternatives = is_array( $alternatives ) ? array_values( $alternatives ) : array();
		$tags         = get_the_terms( $post->ID, Velo_Glossary::TAG_TAXONOMY );
		$tag_names    = is_array( $tags ) ? array_values( wp_list_pluck( $tags, 'name' ) ) : array();
		$tag_slugs    = is_array( $tags ) ? array_values( wp_list_pluck( $tags, 'slug' ) ) : array();

		return array(
			'id'                     => absint( $post->ID ),
			'term'                   => get_post_field( 'post_title', $post->ID, 'raw' ),
			'definition'             => get_post_field( 'post_content', $post->ID, 'raw' ),
			'status'                 => $post->post_status,
			'alternative_terms'      => $alternatives,
			'tags'                   => $tag_names,
			'tag_slugs'              => $tag_slugs,
			'associated_content_ids' => Velo_Glossary::get_associated_post_ids( $post->ID ),
			'related_term_ids'       => Velo_Glossary::get_related_term_ids( $post->ID ),
			'edit_link'              => get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	/**
	 * Get a glossary post from ability input.
	 *
	 * @param array $input Ability input.
	 * @return WP_Post|WP_Error
	 */
	protected function get_glossary_post_from_input( array $input ) {
		$post_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( ! $post_id ) {
			return $this->error( 'missing_id', __( 'A glossary term id is required.', 'velo-glossary' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'glossary' !== $post->post_type ) {
			return $this->error( 'invalid_id', __( 'The id must reference an existing glossary term.', 'velo-glossary' ) );
		}

		return $post;
	}

	/**
	 * Set glossary tags by tag name.
	 *
	 * @param int   $post_id Glossary post ID.
	 * @param mixed $tags Raw tag list.
	 * @return true|WP_Error
	 */
	protected function set_tags( int $post_id, $tags ) {
		$taxonomy = get_taxonomy( Velo_Glossary::TAG_TAXONOMY );
		if ( ! $taxonomy ) {
			return $this->error( 'not_initialized', __( 'The glossary tag taxonomy is not registered.', 'velo-glossary' ) );
		}

		if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return $this->error( 'invalid_tags', __( 'You are not allowed to assign glossary tags.', 'velo-glossary' ) );
		}

		$tag_names = $this->sanitize_string_list( $tags, 1 );
		foreach ( $tag_names as $tag_name ) {
			if ( ! term_exists( $tag_name, Velo_Glossary::TAG_TAXONOMY ) && ! current_user_can( $taxonomy->cap->manage_terms ) ) {
				return $this->error( 'invalid_tags', __( 'You are not allowed to create new glossary tags.', 'velo-glossary' ) );
			}
		}

		$result = wp_set_object_terms( $post_id, $tag_names, Velo_Glossary::TAG_TAXONOMY, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Validate associated content IDs.
	 *
	 * @param mixed $ids Raw IDs.
	 * @return array|WP_Error
	 */
	protected function validate_associated_content_ids( $ids ) {
		$ids     = $this->sanitize_id_list( $ids );
		$invalid = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post || ! Velo_Glossary_Admin::is_associable_post_for_user( $post ) ) {
				$invalid[] = $id;
			}
		}

		if ( $invalid ) {
			return $this->error(
				'invalid_associated_content_ids',
				sprintf(
					/* translators: %s: comma-separated invalid post IDs. */
					__( 'These associated content IDs are invalid or not editable: %s.', 'velo-glossary' ),
					implode( ', ', $invalid )
				)
			);
		}

		return $ids;
	}

	/**
	 * Validate related glossary term IDs.
	 *
	 * @param mixed $ids Raw IDs.
	 * @param int   $current_post_id Current glossary term ID.
	 * @return array|WP_Error
	 */
	protected function validate_related_term_ids( $ids, int $current_post_id ) {
		$ids     = $this->sanitize_id_list( $ids );
		$invalid = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! Velo_Glossary_Admin::is_related_term_candidate_for_user( $post, $current_post_id ) ) {
				$invalid[] = $id;
			}
		}

		if ( $invalid ) {
			return $this->error(
				'invalid_related_term_ids',
				sprintf(
					/* translators: %s: comma-separated invalid glossary term IDs. */
					__( 'These related term IDs are invalid or not editable: %s.', 'velo-glossary' ),
					implode( ', ', $invalid )
				)
			);
		}

		return $ids;
	}

	/**
	 * Sanitize a list of integer IDs.
	 *
	 * @param mixed $ids Raw IDs.
	 * @return array
	 */
	protected function sanitize_id_list( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Sanitize a list of strings.
	 *
	 * @param mixed $values Raw values.
	 * @param int   $minimum_length Minimum accepted string length.
	 * @return array
	 */
	protected function sanitize_string_list( $values, int $minimum_length ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$values = array_map( 'sanitize_text_field', $values );
		$values = array_map( 'trim', $values );
		$values = array_filter(
			$values,
			static function ( string $value ) use ( $minimum_length ): bool {
				return strlen( $value ) >= $minimum_length;
			}
		);

		return array_values( array_unique( $values ) );
	}

	/**
	 * Normalize list status filter.
	 *
	 * @param string $status Raw status.
	 * @return string|array
	 */
	protected function normalize_list_status( string $status ) {
		return match ( sanitize_key( $status ) ) {
			'publish', 'draft', 'pending', 'private', 'trash' => sanitize_key( $status ),
			default => array( 'publish', 'draft', 'pending', 'private' ),
		};
	}

	/**
	 * Get a capability from the glossary post type object.
	 *
	 * @param string $cap Capability key.
	 * @return string
	 */
	protected function get_post_type_cap( string $cap ): string {
		$post_type = get_post_type_object( 'glossary' );
		if ( $post_type && isset( $post_type->cap->$cap ) ) {
			return $post_type->cap->$cap;
		}

		return $cap;
	}

	/**
	 * Build ability metadata.
	 *
	 * @param bool $readonly Whether the ability is read-only.
	 * @param bool $destructive Whether the ability is destructive.
	 * @param bool $idempotent Whether the ability is idempotent.
	 * @return array
	 */
	protected function get_meta( bool $readonly, bool $destructive, bool $idempotent ): array {
		return array(
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations'  => array(
				'readonly'    => $readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
		);
	}

	/**
	 * Build a plugin-prefixed WP_Error.
	 *
	 * @param string $code Error code suffix.
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	protected function error( string $code, string $message ): WP_Error {
		return new WP_Error( 'velo_glossary_' . $code, $message );
	}

	/**
	 * Input schema for list-terms.
	 *
	 * @return array
	 */
	protected function get_list_input_schema(): array {
		return array(
			'type'                 => 'object',
			'default'              => array(),
			'additionalProperties' => false,
			'properties'           => array(
				'search'                => array(
					'type'        => 'string',
					'description' => __( 'Optional search string for term titles and definitions.', 'velo-glossary' ),
					'default'     => '',
				),
				'status'                => array(
					'type'        => 'string',
					'description' => __( 'Post status filter. Use any to include editable non-trash statuses.', 'velo-glossary' ),
					'enum'        => array( 'any', 'publish', 'draft', 'pending', 'private', 'trash' ),
					'default'     => 'any',
				),
				'tag'                   => array(
					'type'        => 'string',
					'description' => __( 'Optional glossary tag slug.', 'velo-glossary' ),
					'default'     => '',
				),
				'associated_content_id' => array(
					'type'        => 'integer',
					'description' => __( 'Optional associated content post ID filter.', 'velo-glossary' ),
					'default'     => 0,
				),
				'related_term_id'       => array(
					'type'        => 'integer',
					'description' => __( 'Optional related glossary term ID filter.', 'velo-glossary' ),
					'default'     => 0,
				),
				'page'                  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
				),
				'per_page'              => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				),
			),
		);
	}

	/**
	 * Input schema for get/trash by ID.
	 *
	 * @return array
	 */
	protected function get_id_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'description' => __( 'Glossary term post ID.', 'velo-glossary' ),
					'minimum'     => 1,
				),
			),
		);
	}

	/**
	 * Input schema for save-term.
	 *
	 * @return array
	 */
	protected function get_save_input_schema(): array {
		$string_list = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
		$id_list     = array(
			'type'  => 'array',
			'items' => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'id'                     => array(
					'type'        => 'integer',
					'description' => __( 'Existing glossary term ID. Omit to create a new term.', 'velo-glossary' ),
					'minimum'     => 1,
				),
				'term'                   => array(
					'type'        => 'string',
					'description' => __( 'Glossary term title. Required when creating a new term.', 'velo-glossary' ),
				),
				'definition'             => array(
					'type'        => 'string',
					'description' => __( 'Glossary definition/content. HTML is sanitized with the normal post content allowlist.', 'velo-glossary' ),
				),
				'status'                 => array(
					'type'        => 'string',
					'description' => __( 'Post status for the glossary term.', 'velo-glossary' ),
					'enum'        => self::$editable_statuses,
				),
				'alternative_terms'      => $string_list,
				'tags'                   => $string_list,
				'associated_content_ids' => $id_list,
				'related_term_ids'       => $id_list,
			),
		);
	}
}
