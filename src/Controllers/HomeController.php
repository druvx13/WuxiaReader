<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Novel;
use App\Models\User;

/**
 * HomeController
 *
 * Manages the landing page of the application.
 */
class HomeController
{
    /**
     * Displays the home page.
     *
     * Fetches all novels and renders the home view.
     *
     * @return void
     */
    public function index()
    {
        $novels = Novel::findAll();

        $currentUser = null;
        if (!empty($_SESSION['user_id'])) {
            $currentUser = User::find($_SESSION['user_id']);
        }

        View::render('home', [
            'novels' => $novels,
            'current_user' => $currentUser
        ]);
    }
}
