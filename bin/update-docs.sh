#!/usr/bin/env bash
#
# Sync the GitHub Pages site (docs/index.html) with the plugin's real version
# and changelog.
#
# The page is hand-authored. This script owns exactly two regions of it:
#
#   <!-- BEGIN:VERSION -->1.0.1<!-- END:VERSION -->   (may appear many times)
#   <!-- BEGIN:CHANGELOG --> ... <!-- END:CHANGELOG -->
#
# Sources of truth:
#   - version   : the "Version:" header in nr-post-exporter.php
#   - changelog : CHANGELOG.md
#
# Usage:
#   bin/update-docs.sh            Rewrite docs/index.html in place.
#   bin/update-docs.sh --check    Exit 1 if docs/index.html is out of sync.
#
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_file="${repo_root}/nr-post-exporter.php"
changelog_file="${repo_root}/CHANGELOG.md"
index_file="${repo_root}/docs/index.html"

# How many releases to render on the page. Older entries stay in CHANGELOG.md.
max_releases="${MAX_RELEASES:-5}"

check_only=0
case "${1:-}" in
  --check) check_only=1 ;;
  "") ;;
  *)
    echo "Usage: $0 [--check]" >&2
    exit 2
    ;;
esac

for f in "${plugin_file}" "${changelog_file}" "${index_file}"; do
  if [[ ! -f "${f}" ]]; then
    echo "error: missing ${f}" >&2
    exit 1
  fi
done

version="$(sed -n -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.]+)?).*/\1/p' "${plugin_file}" | head -n1)"
if [[ -z "${version}" ]]; then
  echo "error: could not read Version from ${plugin_file}" >&2
  exit 1
fi

for marker in 'BEGIN:VERSION' 'END:VERSION' 'BEGIN:CHANGELOG' 'END:CHANGELOG'; do
  if ! grep -q "<!-- ${marker} -->" "${index_file}"; then
    echo "error: marker <!-- ${marker} --> not found in ${index_file}" >&2
    exit 1
  fi
done

tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT
rendered_changelog="${tmp_dir}/changelog.html"
rendered_index="${tmp_dir}/index.html"

# --- CHANGELOG.md -> HTML -------------------------------------------------
awk -v max_releases="${max_releases}" '
function esc(s) {
  gsub(/&/, "\\&amp;", s)
  gsub(/</, "\\&lt;", s)
  gsub(/>/, "\\&gt;", s)
  return s
}
function codespans(s,   n) {
  n = 0
  while (match(s, /`/)) {
    if (n % 2 == 0) {
      s = substr(s, 1, RSTART - 1) "<code>" substr(s, RSTART + 1)
    } else {
      s = substr(s, 1, RSTART - 1) "</code>" substr(s, RSTART + 1)
    }
    n++
  }
  if (n % 2 == 1) { s = s "</code>" }
  return s
}
function close_list() {
  if (in_list) { print "        </ul>"; in_list = 0 }
}
function close_release() {
  close_list()
  if (in_release) { print "      </article>"; in_release = 0 }
}
# Link-reference definitions at the bottom end the changelog body.
/^\[[^]]+\]:[[:space:]]*http/ { next }
/^##[[:space:]]+\[/ {
  if (released >= max_releases) { done = 1; next }
  done = 0
  close_release()
  line = $0
  sub(/^##[[:space:]]+/, "", line)
  ver = line
  date = ""
  sub(/^\[/, "", ver)
  sub(/\].*$/, "", ver)
  if (match(line, /-[[:space:]]*[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]/)) {
    date = substr(line, RSTART, RLENGTH)
    sub(/^-[[:space:]]*/, "", date)
  }
  print "      <article class=\"release\">"
  if (date != "") {
    printf "        <h3>%s <time datetime=\"%s\">%s</time></h3>\n", esc(ver), esc(date), esc(date)
  } else {
    printf "        <h3>%s</h3>\n", esc(ver)
  }
  in_release = 1
  released++
  next
}
done { next }
/^###[[:space:]]+/ {
  close_list()
  group = $0
  sub(/^###[[:space:]]+/, "", group)
  printf "        <h4>%s</h4>\n", esc(group)
  next
}
/^[-*][[:space:]]+/ {
  if (!in_release) { next }
  if (!in_list) { print "        <ul>"; in_list = 1 }
  item = $0
  sub(/^[-*][[:space:]]+/, "", item)
  printf "          <li>%s</li>\n", codespans(esc(item))
  next
}
END { close_release() }
' "${changelog_file}" > "${rendered_changelog}"

if [[ ! -s "${rendered_changelog}" ]]; then
  echo "error: rendered changelog is empty; check the format of ${changelog_file}" >&2
  exit 1
fi

# --- inject into docs/index.html ------------------------------------------
awk -v version="${version}" -v changelog="${rendered_changelog}" '
index($0, "<!-- BEGIN:CHANGELOG -->") > 0 {
  print
  while ((getline line < changelog) > 0) { print line }
  close(changelog)
  skipping = 1
  next
}
index($0, "<!-- END:CHANGELOG -->") > 0 { skipping = 0; print; next }
skipping { next }
{
  gsub(/<!-- BEGIN:VERSION -->[^<]*<!-- END:VERSION -->/, "<!-- BEGIN:VERSION -->" version "<!-- END:VERSION -->")
  print
}
' "${index_file}" > "${rendered_index}"

if [[ "${check_only}" -eq 1 ]]; then
  if diff -u "${index_file}" "${rendered_index}" > "${tmp_dir}/diff.txt"; then
    echo "docs/index.html is in sync (version ${version})."
    exit 0
  fi
  echo "error: docs/index.html is out of sync with the plugin version and CHANGELOG.md." >&2
  echo "Run: bin/update-docs.sh" >&2
  echo >&2
  cat "${tmp_dir}/diff.txt" >&2
  exit 1
fi

if cmp -s "${index_file}" "${rendered_index}"; then
  echo "docs/index.html already up to date (version ${version})."
else
  cat "${rendered_index}" > "${index_file}"
  echo "docs/index.html updated (version ${version}, ${max_releases} most recent releases)."
fi
