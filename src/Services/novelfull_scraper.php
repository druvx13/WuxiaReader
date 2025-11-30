<?php
// novelfull_scraper.php
// Novelfull-style parser adapted for PHP, targeting novelfull/allnovel/novelnext/etc clones.
// Imports novels + chapters directly into the existing MySQL schema used by your app.
// Reuses novel row if same title+tag already exists, supports live logger callback.

declare(strict_types=1);

const NOVELFULL_ALLOWED_HOSTS = array(
    'allnovel.org', 'www.allnovel.org',
    'allnovelbin.net', 'www.allnovelbin.net',
    'allnovelfull.app', 'www.allnovelfull.app',
    'allnovelfull.com', 'www.allnovelfull.com',
    'allnovelfull.org', 'www.allnovelfull.org',
    'allnovelfull.net', 'www.allnovelfull.net',
    'allnovelnext.com', 'www.allnovelnext.com',
    'all-novelfull.net', 'www.all-novelfull.net',
    'boxnovelfull.com', 'www.boxnovelfull.com',
    'freenovelsread.com', 'www.freenovelsread.com',
    'freewn.com', 'www.freewn.com',
    'novel-bin.com', 'www.novel-bin.com',
    'novel-bin.net', 'www.novel-bin.net',
    'novel-bin.org', 'www.novel-bin.org',
    'novel-next.com', 'www.novel-next.com',
    'novel35.com', 'www.novel35.com',
    'novelactive.org', 'www.novelactive.org',
    'novelbin.com', 'www.novelbin.com',
    'novelbin.me', 'www.novelbin.me',
    'novelbin.net', 'www.novelbin.net',
    'novelbin.org', 'www.novelbin.org',
    'noveldrama.org', 'www.noveldrama.org',
    'novelebook.net', 'www.novelebook.net',
    'novelfull.com', 'www.novelfull.com',
    'novelfull.net', 'www.novelfull.net',
    'novelfullbook.com', 'www.novelfullbook.com',
    'novelfulll.com', 'www.novelfulll.com',
    'novelhulk.net', 'www.novelhulk.net',
    'novelmax.net', 'www.novelmax.net',
    'novelnext.com', 'www.novelnext.com',
    'novelnext.dramanovels.io', 'www.novelnext.dramanovels.io',
    'novelnext.net', 'www.novelnext.net',
    'novelnextz.com', 'www.novelnextz.com',
    'noveltop1.org', 'www.noveltop1.org',
    'noveltrust.net', 'www.noveltrust.net',
    'novelusb.com', 'www.novelusb.com',
    'novelusb.net', 'www.novelusb.net',
    'novelxo.net', 'www.novelxo.net',
    'readnovelfull.me', 'www.readnovelfull.me',
    'thenovelbin.org', 'www.thenovelbin.org',
    'topnovelfull.com', 'www.topnovelfull.com',
    'zinnovel.net', 'www.zinnovel.net',
);

const NOVELFULL_MINIMUM_THROTTLE = 1.0; // seconds (JS minimumThrottle=1000)

/* ---------------- utilities ---------------- */

function nvf_http_get(string $url, array $headers = array(), int $timeout = 60): string {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NovelfullImporter/1.0)',
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

function nvf_throttle(float $seconds): void {
    if ($seconds > 0) {
        usleep((int)($seconds * 1000000));
    }
}

function nvf_load_dom(string $html): array {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    $xpath = new DOMXPath($doc);
    libxml_clear_errors();
    return array($doc, $xpath);
}

function nvf_remove_nodes_by_xpath(DOMXPath $xpath, string $expr, ?DOMNode $context = null): void {
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

function nvf_inner_html(DOMNode $node): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return $html;
}

