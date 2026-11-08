<?php
// Usage: wp eval-file /workspace/wpcli-plugin-meta-check.php

if (!defined('ABSPATH')) {
    exit(1);
}

$plugin_file = WP_PLUGIN_DIR . '/nr-post-exporter/nr-post-exporter.php';
$readme_file = WP_PLUGIN_DIR . '/nr-post-exporter/readme.txt';

if (!is_readable($plugin_file)) {
    fwrite(STDERR, "Plugin file not found: {$plugin_file}\n");
    exit(1);
}

// Collect plugin header data.
if (!function_exists('get_file_data')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}

$headers = array(
    'Name'              => 'Plugin Name',
    'Version'           => 'Version',
    'RequiresAtLeast'   => 'Requires at least',
    'RequiresPHP'       => 'Requires PHP',
    'TextDomain'        => 'Text Domain',
    'DomainPath'        => 'Domain Path',
);

$plugin_data = get_file_data($plugin_file, $headers, 'plugin');

// Parse readme.txt minimal fields.
$readme = array(
    'StableTag'        => null,
    'RequiresAtLeast'  => null,
    'TestedUpTo'       => null,
    'RequiresPHP'      => null,
);

if (is_readable($readme_file)) {
    $lines = file($readme_file, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        if (stripos($line, 'Stable tag:') === 0) {
            $readme['StableTag'] = trim(substr($line, strlen('Stable tag:')));
        } elseif (stripos($line, 'Requires at least:') === 0) {
            $readme['RequiresAtLeast'] = trim(substr($line, strlen('Requires at least:')));
        } elseif (stripos($line, 'Tested up to:') === 0) {
            $readme['TestedUpTo'] = trim(substr($line, strlen('Tested up to:')));
        } elseif (stripos($line, 'Requires PHP:') === 0) {
            $readme['RequiresPHP'] = trim(substr($line, strlen('Requires PHP:')));
        }
    }
}

$slug = 'nr-post-exporter';

// Run checks.
$results = array();

// 1) Text Domain matches slug.
$results['text_domain_matches_slug'] = array(
    'expected' => $slug,
    'actual'   => $plugin_data['TextDomain'] ?? '',
    'ok'       => ($plugin_data['TextDomain'] ?? '') === $slug,
);

// 2) Domain Path suggested '/languages'.
$results['domain_path_languages'] = array(
    'expected' => '/languages',
    'actual'   => $plugin_data['DomainPath'] ?? '',
    'ok'       => ($plugin_data['DomainPath'] ?? '') === '/languages',
);

// 3) Requires at least present (header and readme).
$results['requires_at_least_present'] = array(
    'plugin'  => $plugin_data['RequiresAtLeast'] ?? '',
    'readme'  => $readme['RequiresAtLeast'] ?? '',
    'ok'      => (string)($plugin_data['RequiresAtLeast'] ?? '') !== '' && (string)($readme['RequiresAtLeast'] ?? '') !== '',
);

// 4) Requires PHP present (header and readme).
$results['requires_php_present'] = array(
    'plugin'  => $plugin_data['RequiresPHP'] ?? '',
    'readme'  => $readme['RequiresPHP'] ?? '',
    'ok'      => (string)($plugin_data['RequiresPHP'] ?? '') !== '' && (string)($readme['RequiresPHP'] ?? '') !== '',
);

// 5) Tested up to present in readme.
$results['tested_up_to_present'] = array(
    'readme'  => $readme['TestedUpTo'] ?? '',
    'ok'      => (string)($readme['TestedUpTo'] ?? '') !== '',
);

// 6) Stable tag/version consistency (if stable tag is not 'trunk').
$stable = (string)($readme['StableTag'] ?? '');
$version = (string)($plugin_data['Version'] ?? '');
$results['version_consistency'] = array(
    'plugin_version' => $version,
    'stable_tag'     => $stable,
    'ok'             => ($stable === 'trunk') || ($stable === $version && $version !== ''),
);

// Return as JSON.
echo wp_json_encode(array(
    'plugin_headers' => $plugin_data,
    'readme'         => $readme,
    'checks'         => $results,
)) . "\n";
