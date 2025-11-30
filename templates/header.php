<?php
use App\Core\Config;
$base = Config::get('BASE_URL');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Wuxia Reader') ?> – Wuxia Reader</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <a href="<?= $base ?>/" class="logo">Wuxia Reader</a>

        <nav class="nav">
            <a href="<?= $base ?>/">Novels</a>
            <?php if (!empty($current_user) && $current_user['role'] === 'admin'): ?>
                <a href="<?= $base ?>/admin/management" class="nav__admin">Management</a>
            <?php endif; ?>
        </nav>

        <div class="auth">
            <?php if (!empty($current_user)): ?>
                <span class="auth__user">👤 <?= htmlspecialchars($current_user['username']) ?> (<?= htmlspecialchars($current_user['role']) ?>)</span>
                <form method="post" action="<?= $base ?>/logout" class="auth__form">
                    <button type="submit">Logout</button>
                </form>
            <?php else: ?>
                <a href="<?= $base ?>/login">Login</a>
                <a href="<?= $base ?>/signup">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="site-main">
