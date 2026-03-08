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

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form form--admin">
            <label>
                <?= ucfirst($source) ?> novel URL
                <input type="url" name="url" required
                       placeholder="https://<?= $source ?>.com/..."
                       value="<?= htmlspecialchars($url ?? '') ?>">
            </label>

            <label>
                Start chapter (1-based)
                <input type="number" name="start" min="1" value="<?= htmlspecialchars($start ?? '1') ?>">
            </label>

            <label>
                End chapter (optional, blank = all)
                <input type="number" name="end" min="1" value="<?= htmlspecialchars($end ?? '') ?>">
            </label>

            <label>
                Throttle between requests (seconds, min <?= $throttleDefault ?>)
                <input type="number" step="0.1" name="throttle"
                       value="<?= htmlspecialchars($throttle ?? (string)$throttleDefault) ?>">
            </label>

            <label style="flex-direction:row;align-items:center;gap:0.4rem;">
                <input type="checkbox" name="preserve_titles" <?= !empty($preserve) ? 'checked' : '' ?>>
                <span style="font-size:0.85rem;">Preserve original chapter titles (no renumbering)</span>
            </label>

            <details style="margin-top:0.5rem;">
                <summary style="cursor:pointer;font-size:0.85rem;color:var(--muted);user-select:none;">
                    ▶ Cloudflare Bypass (optional)
                </summary>
                <div style="margin-top:0.75rem;padding:0.85rem;background:var(--surface2,#f3f4f6);border-radius:6px;border:1px solid var(--border,#ddd);">
                    <p style="margin:0 0 0.5rem;font-size:0.82rem;color:var(--muted);">
                        Use this if the import fails with <strong>HTTP 403</strong> (Cloudflare challenge). Two options:
                    </p>
                    <ol style="margin:0 0 0.75rem;padding-left:1.2rem;font-size:0.82rem;color:var(--muted);line-height:1.6;">
                        <li><strong>Automatic (recommended):</strong> Run <a href="https://github.com/FlareSolverr/FlareSolverr" target="_blank" rel="noopener">FlareSolverr</a> and add
                            <code style="font-size:0.8rem;background:var(--surface,#fff);padding:1px 4px;border-radius:3px;">FLARESOLVERR_URL=http://localhost:8191</code>
                            to your <code style="font-size:0.8rem;">.env</code> — challenges are solved automatically.</li>
                        <li><strong>Manual cookie:</strong> Open the novel URL in your browser, let Cloudflare verify you,
                            then open DevTools → Application → Cookies → find <code style="font-size:0.8rem;">cf_clearance</code>
                            and paste its value below.</li>
                    </ol>
                    <label style="font-size:0.85rem;">
                        <code style="font-size:0.8rem;">cf_clearance</code> cookie value (optional)
                        <input type="text" name="cf_cookie"
                               placeholder="Paste cf_clearance value here…"
                               value="<?= htmlspecialchars($cf_cookie ?? '') ?>"
                               style="font-family:monospace;font-size:0.8rem;">
                    </label>
                </div>
            </details>

            <button type="submit" class="btn btn--admin">Import</button>
        </form>
    </section>
<?php include __DIR__ . '/../footer.php'; ?>
