<?php

/**
 * Entry Point
 *
 * This file serves as the front controller for the application.
 * It handles all incoming requests, initializes the environment, sets up routing,
 * and dispatches requests to the appropriate controllers.
 */

require_once __DIR__ . '/autoload.php';

use App\Core\Config;
use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\NovelController;
use App\Controllers\AdminController;

session_start();

// Load environment variables
Config::load(__DIR__ . '/../.env');

// Router setup
$router = new Router();

// Routes
$router->add('GET', '/', [HomeController::class, 'index']);

// Auth
$router->add('ANY', '/login', [AuthController::class, 'login']);
$router->add('ANY', '/signup', [AuthController::class, 'signup']);
$router->add('POST', '/logout', [AuthController::class, 'logout']);

// Novel
$router->add('GET', '#^/novel/(\d+)$#', [NovelController::class, 'show']);
$router->add('GET', '#^/chapter/(\d+)$#', [NovelController::class, 'showChapter']);

// AJAX
$router->add('POST', '/like', [NovelController::class, 'like']);
$router->add('POST', '/comment', [NovelController::class, 'comment']);

// Admin
$router->add('GET', '/admin/management', [AdminController::class, 'management']);
$router->add('ANY', '/admin/add-novel', [AdminController::class, 'addNovel']);
$router->add('ANY', '/admin/add-chapter', [AdminController::class, 'addChapter']);
$router->add('ANY', '/admin/import-fanmtl', [AdminController::class, 'importFanmtl']);
$router->add('ANY', '/admin/import-novelhall', [AdminController::class, 'importNovelhall']);
$router->add('ANY', '/admin/import-novelfull', [AdminController::class, 'importNovelfull']);

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
