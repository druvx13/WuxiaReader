<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\SessionHelper;
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

        $currentUser = SessionHelper::getCurrentUser();

        View::render('home', [
            'novels' => $novels,
            'current_user' => $currentUser
        ]);
    }
}
