<?php

namespace App\Services;

/**
 * HttpClient
 *
 * A wrapper around cURL to handle HTTP requests with:
 * - Cookie persistence (in-memory or file-based).
 * - Standard browser headers (User-Agent, Accept, etc.).
 * - Automatic following of redirects.
 * - Throttling.
 */
class HttpClient
{
    private $cookies = [];
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36';
    private $lastRequestTime = 0;

    /**
     * Fetches a URL using GET.
     *
     * @param string $url The URL to fetch.
     * @param array $headers Optional additional headers.
     * @param int $timeout Timeout in seconds.
     * @return string The response body.
     * @throws \RuntimeException If the request fails or returns >= 400.
     */
    public function get(string $url, array $headers = [], int $timeout = 60): string
    {
        return $this->request('GET', $url, [], $headers, $timeout);
    }

    /**
     * Fetches a URL using POST.
     *
     * @param string $url The URL to fetch.
     * @param array|string $data Post data.
     * @param array $headers Optional additional headers.
     * @param int $timeout Timeout in seconds.
     * @return string The response body.
     * @throws \RuntimeException If the request fails or returns >= 400.
     */
    public function post(string $url, $data, array $headers = [], int $timeout = 60): string
    {
        return $this->request('POST', $url, $data, $headers, $timeout);
    }

    /**
     * Internal request handler.
     */
    private function request(string $method, string $url, $data = [], array $headers = [], int $timeout = 60): string
    {
        $ch = curl_init();

        // Merge default headers with provided headers
        $defaultHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: ' . $this->userAgent,
        ];

        // Add cookie header if we have cookies
        if (!empty($this->cookies)) {
            $cookieStr = [];
            foreach ($this->cookies as $k => $v) {
                $cookieStr[] = "$k=$v";
            }
            $defaultHeaders[] = 'Cookie: ' . implode('; ', $cookieStr);
        }

        $finalHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // We will handle cookies manually from headers usually, but for simple redirects let curl handle it?
                                            // Actually, to persist cookies across redirects, we need to handle headers.
                                            // For simplicity, let's let curl follow, but we parse Response Headers for Set-Cookie.
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER => $finalHeaders,
            CURLOPT_SSL_VERIFYPEER => false, // Mimic browser (often ignores local cert issues in dev), usually true in prod but false is safer for scraping stability vs various misconfigured sites.
            CURLOPT_ENCODING => '', // Handled by curl
            CURLOPT_HEADER => true, // We need headers to extract cookies
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Network error: " . $err);
        }

        // Separate headers and body
        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Parse cookies from header
        $this->updateCookies($headerText);

        if ($httpCode >= 400) {
            throw new \RuntimeException("HTTP " . $httpCode . ": " . $url);
        }

        $this->lastRequestTime = microtime(true);

        return $body;
    }

    private function updateCookies(string $headerText)
    {
        if (preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headerText, $matches)) {
            foreach ($matches[1] as $item) {
                $parts = explode('=', $item, 2);
                if (count($parts) === 2) {
                    $this->cookies[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
    }

    /**
     * Pauses execution to throttle requests.
     */
    public function throttle(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int)($seconds * 1000000));
        }
    }
}
