Test Environment for WP Post Exporter

What this does
- Spins up a minimal WordPress 7.1 Multisite (subdirectory) on http://localhost:8080 with MariaDB.
- Creates sites: test1 and test2.
- Network-activates this plugin from the repo.
- Seeds multilingual content + taxonomies on test1.
- Exports the post using the plugin’s Export class.
- Imports it into test2 using a helper that mirrors the plugin’s Import logic.
- Verifies content and taxonomy terms.
- Integrates WordPress Plugin Check for compliance checks.

Requirements
- Docker and Docker Compose plugin installed.

Port configuration
- Tests default to host port `8080`. If that port is taken, export `NRPEX_TEST_PORT=<available-port>` before running any scripts. All WordPress URLs, image selection, and docker bindings will honor that value.
- Set `NRPEX_WORDPRESS_IMAGE` to an official WordPress image when testing another supported version.

Quick start
1) Start and run the test end-to-end:
- `bash test/bin/run.sh`

2) Access the network (replace 8080 with your `NRPEX_TEST_PORT` if overridden):
- Set `NRPEX_WORDPRESS_IMAGE` to an official WordPress image when testing another supported version.
   - WP Admin: `http://localhost:8080/wp-admin/` (admin / admin)
   - test1: `http://localhost:8080/test1/`
   - test2: `http://localhost:8080/test2/`

Notes
- The export helper writes to `test/tmp/export.json` inside this repo.
- To re-run from scratch, you can stop and remove containers/volumes:
  - `bash test/bin/down.sh`

Plugin Check
- Run WP.org Plugin Check (and supplemental meta checks):
  - `bash test/bin/plugin-check.sh`
- Outputs:
  - `test/tmp/plugin-check.json` (if WP-CLI command is available)
  - `test/tmp/meta-check.json` (header/readme consistency checks)
- Notes:
  - The script rebuilds a distributable copy using `.distignore` and mounts it into the containers, matching what you'll upload to WordPress.org.
  - The official Plugin Check plugin is installed into the test site. Some checks may only be available via WP Admin (Tools → Plugin Check). The script will still generate meta checks JSON even if the WP-CLI command is not available.
