<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/* Already signed in? Nothing to do here. */
if (is_logged_in()) {
    header('Location: index.php', true, 303);
    exit;
}

$error   = '';
$notice  = isset($_GET['timeout']) ? 'Your session expired. Please sign in again.' : '';
$locked  = lockout_remaining();
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));

    if ($locked > 0) {
        $error = 'Too many attempts. Try again in ' . ceil($locked / 60) . ' minute(s).';
    } elseif (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } elseif (attempt_login($username, (string) ($_POST['password'] ?? ''))) {
        /* Return them to the gallery, keeping the search they were on.
           Only the query string is carried over — never a host or path from
           the stored URL, so this can't be turned into an open redirect. */
        $target = (string) ($_SESSION['after_login'] ?? '');
        unset($_SESSION['after_login']);

        $query = parse_url($target, PHP_URL_QUERY);
        header('Location: index.php' . ($query ? '?' . $query : ''), true, 303);
        exit;
    } else {
        record_failed_login();
        $locked = lockout_remaining();
        $error  = $locked > 0
            ? 'Too many attempts. Try again in ' . ceil($locked / 60) . ' minute(s).'
            : 'Incorrect username or password.';
        /* Slow down automated guessing a little. */
        usleep(400000);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in &middot; File Vault</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="login-shell">
  <form class="card login-card" method="post" action="login.php">
    <h1>File Vault</h1>
    <p class="lede">Sign in to upload and view files.</p>

<?php if ($error): ?>
    <div class="flash error" role="alert"><?= e($error) ?></div>
<?php elseif ($notice): ?>
    <div class="flash success" role="status"><?= e($notice) ?></div>
<?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="username"
             value="<?= e($username) ?>" autocomplete="username"
             autocapitalize="none" spellcheck="false" required autofocus>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             autocomplete="current-password" required>
    </div>

    <button type="submit"<?= $locked > 0 ? ' disabled' : '' ?>>Sign in</button>
  </form>
</div>
</body>
</html>
