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
