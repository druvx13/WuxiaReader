<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\User;

class AuthController
{
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

    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            session_destroy();
            View::redirect('/');
        }
    }
}
