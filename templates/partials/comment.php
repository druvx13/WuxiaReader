<!--
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
-->
    <article class="comment">
        <div class="comment__meta">
            <span class="comment__author"><?= htmlspecialchars($username) ?></span>
            <span class="comment__time">just now</span>
        </div>
        <p class="comment__text"><?= nl2br(htmlspecialchars($text)) ?></p>
    </article>
