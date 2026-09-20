<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/* POST only. A logout link can be triggered by a prefetch or an <img> tag
   on another site, which is a nuisance rather than a danger — but there is
   no reason to allow it. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    header('Location: index.php', true, 303);
    exit;
}

logout();

header('Location: login.php', true, 303);
exit;
