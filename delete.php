<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/* ---------------------------------------------------------------
   delete.php — removes one file from uploads/, then redirects back
   --------------------------------------------------------------- */

require_login();

/* Carried through so a delete does not drop the user's active search. */
$query = mb_substr(trim((string) ($_POST['q'] ?? '')), 0, 60);
$home  = 'index.php' . ($query !== '' ? '?q=' . rawurlencode($query) : '');

/* ---------- 1. Request sanity ---------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back('error', 'Invalid request method.', $home);
}

if (!csrf_ok()) {
    back('error', 'Security token expired. Please try again.', $home);
}

/* ---------- 2. Reduce the name to a bare filename ---------- */

$name = basename(trim((string) ($_POST['filename'] ?? '')));

if ($name === '' || $name === '.' || $name === '..') {
    back('error', 'No file specified.', $home);
}

/* Only the types this gallery manages can be deleted through it. */
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXT, true)) {
    back('error', 'That file type cannot be removed here.', $home);
}

/* ---------- 3. Prove the resolved path really sits in uploads/ ---------- */

$base = realpath(UPLOAD_DIR);
$path = realpath(UPLOAD_DIR . '/' . $name);

if ($base === false || $path === false || !is_file($path)) {
    back('error', 'That file no longer exists.', $home);
}

/* basename() already blocks traversal; this is the belt to that braces,
   and it also catches a symlink pointing outside the folder. */
if (dirname($path) !== $base) {
    back('error', 'That file is outside the uploads folder.', $home);
}

/* ---------- 4. Remove ---------- */

if (!unlink($path)) {
    back('error', 'Could not delete the file. Check folder permissions.', $home);
}

back('success', '"' . $name . '" was deleted.', $home);
