<?php
// Usage: wp eval-file /workspace/wpcli-import.php <input_path> [--url=...] [--user=...]

use Nikolareljin\NrPostExporter\Post\Import;

if (!defined('ABSPATH')) {
    exit(1);
}

if (!isset($args) || count($args) < 1) {
    fwrite(STDERR, "Usage: wp eval-file wpcli-import.php <input_path>\n");
    exit(1);
}

$input = $args[0];
if (!is_readable($input)) {
    fwrite(STDERR, "Input file not readable\n");
    exit(1);
}

$handle = fopen($input, 'rb');
$json = $handle ? stream_get_contents($handle) : false;
if (is_resource($handle)) {
    fclose($handle);
}
if ($json === false) {
    fwrite(STDERR, "Failed to read input\n");
    exit(1);
}

try {
    $payload = json_decode($json, true, 30, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

$result = Import::import_payload($payload, get_current_user_id());
if (is_wp_error($result)) {
    fwrite(STDERR, $result->get_error_message() . "\n");
    exit(1);
}

echo (int) $result . "\n";
