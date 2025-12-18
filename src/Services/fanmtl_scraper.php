<?php
// fanmtl_scraper.php
// Readwn-style parser adapted for PHP, targeting fanmtl.com and related clones.
// Imports novels + chapters directly into the existing MySQL schema used by your app.

declare(strict_types=1);

use App\Services\HttpClient;

const FMTL_ALLOWED_HOSTS = array(
    'fannovel.com', 'www.fannovel.com',
    'fannovels.com', 'www.fannovels.com',
    'fansmtl.com', 'www.fansmtl.com',
    'fanmtl.com', 'www.fanmtl.com',
    'novelmt.com', 'www.novelmt.com',
    'novelmtl.com', 'www.novelmtl.com',
    'readwn.com', 'www.readwn.com',
    'wuxiabee.com', 'www.wuxiabee.com',
    'wuxiabee.net', 'www.wuxiabee.net',
    'wuxiabee.org', 'www.wuxiabee.org',
    'wuxiafox.com', 'www.wuxiafox.com',
    'wuxiago.com', 'www.wuxiago.com',
    'wuxiahere.com', 'www.wuxiahere.com',
    'wuxiahub.com', 'www.wuxiahub.com',
    'wuxiamtl.com', 'www.wuxiamtl.com',
    'wuxiaone.com', 'www.wuxiaone.com',
    'wuxiap.com', 'www.wuxiap.com',
    'wuxiapub.com', 'www.wuxiapub.com',
    'wuxiaspot.com', 'www.wuxiaspot.com',
    'wuxiar.com', 'www.wuxiar.com',
    'wuxiau.com', 'www.wuxiau.com',
    'wuxiazone.com', 'www.wuxiazone.com'
);

const FMTL_MINIMUM_THROTTLE = 3.0; // seconds

/* ---------------- utilities ---------------- */

/**
 * Loads HTML content into a DOMDocument and creates a DOMXPath.
 *
 * Suppresses standard libxml errors during loading.
 *
 * @param string $html The HTML content string.
 * @return array{DOMDocument, DOMXPath} The loaded document and XPath object.
 */
function fmtl_load_dom(string $html): array {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    $xpath = new DOMXPath($doc);
    libxml_clear_errors();
    return array($doc, $xpath);
}

/**
 * Removes DOM nodes matching an XPath expression.
 *
 * @param DOMXPath     $xpath   The XPath object to use for querying.
 * @param string       $expr    The XPath expression to match nodes.
 * @param DOMNode|null $context The context node for the query (optional).
 * @return void
 */
function fmtl_remove_nodes_by_xpath(DOMXPath $xpath, string $expr, ?DOMNode $context = null): void {
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
 * Retrieves the inner HTML of a DOMNode.
 *
 * @param DOMNode $node The node to get inner HTML from.
 * @return string The inner HTML string.
 */
function fmtl_inner_html(DOMNode $node): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return $html;
}

/**
 * Resolves a relative URL against a base URL.
 *
 * Handles absolute URLs, protocol-relative URLs, and relative paths.
 *
 * @param string $base The base URL.
 * @param string $rel  The relative URL to resolve.
 * @return string The absolute URL.
 */
function fmtl_url_join(string $base, string $rel): string {
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
    $abs = preg_replace('#(/\.?/)#', '/', $abs);
    while (preg_match('#/[^/]+/\.\./#', $abs)) {
        $abs = preg_replace('#/[^/]+/\.\./#', '/', $abs, 1);
    }
    return $abs;
}

/**
 * Strips common chapter prefixes (e.g., "Chapter 1: ") from a title.
 *
 * @param string $title The original chapter title.
 * @return string The cleaned title.
 */
function fmtl_strip_leading_chapter_prefix(string $title): string {
    $t = preg_replace('/^\s*(?:Chapter|Chap|Ch)[\s\.\-:]*\d+[\s\.\-:]*\s*/i', '', $title);
    $t = preg_replace('/^\s*\d{1,4}[\.\)\-:\s]+\s*/', '', $t);
    return trim($t);
}

/**
 * Cleans an HTML fragment by removing scripts, styles, ads, and comments.
 *
 * Also resolves relative URLs in src and href attributes.
 *
 * @param string $html    The HTML fragment to clean.
 * @param string $baseUrl The base URL for resolving relative links.
 * @return string The cleaned HTML.
 */