function nvf_url_join(string $base, string $rel): string {
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

function nvf_strip_leading_chapter_prefix(string $title): string {
    $t = preg_replace('/^\s*(?:Chapter|Chap|Ch)[\s\.\-:]*\d+[\s\.\-:]*\s*/i', '', $title);
    $t = preg_replace('/^\s*\d{1,4}[\.\)\-:\s]+\s*/', '', $t);
    return trim($t);
}

/**
 * Clean small HTML fragment, remove obvious junk (ads, scripts) but keep structural tags.
 */
function nvf_clean_fragment_html(string $html, string $baseUrl = ''): string {
    list($doc, $xpath) = nvf_load_dom($html);
    nvf_remove_nodes_by_xpath($xpath, '//script|//style|//noscript|//comment()');
    nvf_remove_nodes_by_xpath($xpath, "//*[contains(@class,'adsbox')]");

    if ($baseUrl !== '') {
        $nodes = $xpath->query('//*[@src or @href]');
        if ($nodes) {
            foreach ($nodes as $el) {
                /** @var DOMElement $el */
                if ($el->hasAttribute('src')) {
                    $v = $el->getAttribute('src');
                    if ($v && !preg_match('#^(https?|data|mailto|tel|javascript):#i', $v)) {
                        $el->setAttribute('src', nvf_url_join($baseUrl, $v));
                    }
                }
                if ($el->hasAttribute('href')) {
                    $v = $el->getAttribute('href');
                    if ($v && !preg_match('#^(https?|data|mailto|tel|javascript):#i', $v)) {
                        $el->setAttribute('href', nvf_url_join($baseUrl, $v));
                    }
                }
            }
        }
    }

    $body = $xpath->query('//body')->item(0);
    return $body ? nvf_inner_html($body) : $html;
}

/* ---------------- TOC helpers (Novelfull style) ---------------- */

function nvf_build_toc_page_url(string $href, int $i): string {
    $parts = parse_url($href);
    if (!$parts) {
        return $href;
    }
    $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
    $host   = isset($parts['host']) ? $parts['host'] : '';
    $path   = isset($parts['path']) ? $parts['path'] : '/';
    $query  = isset($parts['query']) ? $parts['query'] : '';

    $hostLower = strtolower($host);

    if ($hostLower === 'freenovelsread.com' || $hostLower === 'www.freenovelsread.com') {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) >= 1) {
            // JS: link.pathname = link.pathname.split("/")[1] + "/" + i;
            // Approx: "/{firstSegment}/i"
            $path = '/' . $segments[0] . '/' . $i;
        } else {
            $path = '/' . $i;
        }
        $query = '';
    } elseif ($hostLower === 'novelfulll.com' || $hostLower === 'www.novelfulll.com') {
        $path  = $path ?: '/';
        $query = http_build_query(array('page_num' => $i));
    } else {
        $path  = $path ?: '/';
        $query = http_build_query(array('page' => $i, 'per-page' => 50));
    }

    $url = $scheme . '://' . $host . $path;
    if ($query !== '') {
        $url .= '?' . $query;
    }
    return $url;
}

/**
 * Get TOC page URLs, following JS logic:
 * link = "li.last a"; data-page or ?page_num, then 1..limit buildUrlForTocPage
 */
function nvf_get_toc_page_urls(DOMDocument $doc, string $baseUrl): array {
    $xpath = new DOMXPath($doc);
    $linkNode = $xpath->query("//li[contains(@class,'last')]//a")->item(0);
    if (!$linkNode instanceof DOMElement) {
        return array($baseUrl);
    }
    $href = trim($linkNode->getAttribute('href'));
    if ($href === '') {
        return array($baseUrl);
    }
    $href = nvf_url_join($baseUrl, $href);

    $limitAttr = $linkNode->getAttribute('data-page');
    if ($limitAttr === null || $limitAttr === '') {
        $parts = parse_url($href);
        $limitAttr = null;
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (isset($q['page_num'])) {
                $limitAttr = $q['page_num'];
            }
        }
    }
    $limit = (int)($limitAttr !== null && $limitAttr !== '' ? $limitAttr : -1);
    $limit = $limit + 1; // JS: parseInt(limit || "-1") + 1

    if ($limit <= 0) {
        return array($baseUrl);
    }

    $urls = array();
    for ($i = 1; $i <= $limit; $i++) {
        $urls[] = nvf_build_toc_page_url($href, $i);
    }
    return $urls;
}

