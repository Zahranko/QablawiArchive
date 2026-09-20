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

/**
 * Largest accepted upload, in bytes. Office documents run larger than
 * images, so this is 10 MB. Whatever you set here, the server's own
 * upload_max_filesize and post_max_size must be at least as large.
 */
defined('MAX_BYTES') || define('MAX_BYTES', 10 * 1024 * 1024);

defined('ALLOWED_EXT') || define('ALLOWED_EXT', [
    'pdf',
    'jpg', 'jpeg', 'png',
    'doc', 'docx',
    'xls', 'xlsx',
    'ppt', 'pptx',
    'txt', 'csv',
]);

/**
 * Real content types each extension is allowed to have, as finfo reports
 * them. The lists are deliberately tolerant: libmagic identifies the
 * OOXML formats (docx/xlsx/pptx) as plain zip archives on many builds,
 * and the legacy binary formats as generic OLE compound documents, so a
 * strict single-value check would reject perfectly good files.
 *
 * The looseness is safe because nothing here is ever executed or
 * extracted on the server — file.php serves these bytes with a fixed
 * Content-Type and X-Content-Type-Options: nosniff, and uploads/.htaccess
 * blocks direct access to the folder entirely.
 */
defined('ALLOWED_MIME') || define('ALLOWED_MIME', [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],

    'txt'  => ['text/plain'],
    'csv'  => ['text/csv', 'text/plain', 'application/csv'],

    'docx' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
    ],
    'xlsx' => [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ],
    'pptx' => [
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
    ],

    'doc'  => [
        'application/msword',
        'application/vnd.ms-office',
        'application/x-ole-storage',
        'application/CDFV2',
        'application/CDFV2-corrupt',
    ],
    'xls'  => [
        'application/vnd.ms-excel',
        'application/vnd.ms-office',
        'application/x-ole-storage',
        'application/CDFV2',
        'application/CDFV2-corrupt',
    ],
    'ppt'  => [
        'application/vnd.ms-powerpoint',
        'application/vnd.ms-office',
        'application/x-ole-storage',
        'application/CDFV2',
        'application/CDFV2-corrupt',
    ],
]);

/** The Content-Type file.php serves each extension with. */
defined('SERVE_TYPES') || define('SERVE_TYPES', [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'txt'  => 'text/plain; charset=utf-8',
    'csv'  => 'text/csv; charset=utf-8',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
]);
