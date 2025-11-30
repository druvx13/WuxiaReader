<?php
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
        </div>
    </section>
    <style>
        .admin-page__subtitle {
            margin-top: 0.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            opacity: 0.8;
        }
        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            align-items: stretch;
        }
        .card--admin {
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            border: 1px solid rgba(255, 0, 0, 0.08);
            box-shadow: 0 8px 18px rgba(0,0,0,0.04);
        }
        .card--admin h2 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 1.05rem;
        }
        .card--admin p {
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .card--admin .btn--admin {
            display: inline-block;
            font-size: 0.9rem;
        }
    </style>
<?php include __DIR__ . '/../footer.php'; ?>
