<?php
/*
 * Copyright (C) 2026 Druvx13
 *
 * This Work is licensed under the FFP (Freedom For People) License,
 * Version 1.0.
 *
 * A copy of this License must be included in the LICENSE file distributed
 * with this Work.
 *
 * You may also obtain a copy of the License at:
 * https://github.com/druvx13/FFP/blob/main/LICENSE
 *
 * THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED.
 */

use App\Core\Config;
$base = Config::get('BASE_URL');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Wuxia Reader') ?> – Wuxia Reader</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="base-url" content="<?= htmlspecialchars($base) ?>">
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
</head>
<body>
<div class="reading-progress" id="reading-progress" aria-hidden="true"></div>
<header class="site-header">
    <div class="site-header__inner">
        <a href="<?= $base ?>/" class="logo">Wuxia Reader</a>

        <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
        </button>

        <nav class="nav" id="main-nav">
            <a href="<?= $base ?>/">Novels</a>
            <?php if (!empty($current_user) && $current_user['role'] === 'admin'): ?>
                <a href="<?= $base ?>/admin/management" class="nav__admin">Management</a>
            <?php endif; ?>
        </nav>

        <div class="auth" id="main-auth">
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
