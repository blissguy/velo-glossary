<?php
/**
 * CSV import/export tools for Velo Glossary.
 *
 * @package Velo_Glossary
 */

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_CSV {
	const PREVIEW_TRANSIENT_PREFIX = 'velo_glossary_csv_preview_';
	const RESULT_TRANSIENT_PREFIX  = 'velo_glossary_csv_result_';
	const TRANSIENT_TTL            = 900;
	const MAX_ROWS                 = 5000;

	/**
	 * Supported CSV columns keyed by normalized parser field.
	 *
	 * @var array
	 */
	protected static $supported_columns = array(
		'term'                   => 'Term',
		'definition'             => 'Definition',
		'status'                 => 'Status',
		'alternative_terms'      => 'Alternative Terms',
		'tags'                   => 'Tags',
		'associated_content_ids' => 'Associated Content IDs',
		'related_term_ids'       => 'Related Term IDs',
	);

	/**
	 * Valid post statuses accepted from CSV imports.
	 *
	 * @var array
	 */
	protected static $valid_statuses = array( 'publish', 'draft', 'pending', 'private' );

	/**
	 * Register admin hooks.
	 */
	public function __construct() {
		add_action( 'velo_glossary_after_settings_page', array( $this, 'render_settings_section' ) );
		add_action( 'admin_post_velo_glossary_download_sample_csv', array( $this, 'download_sample_csv' ) );
		add_action( 'admin_post_velo_glossary_export_csv', array( $this, 'export_csv' ) );
		add_action( 'admin_post_velo_glossary_preview_import_csv', array( $this, 'preview_import' ) );
		add_action( 'admin_post_velo_glossary_confirm_import_csv', array( $this, 'confirm_import' ) );
		add_action( 'admin_post_velo_glossary_cancel_import_csv', array( $this, 'cancel_import' ) );
	}

	/**
	 * Render CSV import/export controls on the settings page.
	 */
	public function render_settings_section() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only token that selects a user-scoped transient.
		$preview_token = isset( $_GET['velo_glossary_csv_preview'] ) ? sanitize_key( wp_unslash( $_GET['velo_glossary_csv_preview'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only token that selects a user-scoped transient.
		$result_token = isset( $_GET['velo_glossary_csv_result'] ) ? sanitize_key( wp_unslash( $_GET['velo_glossary_csv_result'] ) ) : '';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'CSV Import/Export', 'velo-glossary' ) . '</h2>';

		if ( $result_token ) {
			$this->render_result( $result_token );
		}

		if ( $preview_token ) {
			$this->render_preview( $preview_token );
		}

		$this->render_export_tools();
		$this->render_import_form();
	}

	/**
	 * Download a sample CSV template.
	 */
	public function download_sample_csv() {
		$this->verify_admin_request( 'velo_glossary_download_sample_csv' );

		$this->send_csv(
			'velo-glossary-sample.csv',
			array(
				array_values( self::$supported_columns ),
				array(
					'Exposure',
					'The amount of light captured by the camera sensor.',
					'publish',
					'Exposure Value, EV',
					'exposure, fundamentals',
					'123,456',
					'789,790',
				),
			)
		);
	}

	/**
	 * Export all glossary entries as CSV.
	 */
	public function export_csv() {
		$this->verify_admin_request( 'velo_glossary_export_csv' );

		$rows   = array( array_values( self::$supported_columns ) );
		$posts  = get_posts(
			array(
				'post_type'      => 'glossary',
				'post_status'    => self::$valid_statuses,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$alternatives = get_post_meta( $post->ID, 'alternatives', true );
			$alternatives = is_array( $alternatives ) ? $alternatives : array();
			$tags         = get_the_terms( $post->ID, Velo_Glossary::TAG_TAXONOMY );
			$tag_names    = array();

			if ( is_array( $tags ) ) {
				$tag_names = wp_list_pluck( $tags, 'name' );
			}

			$rows[] = array(
				get_post_field( 'post_title', $post->ID, 'raw' ),
				get_post_field( 'post_content', $post->ID, 'raw' ),
				$post->post_status,
				implode( ', ', $alternatives ),
				implode( ', ', $tag_names ),
				implode( ', ', Velo_Glossary::get_associated_post_ids( $post->ID ) ),
				implode( ', ', Velo_Glossary::get_related_term_ids( $post->ID ) ),
			);
		}

		$this->send_csv( 'velo-glossary-export-' . gmdate( 'Y-m-d' ) . '.csv', $rows );
	}

	/**
	 * Parse an uploaded CSV into a no-write preview.
	 */
	public function preview_import() {
		$this->verify_admin_request( 'velo_glossary_preview_import_csv' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_admin_request() checked the nonce above.
		if ( empty( $_FILES['velo_glossary_csv_file'] ) || ! is_array( $_FILES['velo_glossary_csv_file'] ) ) {
			$this->redirect_with_result( $this->make_error_result( __( 'Please choose a CSV file to import.', 'velo-glossary' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verify_admin_request() checked the nonce above; individual fields are sanitized below.
		$file = $_FILES['velo_glossary_csv_file'];
		if ( ! empty( $file['error'] ) ) {
			$this->redirect_with_result( $this->make_error_result( __( 'The CSV upload failed. Please try again.', 'velo-glossary' ) ) );
		}

		$name      = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$tmp_name  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! $tmp_name || ! is_readable( $tmp_name ) || ! in_array( $extension, array( 'csv', 'txt' ), true ) ) {
			$this->redirect_with_result( $this->make_error_result( __( 'Please upload a CSV file.', 'velo-glossary' ) ) );
		}

		$preview = $this->preview_file( $tmp_name );
		if ( is_wp_error( $preview ) ) {
			$this->redirect_with_result( $this->make_error_result( $preview->get_error_message() ) );
		}

		$token = $this->create_token();
		set_transient( $this->get_preview_transient_key( $token ), $preview, self::TRANSIENT_TTL );

		wp_safe_redirect( $this->get_settings_url( array( 'velo_glossary_csv_preview' => $token ) ) );
		exit;
	}

	/**
	 * Confirm a previously previewed import.
	 */
	public function confirm_import() {
		$this->verify_admin_request( 'velo_glossary_confirm_import_csv' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_admin_request() checked the nonce above.
		$token   = isset( $_POST['velo_glossary_csv_token'] ) ? sanitize_key( wp_unslash( $_POST['velo_glossary_csv_token'] ) ) : '';
		$preview = $token ? get_transient( $this->get_preview_transient_key( $token ) ) : false;

		if ( ! is_array( $preview ) ) {
			$this->redirect_with_result( $this->make_error_result( __( 'The import preview expired. Please upload the CSV again.', 'velo-glossary' ) ) );
		}

		$result = $this->apply_import( $preview );
		delete_transient( $this->get_preview_transient_key( $token ) );
		$this->redirect_with_result( $result );
	}

	/**
	 * Cancel a pending import preview.
	 */
	public function cancel_import() {
		$this->verify_admin_request( 'velo_glossary_cancel_import_csv' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_admin_request() checked the nonce above.
		$token = isset( $_POST['velo_glossary_csv_token'] ) ? sanitize_key( wp_unslash( $_POST['velo_glossary_csv_token'] ) ) : '';
		if ( $token ) {
			delete_transient( $this->get_preview_transient_key( $token ) );
		}

		wp_safe_redirect( $this->get_settings_url() );
		exit;
	}

	/**
	 * Parse a CSV file into a preview payload without writing data.
	 *
	 * @param string $file_path CSV file path.
	 * @return array|WP_Error
	 */
	public function preview_file( $file_path ) {
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV parsing needs a temporary file handle.
		if ( ! $handle ) {
			return new WP_Error( 'velo_glossary_csv_read_error', __( 'The CSV file could not be read.', 'velo-glossary' ) );
		}

		$header_row = fgetcsv( $handle, 0, ',', '"', '\\' );
		if ( ! is_array( $header_row ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the temporary CSV stream opened above.
			return new WP_Error( 'velo_glossary_csv_empty', __( 'The CSV file is empty.', 'velo-glossary' ) );
		}

		$headers = $this->map_headers( $header_row );
		if ( ! array_key_exists( 'term', $headers['indexes'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the temporary CSV stream opened above.
			return new WP_Error( 'velo_glossary_csv_missing_term', __( 'The CSV must include a Term column.', 'velo-glossary' ) );
		}

		$preview = array(
			'created_at'      => time(),
			'rows_total'      => 0,
			'creates'         => 0,
			'updates'         => 0,
			'skipped'         => 0,
			'ignored_headers' => $headers['ignored'],
			'rows'            => array(),
		);

		$line_number = 1;
		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
			++$line_number;

			if ( $line_number > self::MAX_ROWS + 1 ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the temporary CSV stream opened above.
				return new WP_Error(
					'velo_glossary_csv_too_large',
					sprintf(
						/* translators: %d: maximum import row count. */
						__( 'The CSV has more than %d rows. Split it into smaller files and try again.', 'velo-glossary' ),
						self::MAX_ROWS
					)
				);
			}

			if ( $this->is_empty_row( $row ) ) {
				continue;
			}

			++$preview['rows_total'];
			$entry = $this->preview_row( $row, $headers['indexes'], $line_number );

			if ( 'create' === $entry['action'] ) {
				++$preview['creates'];
			} elseif ( 'update' === $entry['action'] ) {
				++$preview['updates'];
			} else {
				++$preview['skipped'];
			}

			$preview['rows'][] = $entry;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the temporary CSV stream opened above.

		return $preview;
	}

	/**
	 * Apply a previously parsed import preview.
	 *
	 * @param array $preview Preview payload.
	 * @return array Result payload.
	 */
	protected function apply_import( $preview ) {
		$result = array(
			'type'            => 'success',
			'message'         => __( 'CSV import finished.', 'velo-glossary' ),
			'rows_total'      => isset( $preview['rows_total'] ) ? absint( $preview['rows_total'] ) : 0,
			'created'         => 0,
			'updated'         => 0,
			'skipped'         => 0,
			'ignored_headers' => isset( $preview['ignored_headers'] ) ? (array) $preview['ignored_headers'] : array(),
			'warnings'        => array(),
		);

		foreach ( (array) $preview['rows'] as $row ) {
			$row_warnings = isset( $row['warnings'] ) ? (array) $row['warnings'] : array();

			if ( 'skip' === $row['action'] ) {
				++$result['skipped'];
				$result['warnings'] = array_merge( $result['warnings'], $row_warnings );
				continue;
			}

			$postarr = array(
				'post_title' => $row['term'],
			);

			if ( 'update' === $row['action'] ) {
				$postarr['ID'] = absint( $row['post_id'] );
			} else {
				$postarr['post_type']   = 'glossary';
				$postarr['post_author'] = get_current_user_id();
			}

			if ( ! empty( $row['present']['definition'] ) ) {
				$postarr['post_content'] = $row['definition'];
			}

			if ( null !== $row['status'] ) {
				$postarr['post_status'] = $row['status'];
			} elseif ( 'create' === $row['action'] ) {
				$postarr['post_status'] = 'draft';
			}

			$post_id = 'update' === $row['action'] ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $post_id ) ) {
				++$result['skipped'];
				$row_warnings[] = sprintf(
					/* translators: 1: CSV line number, 2: WordPress error message. */
					__( 'Line %1$d was not imported: %2$s', 'velo-glossary' ),
					absint( $row['line'] ),
					$post_id->get_error_message()
				);
				$result['warnings'] = array_merge( $result['warnings'], $row_warnings );
				continue;
			}

			$post_id = absint( $post_id );

			if ( ! empty( $row['present']['alternative_terms'] ) ) {
				update_post_meta( $post_id, 'alternatives', $row['alternative_terms'] );
			}

			if ( ! empty( $row['present']['tags'] ) ) {
				wp_set_object_terms( $post_id, $row['tags'], Velo_Glossary::TAG_TAXONOMY, false );
			}

			if ( ! empty( $row['present']['associated_content_ids'] ) ) {
				$associated = $this->validate_associated_ids( $row['associated_content_candidates'], $row['line'] );
				Velo_Glossary::set_associated_post_ids( $post_id, $associated['valid'] );
				$row_warnings = array_merge( $row_warnings, $associated['warnings'] );
			}

			if ( ! empty( $row['present']['related_term_ids'] ) ) {
				$related = $this->validate_related_ids( $row['related_term_candidates'], $post_id, $row['line'] );
				Velo_Glossary::set_related_term_ids( $post_id, $related['valid'] );
				$row_warnings = array_merge( $row_warnings, $related['warnings'] );
			}

			if ( 'create' === $row['action'] ) {
				++$result['created'];
			} else {
				++$result['updated'];
			}

			$result['warnings'] = array_merge( $result['warnings'], $row_warnings );
		}

		$result['warnings'] = array_values( array_unique( $result['warnings'] ) );

		return $result;
	}

	/**
	 * Render sample/export controls.
	 */
	protected function render_export_tools() {
		?>
		<div class="card">
			<h3><?php esc_html_e( 'Export', 'velo-glossary' ); ?></h3>
			<p><?php esc_html_e( 'Download a sample template or export current glossary entries using the supported CSV columns.', 'velo-glossary' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="action" value="velo_glossary_download_sample_csv" />
				<?php wp_nonce_field( 'velo_glossary_download_sample_csv' ); ?>
				<?php submit_button( __( 'Download sample CSV', 'velo-glossary' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="velo_glossary_export_csv" />
				<?php wp_nonce_field( 'velo_glossary_export_csv' ); ?>
				<?php submit_button( __( 'Export glossary CSV', 'velo-glossary' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render import upload form.
	 */
	protected function render_import_form() {
		?>
		<div class="card">
			<h3><?php esc_html_e( 'Import', 'velo-glossary' ); ?></h3>
			<p><?php esc_html_e( 'Upload a CSV to preview creates, updates, skipped rows, ignored columns, and invalid relationship IDs before anything is written.', 'velo-glossary' ); ?></p>
			<p class="description"><?php esc_html_e( 'Terms are matched by exact title. Relationship columns accept comma-separated numeric IDs only.', 'velo-glossary' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="velo_glossary_preview_import_csv" />
				<?php wp_nonce_field( 'velo_glossary_preview_import_csv' ); ?>
				<input type="file" name="velo_glossary_csv_file" accept=".csv,text/csv" required />
				<?php submit_button( __( 'Preview import', 'velo-glossary' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render an import preview.
	 *
	 * @param string $token Preview token.
	 */
	protected function render_preview( $token ) {
		$preview = get_transient( $this->get_preview_transient_key( $token ) );
		if ( ! is_array( $preview ) ) {
			$this->render_notice( 'warning', __( 'The import preview expired. Upload the CSV again to continue.', 'velo-glossary' ) );
			return;
		}

		echo '<div class="notice notice-info inline"><p>';
		printf(
			/* translators: 1: row count, 2: creates, 3: updates, 4: skipped rows. */
			esc_html__( 'Preview: %1$d rows found, %2$d creates, %3$d updates, %4$d skipped.', 'velo-glossary' ),
			absint( $preview['rows_total'] ),
			absint( $preview['creates'] ),
			absint( $preview['updates'] ),
			absint( $preview['skipped'] )
		);
		echo '</p></div>';

		$this->render_ignored_headers( $preview['ignored_headers'] );
		$this->render_warning_list( $this->collect_preview_warnings( $preview ) );

		echo '<table class="widefat striped" style="max-width:960px;margin-bottom:16px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Line', 'velo-glossary' ) . '</th>';
		echo '<th>' . esc_html__( 'Term', 'velo-glossary' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'velo-glossary' ) . '</th>';
		echo '<th>' . esc_html__( 'Associations', 'velo-glossary' ) . '</th>';
		echo '<th>' . esc_html__( 'Related', 'velo-glossary' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( array_slice( (array) $preview['rows'], 0, 50 ) as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['line'] ) . '</td>';
			echo '<td>' . esc_html( $row['term'] ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $row['action'] ) ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $row['associated_content_ids'] ) ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $row['related_term_ids'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		if ( count( (array) $preview['rows'] ) > 50 ) {
			echo '<p class="description">' . esc_html__( 'Only the first 50 preview rows are shown.', 'velo-glossary' ) . '</p>';
		}

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
			<input type="hidden" name="action" value="velo_glossary_confirm_import_csv" />
			<input type="hidden" name="velo_glossary_csv_token" value="<?php echo esc_attr( $token ); ?>" />
			<?php wp_nonce_field( 'velo_glossary_confirm_import_csv' ); ?>
			<?php submit_button( __( 'Confirm import', 'velo-glossary' ), 'primary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
			<input type="hidden" name="action" value="velo_glossary_cancel_import_csv" />
			<input type="hidden" name="velo_glossary_csv_token" value="<?php echo esc_attr( $token ); ?>" />
			<?php wp_nonce_field( 'velo_glossary_cancel_import_csv' ); ?>
			<?php submit_button( __( 'Cancel preview', 'velo-glossary' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render a final import result.
	 *
	 * @param string $token Result token.
	 */
	protected function render_result( $token ) {
		$result = get_transient( $this->get_result_transient_key( $token ) );
		if ( ! is_array( $result ) ) {
			return;
		}

		delete_transient( $this->get_result_transient_key( $token ) );

		if ( 'error' === $result['type'] ) {
			$this->render_notice( 'error', $result['message'] );
			return;
		}

		echo '<div class="notice notice-success inline"><p>';
		printf(
			/* translators: 1: created count, 2: updated count, 3: skipped count. */
			esc_html__( 'Import complete: %1$d created, %2$d updated, %3$d skipped.', 'velo-glossary' ),
			absint( $result['created'] ),
			absint( $result['updated'] ),
			absint( $result['skipped'] )
		);
		echo '</p></div>';

		$this->render_ignored_headers( $result['ignored_headers'] );
		$this->render_warning_list( $result['warnings'] );
	}

	/**
	 * Render ignored CSV headers.
	 *
	 * @param array $headers Ignored headers.
	 */
	protected function render_ignored_headers( $headers ) {
		$headers = array_filter( array_map( 'sanitize_text_field', (array) $headers ) );
		if ( ! $headers ) {
			return;
		}

		echo '<div class="notice notice-warning inline"><p>';
		printf(
			/* translators: %s: comma-separated ignored column names. */
			esc_html__( 'Ignored columns: %s.', 'velo-glossary' ),
			esc_html( implode( ', ', $headers ) )
		);
		echo '</p></div>';
	}

	/**
	 * Render warning messages.
	 *
	 * @param array $warnings Warning messages.
	 */
	protected function render_warning_list( $warnings ) {
		$warnings = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $warnings ) ) ) );
		if ( ! $warnings ) {
			return;
		}

		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Import warnings', 'velo-glossary' ) . '</strong></p><ul style="list-style:disc;margin-left:20px;">';
		foreach ( array_slice( $warnings, 0, 50 ) as $warning ) {
			echo '<li>' . esc_html( $warning ) . '</li>';
		}
		echo '</ul>';

		if ( count( $warnings ) > 50 ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: additional warning count. */
						__( 'And %d more warnings.', 'velo-glossary' ),
						count( $warnings ) - 50
					)
				)
			);
		}

		echo '</div>';
	}

	/**
	 * Render an admin notice.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 */
	protected function render_notice( $type, $message ) {
		printf(
			'<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Preview one CSV row.
	 *
	 * @param array $row CSV row.
	 * @param array $indexes Header indexes.
	 * @param int   $line_number CSV line number.
	 * @return array
	 */
	protected function preview_row( $row, $indexes, $line_number ) {
		$term        = sanitize_text_field( $this->get_row_value( $row, $indexes, 'term' ) );
		$existing_id = $term ? $this->find_glossary_id_by_title( $term ) : 0;
		$present     = array();
		$warnings    = array();

		foreach ( array_keys( self::$supported_columns ) as $key ) {
			$present[ $key ] = array_key_exists( $key, $indexes );
		}

		$entry = array(
			'line'                           => absint( $line_number ),
			'term'                           => $term,
			'action'                         => $term ? ( $existing_id ? 'update' : 'create' ) : 'skip',
			'post_id'                        => $existing_id,
			'present'                        => $present,
			'definition'                     => wp_kses_post( $this->get_row_value( $row, $indexes, 'definition' ) ),
			'status'                         => null,
			'alternative_terms'              => array(),
			'tags'                           => array(),
			'associated_content_candidates'  => array(),
			'associated_content_ids'         => array(),
			'related_term_candidates'        => array(),
			'related_term_ids'               => array(),
			'warnings'                       => array(),
		);

		if ( ! $term ) {
			$entry['warnings'][] = sprintf(
				/* translators: %d: CSV line number. */
				__( 'Line %d was skipped because the Term column is empty.', 'velo-glossary' ),
				absint( $line_number )
			);

			return $entry;
		}

		if ( $present['status'] ) {
			$status = sanitize_key( $this->get_row_value( $row, $indexes, 'status' ) );
			if ( '' === $status && ! $existing_id ) {
				$entry['status'] = 'draft';
			} elseif ( '' === $status ) {
				$entry['status'] = null;
			} elseif ( in_array( $status, self::$valid_statuses, true ) ) {
				$entry['status'] = $status;
			} elseif ( $existing_id ) {
				$warnings[] = sprintf(
					/* translators: 1: CSV line number, 2: invalid post status. */
					__( 'Line %1$d has invalid status "%2$s"; the existing status will be unchanged.', 'velo-glossary' ),
					absint( $line_number ),
					$status
				);
			} else {
				$entry['status'] = 'draft';
				$warnings[]      = sprintf(
					/* translators: 1: CSV line number, 2: invalid post status. */
					__( 'Line %1$d has invalid status "%2$s"; the new term will be created as draft.', 'velo-glossary' ),
					absint( $line_number ),
					$status
				);
			}
		} elseif ( ! $existing_id ) {
			$entry['status'] = 'draft';
		}

		if ( $present['alternative_terms'] ) {
			$entry['alternative_terms'] = $this->sanitize_alternatives( $this->get_row_value( $row, $indexes, 'alternative_terms' ) );
		}

		if ( $present['tags'] ) {
			$entry['tags'] = $this->sanitize_tags( $this->get_row_value( $row, $indexes, 'tags' ) );
		}

		if ( $present['associated_content_ids'] ) {
			$associated                             = $this->parse_id_cell( $this->get_row_value( $row, $indexes, 'associated_content_ids' ), $line_number, __( 'associated content', 'velo-glossary' ) );
			$validated                              = $this->validate_associated_ids( $associated['ids'], $line_number );
			$entry['associated_content_candidates'] = $associated['ids'];
			$entry['associated_content_ids']        = $validated['valid'];
			$warnings                               = array_merge( $warnings, $associated['warnings'], $validated['warnings'] );
		}

		if ( $present['related_term_ids'] ) {
			$related                         = $this->parse_id_cell( $this->get_row_value( $row, $indexes, 'related_term_ids' ), $line_number, __( 'related term', 'velo-glossary' ) );
			$validated                       = $this->validate_related_ids( $related['ids'], $existing_id, $line_number );
			$entry['related_term_candidates'] = $related['ids'];
			$entry['related_term_ids']        = $validated['valid'];
			$warnings                         = array_merge( $warnings, $related['warnings'], $validated['warnings'] );
		}

		$entry['warnings'] = array_values( array_unique( $warnings ) );

		return $entry;
	}

	/**
	 * Map CSV headers to supported columns.
	 *
	 * @param array $header_row CSV header row.
	 * @return array
	 */
	protected function map_headers( $header_row ) {
		$lookup = array();
		foreach ( self::$supported_columns as $key => $label ) {
			$lookup[ $this->normalize_header( $label ) ] = $key;
		}

		$indexes = array();
		$ignored = array();

		foreach ( $header_row as $index => $header ) {
			$header = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header );
			$header = trim( $header );
			if ( '' === $header ) {
				continue;
			}

			$normalized = $this->normalize_header( $header );
			if ( isset( $lookup[ $normalized ] ) ) {
				$indexes[ $lookup[ $normalized ] ] = $index;
			} else {
				$ignored[] = $header;
			}
		}

		return array(
			'indexes' => $indexes,
			'ignored' => array_values( array_unique( $ignored ) ),
		);
	}

	/**
	 * Normalize a header label.
	 *
	 * @param string $header Header label.
	 * @return string
	 */
	protected function normalize_header( $header ) {
		return strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', trim( $header ) ) );
	}

	/**
	 * Get a supported field value from a CSV row.
	 *
	 * @param array  $row CSV row.
	 * @param array  $indexes Header indexes.
	 * @param string $key Supported field key.
	 * @return string
	 */
	protected function get_row_value( $row, $indexes, $key ) {
		if ( ! array_key_exists( $key, $indexes ) ) {
			return '';
		}

		$index = $indexes[ $key ];

		return isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
	}

	/**
	 * Determine whether a CSV row is blank.
	 *
	 * @param array $row CSV row.
	 * @return bool
	 */
	protected function is_empty_row( $row ) {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Split a comma-separated cell.
	 *
	 * @param string $value Raw cell value.
	 * @return array
	 */
	protected function split_cell( $value ) {
		if ( '' === trim( (string) $value ) ) {
			return array();
		}

		$parts = str_getcsv( (string) $value, ',', '"', '\\' );
		$parts = array_map( 'trim', $parts );
		$parts = array_filter(
			$parts,
			static function( $part ) {
				return '' !== $part;
			}
		);

		return array_values( array_unique( $parts ) );
	}

	/**
	 * Sanitize alternatives from a comma-separated cell.
	 *
	 * @param string $value Raw cell value.
	 * @return array
	 */
	protected function sanitize_alternatives( $value ) {
		$alternatives = array_map( 'sanitize_text_field', $this->split_cell( $value ) );
		$alternatives = array_filter(
			$alternatives,
			static function( $alternative ) {
				return strlen( $alternative ) >= 2;
			}
		);

		return array_values( array_unique( $alternatives ) );
	}

	/**
	 * Sanitize tags from a comma-separated cell.
	 *
	 * @param string $value Raw cell value.
	 * @return array
	 */
	protected function sanitize_tags( $value ) {
		$tags = array_map( 'sanitize_text_field', $this->split_cell( $value ) );

		return array_values( array_unique( array_filter( $tags ) ) );
	}

	/**
	 * Parse a comma-separated numeric ID cell.
	 *
	 * @param string $value Raw cell value.
	 * @param int    $line_number CSV line number.
	 * @param string $label Relationship label.
	 * @return array
	 */
	protected function parse_id_cell( $value, $line_number, $label ) {
		$ids      = array();
		$warnings = array();

		foreach ( $this->split_cell( $value ) as $part ) {
			if ( preg_match( '/^[0-9]+$/', $part ) && absint( $part ) > 0 ) {
				$ids[] = absint( $part );
				continue;
			}

			$warnings[] = sprintf(
				/* translators: 1: CSV line number, 2: relationship label, 3: invalid cell value. */
				__( 'Line %1$d skipped invalid %2$s ID "%3$s".', 'velo-glossary' ),
				absint( $line_number ),
				$label,
				$part
			);
		}

		return array(
			'ids'      => array_values( array_unique( $ids ) ),
			'warnings' => $warnings,
		);
	}

	/**
	 * Validate associated content IDs against the same rules as the metabox.
	 *
	 * @param array $ids Candidate post IDs.
	 * @param int   $line_number CSV line number.
	 * @return array
	 */
	protected function validate_associated_ids( $ids, $line_number ) {
		$valid    = array();
		$warnings = array();

		foreach ( array_values( array_unique( array_map( 'absint', (array) $ids ) ) ) as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post && Velo_Glossary_Admin::is_associable_post_for_user( $post ) ) {
				$valid[] = $id;
				continue;
			}

			$warnings[] = sprintf(
				/* translators: 1: CSV line number, 2: invalid post ID. */
				__( 'Line %1$d skipped associated content ID %2$d because it does not exist or is not editable/associable.', 'velo-glossary' ),
				absint( $line_number ),
				$id
			);
		}

		return array(
			'valid'    => $valid,
			'warnings' => $warnings,
		);
	}

	/**
	 * Validate related glossary term IDs against the same rules as the metabox.
	 *
	 * @param array $ids Candidate glossary term IDs.
	 * @param int   $current_post_id Current glossary post ID.
	 * @param int   $line_number CSV line number.
	 * @return array
	 */
	protected function validate_related_ids( $ids, $current_post_id, $line_number ) {
		$valid    = array();
		$warnings = array();

		foreach ( array_values( array_unique( array_map( 'absint', (array) $ids ) ) ) as $id ) {
			$post = get_post( $id );
			if ( Velo_Glossary_Admin::is_related_term_candidate_for_user( $post, $current_post_id ) ) {
				$valid[] = $id;
				continue;
			}

			$warnings[] = sprintf(
				/* translators: 1: CSV line number, 2: invalid glossary term ID. */
				__( 'Line %1$d skipped related term ID %2$d because it does not exist, is not editable, or points to the current term.', 'velo-glossary' ),
				absint( $line_number ),
				$id
			);
		}

		return array(
			'valid'    => $valid,
			'warnings' => $warnings,
		);
	}

	/**
	 * Find an existing glossary entry by exact title.
	 *
	 * @param string $title Glossary title.
	 * @return int
	 */
	protected function find_glossary_id_by_title( $title ) {
		$posts = get_posts(
			array(
				'post_type'              => 'glossary',
				'post_status'            => self::$valid_statuses,
				'title'                  => $title,
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $posts ? absint( $posts[0] ) : 0;
	}

	/**
	 * Collect warnings from a preview payload.
	 *
	 * @param array $preview Preview payload.
	 * @return array
	 */
	protected function collect_preview_warnings( $preview ) {
		$warnings = array();
		foreach ( (array) $preview['rows'] as $row ) {
			$warnings = array_merge( $warnings, (array) $row['warnings'] );
		}

		return array_values( array_unique( $warnings ) );
	}

	/**
	 * Verify a privileged admin-post request.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	protected function verify_admin_request( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Velo Glossary CSV tools.', 'velo-glossary' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Send a CSV download and exit.
	 *
	 * @param string $filename Download filename.
	 * @param array  $rows CSV rows.
	 */
	protected function send_csv( $filename, $rows ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV downloads must stream to php://output.
		foreach ( $rows as $row ) {
			fputcsv( $output, $row, ',', '"', '\\' );
		}
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the CSV download stream.
		exit;
	}

	/**
	 * Create a short token.
	 *
	 * @return string
	 */
	protected function create_token() {
		return strtolower( wp_generate_password( 20, false, false ) );
	}

	/**
	 * Get the settings URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	protected function get_settings_url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'options-general.php?page=velo-glossary' ) );
	}

	/**
	 * Get a user-scoped preview transient key.
	 *
	 * @param string $token Preview token.
	 * @return string
	 */
	protected function get_preview_transient_key( $token ) {
		return self::PREVIEW_TRANSIENT_PREFIX . get_current_user_id() . '_' . sanitize_key( $token );
	}

	/**
	 * Get a user-scoped result transient key.
	 *
	 * @param string $token Result token.
	 * @return string
	 */
	protected function get_result_transient_key( $token ) {
		return self::RESULT_TRANSIENT_PREFIX . get_current_user_id() . '_' . sanitize_key( $token );
	}

	/**
	 * Redirect back to settings with a result payload.
	 *
	 * @param array $result Result payload.
	 */
	protected function redirect_with_result( $result ) {
		$token = $this->create_token();
		set_transient( $this->get_result_transient_key( $token ), $result, self::TRANSIENT_TTL );

		wp_safe_redirect( $this->get_settings_url( array( 'velo_glossary_csv_result' => $token ) ) );
		exit;
	}

	/**
	 * Build an error result payload.
	 *
	 * @param string $message Error message.
	 * @return array
	 */
	protected function make_error_result( $message ) {
		return array(
			'type'            => 'error',
			'message'         => $message,
			'created'         => 0,
			'updated'         => 0,
			'skipped'         => 0,
			'ignored_headers' => array(),
			'warnings'        => array(),
		);
	}
}
