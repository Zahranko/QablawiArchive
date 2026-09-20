<?php
/**
 * Default configuration.
 *
 * Everything here can be overridden without touching this file: create
 * config.local.php next to it and define the same constants. That file is
 * gitignored, so it never reaches the public repository. See README.md.
 */

/* ---------- Accounts ---------- */

/**
 * username => bcrypt hash of the password.
 *
 * Generate a hash for a new password by running this once on the server
 * (hPanel → Advanced → SSH, or paste into a temporary PHP file you delete
 * afterwards):
 *
 *     php -r 'echo password_hash("your new password", PASSWORD_DEFAULT), "\n";'
 */
defined('USERS') || define('USERS', [
    // qablawi / 12  — seeded default. This hash is committed to a public
    // repository, so treat these credentials as publicly known and replace
    // them in config.local.php before the site holds anything private.
    'qablawi' => '$2y$12$7TQOcbxleu3i9GhKW7GJ5.Gf6fNMt5rYhgakQw1.fqNsK0be3/eGu',
]);

/* ---------- Sessions ---------- */

/** How long a login lasts without activity, in seconds. */
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', 60 * 60 * 8);

/** Failed attempts from one session before it is locked out. */
defined('MAX_LOGIN_ATTEMPTS') || define('MAX_LOGIN_ATTEMPTS', 8);

/** Lockout length once that limit is hit, in seconds. */
defined('LOCKOUT_SECONDS') || define('LOCKOUT_SECONDS', 300);

/* ---------- Storage ---------- */

defined('UPLOAD_DIR') || define('UPLOAD_DIR', __DIR__ . '/uploads');

/** Largest accepted upload, in bytes. */
defined('MAX_BYTES') || define('MAX_BYTES', 5 * 1024 * 1024);

defined('ALLOWED_EXT') || define('ALLOWED_EXT', ['pdf', 'jpg', 'jpeg', 'png']);

/** Real content types each extension is allowed to have. */
defined('ALLOWED_MIME') || define('ALLOWED_MIME', [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
]);
