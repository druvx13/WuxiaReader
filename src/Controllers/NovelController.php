<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Novel;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Like;
use App\Models\User;

/**
 * NovelController
 *
 * Handles display and interaction with novels and chapters, including
 * viewing details, reading chapters, liking, and commenting.
 */
class NovelController
{
    /**
     * Displays a specific novel's details.
     *
     * Shows the novel's cover, description, chapters list, comments, and like status.
     *
     * @param int $id The ID of the novel to display.
     * @return void
     */
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

    /**
     * Displays a specific chapter.
     *
     * Shows the chapter content, navigation to previous/next chapters, comments, and like status.
     *
     * @param int $id The ID of the chapter to display.
     * @return void
     */
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

    /**
     * Handles AJAX requests to like or unlike a novel or chapter.
     *
     * Toggles the like status for the current user and the specified target.
     * Returns a JSON response with the success status, new like state, and updated count.
     *
     * @return void
     */
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

    /**
     * Handles the submission of a new comment.
     *
     * Validates the comment text and target (novel or chapter), creates the comment,
     * and renders the partial view for the new comment to be appended to the list.
     *
     * @return void
     */
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
