<?php
/**
 * Export handlers for NR Post Exporter plugin.
 *
 * @package NR_Post_Exporter
 */

namespace Nikolareljin\NrPostExporter\Post;

/**
 * Handles post export UI and data transformation.
 */
class Export {

	/**
	 * Constructor. Hooks initialization.
	 */
	public function __construct() {
		self::init();
	}

	/**
	 * Register admin hooks for export links.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_export_link' ) );
	}

	/**
	 * Sets export links in the admin dashboard for public post types.
	 *
	 * @return void
	 */
	public static function add_export_link() {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);
		foreach ( $post_types as $post_type ) {
			add_filter( $post_type . '_row_actions', array( __CLASS__, 'add_export_link_to_post_row_actions' ), 10, 2 );
		}
		add_action( 'admin_post_nrpexp_export', array( __CLASS__, 'export_post_link_action' ) );
	}

	/**
	 * Defines export link content to post row actions.
	 *
	 * @param array    $actions Actions.
	 * @param \WP_Post $post   Post.
	 * @return array
	 */
	public static function add_export_link_to_post_row_actions( $actions, $post ) {
		if ( current_user_can( 'export' ) && current_user_can( 'edit_post', $post->ID ) ) {
			$actions['export'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( self::get_export_link( $post ) ),
				esc_html__( 'Export', 'nr-post-exporter' )
			);
		}

		return $actions;
	}

	/**
	 * Get export link.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function get_export_link( $post ) {
		$nonce = wp_create_nonce( 'nrpexp_export_' . $post->ID );
		return add_query_arg(
			array(
				'action'   => 'nrpexp_export',
				'post'     => $post->ID,
				'_wpnonce' => $nonce,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Returns Blog Path for multisite; empty string for single site.
	 *
	 * @return string
	 */
	private static function get_site_name() {
		$blog_name = '';
		if ( is_multisite() ) {
			// Get blog URL and strip the base URL and leave the path only.
			$blog_name = str_replace( network_home_url(), '', get_bloginfo( 'url' ) );
		}

		return $blog_name;
	}

	/**
	 * Export single post or page using the admin.php?action=export&post=<post_id> link.
	 * Outputs a JSON file for download.
	 *
	 * @return void
	 */
	public static function export_post_link_action() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'nrpexp_export' !== $action ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $post_id || ! wp_verify_nonce( $nonce, 'nrpexp_export_' . $post_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'nr-post-exporter' ) );
		}

		if ( ! current_user_can( 'export' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nr-post-exporter' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			wp_die( esc_html__( 'Post not found.', 'nr-post-exporter' ) );
		}

		$site_name = self::get_site_name();
		$export    = self::export_post( $post );

		// Current date in format YYYY-mm-dd_HH-ii-ss.
		$current_date = gmdate( 'Y-m-d--H-i-s' );

		$filename = sanitize_file_name(
			sprintf(
				'%s-%s-%s.%s.json',
				$post->post_type,
				$post->ID,
				$site_name,
				$current_date
			)
		);

		// Use WordPress helper to send JSON with proper headers and exit.
		nocache_headers();
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		wp_send_json( $export );
	}

	/**
	 * Export a single post.
	 * Includes normalization to avoid double-encoded UTF-8 content in JSON payloads.
	 *
	 * @param \WP_Post $post           Post to export.
	 * @param bool     $with_revisions Export with revisions.
	 * @return array
	 */
	public static function export_post( $post, $with_revisions = true ) {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return array();
		}

		$export = array(
			'post_title'        => self::normalize_utf8( $post->post_title ),
			'post_content'      => self::normalize_utf8( $post->post_content ),
			'post_excerpt'      => $post->post_excerpt,
			'post_status'       => $post->post_status,
			'post_type'         => $post->post_type,
			'post_date'         => $post->post_date,
			'post_date_gmt'     => $post->post_date_gmt,
			'post_modified'     => $post->post_modified,
			'post_modified_gmt' => $post->post_modified_gmt,
			'post_parent'       => $post->post_parent,
			'post_author'       => $post->post_author,
			'menu_order'        => $post->menu_order,
			'comment_status'    => $post->comment_status,
			'ping_status'       => $post->ping_status,
			'post_password'     => $post->post_password,
			'post_name'         => $post->post_name,
			'post_mime_type'    => $post->post_mime_type,
			'guid'              => $post->guid,
			'pinged'            => $post->pinged,
			'to_ping'           => $post->to_ping,
			'filter'            => $post->filter,
			'post_meta'         => self::export_post_meta( $post->ID ),
			'terms'             => self::export_post_terms( $post->ID ),
		);
		if ( $with_revisions ) {
			$export['revisions'] = self::export_post_revisions( $post->ID );
		}

		return $export;
	}

	/**
	 * Normalize content for UTF-8 encoding by encoding to HTML entities.
	 * Prevents double-encoded characters when later JSON-encoded and re-imported.
	 * Applies recursively for arrays.
	 *
	 * @param mixed $value Value to normalize.
	 * @return mixed
	 */
	public static function normalize_utf8( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $val ) {
				$value[ $key ] = self::normalize_utf8( $val );
			}
			return $value;
		}

		if ( is_string( $value ) ) {
      // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.htmlentities_htmlspecialchars -- Internal normalization for export payload.
			return htmlentities( $value, ENT_QUOTES, 'UTF-8' );
		}

		return $value;
	}

	/**
	 * Export post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array Meta key-value pairs.
	 */
	public static function export_post_meta( $post_id ) {
		$meta = get_post_meta( $post_id );

		$export = array();
		foreach ( $meta as $key => $value ) {
			$export[ $key ] = self::normalize_utf8( $value[0] );
		}

		return $export;
	}

	/**
	 * Export post terms.
	 *
	 * @param int $post_id Post ID.
	 * @return array Taxonomy => slugs mapping.
	 */
	public static function export_post_terms( $post_id ) {
		$export = array();

		$taxonomies = get_object_taxonomies( get_post_type( $post_id ) );
		foreach ( $taxonomies as $taxonomy ) {
			try {
				// Get terms for the post.
				$terms = get_the_terms( $post_id, $taxonomy );
			} catch ( \Exception $e ) {
				do_action( 'nrpexp_export_terms_error', $e, $taxonomy, $post_id );
				$terms = array();
			}

			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$export[ $term->taxonomy ][] = $term->slug;
				}
			}
		}

		return $export;
	}

	/**
	 * Exports all post revisions for a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of exported revisions.
	 */
	public static function export_post_revisions( $post_id ) {
		$revisions = wp_get_post_revisions( $post_id );

		$export = array();
		if ( is_array( $revisions ) ) {
			foreach ( $revisions as $revision ) {
				$export[] = self::export_post( $revision, false );
			}
		}

		return $export;
	}
}
