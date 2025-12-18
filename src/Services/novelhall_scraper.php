<?php
// novelhall_scraper.php
// Novelhall-style parser adapted for PHP, targeting novelhall.com.
// Imports novels + chapters directly into your MySQL schema: novels / chapters.

declare(strict_types=1);

use App\Services\HttpClient;

const NOVELHALL_ALLOWED_HOSTS = array(
    'novelhall.com',
    'www.novelhall.com'
);

const NOVELHALL_MINIMUM_THROTTLE = 3.0; // seconds

/* ---------------- utilities ---------------- */

/**
 * Loads HTML content into a DOMDocument and creates a DOMXPath.
 *
 * Suppresses standard libxml errors during loading.
 *
 * @param string $html The HTML content string.
 * @return array{DOMDocument, DOMXPath} The loaded document and XPath object.
 */
function nh_load_dom(string $html): array {
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
function nh_remove_nodes_by_xpath(DOMXPath $xpath, string $expr, ?DOMNode $context = null): void {
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
function nh_inner_html(DOMNode $node): string {
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
function nh_url_join(string $base, string $rel): string {
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
function nh_strip_leading_chapter_prefix(string $title): string {
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
function nh_clean_fragment_html(string $html, string $baseUrl = ''): string {
    list($doc, $xpath) = nh_load_dom($html);
    nh_remove_nodes_by_xpath($xpath, '//script|//style|//noscript|//comment()');
    nh_remove_nodes_by_xpath($xpath, "//*[contains(@class,'adsbox')]");

    if ($baseUrl !== '') {
        $nodes = $xpath->query('//*[@src or @href]');
        if ($nodes) {
            foreach ($nodes as $el) {
                if ($el instanceof DOMElement) {
                    if ($el->hasAttribute('src')) {
                        $v = $el->getAttribute('src');
                        if ($v && !preg_match('#^(https?|data|mailto|tel|javascript):#i', $v)) {
                            $el->setAttribute('src', nh_url_join($baseUrl, $v));
                        }
                    }
                    if ($el->hasAttribute('href')) {
                        $v = $el->getAttribute('href');
                        if ($v && !preg_match('#^(https?|data|mailto|tel|javascript):#i', $v)) {
                            $el->setAttribute('href', nh_url_join($baseUrl, $v));
                        }
                    }
                }
            }
        }
    }

    $body = $xpath->query('//body')->item(0);
    return $body ? nh_inner_html($body) : $html;
}

/* ---------------- Novelhall TOC helpers ---------------- */

/**
 * Extracts the chapter list from the novel page.
 *
 * Heuristic: Finds the `div.book-catalog` element with the most anchor tags,
 * assuming it is the main chapter list.
 *
 * @param DOMDocument $doc     The DOMDocument of the novel page.
 * @param string      $baseUrl The base URL for resolving relative links.
 * @return array List of chapters, each as ['name' => string, 'url' => string].
 */
function nh_extract_chapter_list(DOMDocument $doc, string $baseUrl): array {
    $xpath = new DOMXPath($doc);
    $catalogs = $xpath->query("//div[contains(@class,'book-catalog')]");
    $bestAnchors = array();
    $bestCount = 0;

    if ($catalogs) {
        foreach ($catalogs as $c) {
            $anchors = $xpath->query(".//a", $c);
            $count = $anchors ? $anchors->length : 0;
            if ($count > $bestCount) {
                $bestAnchors = $anchors;
                $bestCount = $count;
            }
        }
    }

    $out = array();
    if ($bestCount === 0 || !$bestAnchors) {
        return $out;
    }

    foreach ($bestAnchors as $a) {
        if (!($a instanceof DOMElement)) continue;
        $href = trim($a->getAttribute('href'));
        if ($href === '') {
            continue;
        }
        $href = nh_url_join($baseUrl, $href);

        $titleText = trim(preg_replace('/\s+/', ' ', $a->textContent));
        if ($titleText === '') {
            $titleText = 'Chapter';
        }

        $out[] = array(
            'name' => $titleText,
            'url'  => $href
        );
    }

    return $out;
}

/* ---------------- chapter page ---------------- */

/**
 * Fetches and parses the content of a single chapter from NovelHall.
 *
 * @param string $url      The URL of the chapter.
 * @param float  $throttle Minimum delay between requests.
 * @return array Associative array with 'title' and 'content'.
 */
function novelhall_fetch_chapter_content(HttpClient $http, string $url, float $throttle = NOVELHALL_MINIMUM_THROTTLE): array {
    if ($throttle < NOVELHALL_MINIMUM_THROTTLE) {
        $throttle = NOVELHALL_MINIMUM_THROTTLE;
    }
    $http->throttle($throttle);

    $html = $http->get($url);
    list($doc, $xpath) = nh_load_dom($html);

    // content: article div.entry-content
    $contentNode = $xpath->query("//article//div[contains(@class,'entry-content')]")->item(0);
    if (!$contentNode) {
        // small fallback
        $contentNode = $xpath->query("//div[contains(@class,'entry-content')]")->item(0);
    }
    if ($contentNode) {
        nh_remove_nodes_by_xpath($xpath, ".//*[contains(@class,'adsbox')]", $contentNode);
    }

    // chapter title: article div.single-header h1
    $titleNode = $xpath->query("//article//div[contains(@class,'single-header')]//h1")->item(0);
    if (!$titleNode) {
        $titleNode = $xpath->query("//h1")->item(0);
    }
    $title = $titleNode ? trim(preg_replace('/\s+/', ' ', $titleNode->textContent)) : '';

    $contentHtml = $contentNode ? nh_inner_html($contentNode) : '';
    $contentHtml = nh_clean_fragment_html($contentHtml, $url);

    return array(
        'title'   => $title,
        'content' => $contentHtml
    );
}

/* ---------------- novel page ---------------- */

/**
 * Parses the main novel page to extract metadata and chapters from NovelHall.
 *
 * @param string        $url      The URL of the novel page.
 * @param float         $throttle Minimum delay between requests.
 * @param callable|null $log      Optional callback for logging.
 * @return array Associative array containing novel metadata and list of chapters.
 */
function novelhall_parse_novel_page(HttpClient $http, string $url, float $throttle = NOVELHALL_MINIMUM_THROTTLE, ?callable $log = null): array {
    if ($throttle < NOVELHALL_MINIMUM_THROTTLE) {
        $throttle = NOVELHALL_MINIMUM_THROTTLE;
    }

    if ($log) {
        $log("Fetching novel page: $url");
    }

    $html = $http->get($url);
    list($doc, $xpath) = nh_load_dom($html);

    $novel = array(
        'url'      => $url,
        'title'    => '',
        'author'   => '',
        'summary'  => '',
        'cover'    => '',
        'chapters' => array()
    );

    // Title: div.book-info h1
    $titleNode = $xpath->query("//div[contains(@class,'book-info')]//h1")->item(0);
    if (!$titleNode) {
        $titleNode = $xpath->query("//h1")->item(0);
    }
    if ($titleNode) {
        $novel['title'] = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
    }

    // Author: <meta property="books:author" content="...">
    $authorMeta = $xpath->query("//meta[@property='books:author']")->item(0);
    if ($authorMeta instanceof DOMElement) {
        $metaContent = $authorMeta->getAttribute('content');
        if ($metaContent !== '') {
            $novel['author'] = trim($metaContent);
        }
    }

    // Cover: first img under div.book-img
    $coverNode = $xpath->query("//div[contains(@class,'book-img')]//img")->item(0);
    if ($coverNode instanceof DOMElement) {
        $src = $coverNode->getAttribute('src');
        if ($src) {
            $novel['cover'] = nh_url_join($url, $src);
        }
        if ($novel['title'] === '') {
            $alt = trim($coverNode->getAttribute('alt') ?: '');
            if ($alt !== '') {
                $novel['title'] = $alt;
            }
        }
    }

    // Summary: div.book-info div.intro (can be multiple, join them)
    $introNodes = $xpath->query("//div[contains(@class,'book-info')]//div[contains(@class,'intro')]");
    if ($introNodes && $introNodes->length) {
        $summaryPieces = array();
        foreach ($introNodes as $node) {
            $txt = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($txt !== '') {
                $summaryPieces[] = $txt;
            }
        }
        if (!empty($summaryPieces)) {
            $novel['summary'] = implode("\n\n", $summaryPieces);
        }
    }

    // Chapters: use Novelhall getChapterUrls logic
    $novel['chapters'] = nh_extract_chapter_list($doc, $url);

    if ($log) {
        $log("Parsed novel title: " . ($novel['title'] ?: '(untitled)'));
        $log("Author: " . ($novel['author'] ?: '(unknown)'));
        $log("Cover URL: " . ($novel['cover'] ?: '(none)'));
        $log("Found " . count($novel['chapters']) . " chapter links on TOC.");
    }

    return $novel;
}

/* ---------------- IMPORT INTO DB ---------------- */

/**
 * Import novel + chapters from novelhall.com into DB.
 *
 * @param PDO      $pdo
 * @param string   $url
 * @param int      $startChapter   1-based index of first chapter
 * @param int|null $endChapter     1-based index of last chapter, or null for all
 * @param float    $throttle       delay between requests (seconds)
 * @param bool     $preserveTitles if true, keep original chapter titles
 * @param callable|null $logger    optional logger: function(string $msg): void
 *
 * @return int newly created novel ID
 */
function novelhall_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter = 1,
    ?int $endChapter = null,
    float $throttle = NOVELHALL_MINIMUM_THROTTLE,
    bool $preserveTitles = false,
    ?callable $logger = null
): int {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host || !in_array(strtolower($host), NOVELHALL_ALLOWED_HOSTS, true)) {
        throw new RuntimeException("Unsupported host for Novelhall scraper: " . $host);
    }

    if ($throttle < NOVELHALL_MINIMUM_THROTTLE) {
        $throttle = NOVELHALL_MINIMUM_THROTTLE;
    }

    $http = new HttpClient();

    if ($logger) {
        $logger("Fetching Novelhall novel page…");
    }

    $novel = novelhall_parse_novel_page($http, $url, $throttle);
    if (empty($novel['chapters'])) {
        throw new RuntimeException("No chapters found on this Novelhall novel page.");
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

        // try reuse existing novel with same title + source tag
        $stmt = $pdo->prepare("SELECT id FROM novels WHERE title = ? AND tags = 'novelhall' LIMIT 1");
        $stmt->execute([$baseTitle]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $novelId = (int)$existingId;
            if ($logger) {
                $logger("Reusing existing Novelhall novel (ID {$novelId}) with title '{$baseTitle}'.");
            }
        } else {
            if ($logger) {
                $logger("Creating new Novelhall novel with title '{$baseTitle}'.");
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
                'novelhall'
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
                $res = novelhall_fetch_chapter_content($http, $ch['url'], $throttle);
                if (!empty($res['title'])) {
                    $ch['name'] = $res['title'];
                }
                $contentHtml = $res['content'] ?: '<p><em>(empty chapter)</em></p>';
            }

            $rawTitle = isset($ch['name']) ? trim($ch['name']) : '';
            if (!$preserveTitles) {
                $short = nh_strip_leading_chapter_prefix($rawTitle);
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

            // same paragraph-preserving clean as before
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
            $logger("Novelhall import complete for novel ID {$novelId}.");
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
