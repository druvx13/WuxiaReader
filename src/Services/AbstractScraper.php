<?php
/**
 * AbstractScraper Class
 *
 * Base class for all web scrapers providing common functionality
 * for HTTP requests, DOM manipulation, and content processing.
 *
 * @package    WuxiaReader
 * @subpackage Services
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 */

namespace App\Services;

use DOMDocument;
use DOMNode;
use DOMXPath;
use PDO;
use RuntimeException;

/**
 * AbstractScraper
 *
 * Provides common scraping utilities for all novel scrapers.
 */
abstract class AbstractScraper
{
    /**
     * @var float Minimum throttle time between requests in seconds
     */
    protected float $minimumThrottle = 1.0;

    /**
     * @var string User agent string for HTTP requests
     */
    protected string $userAgent = 'Mozilla/5.0 (compatible; WuxiaReader/1.0)';

    /**
     * @var array Allowed hosts for this scraper
     */
    protected array $allowedHosts = [];

    /**
     * Performs an HTTP GET request using cURL.
     *
     * @param string $url     The URL to fetch.
     * @param array  $headers Optional HTTP headers to send.
     * @param int    $timeout Request timeout in seconds.
     * @return string The response body.
     * @throws RuntimeException If the request fails or returns an error status.
     */
    protected function httpGet(string $url, array $headers = [], int $timeout = 60): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => ''
        ]);
        
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($resp === false) {
            throw new RuntimeException("Network error: " . $err);
        }
        if ($httpCode >= 400) {
            throw new RuntimeException("HTTP " . $httpCode . ": " . $url);
        }
        
        return $resp;
    }

    /**
     * Pauses execution for a specified number of seconds to throttle requests.
     *
     * @param float $seconds The number of seconds to sleep.
     * @return void
     */
    protected function throttle(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int)($seconds * 1000000));
        }
    }

    /**
     * Loads HTML content into a DOMDocument and creates a DOMXPath.
     *
     * Suppresses standard libxml errors during loading.
     *
     * @param string $html The HTML content string.
     * @return array{DOMDocument, DOMXPath} The loaded document and XPath object.
     */
    protected function loadDom(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        $xpath = new DOMXPath($doc);
        libxml_clear_errors();
        return [$doc, $xpath];
    }

    /**
     * Removes DOM nodes matching an XPath expression.
     *
     * @param DOMXPath  $xpath   The XPath object.
     * @param string    $expr    The XPath expression to match nodes.
     * @param DOMNode|null $context Optional context node to search within.
     * @return void
     */
    protected function removeNodesByXpath(DOMXPath $xpath, string $expr, ?DOMNode $context = null): void
    {
        $nodes = $xpath->query($expr, $context);
        if (!$nodes) {
            return;
        }
        foreach ($nodes as $n) {
            if ($n->parentNode) {
                $n->parentNode->removeChild($n);
            }
        }
    }

    /**
     * Extracts the inner HTML content of a DOM node.
     *
     * @param DOMNode $node The DOM node to extract HTML from.
     * @return string The inner HTML as a string.
     */
    protected function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /**
     * Joins a base URL with a relative URL.
     *
     * @param string $base The base URL.
     * @param string $rel  The relative URL.
     * @return string The joined absolute URL.
     */
    protected function urlJoin(string $base, string $rel): string
    {
        if (preg_match('#^https?://#i', $rel)) {
            return $rel;
        }
        if ($rel === '') {
            return $base;
        }
        if (strpos($rel, '//') === 0) {
            $scheme = parse_url($base, PHP_URL_SCHEME);
            if ($scheme === null || $scheme === false) {
                $scheme = 'https';
            }
            return $scheme . ':' . $rel;
        }
        if ($rel[0] === '/') {
            $parts = parse_url($base);
            $scheme = $parts['scheme'] ?? 'https';
            $host = $parts['host'] ?? '';
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            return $scheme . '://' . $host . $port . $rel;
        }
        $baseDir = preg_replace('#/[^/]*$#', '/', $base);
        return $baseDir . $rel;
    }

    /**
     * Strips common chapter prefixes from a chapter title.
     *
     * @param string $title The chapter title.
     * @return string The cleaned title.
     */
    protected function stripLeadingChapterPrefix(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('/^Chapter\s+\d+\s*[:\-–—]\s*/i', '', $title);
        $title = preg_replace('/^Ch\.?\s+\d+\s*[:\-–—]\s*/i', '', $title);
        $title = preg_replace('/^Ch\s+\d+\s*[:\-–—]\s*/i', '', $title);
        $title = preg_replace('/^\d+\s*[:\-–—]\s*/', '', $title);
        return trim($title);
    }

    /**
     * Cleans HTML fragment content by removing scripts, styles, and unwanted attributes.
     *
     * @param string $html    The HTML content to clean.
     * @param string $baseUrl Optional base URL for resolving relative links.
     * @return string The cleaned HTML.
     */
    protected function cleanFragmentHtml(string $html, string $baseUrl = ''): string
    {
        if (trim($html) === '') {
            return '';
        }

        [$doc, $xpath] = $this->loadDom($html);
        
        // Remove scripts and styles
        $this->removeNodesByXpath($xpath, '//script');
        $this->removeNodesByXpath($xpath, '//style');
        
        // Remove unwanted attributes
        $allNodes = $xpath->query('//*[@style or @class or @id or @onclick]');
        if ($allNodes) {
            foreach ($allNodes as $node) {
                if ($node->hasAttribute('style')) {
                    $node->removeAttribute('style');
                }
                if ($node->hasAttribute('class')) {
                    $node->removeAttribute('class');
                }
                if ($node->hasAttribute('id')) {
                    $node->removeAttribute('id');
                }
                if ($node->hasAttribute('onclick')) {
                    $node->removeAttribute('onclick');
                }
            }
        }
        
        // Resolve relative URLs if base URL provided
        if ($baseUrl !== '') {
            $links = $xpath->query('//a[@href]');
            if ($links) {
                foreach ($links as $a) {
                    $href = $a->getAttribute('href');
                    $absHref = $this->urlJoin($baseUrl, $href);
                    $a->setAttribute('href', $absHref);
                }
            }
            
            $imgs = $xpath->query('//img[@src]');
            if ($imgs) {
                foreach ($imgs as $img) {
                    $src = $img->getAttribute('src');
                    $absSrc = $this->urlJoin($baseUrl, $src);
                    $img->setAttribute('src', $absSrc);
                }
            }
        }
        
        $body = $xpath->query('//body')->item(0);
        if (!$body) {
            return '';
        }
        
        return $this->innerHtml($body);
    }

    /**
     * Validates if a URL belongs to an allowed host.
     *
     * @param string $url The URL to validate.
     * @return bool True if the host is allowed, false otherwise.
     */
    protected function isAllowedHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }
        return in_array(strtolower($host), $this->allowedHosts, true);
    }

    /**
     * Abstract method to fetch chapter content.
     * Must be implemented by child classes.
     *
     * @param string $url      The chapter URL.
     * @param float  $throttle Throttle time in seconds.
     * @return array{title: string, content: string} Chapter data.
     */
    abstract public function fetchChapterContent(string $url, float $throttle): array;

    /**
     * Abstract method to parse novel page.
     * Must be implemented by child classes.
     *
     * @param string        $url      The novel page URL.
     * @param float         $throttle Throttle time in seconds.
     * @param callable|null $log      Optional logging callback.
     * @return array Novel metadata and chapter list.
     */
    abstract public function parseNovelPage(string $url, float $throttle, ?callable $log = null): array;

    /**
     * Abstract method to import novel to database.
     * Must be implemented by child classes.
     *
     * @param PDO           $pdo            Database connection.
     * @param string        $url            Novel page URL.
     * @param int           $startChapter   Starting chapter number.
     * @param int|null      $endChapter     Ending chapter number (null for all).
     * @param float         $throttle       Throttle time in seconds.
     * @param bool          $preserveTitles Whether to preserve original titles.
     * @param callable|null $log            Optional logging callback.
     * @return int The ID of the imported/updated novel.
     */
    abstract public function importToDb(
        PDO $pdo,
        string $url,
        int $startChapter,
        ?int $endChapter,
        float $throttle,
        bool $preserveTitles,
        ?callable $log = null
    ): int;
}
