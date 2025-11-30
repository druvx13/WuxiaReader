<?php
// index.php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fanmtl_scraper.php';
require_once __DIR__ . '/novelhall_scraper.php';

$pdo = db();

$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch() ?: null;
}

/* --------- Helpers --------- */

function redirect($path)
{
    if (strpos($path, 'http') !== 0) {
        $path = BASE_URL . $path;
    }
    header('Location: ' . $path);
    exit;
}

function require_login()
{
    global $currentUser;
    if (!$currentUser) {
        http_response_code(401);
        redirect('/login');
    }
}

function require_admin()
{
    global $currentUser;
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo "403 Forbidden";
        exit;
    }
}

function render_header($title)
{
    global $currentUser;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= h($title) ?> – Wuxia Reader</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <a href="<?= BASE_URL ?>/" class="logo">Wuxia Reader</a>

        <nav class="nav">
            <a href="<?= BASE_URL ?>/">Novels</a>
            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>/admin/management" class="nav__admin">Management</a>
            <?php endif; ?>
        </nav>

        <div class="auth">
            <?php if ($currentUser): ?>
                <span class="auth__user">👤 <?= h($currentUser['username']) ?> (<?= h($currentUser['role']) ?>)</span>
                <form method="post" action="<?= BASE_URL ?>/logout" class="auth__form">
                    <button type="submit">Logout</button>
                </form>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login">Login</a>
                <a href="<?= BASE_URL ?>/signup">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="site-main">
    <?php
}

function render_footer()
{
    ?>
</main>
<script src="<?= BASE_URL ?>/assets/app.js"></script>
</body>
</html>
    <?php
}

/* --------- Routing --------- */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

/* ---- Auth routes ---- */

if ($path === '/login') {
    if ($method === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            redirect('/');
        } else {
            $error = "Invalid credentials.";
        }
    }

    render_header('Login');
    ?>
    <section class="auth-page">
        <h1>Login</h1>
        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" class="form">
            <label>
                Username
                <input type="text" name="username" required>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <button type="submit">Login</button>
        </form>
    </section>
    <?php
    render_footer();
    exit;
}

if ($path === '/signup') {
    if ($method === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $password2 = trim($_POST['password2'] ?? '');

        $errors = [];

        if (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters.";
        }
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
        if ($password !== $password2) {
            $errors[] = "Passwords do not match.";
        }

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $errors[] = "Username already taken.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'reader')");
                $stmt->execute([$username, $hash]);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                redirect('/');
            }
        }
    }

    render_header('Sign up');
    ?>
    <section class="auth-page">
        <h1>Sign up</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" class="form">
            <label>
                Username
                <input type="text" name="username" required>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <label>
                Confirm password
                <input type="password" name="password2" required>
            </label>
            <button type="submit">Create account</button>
        </form>
    </section>
    <?php
    render_footer();
    exit;
}

if ($path === '/logout' && $method === 'POST') {
    session_destroy();
    redirect('/');
}

/* ---- AJAX: like ---- */

