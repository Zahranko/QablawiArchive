<?php
declare(strict_types=1);
session_start();

/* ---------------------------------------------------------------
   delete.php — removes one file from uploads/, then redirects back
   --------------------------------------------------------------- */

const UPLOAD_DIR  = __DIR__ . '/uploads';
const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];

/** Store a flash message and bounce back to the gallery. */
function back(string $type, string $message, string $query = '')
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    $url = 'index.php' . ($query !== '' ? '?q=' . rawurlencode($query) : '');
    header('Location: ' . $url, true, 303);
    exit;
}

/* Carried through so a delete does not drop the user's active search. */
$query = mb_substr(trim((string) ($_POST['q'] ?? '')), 0, 60);

/* ---------- 1. Request sanity ---------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back('error', 'Invalid request method.', $query);
}

if (
    empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])
) {
    back('error', 'Security token expired. Please try again.', $query);
}

/* ---------- 2. Reduce the name to a bare filename ---------- */

$name = basename(trim((string) ($_POST['filename'] ?? '')));

if ($name === '' || $name === '.' || $name === '..') {
    back('error', 'No file specified.', $query);
}

/* Only the types this gallery manages can be deleted through it. */
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXT, true)) {
    back('error', 'That file type cannot be removed here.', $query);
}

/* ---------- 3. Prove the resolved path really sits in uploads/ ---------- */

$base = realpath(UPLOAD_DIR);
$path = realpath(UPLOAD_DIR . '/' . $name);

if ($base === false || $path === false || !is_file($path)) {
    back('error', 'That file no longer exists.', $query);
}

/* basename() already blocks traversal; this is the belt to that braces,
   and it also catches a symlink pointing outside the folder. */
if (dirname($path) !== $base) {
    back('error', 'That file is outside the uploads folder.', $query);
}

/* ---------- 4. Remove ---------- */

if (!unlink($path)) {
    back('error', 'Could not delete the file. Check folder permissions.', $query);
}

back('success', '"' . $name . '" was deleted.', $query);
