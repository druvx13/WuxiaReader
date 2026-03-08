<?php
// generic_scraper.php
// A generic scraper for WuxiaReader, ported and adapted from WebToEpub's DefaultParser and Util.
// It attempts to parse novel sites without specific scrapers by using heuristics.

declare(strict_types=1);

/* ---------------- Utilities Class (Ported from WebToEpub/Util.js) ---------------- */

class ScraperUtils {

    /**
     * Resolves a relative URL against a base URL.
     */
    public static function resolveRelativeUrl(string $base, string $rel): string {
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
        $parts = parse_url($base);
        if (!$parts) {
            return $rel;
        }
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $host   = isset($parts['host']) ? $parts['host'] : '';
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path   = isset($parts['path']) ? $parts['path'] : '/';

        if (strpos($rel, '/') === 0) {
            return $scheme . '://' . $host . $port . $rel;
        }

        $dir = preg_replace('#/[^/]*$#', '/', $path);
        $abs = $scheme . '://' . $host . $port . rtrim($dir, '/') . '/' . ltrim($rel, '/');
        // Simple dot segment removal
        $abs = preg_replace('#(/\.?/)#', '/', $abs);
        while (preg_match('#/[^/]+/\.\./#', $abs)) {
            $abs = preg_replace('#/[^/]+/\.\./#', '/', $abs, 1);
        }
        return $abs;
    }

    public static function removeTrailingSlash(string $url): string {
        return (substr($url, -1) === '/') ? substr($url, 0, -1) : $url;
    }

    public static function removeAnchor(string $url): string {
        $index = strpos($url, '#');
        return ($index !== false) ? substr($url, 0, $index) : $url;
    }

    public static function normalizeUrlForCompare(string $url): string {
        $noTrailingSlash = self::removeTrailingSlash(self::removeAnchor($url));
        $protocolSeparator = "://";
        $protocolIndex = strpos($noTrailingSlash, $protocolSeparator);
        return ($protocolIndex === false) ? $noTrailingSlash
            : substr($noTrailingSlash, $protocolIndex + strlen($protocolSeparator));
    }

    public static function isNullOrEmpty(?string $s): bool {
        return ($s === null || trim($s) === '');
    }

    public static function getHostName(string $url): string {
        return parse_url($url, PHP_URL_HOST) ?? '';
    }
}

/* ---------------- Generic Parser Logic ---------------- */

class GenericParser {
    private $pdo;
    private $logger;

