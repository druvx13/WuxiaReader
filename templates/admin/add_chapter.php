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

$title = 'Admin: Add Chapter';
include __DIR__ . '/../header.php';
use App\Core\Config;
$base = Config::get('BASE_URL');
?>
    <section class="admin-page">
        <h1>Add Chapter</h1>
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
                Novel
                <select name="novel_id" required>
                    <option value="">Select novel</option>
                    <?php foreach ($novels as $n): ?>
                        <option value="<?= (int)$n['id'] ?>" <?= (!empty($novel_id) && $novel_id == $n['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($n['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Chapter title
                <input type="text" name="chapter_title" required value="<?= htmlspecialchars($chapter_title ?? '') ?>">
            </label>
            <label>
                Order index (optional)
                <input type="number" name="order_index" min="1" value="<?= htmlspecialchars($order_index ?? '') ?>">
            </label>
            <label>
                Content
                <textarea name="content" rows="20" class="textarea--mono" required><?= htmlspecialchars($content ?? '') ?></textarea>
            </label>
            <button type="submit" class="btn btn--admin">Create chapter</button>
        </form>
    </section>
<?php include __DIR__ . '/../footer.php'; ?>
