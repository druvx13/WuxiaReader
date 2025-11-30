<?php
$title = 'Admin: Add Novel';
include __DIR__ . '/../header.php';
use App\Core\Config;
$base = Config::get('BASE_URL');
?>
    <section class="admin-page">
        <h1>Add Novel</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form form--admin" enctype="multipart/form-data">
            <label>
                Title
                <input type="text" name="title" required value="<?= htmlspecialchars($title) ?>">
            </label>
            <label>
                Author
                <input type="text" name="author" value="<?= htmlspecialchars($author) ?>">
            </label>

            <label>
                Cover image (upload)
                <input type="file" name="cover_file" accept="image/jpeg,image/png,image/webp">
            </label>

            <label>
                OR cover URL (optional)
                <input type="url" name="cover_url" placeholder="https://…" value="<?= htmlspecialchars($_POST['cover_url'] ?? '') ?>">
            </label>

            <label>
                Tags (comma-separated)
                <input type="text" name="tags" value="<?= htmlspecialchars($tags) ?>" placeholder="wuxia, cultivation, swordsman">
            </label>
            <label>
                Description
                <textarea name="description" rows="6"><?= htmlspecialchars($description) ?></textarea>
            </label>
            <button type="submit" class="btn btn--admin">Create novel</button>
        </form>
    </section>
<?php include __DIR__ . '/../footer.php'; ?>
