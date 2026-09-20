<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/**
 * Serves a file from uploads/ to a signed-in user.
 *
 * uploads/.htaccess denies direct web access to that folder, so this script
 * is the only way in. Without it, putting a login on index.php would achieve
 * nothing: anyone who guessed uploads/invoice.pdf would still get the file.
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

$types = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];

$size  = (int) filesize($path);
$mtime = (int) filemtime($path);
$etag  = '"' . md5($name . $size . $mtime) . '"';

/* Let the browser reuse what it already has, but only in a private cache —
   never a shared proxy, since these files are behind a login. */
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . $size);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

$sent  = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$since = strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) ?: 0;

if ($sent === $etag || ($since && $since >= $mtime)) {
    http_response_code(304);
    exit;
}

/* Clear any buffering so a large PDF streams instead of filling memory. */
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($path);
