=== NR Post Exporter ===
Contributors: nreljin
Donate link: https://profiles.wordpress.org/nikolareljin/
Tags: export, import, posts, revisions
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.1
Requires PHP: 7.4
License: MIT
License URI: https://opensource.org/licenses/MIT

Export/import individual posts with meta, terms, and revisions. Adds Export row action and Import screen.

== Description ==

A lightweight tool to move content between WordPress sites with precision.

Features
- Export a single post or page to JSON (includes meta, terms, and revisions)
- Import a previously exported JSON to recreate the post on another site
- Works with all public post types; sets current user as the author on import
- Adds an Export action in Posts/Pages list rows
- Adds Tools → Post Import with a file upload form
- Robust UTF-8 handling: exports HTML‑entity encode content/meta; imports decode to avoid double‑encoding

Perfect for
- Migrating content between environments
- Duplicating complex posts across sites
- Archiving rich post data including revision history

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/nr-post-exporter` directory, or install via the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. To export: go to Posts → All Posts (or Pages), hover a row, click Export.
4. To import: go to Tools → Post Import and upload the exported JSON file.

== Frequently Asked Questions ==

= What does the JSON include? =
Post core fields, custom fields (meta), terms for all taxonomies, and the full revisions history.

= Does it support custom post types? =
Yes, any public post type.

= Who will be set as the author on import? =
The current logged-in user performing the import.

= Does this replace WordPress’s native WXR export? =
No. This focuses on one-post-at-a-time portability with revisions and meta fidelity.

= Are images and attachments included? =
Attachment references in content and meta are preserved as-is; media files are not transferred.

= Is there a file size limit? =
Imports are limited to about 1MB per JSON file.

= Why does the title change on import? =
Imported posts are prefixed with "Imported:" to make them easier to spot.

== Screenshots ==
1. Export link in post row actions.
2. Import page under Tools menu.

== Changelog ==

= 1.0.1 =
* Documentation only. Added a project site with export/import walkthroughs, the JSON payload structure, install steps, and FAQ. No change to plugin behaviour.

= 1.0.0 =
* Initial release. Extracted from an internal toolkit and packaged as a standalone plugin.


== Upgrade Notice ==

= 1.0.1 =
Documentation update only. Nothing changes in the plugin itself.

= 1.0.0 =
Initial release.