function fmtl_clean_fragment_html(string $html, string $baseUrl = ''): string {
    list($doc, $xpath) = fmtl_load_dom($html);
    fmtl_remove_nodes_by_xpath($xpath, '//script|//style|//noscript|//comment()');
    fmtl_remove_nodes_by_xpath($xpath, "//*[contains(@class,'adsbox')]");

    if ($baseUrl !== '') {
        $nodes = $xpath->query('//*[@src or @href]');
        if ($nodes) {
            foreach ($nodes as $el) {
                if ($el instanceof DOMElement) {
                    if ($el->hasAttribute('src')) {
                        $v = $el->getAttribute('src');
                        if ($v && !preg_match('#^(https?|data|mailto|tel|javascript):#i', $v)) {
                            $el->setAttribute('src', fmtl_url_join($baseUrl, $v));
                        }
                    }
                    if ($el->hasAttribute('href')) {
                        $v = $el->getAttribute('href');
                        if ($v && !preg_match('#^(https?|data|mailto|tel|javascript):#i', $v)) {
                            $el->setAttribute('href', fmtl_url_join($baseUrl, $v));
                        }
                    }
                }
            }
        }
    }

    $body = $xpath->query('//body')->item(0);
    return $body ? fmtl_inner_html($body) : $html;
}

/* ---- URL helper for pagination (?page=N) ---- */

/**
 * Helper class for manipulating URLs, specifically for pagination.
 */
class FmtlURLWrapper {
    private array $parts;
    private string $scheme;
    private string $host;
    private $port;
    private string $path;
    private array $queryParams = array();
    private string $fragment;

    /**
     * Constructor.
     *
     * @param string $url The initial URL to parse.
     */
    public function __construct(string $url) {
        $this->parts  = parse_url($url);
        $this->scheme = isset($this->parts['scheme']) ? $this->parts['scheme'] : 'https';
        $this->host   = isset($this->parts['host']) ? $this->parts['host'] : '';
        $this->port   = isset($this->parts['port']) ? $this->parts['port'] : null;
        $this->path   = isset($this->parts['path']) ? $this->parts['path'] : '/';
        if (isset($this->parts['query'])) {
            parse_str($this->parts['query'], $this->queryParams);
        }
        $this->fragment = isset($this->parts['fragment']) ? $this->parts['fragment'] : '';
    }

    /**
     * Sets a query parameter.
     *
     * @param string $k The parameter key.
     * @param string $v The parameter value.
     * @return void
     */
    public function setQueryParam(string $k, string $v): void {
        $this->queryParams[$k] = $v;
    }

    /**
     * Reconstructs the URL string from its components.
     *
     * @return string The full URL.
     */
    public function href(): string {
        $port = $this->port ? ':' . $this->port : '';
        $q    = !empty($this->queryParams) ? '?' . http_build_query($this->queryParams) : '';
        $f    = $this->fragment !== '' ? '#' . $this->fragment : '';
        return $this->scheme . '://' . $this->host . $port . $this->path . $q . $f;
    }

    /**
     * Clone handler.
     */
    public function __clone() {}
}

/* ---------------- Readwn-style TOC helpers ---------------- */

/**
 * Extracts Table of Contents (TOC) pagination URLs.
 *
 * Looks for pagination links that contain a 'page' query parameter.
 * Reconstructs the full list of page URLs based on the maximum page number found.
 *
 * @param DOMDocument $doc     The DOMDocument of the current page.
 * @param string      $baseUrl The base URL of the current page.
 * @return array List of TOC page URLs.
 */
function fmtl_get_toc_page_urls(DOMDocument $doc, string $baseUrl): array {
    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query("//ul[contains(@class,'pagination')]//li//a");
    if (!$nodes || !$nodes->length) {
        return array($baseUrl);
    }

    $links = array();
    foreach ($nodes as $a) {
        /** @var DOMElement $a */
        $href = trim($a->getAttribute('href'));
        if ($href === '') {
            continue;
        }
        $full  = fmtl_url_join($baseUrl, $href);
        $parts = parse_url($full);
        if (!empty($parts['query']) && strpos($parts['query'], 'page=') !== false) {
            $links[] = $full;
        }
    }
    if (!$links) {
        return array($baseUrl);
    }

    $pageIds = array();
    foreach ($links as $full) {
        $parts = parse_url($full);
        $q = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (isset($q['page'])) {
                $pageIds[] = (int)$q['page'];
            }
        }
    }
    if (!$pageIds) {
        return array($baseUrl);
    }

    $maxPage = max($pageIds);
    $baseExample = $links[0];
    $wrapper = new FmtlURLWrapper($baseExample);

    $urls = array();
    for ($i = 1; $i <= $maxPage; $i++) {
        $clone = clone $wrapper;
        $clone->setQueryParam('page', (string)$i);
        $urls[] = $clone->href();
    }
    return $urls;
}

