<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Novel;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Like;
use App\Models\User;

class NovelController
{
    public function show($id)
    {
        $novel = Novel::find($id);
        if (!$novel) {
            http_response_code(404);
            View::render('404');
            return;
        }

        $chapters = Chapter::findByNovelId($id);
        $comments = Comment::findByNovel($id);
        $likesCount = Like::count('novel', $id);

        $currentUser = null;
        if (!empty($_SESSION['user_id'])) {
            $currentUser = User::find($_SESSION['user_id']);
        }

        $likedByUser = Like::isLikedByUser('novel', $id, $currentUser['id'] ?? null);

        View::render('novel', [
            'novel' => $novel,
            'chapters' => $chapters,
            'comments' => $comments,
            'likes_count' => $likesCount,
            'liked_by_user' => $likedByUser,
            'current_user' => $currentUser
        ]);
    }

    public function showChapter($id)
    {
        $chapter = Chapter::find($id);
        if (!$chapter) {
            http_response_code(404);
            View::render('404');
            return;
        }

        $comments = Comment::findByChapter($id);
        $prev = Chapter::findPrevious($chapter['novel_id'], $chapter['order_index']);
        $next = Chapter::findNext($chapter['novel_id'], $chapter['order_index']);
        $likesCount = Like::count('chapter', $id);

        $currentUser = null;
        if (!empty($_SESSION['user_id'])) {
            $currentUser = User::find($_SESSION['user_id']);
        }

        $likedByUser = Like::isLikedByUser('chapter', $id, $currentUser['id'] ?? null);

        View::render('chapter', [
            'chapter' => $chapter,
            'prev' => $prev,
            'next' => $next,
            'comments' => $comments,
            'likes_count' => $likesCount,
            'liked_by_user' => $likedByUser,
            'current_user' => $currentUser
        ]);
    }

    public function like()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        header('Content-Type: application/json');

        $currentUser = null;
        if (!empty($_SESSION['user_id'])) {
            $currentUser = User::find($_SESSION['user_id']);
        }

        if (!$currentUser) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $targetType = $_POST['target_type'] ?? '';
        $targetId   = (int)($_POST['target_id'] ?? 0);

        if (!in_array($targetType, ['novel', 'chapter'], true) || $targetId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        try {
            $result = Like::toggle($currentUser['id'], $targetType, $targetId);
            echo json_encode([
                'success' => true,
                'liked' => $result['liked'],
                'count' => $result['count'],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server error']);
        }
        exit;
    }

    public function comment()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             return;
        }

        $currentUser = null;
        if (!empty($_SESSION['user_id'])) {
            $currentUser = User::find($_SESSION['user_id']);
        }

        if (!$currentUser) {
            http_response_code(401);
            echo "Unauthorized";
            exit;
        }

        $text = trim($_POST['text'] ?? '');
        $novelId   = (int)($_POST['novel_id'] ?? 0);
        $chapterId = (int)($_POST['chapter_id'] ?? 0);

        if (strlen($text) < 3 || strlen($text) > 500) {
            http_response_code(400);
            echo "Comment must be between 3 and 500 characters.";
            exit;
        }

        if (($novelId <= 0 && $chapterId <= 0) || ($novelId > 0 && $chapterId > 0)) {
            http_response_code(400);
            echo "Invalid target.";
            exit;
        }

        Comment::create(
            $currentUser['id'],
            $text,
            $novelId > 0 ? $novelId : null,
            $chapterId > 0 ? $chapterId : null
        );

        $username = $currentUser['username'];

        header('Content-Type: text/html; charset=utf-8');
        View::render('partials/comment', ['username' => $username, 'text' => $text]);
        exit;
    }
}
