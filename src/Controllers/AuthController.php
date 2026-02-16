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
use App\Models\User;

/**
 * AuthController
 *
 * Handles user authentication including login, signup, and logout.
 */
class AuthController
{
    /**
     * Handles the user login process.
     *
     * Processes the login form submission. If credentials are valid,
     * starts a user session and redirects to home. Otherwise, displays an error.
     *
     * @return void
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            $user = User::findByUsername($username);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                View::redirect('/');
            } else {
                $error = "Invalid credentials.";
                View::render('login', ['error' => $error]);
                return;
            }
        }
        View::render('login');
    }

    /**
     * Handles the user signup process.
     *
     * Processes the registration form. Validates input and creates a new user
     * if the username is unique and passwords match.
     *
     * @return void
     */
    public function signup()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $password2 = trim($_POST['password2'] ?? '');

            $errors = [];

            if (strlen($username) < 3) {
                $errors[] = "Username must be at least 3 characters.";
            }
            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters.";
            }
            if ($password !== $password2) {
                $errors[] = "Passwords do not match.";
            }

            if (!$errors) {
                if (User::findByUsername($username)) {
                    $errors[] = "Username already taken.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $id = User::create($username, $hash);
                    $_SESSION['user_id'] = $id;
                    View::redirect('/');
                }
            }

            if (!empty($errors)) {
                 View::render('signup', ['errors' => $errors]);
                 return;
            }
        }
        View::render('signup');
    }

    /**
     * Handles user logout.
     *
     * Destroys the current session and redirects to the home page.
     *
     * @return void
     */
    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            session_destroy();
            View::redirect('/');
        }
    }
}
