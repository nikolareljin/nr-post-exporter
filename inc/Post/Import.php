<?php
/**
 * Import handlers for NR Post Exporter plugin.
 *
 * @package NR_Post_Exporter
 */

namespace Nikolareljin\NrPostExporter\Post;

/**
 * Handles post import form and reconstruction of posts from JSON.
 */
class Import {

	const ENCODE_OPTIONS   = JSON_HEX_APOS;
	const MAX_UPLOAD_BYTES = 1000000;

	/**
	 * Renders the import form.
	 *
	 * @return void
	 */
	public static function import_post_button() {
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nrpexp_import_form" enctype="multipart/form-data" method="post">
			<input type="hidden" name="action" value="nrpexp_import" />
			<input type="file" accept="application/json,.json" id="upload" name="upload" required />
			<?php
			wp_nonce_field( 'nrpexp_import', 'post_import_nonce' );
			submit_button( __( 'Import Post', 'nr-post-exporter' ), 'secondary', 'submit', false );
			?>
		</form>
		<?php
	}

	/**
	 * Displays an import result stored for the current user.
	 *
	 * @return void
	 */
	public static function import_notice() {
		$key    = 'nrpexp_import_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice ' . esc_attr( empty( $notice['success'] ) ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
	}

	/**
	 * Imports a submitted JSON upload.
	 *
	 * @return void
	 */
	public static function post_import() {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'post' !== $request_method || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nr-post-exporter' ) );
		}
		check_admin_referer( 'nrpexp_import', 'post_import_nonce' );
		if ( 'nrpexp_import' !== ( isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'nr-post-exporter' ) );
		}

