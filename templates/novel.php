<?php
$title = $novel['title'];
include 'header.php';
use App\Core\Config;
$base = Config::get('BASE_URL');
?>
    <article class="novel-page">
        <header class="novel-header">
            <div class="novel-header__cover">
                <?php if (!empty($novel['cover_url'])): ?>
                    <img src="<?= htmlspecialchars($novel['cover_url']) ?>" alt="<?= htmlspecialchars($novel['title']) ?>">
                <?php else: ?>
                    <div class="cover-placeholder">No cover</div>
                <?php endif; ?>
            </div>
            <div class="novel-header__meta">
                <h1><?= htmlspecialchars($novel['title']) ?></h1>
                <?php if ($novel['author']): ?>
                    <p class="novel-header__author">by <?= htmlspecialchars($novel['author']) ?></p>
                <?php endif; ?>
                <?php if ($novel['tags']): ?>
                    <ul class="tags">
                        <?php foreach (explode(',', $novel['tags']) as $tag): ?>
                            <li><?= htmlspecialchars(trim($tag)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="like-wrapper">
                    <button
                        class="like-button <?= $liked_by_user ? 'like-button--active' : '' ?>"
                        data-target-type="novel"
                        data-target-id="<?= (int)$novel['id'] ?>"
                        data-liked="<?= $liked_by_user ? '1' : '0' ?>"
                    >
                        <span class="like-button__icon"><?= $liked_by_user ? '♥' : '♡' ?></span>
                        <span class="like-button__count"><?= $likes_count ?></span>
                        <span class="like-button__label">Like</span>
                    </button>
                </div>
            </div>
        </header>

        <section class="novel-description">
            <h2>Description</h2>
            <p><?= nl2br(htmlspecialchars($novel['description'])) ?></p>
        </section>

        <section class="novel-chapters">
            <details open>
                <summary>Chapters (<?= count($chapters) ?>)</summary>
                <ul class="chapter-list">
                    <?php foreach ($chapters as $ch): ?>
                        <li>
                            <a href="<?= $base ?>/chapter/<?= (int)$ch['id'] ?>">
                                <?= htmlspecialchars($ch['order_index']) ?>. <?= htmlspecialchars($ch['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </section>

        <section class="comments-block" data-scope="novel" data-novel-id="<?= (int)$novel['id'] ?>">
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
