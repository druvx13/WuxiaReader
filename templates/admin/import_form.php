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

            <button type="submit" class="btn btn--admin">Import</button>
        </form>
    </section>
<?php include __DIR__ . '/../footer.php'; ?>
