<?php
declare(strict_types=1);
session_start();

/* One-time CSRF token for the upload form. */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Pull and clear the flash message left by upload.php. */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* Active search term, if any. Only ever used for matching and re-display. */
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);

const UPLOAD_DIR = __DIR__ . '/uploads';
const UPLOAD_URL = 'uploads';
const MAX_MB     = 5;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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

    $renderable = ['pdf', 'jpg', 'jpeg', 'png'];
    $files      = [];

    foreach (scandir(UPLOAD_DIR) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = UPLOAD_DIR . '/' . $name;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $renderable, true)) {
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
<title>File Vault</title>
<style>
  :root {
    --bg:        #f4f5f7;
    --surface:   #ffffff;
    --border:    #e3e5e9;
    --text:      #16181d;
    --muted:     #6b7280;
    --accent:    #3b5bfd;
    --accent-fg: #ffffff;
    --ok-bg:     #e7f6ec;
    --ok-fg:     #15633a;
    --err-bg:    #fdecec;
    --err-fg:    #9b1c1c;
    --radius:    14px;
    --shadow:    0 1px 2px rgba(16,18,29,.06), 0 8px 24px rgba(16,18,29,.06);
  }

  @media (prefers-color-scheme: dark) {
    :root {
      --bg:      #0f1115;
      --surface: #171a21;
      --border:  #262a33;
      --text:    #e9ebf0;
      --muted:   #9aa1af;
      --accent:  #6b84ff;
      --ok-bg:   #12301f;
      --ok-fg:   #7fe0a6;
      --err-bg:  #33161a;
      --err-fg:  #ff9ea4;
      --shadow:  0 1px 2px rgba(0,0,0,.4), 0 8px 24px rgba(0,0,0,.35);
    }
  }

  * { box-sizing: border-box; }

  .sr-only {
    position: absolute;
    width: 1px; height: 1px;
    padding: 0; margin: -1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
    border: 0;
  }

  body {
    margin: 0;
    padding: 40px 16px 72px;
    background: var(--bg);
    color: var(--text);
    font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
  }

  .wrap { max-width: 1080px; margin: 0 auto; }

  header { margin-bottom: 28px; }
  header h1 { margin: 0 0 6px; font-size: 1.75rem; letter-spacing: -.02em; }
  header p  { margin: 0; color: var(--muted); }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
  }

  /* ---------- upload area ---------- */

  .uploader { padding: 22px; margin-bottom: 36px; }

  .fields {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 14px;
    align-items: end;
  }

  @media (max-width: 720px) {
    .fields { grid-template-columns: 1fr; }
  }

  .field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }

  label {
    font-size: .8rem;
    font-weight: 600;
    letter-spacing: .01em;
    color: var(--muted);
  }

  input[type="text"], input[type="file"] {
    width: 100%;
    padding: 11px 12px;
    font: inherit;
    font-size: .95rem;
    color: var(--text);
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
  }

  input[type="file"]::file-selector-button {
    margin-right: 10px;
    padding: 6px 12px;
    font: inherit;
    font-size: .85rem;
    color: var(--text);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 7px;
    cursor: pointer;
  }

  input:focus-visible, button:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
  }

  button {
    padding: 11px 22px;
    font: inherit;
    font-weight: 600;
    color: var(--accent-fg);
    background: var(--accent);
    border: 0;
    border-radius: 10px;
    cursor: pointer;
    white-space: nowrap;
  }

  button:hover { filter: brightness(1.08); }

  .hint { margin: 14px 0 0; font-size: .82rem; color: var(--muted); }

  /* ---------- flash message ---------- */

  .flash {
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: .92rem;
    border-radius: 10px;
  }
  .flash.success { background: var(--ok-bg);  color: var(--ok-fg); }
  .flash.error   { background: var(--err-bg); color: var(--err-fg); }

  /* ---------- preview gallery ---------- */

  .section-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
  }
  .section-head h2 { margin: 0; font-size: 1.1rem; letter-spacing: -.01em; }
  .count { font-size: .85rem; color: var(--muted); }

  /* ---------- search ---------- */

  .search {
    display: flex;
    gap: 8px;
    margin-left: auto;
  }

  .search input[type="search"] {
    width: 220px;
    max-width: 48vw;
    padding: 8px 12px;
    font: inherit;
    font-size: .88rem;
    color: var(--text);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
  }

  .search input[type="search"]:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
  }

  .search button {
    padding: 8px 16px;
    font-size: .88rem;
  }

  .search .clear {
    display: inline-flex;
    align-items: center;
    padding: 8px 14px;
    font-size: .88rem;
    font-weight: 600;
    color: var(--muted);
    text-decoration: none;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
  }
  .search .clear:hover { color: var(--text); }

  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 18px;
  }

  .tile { margin: 0; overflow: hidden; display: flex; flex-direction: column; }

  .tile .frame {
    aspect-ratio: 4 / 3;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    overflow: hidden;
  }

  .tile img,
  .tile iframe {
    display: block;
    width: 100%;
    height: 100%;
    border: 0;
  }

  .tile img { object-fit: cover; }

  .meta {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
  }

  .meta .text { min-width: 0; flex: 1; }

  .meta .name {
    display: block;
    font-size: .92rem;
    font-weight: 600;
    color: var(--text);
    text-decoration: none;
    overflow-wrap: anywhere;
  }
  .meta .name:hover { color: var(--accent); }

  .meta .sub { margin-top: 3px; font-size: .78rem; color: var(--muted); }

  .badge {
    display: inline-block;
    margin-right: 6px;
    padding: 1px 7px;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--accent);
    background: rgba(59, 91, 253, .12);
    border-radius: 999px;
  }

  /* ---------- delete ---------- */

  .delete { flex-shrink: 0; margin: 0; }

  .delete button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    color: var(--muted);
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 9px;
  }

  .delete button:hover {
    color: var(--err-fg);
    background: var(--err-bg);
    border-color: transparent;
    filter: none;
  }

  .delete svg { width: 15px; height: 15px; }

  .empty {
    padding: 56px 20px;
    text-align: center;
    color: var(--muted);
  }

  .empty a { color: var(--accent); }
</style>
</head>
<body>
<div class="wrap">

  <header>
    <h1>File Vault</h1>
    <p>Upload a PDF or image, give it a name, and preview it right here.</p>
  </header>

<?php if ($flash): ?>
  <div class="flash <?= e($flash['type']) ?>" role="status">
    <?= e($flash['message']) ?>
  </div>
<?php endif; ?>

  <!-- ============ Upload area ============ -->
  <form class="card uploader" action="upload.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_MB * 1024 * 1024 ?>">

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
      PDF, JPG, JPEG or PNG &middot; up to <?= MAX_MB ?> MB.
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
<?php   $url = UPLOAD_URL . '/' . rawurlencode($file['name']); ?>
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
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
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
