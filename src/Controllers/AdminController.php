<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Config;
use App\Models\User;
use App\Models\Novel;
use App\Models\Chapter;
use finfo;

/**
 * AdminController
 *
 * Handles administrative tasks such as adding novels, adding chapters,
 * and importing novels from external sources.
 */
class AdminController
{
    /**
     * Ensures that the current user is an administrator.
     *
     * Redirects or exits with 403 Forbidden if not authorized.
     *
     * @return array|null The current user data if authorized.
     */
    private function requireAdmin()
    {
        $currentUser = null;
        if (!empty($_SESSION['user_id'])) {
            $currentUser = User::find($_SESSION['user_id']);
        }

        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo "403 Forbidden";
            exit;
        }
        return $currentUser;
    }

    /**
     * Displays the main management dashboard for admins.
     *
     * @return void
     */
    public function management()
    {
        $currentUser = $this->requireAdmin();
        View::render('admin/management', ['current_user' => $currentUser]);
    }

    /**
     * Handles the addition of a new novel.
     *
     * Processes the form submission for creating a novel, including cover image upload.
     * Renders the add novel form if not a POST request or if there are validation errors.
     *
     * @return void
     */
    public function addNovel()
    {
        $currentUser = $this->requireAdmin();

        $title = $author = $tags = $description = '';
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title       = trim($_POST['title'] ?? '');
            $author      = trim($_POST['author'] ?? '');
            $tags        = trim($_POST['tags'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $cover_url_manual = trim($_POST['cover_url'] ?? '');

            if ($title === '') {
                $errors[] = "Title is required.";
            }
            if (strlen($title) > 255) {
                $errors[] = "Title too long.";
            }

            $final_cover_url = null;

            if (!empty($_FILES['cover_file']) && $_FILES['cover_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['cover_file'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "Cover upload failed (error code " . (int)$file['error'] . ").";
                } else {
                    if ($file['size'] > 3 * 1024 * 1024) {
                        $errors[] = "Cover image too large (max 3 MB).";
                    } else {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime  = $finfo->file($file['tmp_name']);
                        $allowed = [
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp',
                        ];
                        if (!isset($allowed[$mime])) {
                            $errors[] = "Invalid cover image type (allowed: JPG, PNG, WEBP).";
                        } else {
                            $ext = $allowed[$mime];

                            $uploadDirFs = __DIR__ . '/../../public/uploads';
                            if (!is_dir($uploadDirFs)) {
                                if (!mkdir($uploadDirFs, 0755, true) && !is_dir($uploadDirFs)) {
                                    $errors[] = "Cannot create uploads directory.";
                                }
                            }

                            if (!$errors) {
                                $basename = bin2hex(random_bytes(8)) . '.' . $ext;
                                $targetFs = $uploadDirFs . '/' . $basename;

                                if (!move_uploaded_file($file['tmp_name'], $targetFs)) {
                                    $errors[] = "Failed to move uploaded cover image.";
                                } else {
                                    $final_cover_url = Config::get('BASE_URL') . '/uploads/' . $basename;
                                }
                            }
                        }
                    }
                }
            }

            if (!$final_cover_url && $cover_url_manual !== '') {
                if (strlen($cover_url_manual) > 500) {
                    $errors[] = "Cover URL too long.";
                } else {
                    $final_cover_url = $cover_url_manual;
                }
            }

            if (!$errors) {
                $id = Novel::create($title, $final_cover_url, $description, $author, $tags);
                View::redirect('/novel/' . $id);
            }
        }

        View::render('admin/add_novel', [
            'current_user' => $currentUser,
            'errors' => $errors,
            'title' => $title,
            'author' => $author,
            'tags' => $tags,
            'description' => $description
        ]);
    }

    /**
     * Handles the addition of a new chapter to an existing novel.
     *
     * Processes the form submission for creating a chapter.
     * Renders the add chapter form if not a POST request or if there are validation errors.
     *
     * @return void
     */
    public function addChapter()
    {
        $currentUser = $this->requireAdmin();

        $errors = [];
        $novel_id = 0;
        $chapter_title = '';
        $content = '';
        $order_index = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $novel_id = (int)($_POST['novel_id'] ?? 0);
            $chapter_title = trim($_POST['chapter_title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $order_index = trim($_POST['order_index'] ?? '');

            if ($novel_id <= 0) {
                $errors[] = "Invalid novel.";
            }
            if ($chapter_title === '') {
                $errors[] = "Chapter title is required.";
            }
            if ($content === '') {
                $errors[] = "Content is required.";
            }

            if ($order_index === '' || !ctype_digit($order_index)) {
                $order_index = Chapter::getNextOrderIndex($novel_id);
            } else {
                $order_index = (int)$order_index;
            }

            if (!Novel::find($novel_id)) {
                $errors[] = "Novel does not exist.";
            }

            if (!$errors) {
                Chapter::create($novel_id, $chapter_title, $content, $order_index);
                View::redirect('/novel/' . $novel_id . '?added=1');
            }
        }

        $novels = Novel::findAll();

        View::render('admin/add_chapter', [
            'current_user' => $currentUser,
            'errors' => $errors,
            'novels' => $novels,
            'novel_id' => $novel_id,
            'chapter_title' => $chapter_title,
            'content' => $content,
            'order_index' => $order_index
        ]);
    }

    /**
     * Initiates the import process for FanMTL.
     *
     * @return void
     */
    public function importFanmtl()
    {
        $this->importGeneric('fanmtl');
    }

    /**
     * Initiates the import process for NovelHall.
     *
     * @return void
     */
    public function importNovelhall()
    {
        $this->importGeneric('novelhall');
    }

    /**
     * Initiates the import process for AllNovel.org.
     *
     * @return void
     */
    public function importAllnovel()
    {
        $this->importGeneric('allnovel');
    }

    /**
     * Initiates the import process for ReadNovelFull.com.
     *
     * @return void
     */
    public function importReadnovelfull()
    {
        $this->importGeneric('readnovelfull');
    }

    /**
     * Generic handler for importing novels from a specified source.
     *
     * Validates input parameters and performs the scraping/import process,
     * streaming the log output to the client.
     *
     * @param string $source The source identifier ('fanmtl' or 'novelhall').
     * @return void
     */
    private function importGeneric($source)
    {
        $currentUser = $this->requireAdmin();
        set_time_limit(0);

        // Require scraper functions
        require_once __DIR__ . '/../Services/fanmtl_scraper.php';
        require_once __DIR__ . '/../Services/novelhall_scraper.php';
        require_once __DIR__ . '/../Services/allnovel_scraper.php';
        require_once __DIR__ . '/../Services/readnovelfull_scraper.php';

        $errors = [];
        $url = $_POST['url'] ?? '';
        $start = $_POST['start'] ?? '1';
        $end = $_POST['end'] ?? '';

        $throttleDefault = 1.0;
        if ($source === 'fanmtl') {
            $throttleDefault = FMTL_MINIMUM_THROTTLE;
        } elseif ($source === 'novelhall') {
            $throttleDefault = NOVELHALL_MINIMUM_THROTTLE;
        } elseif ($source === 'allnovel') {
            $throttleDefault = ALLNOVEL_MINIMUM_THROTTLE;
        } elseif ($source === 'readnovelfull') {
            $throttleDefault = READNOVELFULL_MINIMUM_THROTTLE;
        }
        $throttle = $_POST['throttle'] ?? (string)$throttleDefault;
        $preserve = isset($_POST['preserve_titles']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $url = trim($url);
            $start = trim($start);
            $end = trim($end);
            $throttle = trim($throttle);

            if ($url === '') {
                $errors[] = "URL is required.";
            }
            $startInt = ctype_digit($start) && (int)$start >= 1 ? (int)$start : 1;
            $endInt = null;
            if ($end !== '') {
                if (ctype_digit($end) && (int)$end >= $startInt) {
                    $endInt = (int)$end;
                } else {
                    $errors[] = "End chapter must be a number ≥ start.";
                }
            }
            $thrFloat = (float)$throttle;
            if ($thrFloat < $throttleDefault) {
                $thrFloat = $throttleDefault;
            }

            if (!$errors) {
                // Render header part of the view manually because of streaming
                 View::render('admin/import_log_start', [
                    'current_user' => $currentUser,
                    'source' => $source,
                    'url' => $url,
                    'start' => $startInt,
                    'end' => $endInt,
                    'throttle' => $thrFloat
                ]);

                // Stream logic
                @ini_set('output_buffering', 'off');
                @ini_set('zlib.output_compression', 0);
                while (ob_get_level() > 0) { @ob_end_flush(); }
                ob_implicit_flush(true);

                $logger = function (string $msg): void {
                    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
                    echo '<div class="log-line">' . date('H:i:s') . ' — ' . $safe . "</div>\n";
                    @ob_flush();
                    @flush();
                };

                try {
                    $pdo = \App\Core\Database::connect();
                    if ($source === 'fanmtl') {
                         $newId = fanmtl_import_to_db(
                            $pdo,
                            $url,
                            $startInt,
                            $endInt,
                            $thrFloat,
                            !empty($preserve),
                            $logger
                        );
                    } elseif ($source === 'novelhall') {
                        $newId = novelhall_import_to_db(
                            $pdo,
                            $url,
                            $startInt,
                            $endInt,
                            $thrFloat,
                            !empty($preserve),
                            $logger
                        );
                    } elseif ($source === 'readnovelfull') {
                        $newId = readnovelfull_import_to_db(
                            $pdo,
                            $url,
                            $startInt,
                            $endInt,
                            $thrFloat,
                            !empty($preserve),
                            $logger
                        );
                    } else {
                        // allnovel
                        $newId = allnovel_import_to_db(
                            $pdo,
                            $url,
                            $startInt,
                            $endInt,
                            $thrFloat,
                            !empty($preserve),
                            $logger
                        );
                    }

                    echo '<div class="log-line log-line--done">Import finished. New novel ID: ' . (int)$newId . '.</div>';
                    echo "</div>\n";
                    echo '<p style="margin-top:1rem;"><a class="btn btn--admin" href="' . Config::get('BASE_URL') . '/novel/' . (int)$newId . '">Open imported novel</a></p>';
                } catch (\Throwable $e) {
                    echo '<div class="log-line log-line--error">Error: ' .
                         htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
                         "</div>\n";
                    echo "</div>\n";
                }

                View::render('admin/import_log_end');
                exit;
            }
        }

        View::render('admin/import_form', [
            'current_user' => $currentUser,
            'source' => $source,
            'errors' => $errors,
            'url' => $url,
            'start' => $start,
            'end' => $end,
            'throttle' => $throttle,
            'preserve' => $preserve,
            'throttleDefault' => $throttleDefault
        ]);
    }
}
