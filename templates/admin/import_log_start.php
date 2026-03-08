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

$title = 'Admin: Import ' . ucfirst($source);
include __DIR__ . '/../header.php';
use App\Core\Config;
$base = Config::get('BASE_URL');
?>
    <section class="admin-page">
        <h1>Import from <?= ucfirst($source) ?></h1>

        <p style="font-size:0.85rem;color:var(--muted);margin-top:0;">
            Only use this with content you are allowed to copy and in accordance with <?= $source ?>.com terms.
        </p>

        <p style="font-size:0.85rem;margin-top:0.5rem;">
            Source: <code><?= htmlspecialchars($url) ?></code><br>
            Chapters: <?= (int)$start ?> – <?= $end ? (int)$end : 'end' ?><br>
            Throttle: <?= htmlspecialchars((string)$throttle, ENT_QUOTES, 'UTF-8') ?>s
        </p>

        <div class="import-log" id="import-log">
            <div class="log-line">Starting <?= ucfirst($source) ?> import…</div>
