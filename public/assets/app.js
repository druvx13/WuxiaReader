// assets/app.js

document.addEventListener('DOMContentLoaded', function () {
    bindLikes();
    bindComments();
    bindDistractionToggle();
});

function fetchPost(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams(data)
    });
}

/* ---- Likes ---- */

function bindLikes() {
    document.querySelectorAll('.like-button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetType = btn.dataset.targetType;
            var targetId = btn.dataset.targetId;
            if (!targetType || !targetId) return;

            fetchPost('/like', {
                target_type: targetType,
                target_id: targetId
            }).then(function (res) {
                if (res.status === 401) {
                    alert('Login required to like.');
                    return null;
                }
                return res.json();
            }).then(function (json) {
                if (!json || !json.success) return;
                var liked = json.liked;
                var countSpan = btn.querySelector('.like-button__count');
                var iconSpan = btn.querySelector('.like-button__icon');
                btn.dataset.liked = liked ? '1' : '0';
                btn.classList.toggle('like-button--active', liked);
                if (countSpan) countSpan.textContent = json.count;
                if (iconSpan) iconSpan.textContent = liked ? '♥' : '♡';
            }).catch(function () {
                // Silent
            });
        });
    });
}

/* ---- Comments ---- */

function bindComments() {
    document.querySelectorAll('.comments-block').forEach(function (block) {
        var form = block.querySelector('.comment-form');
        if (!form) return;

        var scope = block.dataset.scope;
        var novelId = block.dataset.novelId;
        var chapterId = block.dataset.chapterId;
        var list = block.querySelector('.comments-list');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var textarea = form.querySelector('textarea[name="text"]');
            if (!textarea) return;
            var text = textarea.value.trim();
            if (text.length < 3) {
                alert('Comment too short.');
                return;
            }
            if (text.length > 500) {
                alert('Comment too long.');
                return;
            }
            var payload = { text: text };
            if (scope === 'novel' && novelId) {
                payload.novel_id = novelId;
            } else if (scope === 'chapter' && chapterId) {
                payload.chapter_id = chapterId;
            } else {
                return;
            }

            fetchPost('/comment', payload)
                .then(function (res) {
                    if (res.status === 401) {
                        alert('Login required to comment.');
                        return null;
                    }
                    if (!res.ok) {
                        return res.text().then(function (t) {
                            alert(t || 'Error posting comment.');
                            return null;
                        });
                    }
                    return res.text();
                })
                .then(function (html) {
                    if (!html || !list) return;
                    var div = document.createElement('div');
                    div.innerHTML = html.trim();
                    var node = div.firstElementChild;
                    if (!node) return;
                    list.appendChild(node);
                    textarea.value = '';
                })
                .catch(function () {
                    // Silent
                });
        });
    });
}

/* ---- Distraction free mode ---- */

function bindDistractionToggle() {
    var btn = document.querySelector('.distraction-toggle');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var body = document.body;
        var isDF = body.classList.toggle('distraction-free');
        btn.textContent = isDF ? 'Exit distraction-free' : 'Distraction-free';
    });
}
