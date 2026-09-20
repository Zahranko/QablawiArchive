<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/* ---------------------------------------------------------------
   upload.php — receives the form, validates, stores, redirects back
   --------------------------------------------------------------- */

require_login();

/**
 * Turn any user-supplied string into a safe, traversal-proof file stem.
 * Keeps letters/digits/space/dash/underscore only, so "../../etc/passwd"
 * collapses to "etcpasswd" and can never escape uploads/.
 */
function safe_stem(string $raw): string
{
    $stem = basename(trim($raw));                       // strip any path part
    $stem = preg_replace('/[^\p{L}\p{N} _-]+/u', '', $stem) ?? '';
    $stem = preg_replace('/[\s_]+/u', '-', $stem) ?? ''; // spaces -> dashes
    $stem = trim($stem, "-. \t\n\r\0\x0B");
    $stem = mb_substr($stem, 0, 60);
    return $stem !== '' ? $stem : 'file';
}

/* ---------- 1. Request sanity ---------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back('error', 'Invalid request method.');
}

if (!csrf_ok()) {
    back('error', 'Security token expired. Please try again.');
}

/* A POST larger than post_max_size arrives with empty $_POST and $_FILES. */
if (empty($_FILES['upload_file'])) {
    back('error', 'The file is too large for this server to accept.');
}

$file = $_FILES['upload_file'];

/* ---------- 2. PHP-level upload errors ---------- */

switch ($file['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_NO_FILE:
        back('error', 'Please choose a file to upload.');
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        back('error', 'That file exceeds the maximum allowed size.');
    default:
        back('error', 'Upload failed. Please try again.');
}

/* ---------- 3. Size ---------- */

if ($file['size'] <= 0) {
    back('error', 'The selected file is empty.');
}
if ($file['size'] > MAX_BYTES) {
    back('error', 'Maximum file size is ' . round(MAX_BYTES / 1048576, 1) . ' MB.');
}

/* ---------- 4. Extension whitelist ---------- */

$ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXT, true)) {
    back('error', 'That file type is not accepted. Allowed: '
        . strtoupper(implode(', ', ALLOWED_EXT)) . '.');
}

/* ---------- 5. Real content type, not just the extension ---------- */

if (!is_uploaded_file($file['tmp_name'])) {
    back('error', 'Upload failed. Please try again.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = (string) $finfo->file($file['tmp_name']);

$allowed = ALLOWED_MIME;
if (!in_array($mime, $allowed[$ext], true)) {
    back('error', 'The file contents do not match its extension.');
}

/* Images must actually decode as images. Only images: getimagesize()
   returns false for documents, so testing anything else here would
   reject every valid PDF, spreadsheet and Word file. */
if (in_array($ext, IMAGE_EXT, true) && getimagesize($file['tmp_name']) === false) {
    back('error', 'That image appears to be corrupt.');
}

/* ---------- 6. Build a unique, safe destination ---------- */

if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
    back('error', 'Server storage is unavailable.');
}
if (!is_writable(UPLOAD_DIR)) {
    back('error', 'The uploads folder is not writable.');
}

$stem     = safe_stem((string) ($_POST['custom_name'] ?? ''));
$filename = $stem . '.' . $ext;
$target   = UPLOAD_DIR . '/' . $filename;

/* Never silently overwrite: append -2, -3, ... on collision. */
for ($i = 2; file_exists($target) && $i < 1000; $i++) {
    $filename = $stem . '-' . $i . '.' . $ext;
    $target   = UPLOAD_DIR . '/' . $filename;
}

/* ---------- 7. Store ---------- */

if (!move_uploaded_file($file['tmp_name'], $target)) {
    back('error', 'Could not save the file. Check folder permissions.');
}
chmod($target, 0644);

back('success', '"' . $filename . '" uploaded successfully.');
