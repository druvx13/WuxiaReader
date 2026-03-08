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

/**
 * Cloudflare bypass helpers shared by all scrapers.
 *
 * Two strategies are supported:
 *
 *   1. FlareSolverr (automatic) — set FLARESOLVERR_URL in .env, e.g.
 *      FLARESOLVERR_URL=http://localhost:8191
 *      FlareSolverr is a free/open-source proxy that uses a real headless
 *      Chrome browser to solve Cloudflare JS-challenges automatically.
 *      Docker: docker run -p 8191:8191 ghcr.io/flaresolverr/flaresolverr:latest
 *
 *   2. Manual cf_clearance cookie — open the site in your browser,
 *      solve the Cloudflare challenge once, then copy the cf_clearance
 *      cookie value from DevTools (Application → Cookies) and paste it
 *      into the "Cloudflare Bypass" field in the import form.
 *      Stored in $GLOBALS['_SCRAPER_CF_COOKIE'] before the import runs.
 */

/**
 * Returns the manually-supplied Cloudflare cookie string (if any).
 * Scrapers call this and include the result in each HTTP request.
 *
 * @return string Cookie header value, e.g. "cf_clearance=abc123; __cf_bm=xyz"
 */
function cf_get_extra_cookie(): string
{
    return $GLOBALS['_SCRAPER_CF_COOKIE'] ?? '';
}

/**
 * Returns the configured FlareSolverr base URL, or empty string if not set.
 * Reads from the .env-loaded Config class (if available) then from getenv().
 *
 * @return string  Base URL such as "http://localhost:8191", or "".
 */
function cf_flaresolverr_url(): string
{
    $url = '';
    if (class_exists('\\App\\Core\\Config')) {
        $url = (string)\App\Core\Config::get('FLARESOLVERR_URL', '');
    }
    if ($url === '') {
        $url = (string)getenv('FLARESOLVERR_URL');
    }
    return rtrim($url, '/');
}

/**
 * Fetches $url via FlareSolverr and returns the response HTML on success,
 * or null on failure (FlareSolverr not configured, unreachable, or error).
 *
 * FlareSolverr API docs: https://github.com/FlareSolverr/FlareSolverr
 * POST /v1  {"cmd":"request.get","url":"...","maxTimeout":60000}
 *
 * @param string $url     URL to fetch through FlareSolverr.
 * @param int    $timeout Seconds to wait (Cloudflare JS challenges can take 30s+).
 * @return string|null    HTML body on success, null on any failure.
 */
function cf_try_flaresolverr(string $url, int $timeout = 120): ?string
{
    $flareSolverrBase = cf_flaresolverr_url();
    if ($flareSolverrBase === '') {
        return null;
    }

    $payload = json_encode(array(
        'cmd'        => 'request.get',
        'url'        => $url,
        'maxTimeout' => max(10000, ($timeout - 10) * 1000),
    ));

    $ch = curl_init($flareSolverrBase . '/v1');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        return null;
    }

    $data = json_decode($resp, true);
    if (
        !is_array($data) ||
        ($data['status'] ?? '') !== 'ok' ||
        !isset($data['solution']['response'])
    ) {
        return null;
    }

    return (string)$data['solution']['response'];
}
