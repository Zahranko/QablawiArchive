<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/**
 * Serves a file from uploads/ to a signed-in user.
 *
 * uploads/.htaccess denies direct web access to that folder, so this script
 * is the only way in. Without it, putting a login on index.php would achieve
 * nothing: anyone who guessed uploads/invoice.pdf would still get the file.
 *
 *   file.php?f=name.pdf          view it
 *   file.php?f=name.pdf&dl=1     download it
 */

require_login();

$name = basename(trim((string) ($_GET['f'] ?? '')));

if ($name === '' || $name === '.' || $name === '..') {
    http_response_code(400);
    exit('Bad request.');
}

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXT, true)) {
    http_response_code(404);
    exit('Not found.');
}

$base = realpath(UPLOAD_DIR);
$path = realpath(UPLOAD_DIR . '/' . $name);

if ($base === false || $path === false || !is_file($path) || dirname($path) !== $base) {
    http_response_code(404);
    exit('Not found.');
}

$types    = SERVE_TYPES;
$size     = (int) filesize($path);
$mtime    = (int) filemtime($path);
$etag     = '"' . md5($name . $size . $mtime) . '"';
$download = isset($_GET['dl']);

/* A filename in a header has to survive quoting. Give a plain ASCII
   fallback plus the RFC 5987 form that carries the real name. */
$ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'file';
$ascii = str_replace(['"', '\\'], '', $ascii);

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . $size);
header(sprintf(
    'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
    $download ? 'attachment' : 'inline',
    $ascii,
    rawurlencode($name)
));
header('X-Content-Type-Options: nosniff');
/* Private, never a shared proxy: these files sit behind a login. */
header('Cache-Control: private, max-age=600, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

$sent  = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$since = strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) ?: 0;

if ($sent === $etag || ($since && $since >= $mtime)) {
    http_response_code(304);
    exit;
}

/* Clear any buffering so a large file streams instead of filling memory. */
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($path);
