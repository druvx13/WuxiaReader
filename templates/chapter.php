<?php
$title = $chapter['title'];
include 'header.php';
use App\Core\Config;
$base = Config::get('BASE_URL');
?>
    <article class="chapter-page">
        <header class="chapter-header">
            <div class="chapter-header__nav">
                <a href="<?= $base ?>/novel/<?= (int)$chapter['novel_id'] ?>" class="chapter-header__novel-link">
                    <?= htmlspecialchars($chapter['novel_title']) ?>
                </a>
            </div>
            <h1><?= htmlspecialchars($chapter['title']) ?></h1>

            <div class="chapter-header__controls">
                <div class="chapter-nav">
                    <?php if ($prev): ?>
                        <a class="chapter-nav__btn" href="<?= $base ?>/chapter/<?= (int)$prev['id'] ?>">← Previous</a>
                    <?php endif; ?>
                    <?php if ($next): ?>
                        <a class="chapter-nav__btn" href="<?= $base ?>/chapter/<?= (int)$next['id'] ?>">Next →</a>
                    <?php endif; ?>
                </div>

                <div class="like-wrapper">
                    <button
                        class="like-button <?= $liked_by_user ? 'like-button--active' : '' ?>"
                        data-target-type="chapter"
                        data-target-id="<?= (int)$chapter['id'] ?>"
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
                <?= nl2br(htmlspecialchars($chapter['content'])) ?>
            </div>
        </section>

        <section class="comments-block" data-scope="chapter" data-chapter-id="<?= (int)$chapter['id'] ?>">
            <header class="comments-header">
                <h2>Comments</h2>
            </header>

            <div class="comments-list">
                <?php foreach ($comments as $c): ?>
                    <article class="comment">
                        <div class="comment__meta">
                            <span class="comment__author"><?= htmlspecialchars($c['username']) ?></span>
                            <span class="comment__time"><?= htmlspecialchars($c['created_at']) ?></span>
                        </div>
                        <p class="comment__text"><?= nl2br(htmlspecialchars($c['text'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($current_user)): ?>
                <form class="comment-form">
                    <textarea name="text" rows="3" maxlength="500" placeholder="Add a comment..."></textarea>
                    <button type="submit">Post</button>
                </form>
            <?php else: ?>
                <p class="comments-login-hint">Login to comment.</p>
            <?php endif; ?>
        </section>
    </article>
<?php include 'footer.php'; ?>
