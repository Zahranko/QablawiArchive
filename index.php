<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/* Nothing on this page is public. */
require_login();

$flash = take_flash();

/* Active search term, if any. Only ever used for matching and re-display. */
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);

$max_mb = (int) round(MAX_BYTES / 1048576);

function human_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

/**
 * Read the gallery: only files whose extension we know how to render,
 * newest first. scandir() returns bare names, never paths, so nothing
 * here can point outside uploads/.
 */
function gallery_files(): array
{
    if (!is_dir(UPLOAD_DIR)) {
        return [];
    }

    $files = [];

    foreach (scandir(UPLOAD_DIR) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = UPLOAD_DIR . '/' . $name;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXT, true)) {
            continue;
        }
        $files[] = [
            'name'     => $name,
            'ext'      => $ext,
            'is_image' => $ext !== 'pdf',
            'size'     => human_size((int) filesize($path)),
            'date'     => date('j M Y, H:i', (int) filemtime($path)),
            'mtime'    => (int) filemtime($path),
        ];
    }

    usort($files, static function (array $a, array $b): int {
        return $b['mtime'] <=> $a['mtime'];
    });

    return $files;
}

/** Case-insensitive substring match on the visible file name. */
function match_name(array $file, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return mb_stripos($file['name'], $needle) !== false;
}

$all   = gallery_files();
$files = array_values(array_filter(
    $all,
    static function (array $file) use ($q): bool {
        return match_name($file, $q);
    }
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>File Vault</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="wrap">

  <header class="topbar">
    <div class="titles">
      <h1>File Vault</h1>
      <p>Upload a PDF or image, give it a name, and preview it right here.</p>
    </div>

    <div class="session">
      <span>Signed in as <strong><?= e(current_user()) ?></strong></span>
      <form method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit">Sign out</button>
      </form>
    </div>
  </header>

<?php if ($flash): ?>
  <div class="flash <?= e($flash['type']) ?>" role="status">
    <?= e($flash['message']) ?>
  </div>
<?php endif; ?>

  <!-- ============ Upload area ============ -->
  <form class="card uploader" action="upload.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_BYTES ?>">

    <div class="fields">
      <div class="field">
        <label for="upload_file">Choose a file</label>
        <input type="file" id="upload_file" name="upload_file"
               accept=".pdf,.jpg,.jpeg,.png" required>
      </div>

      <div class="field">
        <label for="custom_name">Save as</label>
        <input type="text" id="custom_name" name="custom_name"
               maxlength="60" placeholder="e.g. Q3 invoice" required>
      </div>

      <div class="field">
        <button type="submit">Upload</button>
      </div>
    </div>

    <p class="hint">
      PDF, JPG, JPEG or PNG &middot; up to <?= $max_mb ?> MB.
      The original extension is kept automatically.
    </p>
  </form>

  <!-- ============ Preview gallery ============ -->
  <div class="section-head">
    <h2>Preview gallery</h2>
    <span class="count">
<?php if ($q !== ''): ?>
      <?= count($files) ?> of <?= count($all) ?> file<?= count($all) === 1 ? '' : 's' ?>
<?php else: ?>
      <?= count($all) ?> file<?= count($all) === 1 ? '' : 's' ?>
<?php endif; ?>
    </span>

    <form class="search" action="index.php" method="get" role="search">
      <label class="sr-only" for="q">Search files by name</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>"
             maxlength="60" placeholder="Search by name&hellip;">
      <button type="submit">Search</button>
<?php if ($q !== ''): ?>
      <a class="clear" href="index.php">Clear</a>
<?php endif; ?>
    </form>
  </div>

<?php if (!$all): ?>
  <div class="card empty">Nothing here yet &mdash; your first upload will appear in this space.</div>
<?php elseif (!$files): ?>
  <div class="card empty">
    No files match &ldquo;<?= e($q) ?>&rdquo;. <a href="index.php">Show all files</a>
  </div>
<?php else: ?>
  <div class="grid">
<?php foreach ($files as $file): ?>
<?php   $url = 'file.php?f=' . rawurlencode($file['name']); ?>
    <figure class="card tile">
      <div class="frame">
<?php   if ($file['is_image']): ?>
        <img src="<?= e($url) ?>" alt="<?= e($file['name']) ?>" loading="lazy">
<?php   else: ?>
        <iframe src="<?= e($url) ?>#toolbar=0&amp;navpanes=0&amp;view=FitH"
                title="<?= e($file['name']) ?>" loading="lazy"></iframe>
<?php   endif; ?>
      </div>
      <figcaption class="meta">
        <div class="text">
          <a class="name" href="<?= e($url) ?>" target="_blank" rel="noopener">
            <?= e($file['name']) ?>
          </a>
          <div class="sub">
            <span class="badge"><?= e($file['ext']) ?></span>
            <?= e($file['size']) ?> &middot; <?= e($file['date']) ?>
          </div>
        </div>

        <form class="delete" action="delete.php" method="post"
              onsubmit="return confirm(<?= e((string) json_encode('Delete "' . $file['name'] . '"? This cannot be undone.', JSON_INVALID_UTF8_SUBSTITUTE)) ?>);">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="filename" value="<?= e($file['name']) ?>">
          <input type="hidden" name="q" value="<?= e($q) ?>">
          <button type="submit" title="Delete <?= e($file['name']) ?>"
                  aria-label="Delete <?= e($file['name']) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
              <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5"/>
            </svg>
          </button>
        </form>
      </figcaption>
    </figure>
<?php endforeach; ?>
  </div>
<?php endif; ?>

</div>
</body>
</html>
