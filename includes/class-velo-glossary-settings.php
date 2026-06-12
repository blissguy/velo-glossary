<?php
/**
 * Settings and loading rules for Velo Glossary.
 *
 * @package Velo_Glossary
 */

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_Settings {
	const OPTION_NAME   = 'velo_glossary_settings';
	const DISABLED_META = '_velo_glossary_disabled';
	const REWRITE_FLUSH_OPTION = 'velo_glossary_needs_rewrite_flush';

	// Void elements never wrap text and never produce a close tag, so they cannot define an exclusion zone.
	const VOID_ELEMENTS = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );

	/**
	 * Register settings hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 30 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_disable_metabox' ) );
		add_action( 'save_post', array( $this, 'save_disable_metabox' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'queue_rewrite_flush' ), 10, 2 );
	}

	/**
	 * Register the WordPress settings fields.
	 */
	public function register_settings() {
		register_setting(
			'velo_glossary',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'velo_glossary_loading',
			__( 'Loading Rules', 'velo-glossary' ),
			array( $this, 'render_loading_section' ),
			'velo-glossary'
		);

		add_settings_field(
			'enabled_post_types',
			__( 'Post types', 'velo-glossary' ),
			array( $this, 'render_post_types_field' ),
			'velo-glossary',
			'velo_glossary_loading'
		);

		add_settings_field(
			'include_archives',
			__( 'Archive and list views', 'velo-glossary' ),
			array( $this, 'render_archives_field' ),
			'velo-glossary',
			'velo_glossary_loading'
		);

		add_settings_field(
			'include_comments',
			__( 'Comments', 'velo-glossary' ),
			array( $this, 'render_comments_field' ),
			'velo-glossary',
			'velo_glossary_loading'
		);

		add_settings_field(
			'excluded_tags',
			__( 'Excluded tags', 'velo-glossary' ),
			array( $this, 'render_excluded_tags_field' ),
			'velo-glossary',
			'velo_glossary_loading'
		);

		add_settings_field(
			'excluded_classes',
			__( 'Excluded class names', 'velo-glossary' ),
			array( $this, 'render_excluded_classes_field' ),
			'velo-glossary',
			'velo_glossary_loading'
		);

		add_settings_section(
			'velo_glossary_associations',
			__( 'Term Associations', 'velo-glossary' ),
			array( $this, 'render_associations_section' ),
			'velo-glossary'
		);

		add_settings_field(
			'limit_to_associated_content',
			__( 'Associated content', 'velo-glossary' ),
			array( $this, 'render_limit_to_associated_content_field' ),
			'velo-glossary',
			'velo_glossary_associations'
		);

		add_settings_field(
			'include_unassociated_terms',
			__( 'Fallback terms', 'velo-glossary' ),
			array( $this, 'render_include_unassociated_terms_field' ),
			'velo-glossary',
			'velo_glossary_associations'
		);

		add_settings_section(
			'velo_glossary_taxonomy_urls',
			__( 'Frontend URLs', 'velo-glossary' ),
			array( $this, 'render_frontend_urls_section' ),
			'velo-glossary'
		);

		add_settings_field(
			'enable_entry_single_pages',
			__( 'Glossary entry single pages', 'velo-glossary' ),
			array( $this, 'render_enable_entry_single_pages_field' ),
			'velo-glossary',
			'velo_glossary_taxonomy_urls'
		);

		add_settings_field(
			'enable_tag_archives',
			__( 'Glossary tag archives', 'velo-glossary' ),
			array( $this, 'render_enable_tag_archives_field' ),
			'velo-glossary',
			'velo_glossary_taxonomy_urls'
		);
	}

	/**
	 * Add the settings page under Settings.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Velo Glossary', 'velo-glossary' ),
			__( 'Velo Glossary', 'velo-glossary' ),
			'manage_options',
			'velo-glossary',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$input           = is_array( $input ) ? $input : array();
		$available_types = array_keys( self::get_available_post_types() );
		$enabled_types   = isset( $input['enabled_post_types'] ) ? (array) $input['enabled_post_types'] : array();
		$enabled_types   = array_map( 'sanitize_key', $enabled_types );
		$enabled_types   = array_values( array_intersect( $enabled_types, $available_types ) );

		return array(
			'enabled_post_types'          => $enabled_types,
			'include_archives'            => empty( $input['include_archives'] ) ? 0 : 1,
			'include_comments'            => empty( $input['include_comments'] ) ? 0 : 1,
			'limit_to_associated_content' => empty( $input['limit_to_associated_content'] ) ? 0 : 1,
			'include_unassociated_terms'  => empty( $input['include_unassociated_terms'] ) ? 0 : 1,
			'enable_entry_single_pages'   => empty( $input['enable_entry_single_pages'] ) ? 0 : 1,
			'enable_tag_archives'         => empty( $input['enable_tag_archives'] ) ? 0 : 1,
			'excluded_tags'               => self::sanitize_excluded_tags( isset( $input['excluded_tags'] ) ? $input['excluded_tags'] : '' ),
			'excluded_classes'            => self::sanitize_excluded_classes( isset( $input['excluded_classes'] ) ? $input['excluded_classes'] : '' ),
		);
	}

	/**
	 * Split a comma- or space-separated token list into individual tokens.
	 *
	 * @param string|array $value Raw field value or stored token array.
	 * @return array
	 */
	protected static function parse_token_list( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}

		return preg_split( '/[\s,]+/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
	}

	/**
	 * Sanitize the excluded tags list down to valid, non-void HTML tag names.
	 *
	 * @param string|array $value Raw field value or stored token array.
	 * @return array
	 */
	protected static function sanitize_excluded_tags( $value ) {
		$tags = array();
		foreach ( self::parse_token_list( $value ) as $tag ) {
			$tag = strtolower( $tag );
			if ( preg_match( '/^[a-z][a-z0-9-]*$/', $tag ) && ! in_array( $tag, self::VOID_ELEMENTS, true ) ) {
				$tags[] = $tag;
			}
		}

		return array_values( array_unique( $tags ) );
	}

	/**
	 * Sanitize the excluded class names list.
	 *
	 * @param string|array $value Raw field value or stored token array.
	 * @return array
	 */
	protected static function sanitize_excluded_classes( $value ) {
		$classes = array();
		foreach ( self::parse_token_list( $value ) as $class ) {
			$class = sanitize_html_class( $class );
			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Velo Glossary', 'velo-glossary' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_errors( 'velo_glossary' );
				settings_fields( 'velo_glossary' );
				do_settings_sections( 'velo-glossary' );
				submit_button();
				?>
			</form>
			<?php do_action( 'velo_glossary_after_settings_page' ); ?>
		</div>
		<?php
	}

	/**
	 * Render the settings section description.
	 */
	public function render_loading_section() {
		echo '<p>' . esc_html__( 'Choose where Velo Glossary should replace matching glossary terms and load hovercard assets.', 'velo-glossary' ) . '</p>';
	}

	/**
	 * Render the term associations section description.
	 */
	public function render_associations_section() {
		echo '<p>' . esc_html__( 'Use glossary entry associations to control where terms appear. Saved associations remain available for custom queries and builders either way.', 'velo-glossary' ) . '</p>';

		$disabled_post_type_labels = self::get_disabled_associated_post_type_labels();
		if ( $disabled_post_type_labels ) {
			echo '<div class="notice notice-warning inline"><p>';
			printf(
				/* translators: %s: comma-separated list of post type labels. */
				esc_html__( 'Some associated glossary content is in disabled post types: %s. Terms associated with those items will not display until those post types are enabled above.', 'velo-glossary' ),
				esc_html( implode( ', ', $disabled_post_type_labels ) )
			);
			echo '</p></div>';
		}
	}

	/**
	 * Render the frontend URLs section description.
	 */
	public function render_frontend_urls_section() {
		echo '<p>' . esc_html__( 'Control whether glossary entries and glossary-only taxonomies should be directly accessible on the frontend. Disabling these URLs keeps entries and tags available in admin screens, REST, and builders.', 'velo-glossary' ) . '</p>';
	}

	/**
	 * Render the post type checkboxes.
	 */
	public function render_post_types_field() {
		$settings    = self::get_settings();
		$post_types  = self::get_available_post_types();
		$enabled     = $settings['enabled_post_types'];
		$field_name  = self::OPTION_NAME . '[enabled_post_types][]';

		if ( ! $post_types ) {
			echo '<p>' . esc_html__( 'No public content post types are available.', 'velo-glossary' ) . '</p>';
			return;
		}

			foreach ( $post_types as $post_type => $post_type_object ) {
				?>
				<label>
				<input
					type="checkbox"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $post_type ); ?>"
					<?php checked( in_array( $post_type, $enabled, true ) ); ?>
				/>
				<?php echo esc_html( $post_type_object->labels->name ); ?>
			</label><br />
				<?php
			}

			echo '<p class="description">' . esc_html__( 'Frontend matching only runs on checked post types. Term associations remain saved for queries, but they do not override this loading rule.', 'velo-glossary' ) . '</p>';
		}

	/**
	 * Render the archive setting.
	 */
	public function render_archives_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[include_archives]"
				value="1"
				<?php checked( $settings['include_archives'] ); ?>
			/>
			<?php esc_html_e( 'Process glossary terms in archive and list views.', 'velo-glossary' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the comments setting.
	 */
	public function render_comments_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[include_comments]"
				value="1"
				<?php checked( $settings['include_comments'] ); ?>
			/>
			<?php esc_html_e( 'Process glossary terms in comments.', 'velo-glossary' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the excluded tags setting.
	 */
	public function render_excluded_tags_field() {
		$settings = self::get_settings();
		?>
		<input
			type="text"
			class="regular-text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[excluded_tags]"
			value="<?php echo esc_attr( implode( ', ', $settings['excluded_tags'] ) ); ?>"
		/>
		<p class="description"><?php esc_html_e( 'Comma-separated HTML tags whose content should not be matched, e.g. h1, h2, blockquote. Links, code, and preformatted blocks are always skipped.', 'velo-glossary' ); ?></p>
		<?php
	}

	/**
	 * Render the excluded class names setting.
	 */
	public function render_excluded_classes_field() {
		$settings = self::get_settings();
		?>
		<input
			type="text"
			class="regular-text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[excluded_classes]"
			value="<?php echo esc_attr( implode( ', ', $settings['excluded_classes'] ) ); ?>"
		/>
		<p class="description"><?php esc_html_e( 'Comma-separated class names. Elements with one of these classes, including their nested content, are not matched. Class detection only applies to elements within filtered content.', 'velo-glossary' ); ?></p>
		<?php
	}

	/**
	 * Render the associated content limit setting.
	 */
	public function render_limit_to_associated_content_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[limit_to_associated_content]"
				value="1"
				<?php checked( $settings['limit_to_associated_content'] ); ?>
			/>
			<?php esc_html_e( 'Only show glossary terms on content they are associated with.', 'velo-glossary' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the unassociated terms fallback setting.
	 */
	public function render_include_unassociated_terms_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[include_unassociated_terms]"
				value="1"
				<?php checked( $settings['include_unassociated_terms'] ); ?>
			/>
			<?php esc_html_e( 'When limiting by association, still show glossary terms that do not have associated content everywhere.', 'velo-glossary' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the glossary entry single pages setting.
	 */
	public function render_enable_entry_single_pages_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_entry_single_pages]"
				value="1"
				<?php checked( $settings['enable_entry_single_pages'] ); ?>
			/>
			<?php esc_html_e( 'Enable public single pages for glossary entries.', 'velo-glossary' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Leave this off when glossary entries are only queried into custom frontend layouts.', 'velo-glossary' ); ?></p>
		<?php
	}

	/**
	 * Render the glossary tag archives setting.
	 */
	public function render_enable_tag_archives_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_tag_archives]"
				value="1"
				<?php checked( $settings['enable_tag_archives'] ); ?>
			/>
			<?php esc_html_e( 'Enable public glossary tag archive URLs such as ?velo_glossary_tag=analysis.', 'velo-glossary' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Leave this off when glossary tags are only for organizing entries or builder queries.', 'velo-glossary' ); ?></p>
		<?php
	}

	/**
	 * Register the per-content disable metabox.
	 */
	public function register_disable_metabox() {
		foreach ( self::get_available_post_types() as $post_type => $post_type_object ) {
			if ( ! $post_type_object->show_ui ) {
				continue;
			}

			add_meta_box(
				'velo-glossary-disable',
				__( 'Velo Glossary', 'velo-glossary' ),
				array( $this, 'render_disable_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the per-content disable metabox.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_disable_metabox( $post ) {
		wp_nonce_field( 'velo_glossary_disable', 'velo_glossary_disable_nonce' );
		?>
		<label>
			<input
				type="checkbox"
				name="velo_glossary_disabled"
				value="1"
				<?php checked( self::is_post_disabled( $post->ID ) ); ?>
			/>
			<?php esc_html_e( 'Disable Velo Glossary on this content.', 'velo-glossary' ); ?>
		</label>
		<?php
	}

	/**
	 * Save the per-content disable metabox.
	 *
	 * @param int $post_id Current post ID.
	 */
	public function save_disable_metabox( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['velo_glossary_disable_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['velo_glossary_disable_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'velo_glossary_disable' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post_type            = get_post_type( $post_id );
		$available_post_types = self::get_available_post_types();
		if ( ! isset( $available_post_types[ $post_type ] ) ) {
			return;
		}

		$is_disabled = isset( $_POST['velo_glossary_disabled'] ) ? sanitize_text_field( wp_unslash( $_POST['velo_glossary_disabled'] ) ) : '';
		if ( $is_disabled ) {
			update_post_meta( $post_id, self::DISABLED_META, '1' );
			return;
		}

		delete_post_meta( $post_id, self::DISABLED_META );
	}

	/**
	 * Get public content post types that Velo Glossary can target.
	 *
	 * @return array
	 */
	public static function get_available_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		unset( $post_types['attachment'] );

		uasort(
			$post_types,
			static function( $a, $b ) {
				return strcasecmp( $a->labels->name, $b->labels->name );
			}
		);

		return $post_types;
	}

	/**
	 * Get labels for disabled post types that currently have associated glossary terms.
	 *
	 * @return array
	 */
	protected static function get_disabled_associated_post_type_labels() {
		$settings       = self::get_settings();
		$available      = self::get_available_post_types();
		$disabled_types = array_diff( array_keys( $available ), $settings['enabled_post_types'] );

		if ( ! $disabled_types ) {
			return array();
		}

		$glossary_ids = get_posts(
			array(
				'post_type'              => 'glossary',
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Settings page warning needs to find glossary entries that use associated content meta.
				'meta_key'               => Velo_Glossary::ASSOCIATED_POST_META,
			)
		);

		$labels = array();
		foreach ( $glossary_ids as $glossary_id ) {
			foreach ( Velo_Glossary::get_associated_post_ids( $glossary_id ) as $associated_post_id ) {
				$post_type = get_post_type( $associated_post_id );
				if ( ! in_array( $post_type, $disabled_types, true ) || empty( $available[ $post_type ] ) ) {
					continue;
				}

				$labels[ $post_type ] = $available[ $post_type ]->labels->name;
			}
		}

		natcasesort( $labels );

		return array_values( $labels );
	}

	/**
	 * Get default settings. Defaults preserve the plugin's previous frontend reach.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'enabled_post_types'          => array_keys( self::get_available_post_types() ),
			'include_archives'            => 1,
			'include_comments'            => 1,
			'limit_to_associated_content' => 0,
			'include_unassociated_terms'  => 0,
			'enable_entry_single_pages'   => 0,
			'enable_tag_archives'         => 0,
			'excluded_tags'               => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ),
			'excluded_classes'            => array(),
		);
	}

	/**
	 * Get sanitized settings with defaults applied.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $settings ) ) {
			return self::get_default_settings();
		}

		$settings        = wp_parse_args( $settings, self::get_default_settings() );
		$available_types = array_keys( self::get_available_post_types() );
		$enabled_types   = array_map( 'sanitize_key', (array) $settings['enabled_post_types'] );

		return array(
			'enabled_post_types'          => array_values( array_intersect( $enabled_types, $available_types ) ),
			'include_archives'            => empty( $settings['include_archives'] ) ? 0 : 1,
			'include_comments'            => empty( $settings['include_comments'] ) ? 0 : 1,
			'limit_to_associated_content' => empty( $settings['limit_to_associated_content'] ) ? 0 : 1,
			'include_unassociated_terms'  => empty( $settings['include_unassociated_terms'] ) ? 0 : 1,
			'enable_entry_single_pages'   => empty( $settings['enable_entry_single_pages'] ) ? 0 : 1,
			'enable_tag_archives'         => empty( $settings['enable_tag_archives'] ) ? 0 : 1,
			'excluded_tags'               => self::sanitize_excluded_tags( $settings['excluded_tags'] ),
			'excluded_classes'            => self::sanitize_excluded_classes( $settings['excluded_classes'] ),
		);
	}

	/**
	 * Get tag names excluded from frontend term matching.
	 *
	 * @return array
	 */
	public static function get_excluded_tags() {
		return self::get_settings()['excluded_tags'];
	}

	/**
	 * Get class names excluded from frontend term matching.
	 *
	 * @return array
	 */
	public static function get_excluded_classes() {
		return self::get_settings()['excluded_classes'];
	}

	/**
	 * Determine whether a post type is enabled.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public static function is_post_type_enabled( $post_type ) {
		$settings = self::get_settings();

		return in_array( $post_type, $settings['enabled_post_types'], true );
	}

	/**
	 * Determine whether frontend matching should use glossary/content associations.
	 *
	 * @return bool
	 */
	public static function should_limit_terms_to_associations() {
		return (bool) self::get_settings()['limit_to_associated_content'];
	}

	/**
	 * Determine whether unassociated terms should act as global terms.
	 *
	 * @return bool
	 */
	public static function should_include_unassociated_terms() {
		$settings = self::get_settings();

		return ! empty( $settings['limit_to_associated_content'] ) && ! empty( $settings['include_unassociated_terms'] );
	}

	/**
	 * Determine whether glossary entries should resolve as public single pages.
	 *
	 * @return bool
	 */
	public static function should_enable_entry_single_pages() {
		return (bool) self::get_settings()['enable_entry_single_pages'];
	}

	/**
	 * Determine whether glossary tag archive/query URLs should resolve publicly.
	 *
	 * @return bool
	 */
	public static function should_enable_tag_archives() {
		return (bool) self::get_settings()['enable_tag_archives'];
	}

	/**
	 * Queue a rewrite flush when frontend URL settings change.
	 *
	 * @param array $old_value Previous settings.
	 * @param array $value     New settings.
	 */
	public function queue_rewrite_flush( $old_value, $value ) {
		$old_value = is_array( $old_value ) ? wp_parse_args( $old_value, self::get_default_settings() ) : self::get_default_settings();
		$value     = is_array( $value ) ? wp_parse_args( $value, self::get_default_settings() ) : self::get_default_settings();

		$url_keys = array( 'enable_entry_single_pages', 'enable_tag_archives' );
		foreach ( $url_keys as $key ) {
			if ( empty( $old_value[ $key ] ) !== empty( $value[ $key ] ) ) {
				update_option( self::REWRITE_FLUSH_OPTION, 1, false );
				return;
			}
		}
	}

	/**
	 * Flush rewrite rules after URL settings changed and post types are registered.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( ! get_option( self::REWRITE_FLUSH_OPTION ) ) {
			return;
		}

		delete_option( self::REWRITE_FLUSH_OPTION );
		flush_rewrite_rules( false );
	}

	/**
	 * Determine whether a single post has disabled Velo Glossary.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_post_disabled( $post_id ) {
		return '1' === get_post_meta( $post_id, self::DISABLED_META, true );
	}

	/**
	 * Determine whether a post can use glossary processing.
	 *
	 * @param int|WP_Post|null $post Post ID or object.
	 * @return bool
	 */
	public static function is_post_allowed( $post = null ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( ! self::is_post_type_enabled( $post->post_type ) ) {
			return false;
		}

		return ! self::is_post_disabled( $post->ID );
	}

	/**
	 * Determine whether the current request can process glossary output.
	 *
	 * @return bool
	 */
	public static function request_allows_processing() {
		if ( is_feed() || is_embed() ) {
			return false;
		}

		if ( is_admin() ) {
			$admin_action = sanitize_key( (string) filter_input( INPUT_GET, 'action', FILTER_UNSAFE_RAW ) );

			return 'o2_read' === $admin_action;
		}

		return true;
	}

	/**
	 * Determine whether the current content filter call should be processed.
	 *
	 * @return bool
	 */
	public static function should_process_content() {
		if ( ! self::request_allows_processing() ) {
			return false;
		}

		if ( is_singular() ) {
			$queried_post_id = get_queried_object_id();
			if ( $queried_post_id && ! self::is_post_allowed( $queried_post_id ) ) {
				return false;
			}
		} elseif ( ! self::get_settings()['include_archives'] ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return true;
		}

		return self::is_post_allowed( $post );
	}

	/**
	 * Determine whether the current comment filter call should be processed.
	 *
	 * @param WP_Comment|int|null $comment Comment object or ID.
	 * @return bool
	 */
	public static function should_process_comment( $comment = null ) {
		if ( ! self::request_allows_processing() || ! self::get_settings()['include_comments'] ) {
			return false;
		}

		if ( is_singular() ) {
			$queried_post_id = get_queried_object_id();
			if ( $queried_post_id && ! self::is_post_allowed( $queried_post_id ) ) {
				return false;
			}
		}

		$comment = get_comment( $comment );
		if ( $comment instanceof WP_Comment && $comment->comment_post_ID ) {
			return self::is_post_allowed( $comment->comment_post_ID );
		}

		return self::should_process_content();
	}

	/**
	 * Determine whether hovercard assets should be enqueued for the current request.
	 *
	 * @return bool
	 */
	public static function should_enqueue_assets() {
		if ( ! self::request_allows_processing() ) {
			return false;
		}

		if ( is_singular() ) {
			$queried_post_id = get_queried_object_id();

			return $queried_post_id ? self::is_post_allowed( $queried_post_id ) : false;
		}

		if ( ! self::get_settings()['include_archives'] ) {
			return false;
		}

		foreach ( self::get_query_post_types() as $post_type ) {
			if ( self::is_post_type_enabled( $post_type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get post types represented by the current frontend query.
	 *
	 * @return array
	 */
	protected static function get_query_post_types() {
		$query_post_type = get_query_var( 'post_type' );
		if ( $query_post_type ) {
			return array_map( 'sanitize_key', (array) $query_post_type );
		}

		if ( is_home() || is_category() || is_tag() || is_date() || is_author() ) {
			return array( 'post' );
		}

		if ( is_search() ) {
			return array_keys( self::get_available_post_types() );
		}

		global $wp_query;
		if ( empty( $wp_query->posts ) ) {
			return array();
		}

		$post_types = array();
		foreach ( $wp_query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$post_types[] = $post->post_type;
			}
		}

		return array_values( array_unique( $post_types ) );
	}
}
