<?php
/**
 * Plugin Name:       NR Post Exporter
 * Plugin URI:        https://github.com/nikolareljin/nr-post-exporter
 * Description:       Export and import individual WordPress posts (with meta, terms, and revisions).
 * Author:            Nikola Reljin
 * Author URI:        https://profiles.wordpress.org/nikolareljin/
 * Version:           1.0.1
 * Requires at least: 5.8
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       nr-post-exporter
 * Domain Path:       /languages
 *
 * @package           NR_Post_Exporter
 */

namespace Nikolareljin\NrPostExporter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Optionally load Composer autoload if present.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Define common plugin paths.
if ( ! defined( 'NRPEXP_PLUGIN_PATH' ) ) {
	define( 'NRPEXP_PLUGIN_PATH', __DIR__ . '/' );
}
if ( ! defined( 'NRPEXP_PLUGIN_URL' ) ) {
	define( 'NRPEXP_PLUGIN_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );
}

// Fallback requires if no Composer autoload is available.
if ( ! class_exists( '\\Nikolareljin\\NrPostExporter\\Post\\Export' ) ) {
	require_once NRPEXP_PLUGIN_PATH . 'inc/Post/Export.php';
}
if ( ! class_exists( '\\Nikolareljin\\NrPostExporter\\Post\\Import' ) ) {
	require_once NRPEXP_PLUGIN_PATH . 'inc/Post/Import.php';
}

use Nikolareljin\NrPostExporter\Post\Export;
use Nikolareljin\NrPostExporter\Post\Import;

// Since WordPress 4.6, translations for plugins hosted on WordPress.org
// are loaded automatically and do not need an explicit call.

// Initialize hooks after plugins load to ensure WP is ready.
add_action(
	'plugins_loaded',
	static function () {
		// Initialize export link injections for post rows.
		Export::init();

		// Register handler for the file upload import action.
		add_action( 'admin_post_nrpexp_import', array( Import::class, 'post_import' ) );
	}
);

// Simple admin page to expose the Import Post form.
add_action(
	'admin_menu',
	static function () {
			add_management_page(
				__( 'Post Import', 'nr-post-exporter' ),
				__( 'Post Import', 'nr-post-exporter' ),
				'edit_posts',
				'nrpexp-import',
				function () {
					echo '<div class="wrap">';
					echo '<h1>' . esc_html__( 'Import Post', 'nr-post-exporter' ) . '</h1>';
					echo '<p>' . esc_html__( 'Upload a previously exported JSON file to create a copy of that post (including meta, taxonomies, and revisions).', 'nr-post-exporter' ) . '</p>';
					Import::import_post_button();
					echo '</div>';
				}
			);
	}
);
