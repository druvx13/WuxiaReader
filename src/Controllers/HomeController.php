<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Novel;

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
        View::render('home', ['novels' => $novels]);
    }
}
