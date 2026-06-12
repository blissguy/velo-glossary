<?php

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_Admin {

	/**
	 * Construct the Glossary object.
	 */
	public function __construct() {
		// Must be after Handbooks Glossary loaded on init priority 10
		add_action( 'init', array( $this, 'register_post_type' ), 20 );
		add_action( 'init', array( $this, 'register_glossary_taxonomy' ), 21 );
		add_action( 'init', array( $this, 'register_association_meta' ), 22 );
		add_action( 'init', array( $this, 'register_related_term_meta' ), 23 );

		add_action( 'add_meta_boxes', array( $this, 'register_glossary_metaboxes' ) );
		add_action( 'edit_form_after_title', array( $this, 'form_after_title' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_velo_glossary_search_content', array( $this, 'ajax_search_content' ) );
		add_action( 'wp_ajax_velo_glossary_search_terms', array( $this, 'ajax_search_terms' ) );
		add_action( 'save_post_glossary', array( $this, 'save_alternatives_metabox' ) );
		add_action( 'save_post_glossary', array( $this, 'save_associations_metabox' ) );
		add_action( 'save_post_glossary', array( $this, 'save_related_terms_metabox' ) );
		add_action( 'before_delete_post', array( $this, 'remove_deleted_glossary_term_relationships' ), 10, 2 );
	}

	/**
	 * When the Handbook Glossary post_type isn't available, register our own.
	 */
	public function register_post_type() {
		if ( post_type_exists( 'glossary' ) ) {
			return;
		}

		$enable_single_pages = Velo_Glossary_Settings::should_enable_entry_single_pages();

		register_post_type(
			'glossary',
			array(
				'labels'             => array(
					'name'               => _x( 'Glossary', 'post type general name', 'velo-glossary' ),
					'singular_name'      => _x( 'Glossary Entry', 'post type singular name', 'velo-glossary' ),
					'add_new'            => _x( 'Add New', 'glossary entry', 'velo-glossary' ),
					'add_new_item'       => _x( 'Add New Entry', 'glossary entry', 'velo-glossary' ),
					'edit_item'          => _x( 'Edit Entry', 'glossary entry', 'velo-glossary' ),
					'new_item'           => _x( 'New Entry', 'glossary entry', 'velo-glossary' ),
					'view_item'          => _x( 'View Entry', 'glossary entry', 'velo-glossary' ),
					'search_items'       => _x( 'Search Glossary', 'glossary entry', 'velo-glossary' ),
					'not_found'          => _x( 'No entries found', 'glossary entry', 'velo-glossary' ),
					'not_found_in_trash' => _x( 'No entries found in Trash', 'glossary entry', 'velo-glossary' ),
					'parent_item_colon'  => _x( 'Parent Entry:', 'glossary entry', 'velo-glossary' ),
					'menu_name'          => _x( 'Glossary', 'admin menu', 'velo-glossary' ),
					'name_admin_bar'     => _x( 'Glossary Entry', 'add new on admin bar', 'velo-glossary' ),
				),
				'public'             => true,
				'publicly_queryable' => $enable_single_pages,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'hierarchical'       => false,
				'has_archive'        => false,
				'rewrite'            => $enable_single_pages ? array(
					'slug'       => 'glossary',
					'with_front' => false,
				) : false,
				'query_var'          => $enable_single_pages ? 'glossary' : false,
				'supports'           => array( 'title', 'editor', 'revisions' ),
			)
		);
	}

	/**
	 * Register glossary-only tags for organizing and querying terms.
	 */
	public function register_glossary_taxonomy() {
		if ( taxonomy_exists( Velo_Glossary::TAG_TAXONOMY ) ) {
			register_taxonomy_for_object_type( Velo_Glossary::TAG_TAXONOMY, 'glossary' );
			return;
		}

		register_taxonomy(
			Velo_Glossary::TAG_TAXONOMY,
			'glossary',
			array(
				'labels'            => array(
					'name'                       => _x( 'Glossary Tags', 'taxonomy general name', 'velo-glossary' ),
					'singular_name'              => _x( 'Glossary Tag', 'taxonomy singular name', 'velo-glossary' ),
					'search_items'               => __( 'Search Glossary Tags', 'velo-glossary' ),
					'popular_items'              => __( 'Popular Glossary Tags', 'velo-glossary' ),
					'all_items'                  => __( 'All Glossary Tags', 'velo-glossary' ),
					'edit_item'                  => __( 'Edit Glossary Tag', 'velo-glossary' ),
					'view_item'                  => __( 'View Glossary Tag', 'velo-glossary' ),
					'update_item'                => __( 'Update Glossary Tag', 'velo-glossary' ),
					'add_new_item'               => __( 'Add New Glossary Tag', 'velo-glossary' ),
					'new_item_name'              => __( 'New Glossary Tag Name', 'velo-glossary' ),
					'separate_items_with_commas' => __( 'Separate glossary tags with commas', 'velo-glossary' ),
					'add_or_remove_items'        => __( 'Add or remove glossary tags', 'velo-glossary' ),
					'choose_from_most_used'      => __( 'Choose from the most used glossary tags', 'velo-glossary' ),
					'not_found'                  => __( 'No glossary tags found.', 'velo-glossary' ),
					'menu_name'                  => __( 'Glossary Tags', 'velo-glossary' ),
				),
				'public'             => true,
				'publicly_queryable' => Velo_Glossary_Settings::should_enable_tag_archives(),
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'hierarchical'       => false,
				'rewrite'            => false,
				'query_var'          => Velo_Glossary_Settings::should_enable_tag_archives() ? Velo_Glossary::TAG_TAXONOMY : false,
			)
		);
	}

	/**
	 * Register the association metadata shape.
	 */
	public function register_association_meta() {
		register_post_meta(
			'glossary',
			Velo_Glossary::ASSOCIATED_POST_META,
			array(
				'type'              => 'integer',
				'description'       => __( 'Content associated with this glossary entry.', 'velo-glossary' ),
				'single'            => false,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Register related glossary term metadata.
	 */
	public function register_related_term_meta() {
		register_post_meta(
			'glossary',
			Velo_Glossary::RELATED_TERM_META,
			array(
				'type'              => 'integer',
				'description'       => __( 'Glossary entries related to this glossary entry.', 'velo-glossary' ),
				'single'            => false,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Register Glossary edit metaboxes.
	 */
	public function register_glossary_metaboxes() {
		add_meta_box(
			'alternate-names',
			__( 'Alternate Names', 'velo-glossary' ),
			array( $this, 'alternative_names_metabox' ),
			'glossary',
			'advanced',
			'high',
			array(
				'__block_editor_compatible_meta_box' => true,
			)
		);

		add_meta_box(
			'velo-glossary-associated-content',
			__( 'Associated Content', 'velo-glossary' ),
			array( $this, 'associated_content_metabox' ),
			'glossary',
			'advanced',
			'default',
			array(
				'__block_editor_compatible_meta_box' => true,
			)
		);

		add_meta_box(
			'velo-glossary-related-terms',
			__( 'Related Terms', 'velo-glossary' ),
			array( $this, 'related_terms_metabox' ),
			'glossary',
			'advanced',
			'default',
			array(
				'__block_editor_compatible_meta_box' => true,
			)
		);
	}

	/**
	 * Display the 'Advanced' metaboxes after the title on the Glossary edit screen.
	 */
	public function form_after_title() {
		global $post, $wp_meta_boxes;

		$screen = get_current_screen();
		if ( $screen && $screen->is_block_editor() ) {
			return;
		}

		if ( 'glossary' === $post->post_type ) {
			do_meta_boxes( $screen, 'advanced', $post );
			unset( $wp_meta_boxes['glossary']['advanced'] );
		}
	}

	/**
	 * Output a Alternative Names metabox on the edit screen.
	 */
	public function alternative_names_metabox( $post ) {
		$alternatives = get_post_meta( $post->ID, 'alternatives', true ) ?: array();

		wp_nonce_field( 'velo_glossary_alternatives', 'velo_glossary_alternatives_nonce' );

		echo '<p><label for="alternative_names">' . esc_html__( 'Comma-separated list of alternative names or abbreviations matching this glossary entry. For example, "WordCamp, WC, WordCamps"', 'velo-glossary' ) . '</label></p>';
		echo '<input type="text" id="alternative_names" name="alternative_names" class="large-text" value="' . esc_attr( implode( ', ', $alternatives ) ) . '" />';
	}

	/**
	 * Output the Associated Content metabox on the edit screen.
	 *
	 * @param WP_Post $post Current glossary post.
	 */
	public function associated_content_metabox( $post ) {
		$associated_posts = $this->get_associated_content_posts( $post->ID );

		wp_nonce_field( 'velo_glossary_associations', 'velo_glossary_associations_nonce' );
		?>
		<p><?php esc_html_e( 'Associate this glossary entry with specific content.', 'velo-glossary' ); ?></p>
		<div class="velo-glossary-content-picker">
			<label for="velo-glossary-associated-content-search"><?php esc_html_e( 'Search content', 'velo-glossary' ); ?></label>
			<input
				type="search"
				id="velo-glossary-associated-content-search"
				class="regular-text"
				autocomplete="off"
				placeholder="<?php esc_attr_e( 'Type a title or ID', 'velo-glossary' ); ?>"
			/>
			<p class="description"><?php esc_html_e( 'Select posts, pages, or public custom post type entries related to this glossary term.', 'velo-glossary' ); ?></p>
			<ul id="velo-glossary-associated-content-list" class="velo-glossary-associated-content-list">
				<?php foreach ( $associated_posts as $associated_post ) : ?>
					<?php $this->render_associated_content_item( $associated_post ); ?>
				<?php endforeach; ?>
			</ul>
			<p class="description velo-glossary-associated-content-empty<?php echo $associated_posts ? ' hidden' : ''; ?>">
				<?php esc_html_e( 'No content is associated with this glossary entry yet.', 'velo-glossary' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Output the Related Terms metabox on the edit screen.
	 *
	 * @param WP_Post $post Current glossary post.
	 */
	public function related_terms_metabox( $post ) {
		$related_terms = $this->get_related_term_posts( $post->ID );

		wp_nonce_field( 'velo_glossary_related_terms', 'velo_glossary_related_terms_nonce' );
		?>
		<p><?php esc_html_e( 'Connect this glossary entry to other glossary terms.', 'velo-glossary' ); ?></p>
		<div class="velo-glossary-related-terms-picker">
			<label for="velo-glossary-related-terms-search"><?php esc_html_e( 'Search glossary terms', 'velo-glossary' ); ?></label>
			<input
				type="search"
				id="velo-glossary-related-terms-search"
				class="regular-text"
				autocomplete="off"
				placeholder="<?php esc_attr_e( 'Type a term title or ID', 'velo-glossary' ); ?>"
				data-current-post-id="<?php echo esc_attr( $post->ID ); ?>"
			/>
			<p class="description"><?php esc_html_e( 'Select other glossary entries related to this term. This does not change where hovercards appear.', 'velo-glossary' ); ?></p>
			<ul id="velo-glossary-related-terms-list" class="velo-glossary-related-terms-list">
				<?php foreach ( $related_terms as $related_term ) : ?>
					<?php $this->render_related_term_item( $related_term ); ?>
				<?php endforeach; ?>
			</ul>
			<p class="description velo-glossary-related-terms-empty<?php echo $related_terms ? ' hidden' : ''; ?>">
				<?php esc_html_e( 'No related terms are selected yet.', 'velo-glossary' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets for the Glossary edit screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'glossary' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'velo-glossary-admin',
			plugins_url( '../css/velo-glossary-admin.css', __FILE__ ),
			array(),
			'20260608'
		);

		wp_enqueue_script(
			'velo-glossary-admin',
			plugins_url( '../js/velo-glossary-admin.js', __FILE__ ),
			array( 'jquery', 'jquery-ui-autocomplete' ),
			'20260608',
			true
		);

		wp_localize_script(
			'velo-glossary-admin',
			'veloGlossaryAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'velo_glossary_search_content' ),
				'strings' => array(
					'noResults' => __( 'No matching content found.', 'velo-glossary' ),
					'remove'    => __( 'Remove', 'velo-glossary' ),
				),
			)
		);
	}

	/**
	 * Search associable content for the admin autocomplete field.
	 */
	public function ajax_search_content() {
		check_ajax_referer( 'velo_glossary_search_content', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to search content.', 'velo-glossary' ) ), 403 );
		}

		$term     = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$selected = isset( $_GET['selected'] ) ? wp_parse_id_list( wp_unslash( $_GET['selected'] ) ) : array();
		$results  = array();
		$seen     = array();

		if ( is_numeric( $term ) ) {
			$post = get_post( absint( $term ) );
			if ( $post instanceof WP_Post && $this->is_associable_post( $post ) && ! in_array( $post->ID, $selected, true ) ) {
				$results[]         = $this->format_content_result( $post );
				$seen[ $post->ID ] = true;
			}
		}

			$query_args = array(
				'post_type'      => array_keys( self::get_associable_post_types() ),
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			);

		if ( '' !== $term ) {
			$query_args['s'] = $term;
		}

			$posts = get_posts( $query_args );
			foreach ( $posts as $post ) {
				if ( isset( $seen[ $post->ID ] ) || in_array( $post->ID, $selected, true ) || ! $this->is_associable_post( $post ) ) {
					continue;
				}

			$results[]         = $this->format_content_result( $post );
			$seen[ $post->ID ] = true;
		}

		wp_send_json( $results );
	}

	/**
	 * Search glossary terms for the admin related terms autocomplete field.
	 */
	public function ajax_search_terms() {
		check_ajax_referer( 'velo_glossary_search_content', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to search glossary terms.', 'velo-glossary' ) ), 403 );
		}

		$term            = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$selected        = isset( $_GET['selected'] ) ? wp_parse_id_list( wp_unslash( $_GET['selected'] ) ) : array();
		$current_post_id = isset( $_GET['currentPostId'] ) ? absint( $_GET['currentPostId'] ) : 0;
		$results         = array();
		$seen            = array();

		if ( $current_post_id ) {
			$selected[] = $current_post_id;
		}

		$selected = array_values( array_unique( array_filter( $selected ) ) );

		if ( is_numeric( $term ) ) {
			$post = get_post( absint( $term ) );
			if ( $this->is_related_term_candidate( $post, $current_post_id ) && ! in_array( $post->ID, $selected, true ) ) {
				$results[]         = $this->format_related_term_result( $post );
				$seen[ $post->ID ] = true;
			}
		}

		$query_args = array(
			'post_type'      => 'glossary',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		if ( '' !== $term ) {
			$query_args['s'] = $term;
		}

		$posts = get_posts( $query_args );
		foreach ( $posts as $post ) {
			if ( isset( $seen[ $post->ID ] ) || in_array( $post->ID, $selected, true ) || ! $this->is_related_term_candidate( $post, $current_post_id ) ) {
				continue;
			}

			$results[]         = $this->format_related_term_result( $post );
			$seen[ $post->ID ] = true;
		}

		wp_send_json( $results );
	}

	/**
	 * Save the Alternative Names metabox details.
	 */
	public function save_alternatives_metabox( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['velo_glossary_alternatives_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['velo_glossary_alternatives_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'velo_glossary_alternatives' ) ) {
			return;
		}
		if ( ! isset( $_POST['alternative_names'] ) ) {
			return;
		}

		$names = sanitize_text_field( wp_unslash( $_POST['alternative_names'] ) );
		$names = preg_split( '!,\s*!', $names );
		$names = array_map( 'trim', $names );
		$names = array_unique( $names );

		$names = array_filter(
			$names,
			function( $name ) {
				return strlen( $name ) >= 2;
			}
		);

		update_post_meta( $post_id, 'alternatives', $names );
	}

	/**
	 * Save the Associated Content metabox details.
	 *
	 * @param int $post_id Current glossary post ID.
	 */
	public function save_associations_metabox( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['velo_glossary_associations_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['velo_glossary_associations_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'velo_glossary_associations' ) ) {
			return;
		}

		$associated_post_ids = isset( $_POST['velo_glossary_associated_post_ids'] ) ? wp_parse_id_list( wp_unslash( $_POST['velo_glossary_associated_post_ids'] ) ) : array();
		$associated_post_ids = array_values( array_unique( array_filter( $associated_post_ids ) ) );
		$associated_post_ids = array_filter(
			$associated_post_ids,
			function( $associated_post_id ) {
				$post = get_post( $associated_post_id );

				return $post instanceof WP_Post && $this->is_associable_post( $post );
			}
		);

		Velo_Glossary::set_associated_post_ids( $post_id, $associated_post_ids );
	}

	/**
	 * Save the Related Terms metabox details.
	 *
	 * @param int $post_id Current glossary post ID.
	 */
	public function save_related_terms_metabox( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['velo_glossary_related_terms_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['velo_glossary_related_terms_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'velo_glossary_related_terms' ) ) {
			return;
		}

		$related_term_ids = isset( $_POST['velo_glossary_related_term_ids'] ) ? wp_parse_id_list( wp_unslash( $_POST['velo_glossary_related_term_ids'] ) ) : array();
		$related_term_ids = array_filter(
			array_values( array_unique( array_filter( $related_term_ids ) ) ),
			function( $related_term_id ) use ( $post_id ) {
				return $this->is_related_term_candidate( get_post( $related_term_id ), $post_id );
			}
		);

		Velo_Glossary::set_related_term_ids( $post_id, $related_term_ids );
	}

	/**
	 * Remove deleted glossary entries from related term relationships.
	 *
	 * @param int     $post_id Deleted post ID.
	 * @param WP_Post $post Deleted post object.
	 */
	public function remove_deleted_glossary_term_relationships( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || 'glossary' !== $post->post_type ) {
			return;
		}

		foreach ( Velo_Glossary::get_related_term_ids( $post_id ) as $related_id ) {
			$related_ids = array_diff( Velo_Glossary::get_related_term_ids( $related_id ), array( absint( $post_id ) ) );
			Velo_Glossary::replace_related_term_meta( $related_id, $related_ids );
		}
	}

	/**
	 * Get post types that glossary entries can be associated with.
	 *
	 * @return array
	 */
	public static function get_associable_post_types() {
		$post_types = Velo_Glossary_Settings::get_available_post_types();

		unset( $post_types['glossary'] );

		return $post_types;
	}

	/**
	 * Get associated content posts for a glossary entry.
	 *
	 * @param int $glossary_id Glossary post ID.
	 * @return WP_Post[]
	 */
	protected function get_associated_content_posts( $glossary_id ) {
		$associated_post_ids = Velo_Glossary::get_associated_post_ids( $glossary_id );
		if ( ! $associated_post_ids ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => array_keys( self::get_associable_post_types() ),
				'post_status'    => 'any',
				'post__in'       => $associated_post_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
			)
		);

		return array_values( array_filter( $posts, array( $this, 'is_associable_post' ) ) );
	}

	/**
	 * Get related glossary term posts for a glossary entry.
	 *
	 * @param int $glossary_id Glossary post ID.
	 * @return WP_Post[]
	 */
	protected function get_related_term_posts( $glossary_id ) {
		$related_term_ids = Velo_Glossary::get_related_term_ids( $glossary_id );
		if ( ! $related_term_ids ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'glossary',
				'post_status'    => 'any',
				'post__in'       => $related_term_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
			)
		);

		return array_values(
			array_filter(
				$posts,
				function( $post ) use ( $glossary_id ) {
					return $this->is_related_term_candidate( $post, $glossary_id );
				}
			)
		);
	}

	/**
	 * Render one selected associated content row.
	 *
	 * @param WP_Post $post Associated content post.
	 */
	protected function render_associated_content_item( $post ) {
		$result = $this->format_content_result( $post );
		?>
		<li class="velo-glossary-associated-content-item" data-post-id="<?php echo esc_attr( $result['id'] ); ?>">
			<span class="velo-glossary-associated-content-title"><?php echo esc_html( $result['title'] ); ?></span>
			<span class="velo-glossary-associated-content-meta"><?php echo esc_html( $result['meta'] ); ?></span>
			<button type="button" class="button-link-delete velo-glossary-associated-content-remove"><?php echo esc_html( $result['removeLabel'] ); ?></button>
			<input type="hidden" name="velo_glossary_associated_post_ids[]" value="<?php echo esc_attr( $result['id'] ); ?>" />
		</li>
		<?php
	}

	/**
	 * Render one selected related term row.
	 *
	 * @param WP_Post $post Related glossary term post.
	 */
	protected function render_related_term_item( $post ) {
		$result = $this->format_related_term_result( $post );
		?>
		<li class="velo-glossary-related-terms-item" data-post-id="<?php echo esc_attr( $result['id'] ); ?>">
			<span class="velo-glossary-related-terms-title"><?php echo esc_html( $result['title'] ); ?></span>
			<span class="velo-glossary-related-terms-meta"><?php echo esc_html( $result['meta'] ); ?></span>
			<button type="button" class="button-link-delete velo-glossary-related-terms-remove"><?php echo esc_html( $result['removeLabel'] ); ?></button>
			<input type="hidden" name="velo_glossary_related_term_ids[]" value="<?php echo esc_attr( $result['id'] ); ?>" />
		</li>
		<?php
	}

	/**
	 * Format a content post for the picker.
	 *
	 * @param WP_Post $post Content post.
	 * @return array
	 */
	protected function format_content_result( $post ) {
		$title = get_the_title( $post );
		if ( '' === $title ) {
			$title = __( '(no title)', 'velo-glossary' );
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$post_type_label  = $post_type_object ? $post_type_object->labels->singular_name : $post->post_type;

		return array(
			'id'          => $post->ID,
			'label'       => sprintf(
				/* translators: 1: post title, 2: post type label, 3: post ID. */
				__( '%1$s (%2$s, ID %3$d)', 'velo-glossary' ),
				$title,
				$post_type_label,
				$post->ID
			),
			'title'       => $title,
			'meta'        => sprintf(
				/* translators: 1: post type label, 2: post ID. */
				__( '%1$s ID %2$d', 'velo-glossary' ),
				$post_type_label,
				$post->ID
			),
			'removeLabel' => __( 'Remove', 'velo-glossary' ),
		);
	}

	/**
	 * Format a glossary term for the related terms picker.
	 *
	 * @param WP_Post $post Glossary term post.
	 * @return array
	 */
	protected function format_related_term_result( $post ) {
		$title = get_the_title( $post );
		if ( '' === $title ) {
			$title = __( '(no title)', 'velo-glossary' );
		}

		return array(
			'id'          => $post->ID,
			'label'       => sprintf(
				/* translators: 1: glossary term title, 2: post ID. */
				__( '%1$s (Glossary term, ID %2$d)', 'velo-glossary' ),
				$title,
				$post->ID
			),
			'title'       => $title,
			'meta'        => sprintf(
				/* translators: %d: post ID. */
				__( 'Glossary term ID %d', 'velo-glossary' ),
				$post->ID
			),
			'removeLabel' => __( 'Remove', 'velo-glossary' ),
		);
	}

	/**
	 * Determine whether a post can be associated with a glossary entry.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	protected function is_associable_post( $post ) {
		return self::is_associable_post_for_user( $post );
	}

	/**
	 * Determine whether the current user can associate a post with a glossary entry.
	 *
	 * @param WP_Post|null $post Post object.
	 * @return bool
	 */
	public static function is_associable_post_for_user( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$post_types = self::get_associable_post_types();
		if ( ! isset( $post_types[ $post->post_type ] ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Determine whether a post can be related to a glossary entry.
	 *
	 * @param WP_Post|null $post Current candidate post.
	 * @param int          $current_post_id Current glossary post ID.
	 * @return bool
	 */
	protected function is_related_term_candidate( $post, $current_post_id = 0 ) {
		return self::is_related_term_candidate_for_user( $post, $current_post_id );
	}

	/**
	 * Determine whether the current user can relate a glossary entry to another.
	 *
	 * @param WP_Post|null $post Current candidate post.
	 * @param int          $current_post_id Current glossary post ID.
	 * @return bool
	 */
	public static function is_related_term_candidate_for_user( $post, $current_post_id = 0 ) {
		if ( ! $post instanceof WP_Post || 'glossary' !== $post->post_type ) {
			return false;
		}

		if ( $current_post_id && absint( $post->ID ) === absint( $current_post_id ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post->ID );
	}
}
