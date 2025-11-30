    <article class="comment">
        <div class="comment__meta">
            <span class="comment__author"><?= htmlspecialchars($username) ?></span>
            <span class="comment__time">just now</span>
        </div>
        <p class="comment__text"><?= nl2br(htmlspecialchars($text)) ?></p>
    </article>
