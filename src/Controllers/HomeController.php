<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Novel;

class HomeController
{
    public function index()
    {
        $novels = Novel::findAll();
        View::render('home', ['novels' => $novels]);
    }
}