/**
 * Extract chapter anchors from "ul.list-chapter a".
 */
function nvf_extract_partial_chapter_list(DOMDocument $doc, string $baseUrl): array {
    $xpath = new DOMXPath($doc);
    $links = $xpath->query("//ul[contains(@class,'list-chapter')]//a");
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
        $href = nvf_url_join($baseUrl, $href);

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
 * NovelfullParser.findContent:
 *   #chr-content OR #chapter-content
 * findChapterTitle:
 *   "h2".textContent
 */
function novelfull_fetch_chapter_content(string $url, float $throttle = NOVELFULL_MINIMUM_THROTTLE): array {
    if ($throttle < NOVELFULL_MINIMUM_THROTTLE) {
        $throttle = NOVELFULL_MINIMUM_THROTTLE;
    }
    nvf_throttle($throttle);

    $html = nvf_http_get($url);
    list($doc, $xpath) = nvf_load_dom($html);

    $contentNode = $xpath->query("//*[@id='chr-content']")->item(0);
    if (!$contentNode) {
        $contentNode = $xpath->query("//*[@id='chapter-content']")->item(0);
    }

    if ($contentNode) {
        nvf_remove_nodes_by_xpath($xpath, ".//*[contains(@class,'adsbox')]", $contentNode);
        nvf_remove_nodes_by_xpath($xpath, ".//*[contains(@class,'novel_online') or contains(@class,'unlock-buttons')]", $contentNode);
    }

    $titleNode = $xpath->query("//h2")->item(0);
    $title = $titleNode ? trim(preg_replace('/\s+/', ' ', $titleNode->textContent)) : '';

    $contentHtml = $contentNode ? nvf_inner_html($contentNode) : '';
    $contentHtml = nvf_clean_fragment_html($contentHtml, $url);

    return array(
        'title'   => $title,
        'content' => $contentHtml
    );
}

/* ---------------- novel page ---------------- */

/**
 * Title:       h3.title
 * Author:      ul.info-meta li having h3 "Author:" then <a> text
 * Cover:       first img in div.book
 * Summary:     div.desc-text, div.info
 * Chapters:    ul.list-chapter a  + pagination via li.last a
 */
function novelfull_parse_novel_page(string $url, float $throttle = NOVELFULL_MINIMUM_THROTTLE): array {
    if ($throttle < NOVELFULL_MINIMUM_THROTTLE) {
        $throttle = NOVELFULL_MINIMUM_THROTTLE;
    }
    $html = nvf_http_get($url);
    list($doc, $xpath) = nvf_load_dom($html);

    $novel = array(
        'url'      => $url,
        'title'    => '',
        'author'   => '',
        'summary'  => '',
        'cover'    => '',
        'chapters' => array()
    );

    // Title: h3.title
    $titleNode = $xpath->query("//h3[contains(@class,'title')]")->item(0);
    if (!$titleNode) {
        $titleNode = $xpath->query("//h1|//h2")->item(0);
    }
    if ($titleNode) {
        $novel['title'] = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
    }

    // Author: ul.info-meta li with h3 "Author:"
    $infoLis = $xpath->query("//ul[contains(@class,'info-meta')]//li");
    if ($infoLis) {
        foreach ($infoLis as $li) {
            /** @var DOMElement $li */
            $h3 = $xpath->query(".//h3", $li)->item(0);
            if ($h3 && trim($h3->textContent) === 'Author:') {
                $a = $xpath->query(".//a", $li)->item(0);
                if ($a) {
                    $novel['author'] = trim(preg_replace('/\s+/', ' ', $a->textContent));
                    break;
                }
            }
        }
    }

    // Cover: first img under div.book
    $coverNode = $xpath->query("//div[contains(@class,'book')]//img")->item(0);
    if ($coverNode instanceof DOMElement) {
        $src = $coverNode->getAttribute('src');
        if ($src) {
            $novel['cover'] = nvf_url_join($url, $src);
        }
        if ($novel['title'] === '') {
            $alt = trim($coverNode->getAttribute('alt') ?: '');
            if ($alt !== '') {
                $novel['title'] = $alt;
            }
        }
    }

    // Summary: div.desc-text, div.info
    $summaryNodes = $xpath->query("//div[contains(@class,'desc-text')] | //div[contains(@class,'info')]");
    if ($summaryNodes && $summaryNodes->length) {
        $parts = array();
        foreach ($summaryNodes as $node) {
            /** @var DOMElement $node */
            $txt = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($txt !== '') {
                $parts[] = $txt;
            }
        }
        if (!empty($parts)) {
            $novel['summary'] = implode("\n\n", $parts);
        }
    }

    $novel['chapters'] = array();
    $seen = array();

    // Initial page chapter list
    $partials = nvf_extract_partial_chapter_list($doc, $url);
    foreach ($partials as $p) {
        if (empty($p['url'])) {
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
    $tocUrls = nvf_get_toc_page_urls($doc, $url);
    foreach ($tocUrls as $tocUrl) {
        if ($tocUrl === $url) {
            continue;
        }
        nvf_throttle($throttle);
        try {
            $tocHtml = nvf_http_get($tocUrl);
            list($tDoc, $tXPath) = nvf_load_dom($tocHtml);
            $partials = nvf_extract_partial_chapter_list($tDoc, $tocUrl);
            foreach ($partials as $p) {
                if (empty($p['url'])) {
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
            // ignore failed TOC page
        }
    }

    return $novel;
}

/* ---------------- IMPORT INTO DB ---------------- */

/**
 * Import novel + chapters from Novelfull-style site into DB.
 * Reuses existing novel row if same title + tags='novelfull' already exists.
 *
 * @param PDO      $pdo
 * @param string   $url           main novel URL
 * @param int      $startChapter  1-based start
 * @param int|null $endChapter    1-based end or null for all
 * @param float    $throttle      seconds between HTTP requests
 * @param bool     $preserveTitles keep original chapter titles or renumber
 * @param callable|null $logger   function(string $msg):void  for live logs
 *
 * @return int novel_id
 */
function novelfull_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter = 1,
    ?int $endChapter = null,
    float $throttle = NOVELFULL_MINIMUM_THROTTLE,
    bool $preserveTitles = false,
    ?callable $logger = null
): int {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host || !in_array(strtolower($host), NOVELFULL_ALLOWED_HOSTS, true)) {
        throw new RuntimeException("Unsupported host for Novelfull scraper: " . $host);
    }

    if ($throttle < NOVELFULL_MINIMUM_THROTTLE) {
        $throttle = NOVELFULL_MINIMUM_THROTTLE;
    }

    if ($logger) {
        $logger("Fetching Novelfull-style novel page…");
    }

    $novel = novelfull_parse_novel_page($url, $throttle);
    if (empty($novel['chapters'])) {
        throw new RuntimeException("No chapters found on this Novelfull-style novel page.");
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

        // reuse existing novelfull novel if same title + tag
        $stmt = $pdo->prepare("SELECT id FROM novels WHERE title = ? AND tags = 'novelfull' LIMIT 1");
        $stmt->execute([$baseTitle]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $novelId = (int)$existingId;
            if ($logger) {
                $logger("Reusing existing Novelfull novel (ID {$novelId}) with title '{$baseTitle}'.");
            }
        } else {
            if ($logger) {
                $logger("Creating new Novelfull novel with title '{$baseTitle}'.");
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
                'novelfull'
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
                $res = novelfull_fetch_chapter_content($ch['url'], $throttle);
                if (!empty($res['title'])) {
                    $ch['name'] = $res['title'];
                }
                $contentHtml = $res['content'] ?: '<p><em>(empty chapter)</em></p>';
            }

            $rawTitle = isset($ch['name']) ? trim($ch['name']) : '';
            if (!$preserveTitles) {
                $short = nvf_strip_leading_chapter_prefix($rawTitle);
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

            // paragraph-preserving conversion
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
            $logger("Novelfull import complete for novel ID {$novelId}.");
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
