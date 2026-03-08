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

// assets/app.js

(function () {
    'use strict';

    /* ---- Base URL ---- */

    const baseMeta = document.querySelector('meta[name="base-url"]');
    const BASE_URL = baseMeta ? baseMeta.getAttribute('content').replace(/\/$/, '') : '';

    /* ---- Init ---- */

    document.addEventListener('DOMContentLoaded', function () {
        bindMobileNav();
        bindLikes();
        bindComments();
        bindDistractionToggle();
        bindFontSizeControls();
        bindScrollTop();
        bindReadingProgress();
    });

    /* ---- Helpers ---- */

    function fetchPost(path, data) {
        return fetch(BASE_URL + path, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(data)
        });
    }

    /* ---- Mobile Nav Toggle ---- */

    function bindMobileNav() {
        const toggle = document.getElementById('nav-toggle');
        const nav    = document.getElementById('main-nav');
        const auth   = document.getElementById('main-auth');
        if (!toggle || !nav) return;

        toggle.addEventListener('click', function () {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            nav.classList.toggle('nav--open', !expanded);
            if (auth) auth.classList.toggle('auth--open', !expanded);
        });

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!toggle.contains(e.target) && !nav.contains(e.target) && (!auth || !auth.contains(e.target))) {
                if (nav.classList.contains('nav--open')) {
                    toggle.setAttribute('aria-expanded', 'false');
                    nav.classList.remove('nav--open');
                    if (auth) auth.classList.remove('auth--open');
                }
            }
        });
    }

    /* ---- Likes ---- */

    function bindLikes() {
        document.querySelectorAll('.like-button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetType = btn.dataset.targetType;
                const targetId   = btn.dataset.targetId;
                if (!targetType || !targetId) return;

                fetchPost('/like', {
                    target_type: targetType,
                    target_id:   targetId
                }).then(function (res) {
                    if (res.status === 401) {
                        alert('Login required to like.');
                        return null;
                    }
                    return res.json();
                }).then(function (json) {
                    if (!json || !json.success) return;
                    const liked      = json.liked;
                    const countSpan  = btn.querySelector('.like-button__count');
                    const iconSpan   = btn.querySelector('.like-button__icon');
                    btn.dataset.liked = liked ? '1' : '0';
                    btn.classList.toggle('like-button--active', liked);
                    if (countSpan) countSpan.textContent = json.count;
                    if (iconSpan)  iconSpan.textContent  = liked ? '♥' : '♡';
                }).catch(function () {
                    // Silent
                });
            });
        });
    }

    /* ---- Comments ---- */

    function bindComments() {
        document.querySelectorAll('.comments-block').forEach(function (block) {
            const form = block.querySelector('.comment-form');
            if (!form) return;

            const scope     = block.dataset.scope;
            const novelId   = block.dataset.novelId;
            const chapterId = block.dataset.chapterId;
            const list      = block.querySelector('.comments-list');

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const textarea = form.querySelector('textarea[name="text"]');
                if (!textarea) return;
                const text = textarea.value.trim();
                if (text.length < 3) {
                    alert('Comment too short.');
                    return;
                }
                if (text.length > 500) {
                    alert('Comment too long.');
                    return;
                }
                const payload = { text: text };
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
                        const div = document.createElement('div');
                        div.innerHTML = html.trim();
                        const node = div.firstElementChild;
                        if (!node) return;
                        list.appendChild(node);
                        textarea.value = '';
                        node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    })
                    .catch(function () {
                        // Silent
                    });
            });
        });
    }

    /* ---- Distraction-Free Mode ---- */

    function bindDistractionToggle() {
        const btn = document.querySelector('.distraction-toggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const isDF = document.body.classList.toggle('distraction-free');
            btn.textContent = isDF ? 'Exit distraction-free' : 'Distraction-free';
        });
    }

    /* ---- Font Size Controls ---- */

    const FONT_SIZES   = [0.85, 0.92, 1, 1.1, 1.22, 1.36, 1.5];
    const FONT_KEY     = 'wuxia_font_size_idx';
    let fontSizeIndex  = parseInt(localStorage.getItem(FONT_KEY) || '2', 10);
    if (fontSizeIndex < 0 || fontSizeIndex >= FONT_SIZES.length) fontSizeIndex = 2;

    function applyFontSize() {
        document.documentElement.style.setProperty('--font-reader-size', FONT_SIZES[fontSizeIndex] + 'rem');
        localStorage.setItem(FONT_KEY, String(fontSizeIndex));
    }

    function bindFontSizeControls() {
        const decrease = document.getElementById('font-decrease');
        const increase = document.getElementById('font-increase');
        if (!decrease && !increase) return;

        applyFontSize(); // Apply saved preference on load

        if (decrease) {
            decrease.addEventListener('click', function () {
                if (fontSizeIndex > 0) {
                    fontSizeIndex--;
                    applyFontSize();
                }
            });
        }

        if (increase) {
            increase.addEventListener('click', function () {
                if (fontSizeIndex < FONT_SIZES.length - 1) {
                    fontSizeIndex++;
                    applyFontSize();
                }
            });
        }
    }

    /* ---- Scroll-to-Top Button ---- */

    function bindScrollTop() {
        const btn = document.getElementById('scroll-top');
        if (!btn) return;

        window.addEventListener('scroll', function () {
            btn.classList.toggle('visible', window.scrollY > 400);
        }, { passive: true });

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ---- Reading Progress Bar ---- */

    function bindReadingProgress() {
        const bar     = document.getElementById('reading-progress');
        const content = document.getElementById('chapter-content');
        if (!bar || !content) return;

        function updateProgress() {
            const rect   = content.getBoundingClientRect();
            const total  = content.offsetHeight;
            const viewH  = window.innerHeight;
            const scrolled = -rect.top;
            const readable = total - viewH;
            if (readable <= 0) {
                bar.style.width = '100%';
                return;
            }
            const pct = Math.min(100, Math.max(0, (scrolled / readable) * 100));
            bar.style.width = pct + '%';
        }

        window.addEventListener('scroll', updateProgress, { passive: true });
        updateProgress();
    }

}());