/**
 * Extracts chapter information from a partial list on a page.
 *
 * Targets "ul.chapter-list a" elements.
 *
 * @param DOMDocument $doc     The DOMDocument to parse.
 * @param string      $baseUrl The base URL for resolving relative links.
 * @return array List of chapters, each as ['name' => string, 'url' => string].
 */
function fmtl_extract_partial_chapter_list(DOMDocument $doc, string $baseUrl): array {
    $xpath = new DOMXPath($doc);
    $links = $xpath->query("//ul[contains(@class,'chapter-list')]//a");
    $out   = array();
    if (!$links) {
        return $out;
    }

    foreach ($links as $a) {
        /** @var DOMElement $a */
        $href = trim($a->getAttribute('href'));
        if ($href === '') {
            continue;
        }
        $href = fmtl_url_join($baseUrl, $href);

        $noNode    = $xpath->query(".//*[contains(@class,'chapter-no')]", $a)->item(0);
        $titleNode = $xpath->query(".//*[contains(@class,'chapter-title')]", $a)->item(0);

        $num       = $noNode    ? trim(preg_replace('/\s+/', ' ', $noNode->textContent))    : '';
        $titleText = $titleNode ? trim(preg_replace('/\s+/', ' ', $titleNode->textContent)) : '';

        if ($num !== '' && $titleText !== '') {
            if (strpos($titleText, $num) !== false) {
                $title = $titleText;
            } else {
                $title = $num . ': ' . $titleText;
            }
        } elseif ($titleText !== '') {
            $title = $titleText;
        } elseif ($num !== '') {
            $title = $num;
        } else {
            $title = trim(preg_replace('/\s+/', ' ', $a->textContent));
            if ($title === '') {
                $title = 'Chapter';
            }
        }

        $out[] = array(
            'name' => $title,
            'url'  => $href
        );
    }
    return $out;
}

/* ---------------- chapter page ---------------- */

/**
 * Fetches and parses the content of a single chapter.
 *
 * @param string $url      The URL of the chapter.
 * @param float  $throttle The minimum time (in seconds) to wait before the request.
 * @return array Associative array with 'title' and 'content'.
 */
function fmtl_fetch_chapter_content(HttpClient $http, string $url, float $throttle = FMTL_MINIMUM_THROTTLE): array {
    if ($throttle < FMTL_MINIMUM_THROTTLE) {
        $throttle = FMTL_MINIMUM_THROTTLE;
    }
    $http->throttle($throttle);

    $html = $http->get($url);
    list($doc, $xpath) = fmtl_load_dom($html);

    $contentNode = $xpath->query("//div[contains(@class,'chapter-content')]")->item(0);
    if (!$contentNode) {
        $contentNode =
            $xpath->query("//div[@id='chapter-content']")->item(0) ?:
            $xpath->query("//article")->item(0) ?:
            $xpath->query("//main")->item(0);
    }
    if ($contentNode) {
        fmtl_remove_nodes_by_xpath($xpath, ".//*[contains(@class,'adsbox')]", $contentNode);
    }

    $titleNode = $xpath->query("//h2")->item(0);
    $title = $titleNode ? trim(preg_replace('/\s+/', ' ', $titleNode->textContent)) : '';

    $contentHtml = $contentNode ? fmtl_inner_html($contentNode) : '';
    $contentHtml = fmtl_clean_fragment_html($contentHtml, $url);

    return array(
        'title'   => $title,
        'content' => $contentHtml
    );
}

/* ---------------- novel page ---------------- */

/**
 * Parses the main novel page to extract metadata and the full list of chapters.
 *
 * Handles pagination of the chapter list if present.
 *
 * @param string        $url      The URL of the novel page.
 * @param float         $throttle Minimum delay between requests.
 * @param callable|null $log      Optional callback (string $msg): void.
 * @return array Associative array containing novel metadata and list of chapters.
 */