if ($path === '/like' && $method === 'POST') {
    header('Content-Type: application/json');
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $target_type = $_POST['target_type'] ?? '';
    $target_id   = (int)($_POST['target_id'] ?? 0);

    if (!in_array($target_type, ['novel', 'chapter'], true) || $target_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE target_type = ? AND target_id = ? AND user_id = ?");
        $stmt->execute([$target_type, $target_id, $currentUser['id']]);
        $like = $stmt->fetch();

        $liked = false;
        if ($like) {
            $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
            $stmt->execute([$like['id']]);
            $liked = false;
        } else {
            $stmt = $pdo->prepare("INSERT INTO likes (target_type, target_id, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$target_type, $target_id, $currentUser['id']]);
            $liked = true;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM likes WHERE target_type = ? AND target_id = ?");
        $stmt->execute([$target_type, $target_id]);
        $count = (int) $stmt->fetch()['c'];

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'count' => $count,
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
    exit;
}

/* ---- AJAX: comment ---- */

if ($path === '/comment' && $method === 'POST') {
    if (!$currentUser) {
        http_response_code(401);
        echo "Unauthorized";
        exit;
    }

    $text = trim($_POST['text'] ?? '');
    $novel_id   = (int)($_POST['novel_id'] ?? 0);
    $chapter_id = (int)($_POST['chapter_id'] ?? 0);

    if (strlen($text) < 3 || strlen($text) > 500) {
        http_response_code(400);
        echo "Comment must be between 3 and 500 characters.";
        exit;
    }

    if (($novel_id <= 0 && $chapter_id <= 0) || ($novel_id > 0 && $chapter_id > 0)) {
        http_response_code(400);
        echo "Invalid target.";
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO comments (novel_id, chapter_id, user_id, text)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $novel_id > 0 ? $novel_id : null,
        $chapter_id > 0 ? $chapter_id : null,
        $currentUser['id'],
        $text
    ]);

    $username = $currentUser['username'];

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <article class="comment">
        <div class="comment__meta">
            <span class="comment__author"><?= h($username) ?></span>
            <span class="comment__time">just now</span>
        </div>
        <p class="comment__text"><?= nl2br(h($text)) ?></p>
    </article>
    <?php
    exit;
}

/* ---- Admin: management hub ---- */

if ($path === '/admin/management') {
    require_admin();

    render_header('Management');
    ?>
    <section class="admin-page">
        <h1>Management</h1>

        <p class="admin-page__subtitle">
            Novel and chapter management tools. Only admins can access this page.
        </p>

        <div class="management-grid">
            <article class="card card--admin">
                <h2>Manual: Add Novel</h2>
                <p>Create a new novel entry with title, author, cover and description.</p>
                <a href="<?= BASE_URL ?>/admin/add-novel" class="btn btn--admin">Go to Add Novel</a>
            </article>

            <article class="card card--admin">
                <h2>Manual: Add Chapter</h2>
                <p>Attach a chapter to an existing novel with ordering.</p>
                <a href="<?= BASE_URL ?>/admin/add-chapter" class="btn btn--admin">Go to Add Chapter</a>
            </article>

            <article class="card card--admin">
                <h2>Import: FanMTL / Readwn-style</h2>
                <p>Import a novel and chapters from fanmtl.com and compatible clones.</p>
                <a href="<?= BASE_URL ?>/admin/import-fanmtl" class="btn btn--admin">Go to FanMTL Import</a>
            </article>

            <article class="card card--admin">
                <h2>Import: Novelhall</h2>
                <p>Import a novel and chapters from novelhall.com.</p>
                <a href="<?= BASE_URL ?>/admin/import-novelhall" class="btn btn--admin">Go to Novelhall Import</a>
            </article>
        </div>
    </section>
    <style>
        .admin-page__subtitle {
            margin-top: 0.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            opacity: 0.8;
        }
        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            align-items: stretch;
        }
        .card--admin {
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            border: 1px solid rgba(255, 0, 0, 0.08);
            box-shadow: 0 8px 18px rgba(0,0,0,0.04);
        }
        .card--admin h2 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 1.05rem;
        }
        .card--admin p {
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .card--admin .btn--admin {
            display: inline-block;
            font-size: 0.9rem;
        }
    </style>
    <?php
    render_footer();
    exit;
}

/* ---- Admin: add novel (with cover upload) ---- */

if ($path === '/admin/add-novel') {
    require_admin();

    $title = $author = $tags = $description = '';
    $errors = [];

    if ($method === 'POST') {
        $title       = trim($_POST['title'] ?? '');
        $author      = trim($_POST['author'] ?? '');
        $tags        = trim($_POST['tags'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $cover_url_manual = trim($_POST['cover_url'] ?? '');

        if ($title === '') {
            $errors[] = "Title is required.";
        }
        if (strlen($title) > 255) {
            $errors[] = "Title too long.";
        }

        $final_cover_url = null;

        if (!empty($_FILES['cover_file']) && $_FILES['cover_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['cover_file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Cover upload failed (error code " . (int)$file['error'] . ").";
            } else {
                if ($file['size'] > 3 * 1024 * 1024) {
                    $errors[] = "Cover image too large (max 3 MB).";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->file($file['tmp_name']);
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (!isset($allowed[$mime])) {
                        $errors[] = "Invalid cover image type (allowed: JPG, PNG, WEBP).";
                    } else {
                        $ext = $allowed[$mime];

                        $uploadDirFs = __DIR__ . '/uploads';
                        if (!is_dir($uploadDirFs)) {
                            if (!mkdir($uploadDirFs, 0755, true) && !is_dir($uploadDirFs)) {
                                $errors[] = "Cannot create uploads directory.";
                            }
                        }

                        if (!$errors) {
                            $basename = bin2hex(random_bytes(8)) . '.' . $ext;
                            $targetFs = $uploadDirFs . '/' . $basename;

                            if (!move_uploaded_file($file['tmp_name'], $targetFs)) {
                                $errors[] = "Failed to move uploaded cover image.";
                            } else {
                                $final_cover_url = rtrim(BASE_URL, '/') . '/uploads/' . $basename;
                            }
                        }
                    }
                }
            }
        }

        if (!$final_cover_url && $cover_url_manual !== '') {
            if (strlen($cover_url_manual) > 500) {
                $errors[] = "Cover URL too long.";
            } else {
                $final_cover_url = $cover_url_manual;
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
                INSERT INTO novels (title, cover_url, description, author, tags)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $final_cover_url ?: null,
                $description,
                $author,
                $tags
            ]);

            $novel_id = $pdo->lastInsertId();
            redirect('/novel/' . $novel_id);
        }
    }

    render_header('Admin: Add Novel');
    ?>
    <section class="admin-page">
        <h1>Add Novel</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form form--admin" enctype="multipart/form-data">
            <label>
                Title
                <input type="text" name="title" required value="<?= h($title) ?>">
            </label>
            <label>
                Author
                <input type="text" name="author" value="<?= h($author) ?>">
            </label>

            <label>
                Cover image (upload)
                <input type="file" name="cover_file" accept="image/jpeg,image/png,image/webp">
            </label>

            <label>
                OR cover URL (optional)
                <input type="url" name="cover_url" placeholder="https://…" value="<?= h($_POST['cover_url'] ?? '') ?>">
            </label>

            <label>
                Tags (comma-separated)
                <input type="text" name="tags" value="<?= h($tags) ?>" placeholder="wuxia, cultivation, swordsman">
            </label>
            <label>
                Description
                <textarea name="description" rows="6"><?= h($description) ?></textarea>
            </label>
            <button type="submit" class="btn btn--admin">Create novel</button>
        </form>
    </section>
    <?php
    render_footer();
    exit;
}

/* ---- Admin: import from Novelhall (with live log) ---- */

if ($path === '/admin/import-novelhall') {
    require_admin();
    set_time_limit(0);

    $errors = [];
    $url = $_POST['url'] ?? '';
    $start = $_POST['start'] ?? '1';
    $end = $_POST['end'] ?? '';
    $throttle = $_POST['throttle'] ?? (string)NOVELHALL_MINIMUM_THROTTLE;
    $preserve = isset($_POST['preserve_titles']);

    if ($method === 'POST') {
        $url = trim($url);
        $start = trim($start);
        $end = trim($end);
        $throttle = trim($throttle);

        if ($url === '') {
            $errors[] = "URL is required.";
        }
        $startInt = (ctype_digit($start) && (int)$start >= 1) ? (int)$start : 1;
        $endInt = null;
        if ($end !== '') {
            if (ctype_digit($end) && (int)$end >= $startInt) {
                $endInt = (int)$end;
            } else {
                $errors[] = "End chapter must be a number ≥ start.";
            }
        }

        $thrFloat = (float)$throttle;
        if ($thrFloat < NOVELHALL_MINIMUM_THROTTLE) {
            $thrFloat = NOVELHALL_MINIMUM_THROTTLE;
        }

        if (!$errors) {
            render_header('Admin: Import Novelhall');
            ?>
            <section class="admin-page">
                <h1>Import from Novelhall</h1>

                <p style="font-size:0.85rem;color:var(--muted);margin-top:0;">
                    Only use this with content you are allowed to copy and in accordance with novelhall.com terms.
                </p>

                <p style="font-size:0.85rem;margin-top:0.5rem;">
                    Source: <code><?= h($url) ?></code><br>
                    Chapters: <?= (int)$startInt ?> – <?= $endInt ? (int)$endInt : 'end' ?><br>
                    Throttle: <?= htmlspecialchars((string)$thrFloat, ENT_QUOTES, 'UTF-8') ?>s
                </p>

                <style>
                    .import-log {
                        background: #000;
                        color: #0f0;
                        font-family: monospace;
                        font-size: 0.8rem;
                        padding: 0.75rem;
                        border-radius: 0.5rem;
                        max-height: 360px;
                        overflow: auto;
                        margin-top: 1rem;
                        border: 1px solid #222;
                    }
                    .import-log .log-line {
                        margin: 0;
                        padding: 0;
                        white-space: pre-wrap;
                    }
                    .import-log .log-line--error {
                        color: #f88;
                    }
                    .import-log .log-line--done {
                        color: #8f8;
                    }
                </style>

                <div class="import-log" id="import-log">
                    <div class="log-line">Starting Novelhall import…</div>
            <?php

            // force streaming
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', 0);
            while (ob_get_level() > 0) { @ob_end_flush(); }
            ob_implicit_flush(true);

            $logger = function (string $msg): void {
                $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
                echo '<div class="log-line">' . date('H:i:s') . ' — ' . $safe . "</div>\n";
                @ob_flush();
                @flush();
            };

            try {
                $newId = novelhall_import_to_db(
                    $pdo,
                    $url,
                    $startInt,
                    $endInt,
                    $thrFloat,
                    !empty($preserve),
                    $logger
                );
                echo '<div class="log-line log-line--done">Import finished. New novel ID: ' . (int)$newId . '.</div>';
                echo "</div>\n";
                echo '<p style="margin-top:1rem;"><a class="btn btn--admin" href="' . BASE_URL . '/novel/' . (int)$newId . '">Open imported novel</a></p>';
            } catch (Throwable $e) {
                echo '<div class="log-line log-line--error">Error: ' .
                     htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
                     "</div>\n";
                echo "</div>\n";
            }

            echo "</section>\n";
            render_footer();
            exit;
        }
    }

    // GET or POST with validation errors: normal form
    render_header('Admin: Import Novelhall');
    ?>
    <section class="admin-page">
        <h1>Import from Novelhall</h1>

        <p style="font-size:0.85rem;color:var(--muted);margin-top:0;">
            Only use this with content you are allowed to copy and in accordance with novelhall.com terms.
        </p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form form--admin">
            <label>
                Novelhall novel URL
                <input type="url" name="url" required
                       placeholder="https://novelhall.com/..."
                       value="<?= h($url ?? '') ?>">
            </label>

            <label>
                Start chapter (1-based)
                <input type="number" name="start" min="1" value="<?= h($start ?? '1') ?>">
            </label>

            <label>
                End chapter (optional, blank = all)
                <input type="number" name="end" min="1" value="<?= h($end ?? '') ?>">
            </label>

            <label>
                Throttle between requests (seconds, min <?= NOVELHALL_MINIMUM_THROTTLE ?>)
                <input type="number" step="0.1" name="throttle"
                       value="<?= h($throttle ?? (string)NOVELHALL_MINIMUM_THROTTLE) ?>">
            </label>

            <label style="flex-direction:row;align-items:center;gap:0.4rem;">
                <input type="checkbox" name="preserve_titles" <?= !empty($preserve) ? 'checked' : '' ?>>
                <span style="font-size:0.85rem;">Preserve original chapter titles (no renumbering)</span>
            </label>

            <button type="submit" class="btn btn--admin">Import</button>
        </form>
    </section>
    <?php
    render_footer();
    exit;
}

/* ---- Admin: import from FanMTL (with live log) ---- */

if ($path === '/admin/import-fanmtl') {
    require_admin();
    set_time_limit(0);

    $errors = [];
    $url = $_POST['url'] ?? '';
    $start = $_POST['start'] ?? '1';
    $end = $_POST['end'] ?? '';
    $throttle = $_POST['throttle'] ?? (string)FMTL_MINIMUM_THROTTLE;
    $preserve = isset($_POST['preserve_titles']);

    if ($method === 'POST') {
        $url = trim($url);
        $start = trim($start);
        $end = trim($end);
        $throttle = trim($throttle);

        if ($url === '') {
            $errors[] = "URL is required.";
        }
        $startInt = ctype_digit($start) && (int)$start >= 1 ? (int)$start : 1;
        $endInt = null;
        if ($end !== '') {
            if (ctype_digit($end) && (int)$end >= $startInt) {
                $endInt = (int)$end;
            } else {
                $errors[] = "End chapter must be a number ≥ start.";
            }
        }
        $thrFloat = (float)$throttle;
        if ($thrFloat < FMTL_MINIMUM_THROTTLE) {
            $thrFloat = FMTL_MINIMUM_THROTTLE;
        }

        if (!$errors) {
            render_header('Admin: Import FanMTL');
            ?>
            <section class="admin-page">
                <h1>Import from FanMTL</h1>

                <p style="font-size:0.85rem;color:var(--muted);margin-top:0;">
                    Only use this with content you are allowed to copy and in accordance with fanmtl.com terms.
                </p>

                <p style="font-size:0.85rem;margin-top:0.5rem;">
                    Source: <code><?= h($url) ?></code><br>
                    Chapters: <?= (int)$startInt ?> – <?= $endInt ? (int)$endInt : 'end' ?><br>
                    Throttle: <?= htmlspecialchars((string)$thrFloat, ENT_QUOTES, 'UTF-8') ?>s
                </p>

                <style>
                    .import-log {
                        background: #000;
                        color: #0f0;
                        font-family: monospace;
                        font-size: 0.8rem;
                        padding: 0.75rem;
                        border-radius: 0.5rem;
                        max-height: 360px;
                        overflow: auto;
                        margin-top: 1rem;
                        border: 1px solid #222;
                    }
                    .import-log .log-line {
                        margin: 0;
                        padding: 0;
                        white-space: pre-wrap;
                    }
                    .import-log .log-line--error {
                        color: #f88;
                    }
                    .import-log .log-line--done {
                        color: #8f8;
                    }
                </style>

                <div class="import-log" id="import-log">
                    <div class="log-line">Starting FanMTL import…</div>
            <?php

            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', 0);
            while (ob_get_level() > 0) { @ob_end_flush(); }
            ob_implicit_flush(true);

            $logger = function (string $msg): void {
                $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
                echo '<div class="log-line">' . date('H:i:s') . ' — ' . $safe . "</div>\n";
                @ob_flush();
                @flush();
            };

            try {
                $newId = fanmtl_import_to_db(
                    $pdo,
                    $url,
                    $startInt,
                    $endInt,
                    $thrFloat,
                    !empty($preserve),
                    $logger
                );
                echo '<div class="log-line log-line--done">Import finished. New novel ID: ' . (int)$newId . '.</div>';
                echo "</div>\n";
                echo '<p style="margin-top:1rem;"><a class="btn btn--admin" href="' . BASE_URL . '/novel/' . (int)$newId . '">Open imported novel</a></p>';
            } catch (Throwable $e) {
                echo '<div class="log-line log-line--error">Error: ' .
                     htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
                     "</div>\n";
                echo "</div>\n";
            }

            echo "</section>\n";
            render_footer();
            exit;
        }
    }

    render_header('Admin: Import FanMTL');
    ?>
    <section class="admin-page">
        <h1>Import from FanMTL</h1>

        <p style="font-size:0.85rem;color:var(--muted);margin-top:0;">
            Only use this with content you are allowed to copy and in accordance with fanmtl.com terms.
        </p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form form--admin">
            <label>
                FanMTL novel URL
                <input type="url" name="url" required
                       placeholder="https://fanmtl.com/novel/..."
                       value="<?= h($url ?? '') ?>">
            </label>

            <label>
                Start chapter (1-based)
                <input type="number" name="start" min="1" value="<?= h($start ?? '1') ?>">
            </label>

            <label>
                End chapter (optional, blank = all)
                <input type="number" name="end" min="1" value="<?= h($end ?? '') ?>">
            </label>

            <label>
                Throttle between requests (seconds, min <?= FMTL_MINIMUM_THROTTLE ?>)
                <input type="number" step="0.1" name="throttle"
                       value="<?= h($throttle ?? (string)FMTL_MINIMUM_THROTTLE) ?>">
            </label>

            <label style="flex-direction:row;align-items:center;gap:0.4rem;">
                <input type="checkbox" name="preserve_titles" <?= !empty($preserve) ? 'checked' : '' ?>>
                <span style="font-size:0.85rem;">Preserve original chapter titles (no renumbering)</span>
            </label>

            <button type="submit" class="btn btn--admin">Import</button>
        </form>
    </section>
    <?php
    render_footer();
    exit;
}

/* ---- Admin: add chapter ---- */

if (strpos($path, '/admin') === 0) {
    require_admin();
}

if ($path === '/admin/add-chapter') {
    if ($method === 'POST') {
        $novel_id = (int)($_POST['novel_id'] ?? 0);
        $chapter_title = trim($_POST['chapter_title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $order_index = trim($_POST['order_index'] ?? '');

        $errors = [];

        if ($novel_id <= 0) {
            $errors[] = "Invalid novel.";
        }
        if ($chapter_title === '') {
            $errors[] = "Chapter title is required.";
        }
        if ($content === '') {
            $errors[] = "Content is required.";
        }

        if ($order_index === '' || !ctype_digit($order_index)) {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 AS next_idx FROM chapters WHERE novel_id = ?");
            $stmt->execute([$novel_id]);
            $order_index = (int)$stmt->fetch()['next_idx'];
        } else {
            $order_index = (int)$order_index;
        }

        $stmt = $pdo->prepare("SELECT id FROM novels WHERE id = ?");
        $stmt->execute([$novel_id]);
        if (!$stmt->fetch()) {
            $errors[] = "Novel does not exist.";
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
                INSERT INTO chapters (novel_id, title, content, order_index)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$novel_id, $chapter_title, $content, $order_index]);

            redirect('/novel/' . $novel_id . '?added=1');
        }
    }

    $novels = $pdo->query("SELECT id, title FROM novels ORDER BY created_at DESC")->fetchAll();

    render_header('Admin: Add Chapter');
    ?>
    <section class="admin-page">
        <h1>Add Chapter</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" class="form form--admin">
            <label>
                Novel
                <select name="novel_id" required>
                    <option value="">Select novel</option>
                    <?php foreach ($novels as $n): ?>
                        <option value="<?= (int)$n['id'] ?>" <?= (!empty($novel_id) && $novel_id == $n['id']) ? 'selected' : '' ?>>
                            <?= h($n['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Chapter title
                <input type="text" name="chapter_title" required value="<?= h($chapter_title ?? '') ?>">
            </label>
            <label>
                Order index (optional)
                <input type="number" name="order_index" min="1" value="<?= h($order_index ?? '') ?>">
            </label>
            <label>
                Content
                <textarea name="content" rows="20" class="textarea--mono" required><?= h($content ?? '') ?></textarea>
            </label>
            <button type="submit" class="btn btn--admin">Create chapter</button>
        </form>
    </section>
    <?php
    render_footer();
    exit;
}

/* ---- Reader: novel page ---- */

if (preg_match('#^/novel/(\d+)$#', $path, $m)) {
    $novel_id = (int)$m[1];

    $stmt = $pdo->prepare("SELECT * FROM novels WHERE id = ?");
    $stmt->execute([$novel_id]);
    $novel = $stmt->fetch();

    if (!$novel) {
        http_response_code(404);
        render_header('Not found');
        echo "<h1>404 – Novel not found</h1>";
        render_footer();
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, title, order_index FROM chapters WHERE novel_id = ? ORDER BY order_index ASC");
    $stmt->execute([$novel_id]);
    $chapters = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT c.text, c.created_at, u.username
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.novel_id = ? AND c.chapter_id IS NULL
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$novel_id]);
    $comments = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM likes WHERE target_type = 'novel' AND target_id = ?");
    $stmt->execute([$novel_id]);
    $likes_count = (int)$stmt->fetch()['c'];

    $liked_by_user = false;
    if ($currentUser) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE target_type='novel' AND target_id=? AND user_id=?");
        $stmt->execute([$novel_id, $currentUser['id']]);
        $liked_by_user = (bool)$stmt->fetch();
    }

    $added = !empty($_GET['added']);

    render_header($novel['title']);
    ?>
    <article class="novel-page">
        <header class="novel-header">
            <div class="novel-header__cover">
                <?php if (!empty($novel['cover_url'])): ?>
                    <img src="<?= h($novel['cover_url']) ?>" alt="<?= h($novel['title']) ?>">
                <?php else: ?>
                    <div class="cover-placeholder">No cover</div>
                <?php endif; ?>
            </div>
            <div class="novel-header__meta">
                <h1><?= h($novel['title']) ?></h1>
                <?php if ($novel['author']): ?>
                    <p class="novel-header__author">by <?= h($novel['author']) ?></p>
                <?php endif; ?>
                <?php if ($novel['tags']): ?>
                    <ul class="tags">
                        <?php foreach (explode(',', $novel['tags']) as $tag): ?>
                            <li><?= h(trim($tag)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="like-wrapper">
                    <button
                        class="like-button <?= $liked_by_user ? 'like-button--active' : '' ?>"
                        data-target-type="novel"
                        data-target-id="<?= (int)$novel_id ?>"
                        data-liked="<?= $liked_by_user ? '1' : '0' ?>"
                    >
                        <span class="like-button__icon"><?= $liked_by_user ? '♥' : '♡' ?></span>
                        <span class="like-button__count"><?= $likes_count ?></span>
                        <span class="like-button__label">Like</span>
                    </button>
                </div>
            </div>
        </header>

        <section class="novel-description">
            <h2>Description</h2>
            <p><?= nl2br(h($novel['description'])) ?></p>
        </section>

        <section class="novel-chapters">
            <details open>
                <summary>Chapters (<?= count($chapters) ?>)</summary>
                <ul class="chapter-list">
                    <?php foreach ($chapters as $ch): ?>
                        <li>
                            <a href="<?= BASE_URL ?>/chapter/<?= (int)$ch['id'] ?>">
                                <?= h($ch['order_index']) ?>. <?= h($ch['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </section>

        <section class="comments-block" data-scope="novel" data-novel-id="<?= (int)$novel_id ?>">
            <header class="comments-header">
                <h2>Comments</h2>
            </header>

            <div class="comments-list">
                <?php foreach ($comments as $c): ?>
                    <article class="comment">
                        <div class="comment__meta">
                            <span class="comment__author"><?= h($c['username']) ?></span>
                            <span class="comment__time"><?= h($c['created_at']) ?></span>
                        </div>
                        <p class="comment__text"><?= nl2br(h($c['text'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($currentUser): ?>
                <form class="comment-form">
                    <textarea name="text" rows="3" maxlength="500" placeholder="Add a comment..."></textarea>
                    <button type="submit">Post</button>
                </form>
            <?php else: ?>
                <p class="comments-login-hint">Login to comment.</p>
            <?php endif; ?>
        </section>
    </article>
    <?php
    render_footer();
    exit;
}

/* ---- Reader: chapter page ---- */

if (preg_match('#^/chapter/(\d+)$#', $path, $m)) {
    $chapter_id = (int)$m[1];

    $stmt = $pdo->prepare("
        SELECT ch.*, n.title AS novel_title, n.id AS novel_id
        FROM chapters ch
        JOIN novels n ON ch.novel_id = n.id
        WHERE ch.id = ?
    ");
    $stmt->execute([$chapter_id]);
    $chapter = $stmt->fetch();

    if (!$chapter) {
        http_response_code(404);
        render_header('Not found');
        echo "<h1>404 – Chapter not found</h1>";
        render_footer();
        exit;
    }

    $novel_id = (int)$chapter['novel_id'];

    $stmt = $pdo->prepare("
        SELECT c.text, c.created_at, u.username
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.chapter_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$chapter_id]);
    $comments = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT id, title, order_index
        FROM chapters
        WHERE novel_id = ? AND order_index < ?
        ORDER BY order_index DESC
        LIMIT 1
    ");
    $stmt->execute([$novel_id, $chapter['order_index']]);
    $prev = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT id, title, order_index
        FROM chapters
        WHERE novel_id = ? AND order_index > ?
        ORDER BY order_index ASC
        LIMIT 1
    ");
    $stmt->execute([$novel_id, $chapter['order_index']]);
    $next = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM likes WHERE target_type = 'chapter' AND target_id = ?");
    $stmt->execute([$chapter_id]);
    $likes_count = (int)$stmt->fetch()['c'];

    $liked_by_user = false;
    if ($currentUser) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE target_type='chapter' AND target_id=? AND user_id=?");
        $stmt->execute([$chapter_id, $currentUser['id']]);
        $liked_by_user = (bool)$stmt->fetch();
    }

    render_header($chapter['title']);
    ?>
    <article class="chapter-page">
        <header class="chapter-header">
            <div class="chapter-header__nav">
                <a href="<?= BASE_URL ?>/novel/<?= (int)$novel_id ?>" class="chapter-header__novel-link">
                    <?= h($chapter['novel_title']) ?>
                </a>
            </div>
            <h1><?= h($chapter['title']) ?></h1>

            <div class="chapter-header__controls">
                <div class="chapter-nav">
                    <?php if ($prev): ?>
                        <a class="chapter-nav__btn" href="<?= BASE_URL ?>/chapter/<?= (int)$prev['id'] ?>">← Previous</a>
                    <?php endif; ?>
                    <?php if ($next): ?>
                        <a class="chapter-nav__btn" href="<?= BASE_URL ?>/chapter/<?= (int)$next['id'] ?>">Next →</a>
                    <?php endif; ?>
                </div>

                <div class="like-wrapper">
                    <button
                        class="like-button <?= $liked_by_user ? 'like-button--active' : '' ?>"
                        data-target-type="chapter"
                        data-target-id="<?= (int)$chapter_id ?>"
                        data-liked="<?= $liked_by_user ? '1' : '0' ?>"
                    >
                        <span class="like-button__icon"><?= $liked_by_user ? '♥' : '♡' ?></span>
                        <span class="like-button__count"><?= $likes_count ?></span>
                        <span class="like-button__label">Like</span>
                    </button>
                </div>

                <button class="distraction-toggle" data-mode="normal">Distraction-free</button>
            </div>
        </header>

        <section class="chapter-content" id="chapter-content">
            <div class="chapter-text">
                <?= nl2br(h($chapter['content'])) ?>
            </div>
        </section>

        <section class="comments-block" data-scope="chapter" data-chapter-id="<?= (int)$chapter_id ?>">
            <header class="comments-header">
                <h2>Comments</h2>
            </header>

            <div class="comments-list">
                <?php foreach ($comments as $c): ?>
                    <article class="comment">
                        <div class="comment__meta">
                            <span class="comment__author"><?= h($c['username']) ?></span>
                            <span class="comment__time"><?= h($c['created_at']) ?></span>
                        </div>
                        <p class="comment__text"><?= nl2br(h($c['text'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($currentUser): ?>
                <form class="comment-form">
                    <textarea name="text" rows="3" maxlength="500" placeholder="Add a comment..."></textarea>
                    <button type="submit">Post</button>
                </form>
            <?php else: ?>
                <p class="comments-login-hint">Login to comment.</p>
            <?php endif; ?>
        </section>
    </article>
    <?php
    render_footer();
    exit;
}

/* ---- Home: list novels ---- */

if ($path === '/') {
    $novels = $pdo->query("
        SELECT n.*, 
            (SELECT COUNT(*) FROM chapters c WHERE c.novel_id = n.id) AS chapter_count
        FROM novels n
        ORDER BY n.created_at DESC
    ")->fetchAll();

    render_header('Novels');
    ?>
    <section class="novel-list-page">
        <h1>Wuxia Novels</h1>
        <div class="novel-grid">
            <?php foreach ($novels as $n): ?>
                <article class="novel-card">
                    <a href="<?= BASE_URL ?>/novel/<?= (int)$n['id'] ?>" class="novel-card__link">
                        <div class="novel-card__cover">
                            <?php if (!empty($n['cover_url'])): ?>
                                <img src="<?= h($n['cover_url']) ?>" alt="<?= h($n['title']) ?>">
                            <?php else: ?>
                                <div class="cover-placeholder">No cover</div>
                            <?php endif; ?>
                        </div>
                        <div class="novel-card__body">
                            <h2><?= h($n['title']) ?></h2>
                            <?php if ($n['author']): ?>
                                <p class="novel-card__author"><?= h($n['author']) ?></p>
                            <?php endif; ?>
                            <p class="novel-card__desc">
                                <?= h(mb_strimwidth($n['description'], 0, 160, '…', 'UTF-8')) ?>
                            </p>
                            <p class="novel-card__chapters">
                                <?= (int)$n['chapter_count'] ?> chapters
                            </p>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    render_footer();
    exit;
}

/* ---- 404 ---- */

http_response_code(404);
render_header('Not found');
?>
<h1>404 – Page not found</h1>
<?php
render_footer();
