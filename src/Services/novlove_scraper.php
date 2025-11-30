<?php
// novlove_scraper.php
// Readwn-style parser adapted for PHP, targeting novlove.com.
// Imports novels + chapters directly into the existing MySQL schema used by your app.

declare(strict_types=1);

const NOVLOVE_ALLOWED_HOSTS = array(
    'novlove.com', 'www.novlove.com'
);

const NOVLOVE_MINIMUM_THROTTLE = 3.0; // seconds

/* ---------------- utilities ---------------- */

/**
 * Performs an HTTP GET request using cURL.
 *
 * @param string $url     The URL to fetch.
 * @param array  $headers Optional HTTP headers to send.
 * @param int    $timeout Request timeout in seconds.
 * @return string The response body.
 * @throws RuntimeException If the request fails or returns an error status.
 */
function novlove_http_get(string $url, array $headers = array(), int $timeout = 60): string {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NovloveImporter/1.0)',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING => ''
    ));
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
function novlove_throttle(float $seconds): void {
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
function novlove_load_dom(string $html): array {
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
function novlove_remove_nodes_by_xpath(DOMXPath $xpath, string $expr, ?DOMNode $context = null): void {
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
function novlove_inner_html(DOMNode $node): string {
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
function novlove_url_join(string $base, string $rel): string {
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
function novlove_strip_leading_chapter_prefix(string $title): string {
    $t = preg_replace('/^\s*(?:Chapter|Chap|Ch)[\s\.\-:]*\d+[\s\.\-:]*\s*/i', '', $title);
    $t = preg_replace('/^\s*\d{1,4}[\.\)\-:\s]+\s*/', '', $t);
    return trim($t);
}

/**
 * Cleans an HTML fragment by removing branding, scripts, styles, ads, and comments.
 *
 * @param string $html    The HTML fragment to clean.
 * @param string $baseUrl The base URL (unused here but kept for consistency).
 * @return string The cleaned HTML.
 */
function novlove_clean_fragment_html(string $html, string $baseUrl = ''): string {
    list($doc, $xpath) = novlove_load_dom($html);
    novlove_remove_nodes_by_xpath($xpath, '//script|//style|//noscript|//comment()');
    novlove_remove_nodes_by_xpath($xpath, "//*[contains(@class,'ads')]");
    novlove_remove_nodes_by_xpath($xpath, "//*[contains(@class,'novlove')]");

    // Remove explicit branding
    $html = preg_replace('#<a[^>]*href=["\']https?://(?:www\.)?novlove\.com[^"\']*["\'][^>]*>.*?</a>#si', '', $doc->saveHTML());
    $html = preg_replace('/\b(?:novlove|novlove\.com)\b/i', '', $html);

    list($doc, $xpath) = novlove_load_dom($html);
    $body = $xpath->query('//body')->item(0);
    return $body ? novlove_inner_html($body) : $html;
}

/* ---------------- novel page ---------------- */

/**
 * Parses the main novel page to extract metadata and the full list of chapters.
 *
 * @param string        $url      The URL of the novel page.
 * @param float         $throttle Minimum delay between requests.
 * @param callable|null $log      Optional callback for logging progress messages.
 * @return array Associative array containing novel metadata and list of chapters.
 */
function novlove_parse_novel_page(string $url, float $throttle = NOVLOVE_MINIMUM_THROTTLE, ?callable $log = null): array {
    if ($throttle < NOVLOVE_MINIMUM_THROTTLE) {
        $throttle = NOVLOVE_MINIMUM_THROTTLE;
    }

    if ($log) {
        $log("Fetching novel page: $url");
    }

    $html = novlove_http_get($url);
    list($doc, $xpath) = novlove_load_dom($html);

    $novel = array(
        'url'      => $url,
        'title'    => '',
        'author'   => '',
        'summary'  => '',
        'cover'    => '',
        'chapters' => array()
    );

    // Title
    $titleNode = $xpath->query("//div[@class='profile-manga']//h1[@class='post-title']")->item(0);
    if (!$titleNode) {
        $titleNode = $xpath->query("//h1")->item(0);
    }
    if ($titleNode) {
        $novel['title'] = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
    }

    // Cover
    $coverNode = $xpath->query("//div[@class='summary_image']//img")->item(0);
    if ($coverNode) {
        $src = $coverNode->getAttribute('data-src') ?: $coverNode->getAttribute('src');
        if ($src) {
            $novel['cover'] = novlove_url_join($url, $src);
        }
    }

    // Summary
    $summaryNode = $xpath->query("//div[contains(@class,'summary-text')]//p")->item(0);
    if ($summaryNode) {
        $novel['summary'] = trim(preg_replace('/\s+/', ' ', $summaryNode->textContent));
    }

    // Author (not always present; fallback to "Unknown")
    $author = "Unknown";
    $authorNodes = $xpath->query("//div[contains(@class,'author-content')]");
    if ($authorNodes->length > 0) {
        foreach ($authorNodes as $node) {
            if (strpos($node->textContent, 'Author') !== false) {
                $author = trim(preg_replace('/Author\s*:\s*/i', '', $node->textContent));
                break;
            }
        }
    }
    $novel['author'] = $author;

    // Chapters
    $chapterNodes = $xpath->query("//ul[@class='listing-chapters_wrap']//li");
    $chapters = array();
    foreach ($chapterNodes as $li) {
        $a = $xpath->query(".//a", $li)->item(0);
        if (!$a) continue;
        $href = $a->getAttribute('href');
        if (!$href) continue;
        $fullUrl = novlove_url_join($url, $href);
        $text = trim(preg_replace('/\s+/', ' ', $a->textContent));
        $chapters[] = array(
            'name' => $text,
            'url'  => $fullUrl
        );
    }

    $novel['chapters'] = array_reverse($chapters); // novlove lists newest first

    if ($log) {
        $log("Parsed novel title: " . ($novel['title'] ?: '(untitled)'));
        $log("Author: " . $novel['author']);
        $log("Cover URL: " . ($novel['cover'] ?: '(none)'));
        $log("Total discovered chapter links: " . count($novel['chapters']));
    }

    return $novel;
}

/* ---------------- chapter page ---------------- */

/**
 * Fetches and parses the content of a single chapter.
 *
 * @param string $url      The URL of the chapter.
 * @param float  $throttle The minimum time (in seconds) to wait before the request.
 * @return array Associative array with 'title' and 'content'.
 */
function novlove_fetch_chapter_content(string $url, float $throttle = NOVLOVE_MINIMUM_THROTTLE): array {
    if ($throttle < NOVLOVE_MINIMUM_THROTTLE) {
        $throttle = NOVLOVE_MINIMUM_THROTTLE;
    }
    novlove_throttle($throttle);

    $html = novlove_http_get($url);
    list($doc, $xpath) = novlove_load_dom($html);

    // Title
    $titleNode = $xpath->query("//h1[@class='text-center']")->item(0);
    $title = $titleNode ? trim(preg_replace('/\s+/', ' ', $titleNode->textContent)) : '';

    // Content
    $contentNode = $xpath->query("//div[contains(@class,'text-left')]")->item(0);
    if (!$contentNode) {
        $contentNode = $xpath->query("//div[@id='chapter-content']")->item(0);
    }

    if ($contentNode) {
        novlove_remove_nodes_by_xpath($xpath, ".//*[contains(@class,'ads')]", $contentNode);
        $contentHtml = novlove_inner_html($contentNode);
        $contentHtml = novlove_clean_fragment_html($contentHtml, $url);
    } else {
        $contentHtml = '<p><em>(content not found)</em></p>';
    }

    return array(
        'title'   => $title,
        'content' => $contentHtml
    );
}

/* ---------------- IMPORT INTO DB ---------------- */

/**
 * Import novel + chapters into DB using Novlove scraper.
 *
 * @param PDO          $pdo
 * @param string       $url
 * @param int          $startChapter   1-based start
 * @param int|null     $endChapter     1-based end or null
 * @param float        $throttle       seconds between HTTP requests
 * @param bool         $preserveTitles preserve site titles if true
 * @param callable|null $log           optional logger: function(string $msg): void
 *
 * @return int newly created novel ID
 */
function novlove_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter = 1,
    ?int $endChapter = null,
    float $throttle = NOVLOVE_MINIMUM_THROTTLE,
    bool $preserveTitles = false,
    ?callable $logger = null
): int {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host || !in_array(strtolower($host), NOVLOVE_ALLOWED_HOSTS, true)) {
        throw new RuntimeException("Unsupported host: " . $host);
    }

    if ($throttle < NOVLOVE_MINIMUM_THROTTLE) {
        $throttle = NOVLOVE_MINIMUM_THROTTLE;
    }

    if ($logger) {
        $logger("Fetching Novlove novel page…");
    }

    $novel = novlove_parse_novel_page($url, $throttle, $logger);
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

        // Reuse existing novel if title + tag match
        $stmt = $pdo->prepare("SELECT id FROM novels WHERE title = ? AND tags = 'novlove' LIMIT 1");
        $stmt->execute([$baseTitle]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $novelId = (int)$existingId;
            if ($logger) {
                $logger("Reusing existing Novlove novel (ID {$novelId}) with title '{$baseTitle}'.");
            }
        } else {
            if ($logger) {
                $logger("Creating new Novlove novel with title '{$baseTitle}'.");
            }
            $stmt = $pdo->prepare("
                INSERT INTO novels (title, cover_url, description, author, tags)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute(array(
                $baseTitle,
                $novel['cover'] ?: null,
                $novel['summary'] ?: '',
                $novel['author'] ?: 'Unknown',
                'novlove'
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

            $res = novlove_fetch_chapter_content($ch['url'], $throttle);
            if (!empty($res['title'])) {
                $ch['name'] = $res['title'];
            }
            $contentHtml = $res['content'] ?: '<p><em>(empty chapter)</em></p>';

            $rawTitle = isset($ch['name']) ? trim($ch['name']) : '';
            if (!$preserveTitles) {
                $short = novlove_strip_leading_chapter_prefix($rawTitle);
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

            // Convert HTML to clean paragraph-separated plain text
            $tmp = $contentHtml;
            $tmp = preg_replace('#<br\s*/?>#i', "\n", $tmp);
            $blockTags = array('p','div','section','article','h1','h2','h3','h4','h5','blockquote','pre','li');
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
            $logger("Novlove import complete for novel ID {$novelId}.");
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
