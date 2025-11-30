<?php
$title = 'Novels';
include 'header.php';
use App\Core\Config;
$base = Config::get('BASE_URL');
?>
    <section class="novel-list-page">
        <h1>Wuxia Novels</h1>
        <div class="novel-grid">
            <?php foreach ($novels as $n): ?>
                <article class="novel-card">
                    <a href="<?= $base ?>/novel/<?= (int)$n['id'] ?>" class="novel-card__link">
                        <div class="novel-card__cover">
                            <?php if (!empty($n['cover_url'])): ?>
                                <img src="<?= htmlspecialchars($n['cover_url']) ?>" alt="<?= htmlspecialchars($n['title']) ?>">
                            <?php else: ?>
                                <div class="cover-placeholder">No cover</div>
                            <?php endif; ?>
                        </div>
                        <div class="novel-card__body">
                            <h2><?= htmlspecialchars($n['title']) ?></h2>
                            <?php if ($n['author']): ?>
                                <p class="novel-card__author"><?= htmlspecialchars($n['author']) ?></p>
                            <?php endif; ?>
                            <p class="novel-card__desc">
                                <?= htmlspecialchars(mb_strimwidth($n['description'], 0, 160, '…', 'UTF-8')) ?>
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
<?php include 'footer.php'; ?>
