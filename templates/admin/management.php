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

$title = 'Management';
include __DIR__ . '/../header.php';
use App\Core\Config;
$base = Config::get('BASE_URL');
?>
    <section class="admin-page">
        <h1>Management</h1>

        <p class="admin-page__subtitle">
            Novel and chapter management tools. Only admins can access this page.
        </p>

        <div class="management-grid">
            <article class="card card--admin">
                <h2>Manual: Add Novel</h2>
                <p>Create a new novel entry with title, author, cover and description.</p>
                <a href="<?= $base ?>/admin/add-novel" class="btn btn--admin">Go to Add Novel</a>
            </article>

            <article class="card card--admin">
                <h2>Manual: Add Chapter</h2>
                <p>Attach a chapter to an existing novel with ordering.</p>
                <a href="<?= $base ?>/admin/add-chapter" class="btn btn--admin">Go to Add Chapter</a>
            </article>

            <article class="card card--admin">
                <h2>Import: FanMTL / Readwn-style</h2>
                <p>Import a novel and chapters from fanmtl.com and compatible clones.</p>
                <a href="<?= $base ?>/admin/import-fanmtl" class="btn btn--admin">Go to FanMTL Import</a>
            </article>

            <article class="card card--admin">
                <h2>Import: Novelhall</h2>
                <p>Import a novel and chapters from novelhall.com.</p>
                <a href="<?= $base ?>/admin/import-novelhall" class="btn btn--admin">Go to Novelhall Import</a>
            </article>

            <article class="card card--admin">
                <h2>Import: AllNovel.org</h2>
                <p>Import a novel and chapters from allnovel.org.</p>
                <a href="<?= $base ?>/admin/import-allnovel" class="btn btn--admin">Go to AllNovel Import</a>
            </article>

            <article class="card card--admin">
                <h2>Import: ReadNovelFull.com</h2>
                <p>Import a novel and chapters from readnovelfull.com.</p>
                <a href="<?= $base ?>/admin/import-readnovelfull" class="btn btn--admin">Go to ReadNovelFull Import</a>
            </article>

            <article class="card card--admin">
                <h2>Import: NovelFull / NovelBin / Novel-Next</h2>
                <p>Import a novel and chapters from novelfull.com and compatible clones (novelbin, novel-next, etc.).</p>
                <a href="<?= $base ?>/admin/import-novelfull" class="btn btn--admin">Go to NovelFull Import</a>
            </article>
        </div>
    </section>
<?php include __DIR__ . '/../footer.php'; ?>
