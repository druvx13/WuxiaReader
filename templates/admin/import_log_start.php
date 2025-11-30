<?php
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
            <div class="log-line">Starting <?= ucfirst($source) ?> import…</div>