		$payload = self::read_upload();
		$result  = is_wp_error( $payload ) ? $payload : self::import_payload( $payload, get_current_user_id() );
		set_transient(
			'nrpexp_import_notice_' . get_current_user_id(),
			array(
				'success' => ! is_wp_error( $result ),
				'message' => is_wp_error( $result ) ? $result->get_error_message() : sprintf(
					/* translators: %d: imported post ID. */
					__( 'Post imported successfully. New post ID: %d.', 'nr-post-exporter' ),
					$result
				),
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'tools.php?page=nrpexp-import' ) );
		exit;
	}

	/**
	 * Reads and decodes a verified JSON upload.
	 *
	 * @return array|\WP_Error
	 */
	private static function read_upload() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_import verifies the nonce before calling this method.
		if ( empty( $_FILES['upload'] ) || ! is_array( $_FILES['upload'] ) ) {
			return new \WP_Error( 'nrpexp_missing_upload', __( 'Select a JSON export file to import.', 'nr-post-exporter' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_import verifies the nonce before calling this method.
		$upload = $_FILES['upload'];
		if ( UPLOAD_ERR_OK !== (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new \WP_Error( 'nrpexp_upload_error', __( 'The upload could not be completed. Please try again.', 'nr-post-exporter' ) );
		}
		$name = isset( $upload['name'] ) ? sanitize_file_name( wp_unslash( $upload['name'] ) ) : '';
		$tmp  = $upload['tmp_name'] ?? '';
		$size = (int) ( $upload['size'] ?? 0 );
		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || $size < 1 || self::MAX_UPLOAD_BYTES < $size ) {
			return new \WP_Error( 'nrpexp_upload_invalid', __( 'Upload a JSON export no larger than 1 MB.', 'nr-post-exporter' ) );
		}
		if ( ! is_string( $tmp ) || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'nrpexp_upload_invalid', __( 'The uploaded file could not be verified.', 'nr-post-exporter' ) );
		}
		try {
			$reader = new SplFileObject( $tmp, 'r' );
			$json   = '';
			foreach ( $reader as $line ) {
				$json .= $line;
			}
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'nrpexp_upload_read', __( 'The uploaded JSON file could not be read.', 'nr-post-exporter' ) );
		}
		if ( false === $json ) {
			return new \WP_Error( 'nrpexp_upload_read', __( 'The uploaded file could not be read.', 'nr-post-exporter' ) );
		}
		try {
			$payload = json_decode( $json, true, 30, JSON_THROW_ON_ERROR );
		} catch ( \Throwable $exception ) {
			return new \WP_Error( 'nrpexp_invalid_json', __( 'The uploaded file is not valid JSON.', 'nr-post-exporter' ) );
		}
		return is_array( $payload ) ? $payload : new \WP_Error( 'nrpexp_invalid_json', __( 'The JSON export must contain a post object.', 'nr-post-exporter' ) );
	}

	/**
	 * Imports one decoded export payload without changing its public JSON schema.
	 *
	 * @param array $payload Export payload.
	 * @param int   $author_id Current user ID.
	 * @return int|\WP_Error
	 */
	public static function import_payload( array $payload, $author_id ) {
		if ( ! self::valid_payload( $payload ) ) {
			return new \WP_Error( 'nrpexp_invalid_payload', __( 'The JSON export contains invalid data.', 'nr-post-exporter' ) );
		}
		$post_data = self::prepare_post_data( $payload, $author_id );
		if ( is_wp_error( $post_data ) ) {
			return $post_data;
		}
		$initial_data = $post_data;
		$revisions    = self::order_revisions( $payload['revisions'] ?? array() );
		if ( ! empty( $revisions ) ) {
			$initial_data = self::prepare_post_data( $revisions[0], $author_id, $post_data['post_type'], $post_data['post_status'] );
			if ( is_wp_error( $initial_data ) ) {
				return $initial_data;
			}
		}
		$initial_data['post_parent'] = 0;
		$post_id                     = wp_insert_post( $initial_data, true, false );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return is_wp_error( $post_id ) ? $post_id : new \WP_Error( 'nrpexp_insert_failed', __( 'The post could not be created.', 'nr-post-exporter' ) );
		}

		foreach ( $revisions as $revision ) {
			$revision_data = self::prepare_post_data( $revision, $author_id, $post_data['post_type'], $post_data['post_status'] );
			if ( is_wp_error( $revision_data ) ) {
				return $revision_data;
			}
			$revision_data['ID'] = $post_id;
			$updated             = wp_update_post( $revision_data, true, false );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			self::save_revision_dates( $post_id, $revision_data );
		}

		$post_data['ID'] = $post_id;
		/* translators: %s: original post title. */
		$post_data['post_title'] = sprintf( __( 'Imported: %s', 'nr-post-exporter' ), $post_data['post_title'] );
		$updated                 = wp_update_post( $post_data, true, false );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		wp_save_post_revision( $post_id );
		self::import_meta( $post_id, $payload['post_meta'] ?? array() );
		self::import_terms( $post_id, $payload['terms'] ?? array() );
		return (int) $post_id;
	}

	/**
	 * Prepares values accepted by the WordPress post APIs.
	 *
	 * @param array       $payload Export payload.
	 * @param int         $author_id Current user ID.
	 * @param string|null $required_type Required type for revisions.
	 * @param string|null $required_status Required status for revisions.
	 * @return array|\WP_Error
	 */
	private static function prepare_post_data( array $payload, $author_id, $required_type = null, $required_status = null ) {
		$data   = self::get_post_data_set( $payload );
		$type   = null === $required_type ? $data['post_type'] : $required_type;
		$type   = $type ? $type : 'post';
		$object = get_post_type_object( $type );
		if ( ! $object ) {
			return new \WP_Error( 'nrpexp_post_type', __( 'The post type in this export is not available on this site.', 'nr-post-exporter' ) );
		}
		$create_cap = ! empty( $object->cap->create_posts ) ? $object->cap->create_posts : $object->cap->edit_posts;
		if ( ! current_user_can( $create_cap ) ) {
			return new \WP_Error( 'nrpexp_create_denied', __( 'You cannot create this post type.', 'nr-post-exporter' ) );
		}
		$status = null === $required_status ? $data['post_status'] : $required_status;
		if ( ! get_post_status_object( $status ) || ( in_array( $status, array( 'publish', 'future' ), true ) && ! current_user_can( $object->cap->publish_posts ) ) || ( 'private' === $status && ! current_user_can( $object->cap->read_private_posts ) ) ) {
			$status = 'draft';
		}
		$data['post_author']  = (int) $author_id;
		$data['post_parent']  = 0;
		$data['post_type']    = $type;
		$data['post_status']  = $status;
		$data['post_content'] = self::normalize_content( $data['post_content'] );
		$data['post_excerpt'] = self::normalize_content( $data['post_excerpt'] );
		$data['post_title']   = self::normalize_text( $data['post_title'] );
		return $data;
	}

	/**
	 * Imports only meta the current user can edit on the new post.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $meta Exported meta.
	 * @return void
	 */
	private static function import_meta( $post_id, $meta ) {
		if ( ! is_array( $meta ) ) {
			return;
		}
		foreach ( $meta as $key => $value ) {
			$key = is_string( $key ) ? $key : '';
			if ( '' !== $key && current_user_can( 'edit_post_meta', $post_id, $key ) ) {
				update_post_meta( $post_id, $key, self::decode_meta_value( $value ) );
			}
		}
	}

	/**
	 * Imports terms only when the current user has assignment and creation permission.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $terms Exported terms.
	 * @return void
	 */
	private static function import_terms( $post_id, $terms ) {
		if ( ! is_array( $terms ) ) {
			return;
		}
		foreach ( $terms as $taxonomy => $slugs ) {
			$taxonomy_object = is_string( $taxonomy ) ? get_taxonomy( $taxonomy ) : null;
			if ( ! $taxonomy_object || ! current_user_can( $taxonomy_object->cap->assign_terms ) || ! is_array( $slugs ) ) {
				continue;
			}
			$term_ids = array();
			foreach ( $slugs as $slug ) {
				$slug = is_string( $slug ) ? sanitize_title( $slug ) : '';
				$term = $slug ? get_term_by( 'slug', $slug, $taxonomy ) : false;
				if ( $term && ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term->term_id;
				} elseif ( $slug && current_user_can( $taxonomy_object->cap->manage_terms ) ) {
					$inserted = wp_insert_term( $slug, $taxonomy, array( 'slug' => $slug ) );
					if ( ! is_wp_error( $inserted ) && ! empty( $inserted['term_id'] ) ) {
						$term_ids[] = (int) $inserted['term_id'];
					}
				}
			}
			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $post_id, array_unique( $term_ids ), $taxonomy, false );
			}
		}
	}

	/**
	 * Saves a revision and updates only that revision's timestamps.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data Revision data.
	 * @return void
	 */
	private static function save_revision_dates( $post_id, array $data ) {
		$revision_id = wp_save_post_revision( $post_id );
		if ( ! is_numeric( $revision_id ) || empty( $data['post_date'] ) ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress has no API for revision timestamps.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_date'         => $data['post_date'],
				'post_date_gmt'     => $data['post_date_gmt'],
				'post_modified'     => $data['post_modified'],
				'post_modified_gmt' => $data['post_modified_gmt'],
			),
			array( 'ID' => (int) $revision_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Prepares the fields accepted by wp_insert_post.
	 *
	 * @param array $post_data Parsed import data.
	 * @return array
	 */
	public static function get_post_data_set( $post_data ) {
		return array(
			'post_content'      => isset( $post_data['post_content'] ) ? (string) $post_data['post_content'] : '',
			'post_title'        => isset( $post_data['post_title'] ) ? (string) $post_data['post_title'] : '',
			'post_excerpt'      => isset( $post_data['post_excerpt'] ) ? (string) $post_data['post_excerpt'] : '',
			'post_status'       => isset( $post_data['post_status'] ) ? sanitize_key( $post_data['post_status'] ) : 'draft',
			'comment_status'    => in_array( $post_data['comment_status'] ?? '', array( 'open', 'closed' ), true ) ? $post_data['comment_status'] : 'closed',
			'ping_status'       => in_array( $post_data['ping_status'] ?? '', array( 'open', 'closed' ), true ) ? $post_data['ping_status'] : 'closed',
			'post_password'     => isset( $post_data['post_password'] ) ? (string) $post_data['post_password'] : '',
			'post_name'         => isset( $post_data['post_name'] ) ? sanitize_title( $post_data['post_name'] ) : '',
			'to_ping'           => isset( $post_data['to_ping'] ) ? sanitize_textarea_field( $post_data['to_ping'] ) : '',
			'pinged'            => isset( $post_data['pinged'] ) ? sanitize_textarea_field( $post_data['pinged'] ) : '',
			'menu_order'        => isset( $post_data['menu_order'] ) ? (int) $post_data['menu_order'] : 0,
			'post_type'         => isset( $post_data['post_type'] ) ? sanitize_key( $post_data['post_type'] ) : 'post',
			'post_mime_type'    => isset( $post_data['post_mime_type'] ) ? sanitize_text_field( $post_data['post_mime_type'] ) : '',
			'post_date'         => self::date_value( $post_data['post_date'] ?? '' ),
			'post_date_gmt'     => self::date_value( $post_data['post_date_gmt'] ?? '' ),
			'post_modified'     => self::date_value( $post_data['post_modified'] ?? '' ),
			'post_modified_gmt' => self::date_value( $post_data['post_modified_gmt'] ?? '' ),
		);
	}

	/**
	 * Validates top-level arrays in the legacy export schema.
	 *
	 * @param array $payload Export payload.
	 * @return bool
	 */
	private static function valid_payload( array $payload ) {
		return ( ! isset( $payload['post_type'] ) || is_string( $payload['post_type'] ) ) && ( ! isset( $payload['revisions'] ) || is_array( $payload['revisions'] ) ) && ( ! isset( $payload['post_meta'] ) || is_array( $payload['post_meta'] ) ) && ( ! isset( $payload['terms'] ) || is_array( $payload['terms'] ) );
	}

	/**
	 * Normalizes HTML entities and applies the current user's HTML policy.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private static function normalize_content( $content ) {
		$content = self::normalize_encoding( self::process_json( $content ) );
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$content = wp_slash( wp_kses_post( wp_unslash( $content ) ) );
		}
		return $content;
	}

	/**
	 * Decodes a text field from the export.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	private static function normalize_text( $value ) {
		return wp_slash( html_entity_decode( wp_unslash( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Decodes meta values recursively.
	 *
	 * @param mixed $value Meta value.
	 * @return mixed
	 */
	private static function decode_meta_value( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::decode_meta_value( $item );
			}
			return $value;
		}
		return is_string( $value ) ? html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : $value;
	}

	/**
	 * Returns a valid MySQL date or an empty string.
	 *
	 * @param mixed $value Date.
	 * @return string
	 */
	private static function date_value( $value ) {
		$value = is_string( $value ) ? $value : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : '';
	}

	/**
	 * Process JSON data and repair legacy double-encoded content.
	 *
	 * @param mixed $input Raw JSON string or nested array.
	 * @return mixed
	 */
	public static function process_json( $input ) {
		$output = $input;
		try {
			if ( is_array( $output ) ) {
				array_walk_recursive(
					$output,
					function ( &$item ) {
						if ( is_string( $item ) ) {
							$item = wp_slash( wp_specialchars_decode( $item, ENT_QUOTES ) );
						}
					}
				);
			} elseif ( is_string( $output ) ) {
				$output = wp_slash( wp_specialchars_decode( $output, ENT_QUOTES ) );
			}
			if ( is_string( $output ) ) {
				$output = preg_replace_callback(
					'#<!-- wp:dj\/([a-zA-Z0-9\-]*) ([{].*?[}]) (\/)?-->(.*<!-- /wp:dj\/\1 -->)?#',
					function ( $matches ) {
						$attributes = json_decode( $matches[2], true );
						if ( ! is_array( $attributes ) ) {
							return $matches[0];
						}
						foreach ( $attributes as $key => $value ) {
							if ( is_string( $value ) ) {
								$attributes[ $key ] = self::unserialize_attributes( $value );
							}
						}
						return '<!-- wp:dj/' . $matches[1] . ' ' . wp_json_encode( $attributes, self::ENCODE_OPTIONS ) . ' ' . ( $matches[3] ?? '' ) . '-->' . ( $matches[4] ?? '' );
					},
					$output
				);
			}
		} catch ( \Throwable $exception ) {
			do_action( 'nrpexp_import_error', $exception );
		}
		return $output;
	}

	/**
	 * Orders revisions from oldest to newest without dropping duplicate dates.
	 *
	 * @param array $revisions Revisions.
	 * @return array
	 */
	public static function order_revisions( array $revisions ): array {
		$revisions = array_values( array_filter( $revisions, 'is_array' ) );
		usort(
			$revisions,
			function ( $left, $right ) {
				return strcmp( (string) ( $left['post_date_gmt'] ?? $left['post_date'] ?? '' ), (string) ( $right['post_date_gmt'] ?? $right['post_date'] ?? '' ) );
			}
		);
		return $revisions;
	}

	/**
	 * Normalizes exported entities to UTF-8 content for database writes.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function normalize_encoding( $content ) {
		return wp_slash( html_entity_decode( wp_unslash( (string) $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Repairs legacy block attribute strings.
	 *
	 * @param string $content Block attributes.
	 * @return string
	 */
	public static function unserialize_attributes( $content ) {
		$output = preg_replace_callback(
			'#\&quot;([a-zA-Z0-9\-]*)\&quot;:(\&quot;.*?\&quot;|\[.*?\])#',
			function ( $matches ) {
				return '"' . $matches[1] . '":' . str_replace( array( '&quot;', '\\' ), array( '"', '' ), $matches[2] );
			},
			$content
		);
		return wp_slash( $output );
	}
}
