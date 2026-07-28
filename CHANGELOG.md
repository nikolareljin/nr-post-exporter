# Changelog

All notable changes to this project will be documented in this file.

## [1.0.1] - 2026-07-28
### Added
- Project site under `docs/`, published with GitHub Pages: export and import walkthroughs, the JSON payload structure, install steps, requirements, and FAQ.
- `bin/update-docs.sh` to sync the site's version and changelog from `nr-post-exporter.php` and `CHANGELOG.md`. Run with `--check` to fail on drift.

### Changed
- Documentation only. No change to plugin runtime behaviour.
- Excluded `docs/` from the distributed plugin package.

## [1.0.0] - 2025-12-22
### Changed
- Set explicit SVN slug/URL for WordPress.org deploys to ensure publishing to the correct repo.
- Switched WP-CLI install in CI to the official builds phar for reliable POT generation.
- Excluded the `test` directory from distributed plugin packages.

## [1.0.0-rc.1] - 2025-10-31
### Updated
- Standardized plugin-specific constants and admin hooks to the `nrpexp` prefix to avoid collisions.
- Unified export/import nonces and action slugs under the new prefix for consistency with WordPress guidelines.
- Updated `readme.txt` metadata: added contributor `nreljin`, trimmed tags to five, and shortened the description to WordPress.org limits.
- Swapped `error_log()` call for the `nrpexp_export_terms_error` action to surface term export failures without shipping debug logging.

## [0.1.2] - 2025-12-20
### Updated
- Refined namespaces and linting to align with WordPress review feedback.

## [0.1.1] - 2025-10-20
### Fixed
- Corrected the WordPress.org dist build workflow to deploy cleanly.

## [0.1.0] - 2025-10-20
### Added
- Export/import functionality for posts using JSON data.
- Local Docker-based test environment and WP PHPCS rulesets.

[1.0.0-rc.1]: https://github.com/nikolareljin/nr-post-exporter/releases/tag/v1.0.0-rc.1
[0.1.2]: https://github.com/nikolareljin/nr-post-exporter/releases/tag/0.1.2
[0.1.1]: https://github.com/nikolareljin/nr-post-exporter/releases/tag/0.1.1
[0.1.0]: https://github.com/nikolareljin/nr-post-exporter/releases/tag/0.1.0