function fmtl_parse_novel_page(HttpClient $http, string $url, float $throttle = FMTL_MINIMUM_THROTTLE, ?callable $log = null): array {
    if ($throttle < FMTL_MINIMUM_THROTTLE) {
        $throttle = FMTL_MINIMUM_THROTTLE;
    }

    if ($log) {
        $log("Fetching novel page: $url");
    }

    $html = $http->get($url);
    list($doc, $xpath) = fmtl_load_dom($html);

    $novel = array(
        'url'      => $url,
        'title'    => '',
        'author'   => '',
        'summary'  => '',
        'cover'    => '',
        'chapters' => array()
    );

    // Title
    $titleNode = $xpath->query("//div[contains(@class,'main-head')]//h1")->item(0);
    if (!$titleNode) {
        $titleNode = $xpath->query("//h1")->item(0);
    }
    if ($titleNode) {
        $novel['title'] = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
    }

    // Cover
    $coverNode = $xpath->query("//figure[contains(@class,'cover')]//img")->item(0);
    if ($coverNode instanceof DOMElement) {
        $src = $coverNode->getAttribute('src');
        if ($src !== '') {
            $novel['cover'] = fmtl_url_join($url, $src);
        }
        if ($novel['title'] === '') {
            $alt = trim($coverNode->getAttribute('alt') ?: '');
            if ($alt !== '') {
                $novel['title'] = $alt;
            }
        }
    }

    // Author
    $authorNode = $xpath->query("//span[@itemprop='author']")->item(0);
    if ($authorNode) {
        $novel['author'] = trim(preg_replace('/\s+/', ' ', $authorNode->textContent));
    }

    // Summary
    $summaryNode = $xpath->query("//div[contains(@class,'summary')]//div[contains(@class,'content')]")->item(0);
    if ($summaryNode) {
        $novel['summary'] = trim(preg_replace('/\s+/', ' ', $summaryNode->textContent));
    }

    // Chapters
    $seen = array();

    // Initial TOC block
    $partials = fmtl_extract_partial_chapter_list($doc, $url);
    foreach ($partials as $p) {
        if (!$p['url']) {
            continue;
        }
        if (isset($seen[$p['url']])) {
            continue;
        }
        $seen[$p['url']] = true;
        $novel['chapters'][] = array(
            'name' => $p['name'],
            'url'  => $p['url']
        );
    }

    // Additional TOC pages via pagination
    $tocUrls = fmtl_get_toc_page_urls($doc, $url);
    if ($log) {
        $log("Discovered " . count($tocUrls) . " TOC page(s).");
    }

    foreach ($tocUrls as $tocUrl) {
        if ($tocUrl === $url) {
            continue;
        }
        if ($log) {
            $log("Fetching TOC page: $tocUrl");
        }
        $http->throttle($throttle);
        try {
            $tocHtml = $http->get($tocUrl);
            list($tDoc, $tXPath) = fmtl_load_dom($tocHtml);
            $partials = fmtl_extract_partial_chapter_list($tDoc, $tocUrl);
            if ($log) {
                $log("TOC page returned " . count($partials) . " chapter links.");
            }
            foreach ($partials as $p) {
                if (!$p['url']) {
                    continue;
                }
                if (isset($seen[$p['url']])) {
                    continue;
                }
                $seen[$p['url']] = true;
                $novel['chapters'][] = array(
                    'name' => $p['name'],
                    'url'  => $p['url']
                );
            }
        } catch (Throwable $e) {
            if ($log) {
                $log("Warning: failed to fetch TOC page $tocUrl – " . $e->getMessage());
            }
            // ignore failed TOC subpage
        }
    }

    if ($log) {
        $log("Parsed novel title: " . ($novel['title'] ?: '(untitled)'));
        $log("Author: " . ($novel['author'] ?: '(unknown)'));
        $log("Cover URL: " . ($novel['cover'] ?: '(none)'));
        $log("Total discovered chapter links: " . count($novel['chapters']));
    }

    return $novel;
}

/* ---------------- IMPORT INTO DB ---------------- */

/**
 * Import novel + chapters into DB using Readwn-style scraper.
 *
 * @param PDO          $pdo
 * @param string       $url
 * @param int          $startChapter   1-based start
 * @param int|null     $endChapter     1-based end or null
 * @param float        $throttle       seconds between HTTP requests
 * @param bool         $preserveTitles preserve site titles if true
 * @param callable|null $logger        optional logger: function(string $msg): void
 *
 * @return int newly created novel ID
 */