    public function __construct(PDO $pdo, ?callable $logger = null) {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    private function log($msg) {
        if ($this->logger) {
            call_user_func($this->logger, $msg);
        }
    }

    protected function httpGet(string $url): string {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true
        ));
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new RuntimeException("Network error: " . $err);
        }
        return $resp;
    }

    private function loadDom(string $html): DOMDocument {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        // UTF-8 Hack
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $doc;
    }

    /**
     * Extracts chapter list from a DOM.
     * Ported from Util.js hyperlinksToChapterList
     */
    public function getChapterUrls(DOMDocument $dom, string $baseUrl): array {
        $xpath = new DOMXPath($dom);
        $links = $xpath->query("//a[@href]");
        $chapters = [];
        $seen = [];

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);

            if (ScraperUtils::isNullOrEmpty($text) || ScraperUtils::isNullOrEmpty($href)) {
                continue;
            }

            $fullUrl = ScraperUtils::resolveRelativeUrl($baseUrl, $href);
            $normalized = ScraperUtils::normalizeUrlForCompare($fullUrl);

            if (isset($seen[$normalized])) {
                continue;
            }

            // Simple heuristic: if text looks like a chapter title or contains digits
            if (preg_match('/chapter|vol|episode|\d+/i', $text)) {
                 $seen[$normalized] = true;
                 $chapters[] = [
                     'name' => $text,
                     'url' => $fullUrl
                 ];
            }
        }
        return $chapters;
    }

    /**
     * Find "Next Chapter" link.
     * Heuristics:
     * 1. Check for rel="next"
     * 2. Check for text containing "Next" or "Next Chapter" or ">"
     */
    public function findNextChapterUrl(DOMDocument $dom, string $baseUrl): ?string {
        $xpath = new DOMXPath($dom);

        // 1. rel="next"
        $relNext = $xpath->query("//a[@rel='next']");
        if ($relNext->length > 0) {
            return ScraperUtils::resolveRelativeUrl($baseUrl, $relNext->item(0)->getAttribute('href'));
        }

        // 2. Text based
        $links = $xpath->query("//a[@href]");
        foreach ($links as $link) {
            $text = trim($link->textContent);
            if (preg_match('/next|Next|Next Chapter|→|»/i', $text)) {
                // Ignore if it seems to be "Next Page" of comments or list
                if (preg_match('/page/i', $text) && !preg_match('/chapter/i', $text)) {
                    continue;
                }
                return ScraperUtils::resolveRelativeUrl($baseUrl, $link->getAttribute('href'));
            }
        }

        return null;
    }

    /**
     * Heuristic to find content.
     */
    public function findContent(DOMDocument $dom): DOMNode {
        $xpath = new DOMXPath($dom);
        // Potential content containers
        $candidates = $xpath->query("//div|//article|//section");
        $bestNode = $dom->getElementsByTagName('body')->item(0);
        $maxLen = 0;

        foreach ($candidates as $node) {
            // Skip nodes with high link density
            $textLen = strlen(trim($node->textContent));
            $linkLen = 0;
            foreach ($node->getElementsByTagName('a') as $a) {
                $linkLen += strlen(trim($a->textContent));
            }

            if ($textLen === 0) continue;

            $linkDensity = $linkLen / $textLen;

            // Adjust scoring
            $score = $textLen * (1 - $linkDensity);

            if ($score > $maxLen) {
                $maxLen = $score;
                $bestNode = $node;
            }
        }

        return $bestNode;
    }

    /**
     * Remove Next/Previous links from content
     */
    public function removeNextPrevLinks(DOMNode $contentNode): void {
        $xpath = new DOMXPath($contentNode->ownerDocument);
        $links = $xpath->query(".//a", $contentNode); // Search only inside content
        foreach ($links as $link) {
            $text = trim($link->textContent);
            if (preg_match('/next|prev|previous|index|toc/i', $text)) {
                $link->parentNode->removeChild($link);
            }
        }
    }

    /**
     * Remove unwanted elements based on WebToEpub logic
     */
    public function removeUnwantedElements(DOMNode $contentNode): void {
        $xpath = new DOMXPath($contentNode->ownerDocument);
        // Standard junk
        $unwanted = $xpath->query(".//script|.//style|.//noscript|.//iframe|.//form|.//input|.//button|.//textarea", $contentNode);
        foreach ($unwanted as $node) {
            $node->parentNode->removeChild($node);
        }

        // Wordpress/Common junk
        $cssSelectors = [
            "div.sharedaddy", "div.wpcnt", "ul.post-categories", "div.mistape_caption",
            "div.wpulike", "div.wp-next-post-navi", ".ezoic-adpicker-ad", ".ezoic-ad",
            "ins.adsbygoogle", "div.sharepost", ".adsbox"
        ];

        // Note: converting CSS to XPath is complex, so we'll just check class attributes for now
        // A robust implementation would use a CSS-to-XPath converter or more complex checks
        $divs = $xpath->query(".//div|.//ul|.//ins", $contentNode);
        foreach ($divs as $node) {
            $class = $node->getAttribute('class');
            if ($class) {
                foreach ($cssSelectors as $sel) {
                    $selClass = str_replace(['div.', 'ul.', 'ins.', '.'], '', $sel);
                    if (strpos($class, $selClass) !== false) {
                        $node->parentNode->removeChild($node);
                        break;
                    }
                }
            }
        }
    }

    public function fetchChapterContent(string $url): array {
        $html = $this->httpGet($url);
        $doc = $this->loadDom($html);

        $contentNode = $this->findContent($doc);

        // Remove unwanted elements first
        $this->removeUnwantedElements($contentNode);
        $this->removeNextPrevLinks($contentNode);

        // Find title - usually h1
        $xpath = new DOMXPath($doc);
        $titleNode = $xpath->query("//h1")->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : "Chapter";

        // Get inner HTML of content
        $contentHtml = '';
        foreach ($contentNode->childNodes as $child) {
            $contentHtml .= $doc->saveHTML($child);
        }

        // Clean content
        $contentHtml = preg_replace('/<!--(.|\s)*?-->/', '', $contentHtml);

        return [
            'title' => $title,
            'content' => $contentHtml,
            'dom' => $doc // return DOM for next chapter finding
        ];
    }

    public function importNovel(string $url, int $startChapter = 1): int {
        $this->log("Fetching novel page: $url");
        $html = $this->httpGet($url);
        $doc = $this->loadDom($html);

        // Metadata extraction
        $xpath = new DOMXPath($doc);

        $titleNode = $xpath->query("//h1")->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : "Unknown Title";

        $authorNode = $xpath->query("//meta[@name='author']|//meta[@property='og:book:author']");
        $author = "Unknown Author";
        if ($authorNode->length > 0) {
            $author = $authorNode->item(0)->getAttribute('content');
        }

        $coverNode = $xpath->query("//meta[@property='og:image']");
        $cover = "";
        if ($coverNode->length > 0) {
            $cover = $coverNode->item(0)->getAttribute('content');
        }

        $descNode = $xpath->query("//meta[@name='description']|//meta[@property='og:description']");
        $summary = "";
        if ($descNode->length > 0) {
            $summary = $descNode->item(0)->getAttribute('content');
        }

        // Import Novel to DB
        $this->pdo->beginTransaction();
        try {
            // Check existing
            $stmt = $this->pdo->prepare("SELECT id FROM novels WHERE title = ? LIMIT 1");
            $stmt->execute([$title]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                $novelId = (int)$existingId;
                $this->log("Updating existing novel: $title (ID: $novelId)");

                // Get current max order_index to append correctly
                $stmt = $this->pdo->prepare("SELECT MAX(order_index) FROM chapters WHERE novel_id = ?");
                $stmt->execute([$novelId]);
                $maxIndex = $stmt->fetchColumn();
                $orderIndex = ($maxIndex !== false) ? (int)$maxIndex + 1 : $startChapter;
            } else {
                $this->log("Creating new novel: $title");
                $stmt = $this->pdo->prepare("INSERT INTO novels (title, cover_url, description, author, tags) VALUES (?, ?, ?, ?, 'generic')");
                $stmt->execute([$title, $cover, $summary, $author]);
                $novelId = (int)$this->pdo->lastInsertId();
                $orderIndex = $startChapter;
            }

            // Chapters
            $chapters = $this->getChapterUrls($doc, $url);
            $this->log("Found " . count($chapters) . " potential chapters.");

            // Fetch existing chapter titles to avoid duplicates (assuming title uniqueness per novel for simplicity)
            // Ideally we'd store source URL, but schema might not have it. Checking title is a reasonable fallback.
            $stmt = $this->pdo->prepare("SELECT title FROM chapters WHERE novel_id = ?");
            $stmt->execute([$novelId]);
            $existingTitles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $existingTitles = array_map('trim', $existingTitles);

            foreach ($chapters as $ch) {
                // Skip if title already exists
                if (in_array(trim($ch['name']), $existingTitles)) {
                    $this->log("Skipping existing chapter: " . $ch['name']);
                    continue;
                }

                $this->log("Fetching chapter: " . $ch['name']);
                $chContent = $this->fetchChapterContent($ch['url']);

                $stmt = $this->pdo->prepare("INSERT INTO chapters (novel_id, title, content, order_index) VALUES (?, ?, ?, ?)");
                $stmt->execute([$novelId, $chContent['title'], $chContent['content'], $orderIndex]);

                $orderIndex++;
                sleep(1); // Basic throttling
            }

            $this->pdo->commit();
            return $novelId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
