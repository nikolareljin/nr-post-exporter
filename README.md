# NR Post Exporter

Export and import individual WordPress posts (with meta, terms, and revisions).

**Project site: https://nikolareljin.github.io/nr-post-exporter/**

See `readme.txt` for WordPress.org metadata and screenshots.

## Features

- Export a single post to JSON from the Posts/Pages list table.
- Import a JSON export from Tools → Post Import.
- Works with all public post types.
- Preserves custom fields, taxonomy terms, and revision history.
- Normalizes UTF-8 content to avoid double-encoding.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Export requires the `export` capability.
- Import requires the `edit_posts` capability.

## Installation

1. Upload the plugin to `/wp-content/plugins/nr-post-exporter` or install via the Plugins screen.
2. Activate the plugin.

## Usage

### Export

1. Go to Posts → All Posts (or Pages).
2. Hover a row and click Export.

<figure>
  <img width="839" height="666" alt="Posts list row with Export action highlighted" title="Step 2: Click Export in the post row actions" src="https://github.com/user-attachments/assets/d16664cd-3939-4954-bce0-dd38dd947afd" />
  <figcaption>Step 2: Click Export from the row actions for the post you want to transfer.</figcaption>
</figure>

3. Save the JSON file locally.

<figure>
  <img width="314" height="173" alt="Save dialog showing a JSON export file" title="Step 3: Save the JSON export locally" src="https://github.com/user-attachments/assets/27b11778-a65f-4409-8665-325e2636fb30" />
  <figcaption>Step 3: Save the downloaded JSON file so it can be imported on another site.</figcaption>
</figure>

### Import

1. Go to Tools → Post Import.
2. Choose a previously exported JSON file.
3. Click Import Post.

<figure>
  <img width="468" height="335" alt="Post Import screen showing file upload and import button" title="Import screen: upload JSON and run import" src="https://github.com/user-attachments/assets/15fa276f-e108-49ce-a18e-68e32ec76b5c" />
  <figcaption>Import screen: upload the exported JSON and run the import.</figcaption>
</figure>

## What the JSON contains

- Post fields (title, content, status, dates, slug, etc.).
- Post meta (custom fields).
- Taxonomy terms (by slug).
- Revisions (when present).

## Import behavior and limits

- The current user becomes the author on import.
- Imported titles are prefixed with `Imported:`.
- Terms are created by slug if they do not exist.
- The upload size limit is ~1MB.
- Media files are not transferred; attachment references remain in content/meta.

## Development

- Install tooling: `composer install`
- Linting: `composer run lint` and `composer run lint:fix`
- Build zip: `bash bin/build-zip.sh`
- Test environment: see `test/README.md`

## Project site

The site under `docs/` is published with GitHub Pages (source: `main`, folder `/docs`).
It is plain HTML with no build step, and it is excluded from the distributed plugin zip
via `.distignore`.

Two regions of `docs/index.html` are generated — the version badge and the changelog.
After bumping the version or editing `CHANGELOG.md`, run:

```bash
bash bin/update-docs.sh          # rewrite the generated regions
bash bin/update-docs.sh --check  # fail if the page is out of sync (used in CI)
```

Everything else on the page is hand-authored; edit it directly.


## Clone traffic

![Clone traffic](https://raw.githubusercontent.com/nikolareljin/stats/main/charts/nr-post-exporter.svg)

_Updated daily. Total and unique cloners over the last 14 days._
