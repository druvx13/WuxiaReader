    <?php
/**
 * Comment Partial Template
 *
 * This partial template displays a single comment element.
 * Used for rendering new comments dynamically via AJAX.
 *
 * @package    WuxiaReader
 * @subpackage Templates/Partials
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 */
?>
    <article class="comment">
        <div class="comment__meta">
            <span class="comment__author"><?= htmlspecialchars($username) ?></span>
            <span class="comment__time">just now</span>
        </div>
        <p class="comment__text"><?= nl2br(htmlspecialchars($text)) ?></p>
    </article>