function fanmtl_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter = 1,
    ?int $endChapter = null,
    float $throttle = FMTL_MINIMUM_THROTTLE,
    bool $preserveTitles = false,
    ?callable $logger = null
): int {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host || !in_array(strtolower($host), FMTL_ALLOWED_HOSTS, true)) {
        throw new RuntimeException("Unsupported host: " . $host);
    }

    if ($throttle < FMTL_MINIMUM_THROTTLE) {
        $throttle = FMTL_MINIMUM_THROTTLE;
    }

    $http = new HttpClient();

    if ($logger) {
        $logger("Fetching FanMTL/Readwn novel page…");
    }

    $novel = fmtl_parse_novel_page($http, $url, $throttle, $logger);
    if (empty($novel['chapters'])) {
        throw new RuntimeException("No chapters found on novel page.");
    }

    $total    = count($novel['chapters']);
    $startIdx = max(0, $startChapter - 1);
    $endIdx   = $endChapter ? min($total - 1, $endChapter - 1) : $total - 1;
    if ($endIdx < $startIdx) {
        $endIdx = $startIdx;
    }

    $pdo->beginTransaction();
    try {
        $baseTitle = $novel['title'] ?: 'Imported Novel';

        // reuse existing fanmtl novel if same title + tag
        $stmt = $pdo->prepare("SELECT id FROM novels WHERE title = ? AND tags = 'fanmtl' LIMIT 1");
        $stmt->execute([$baseTitle]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $novelId = (int)$existingId;
            if ($logger) {
                $logger("Reusing existing FanMTL novel (ID {$novelId}) with title '{$baseTitle}'.");
            }
        } else {
            if ($logger) {
                $logger("Creating new FanMTL novel with title '{$baseTitle}'.");
            }
            $stmt = $pdo->prepare("
                INSERT INTO novels (title, cover_url, description, author, tags)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute(array(
                $baseTitle,
                $novel['cover'] ?: null,
                $novel['summary'] ?: '',
                $novel['author'] ?: '',
                'fanmtl'
            ));
            $novelId = (int)$pdo->lastInsertId();
        }

        $insertChapter = $pdo->prepare("
            INSERT INTO chapters (novel_id, title, content, order_index)
            VALUES (?, ?, ?, ?)
        ");

        $orderIndex = $startChapter;

        for ($i = $startIdx; $i <= $endIdx; $i++, $orderIndex++) {
            $ch = $novel['chapters'][$i];

            if ($logger) {
                $logger("Fetching chapter " . ($i + 1) . " (" . ($ch['name'] ?? '') . ")");
            }

            $contentHtml = isset($ch['content']) ? $ch['content'] : '';
            if (trim($contentHtml) === '') {
                $res = fmtl_fetch_chapter_content($http, $ch['url'], $throttle);
                if (!empty($res['title'])) {
                    $ch['name'] = $res['title'];
                }
                $contentHtml = $res['content'] ?: '<p><em>(empty chapter)</em></p>';
            }

            $rawTitle = isset($ch['name']) ? trim($ch['name']) : '';
            if (!$preserveTitles) {
                $short = fmtl_strip_leading_chapter_prefix($rawTitle);
                if ($short === '') {
                    $short = $rawTitle;
                }
                if ($short !== '') {
                    $title = 'Chapter ' . $orderIndex . ': ' . $short;
                } else {
                    $title = 'Chapter ' . $orderIndex;
                }
            } else {
                $title = $rawTitle !== '' ? $rawTitle : ('Chapter ' . $orderIndex);
            }

            // paragraph-preserving text extraction
            $tmp = $contentHtml;
            $tmp = preg_replace('#<br\s*/?>#i', "\n", $tmp);
            $blockTags = array(
                'p','div','section','article',
                'h1','h2','h3','h4','h5',
                'blockquote','pre','li'
            );
            foreach ($blockTags as $tag) {
                $tmp = preg_replace('#</?' . $tag . '[^>]*>#i', "\n", $tmp);
            }
            $tmp = strip_tags($tmp);
            $tmp = html_entity_decode($tmp, ENT_QUOTES, 'UTF-8');
            $tmp = preg_replace("/\r\n|\r/", "\n", $tmp);
            $tmp = preg_replace('/\n{3,}/', "\n\n", $tmp);
            $contentText = trim($tmp);

            $insertChapter->execute(array(
                $novelId,
                $title,
                $contentText,
                $orderIndex
            ));

            if ($logger) {
                $logger("Saved chapter {$orderIndex} to DB.");
            }
        }

        $pdo->commit();

        if ($logger) {
            $logger("FanMTL import complete for novel ID {$novelId}.");
        }

        return $novelId;

    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($logger) {
            $logger("ERROR: " . $e->getMessage());
        }
        throw $e;
    }
}
