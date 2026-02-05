<?php

namespace App\Core;

use App\Models\User;

/**
 * SessionHelper
 *
 * Provides utilities for managing session-based user data with caching
 * to reduce database queries.
 */
class SessionHelper
{
    /**
     * Gets the current user with caching.
     *
     * Caches the user data in the session to avoid repeated database lookups.
     * The cache is automatically invalidated if the user logs out or session changes.
     *
     * @return array|null The current user data or null if not logged in.
     */
    public static function getCurrentUser()
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $userId = $_SESSION['user_id'];
        $cacheKey = 'cached_user_' . $userId;

        // Check if we have a cached version
        if (isset($_SESSION[$cacheKey])) {
            return $_SESSION[$cacheKey];
        }

        // Fetch from database and cache
        $user = User::find($userId);
        if ($user) {
            $_SESSION[$cacheKey] = $user;
        }

        return $user;
    }

    /**
     * Clears the cached user data.
     *
     * Should be called when user data is updated or user logs out.
     *
     * @return void
     */
    public static function clearUserCache()
    {
        if (!empty($_SESSION['user_id'])) {
            $cacheKey = 'cached_user_' . $_SESSION['user_id'];
            unset($_SESSION[$cacheKey]);
        }
    }
}
