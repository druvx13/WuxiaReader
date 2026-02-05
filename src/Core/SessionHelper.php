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
     * Note: User::find() already excludes password_hash, but we defensively remove
     * it here as well for security in case the User model changes.
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

        // Fetch from database and cache (excluding sensitive fields)
        $user = User::find($userId);
        if ($user) {
            // Defensively remove sensitive fields before caching (User::find already excludes them)
            $cachedUser = $user;
            if (isset($cachedUser['password_hash'])) {
                unset($cachedUser['password_hash']);
            }
            $_SESSION[$cacheKey] = $cachedUser;
            return $cachedUser;
        }

        return null;
    }

    /**
     * Clears the cached user data.
     *
     * Should be called when user data is updated or user logs out.
     *
     * @param int|null $userId Optional user ID to clear cache for. If not provided, clears current user's cache.
     * @return void
     */
    public static function clearUserCache($userId = null)
    {
        if ($userId === null && !empty($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }
        
        if ($userId) {
            $cacheKey = 'cached_user_' . $userId;
            unset($_SESSION[$cacheKey]);
        }
    }
}
