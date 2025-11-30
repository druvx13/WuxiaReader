<?php
// readnovelfull_scraper.php
// Scraper for readnovelfull.com
// Imports novels + chapters directly into the existing MySQL schema.

declare(strict_types=1);

const READNOVELFULL_ALLOWED_HOSTS = array(
    'readnovelfull.com', 'www.readnovelfull.com'
);

const READNOVELFULL_MINIMUM_THROTTLE = 1.0; // seconds

/* ---------------- utilities ---------------- */

function rnf_http_get(string $url, array $headers = array(), int $timeout = 60): string {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ReadNovelFullImporter/1.0)',
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

function rnf_throttle(float $seconds): void {
    if ($seconds > 0) {
        usleep((int)($seconds * 1000000));
    }
}

function rnf_load_dom(string $html): array {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    $xpath = new DOMXPath($doc);
    libxml_clear_errors();
    return array($doc, $xpath);
}

function rnf_remove_nodes_by_xpath(DOMXPath $xpath, string $expr, ?DOMNode $context = null): void {
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

function rnf_inner_html(DOMNode $node): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return $html;
}

function rnf_url_join(string $base, string $rel): string {
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

function rnf_strip_leading_chapter_prefix(string $title): string {
    $t = preg_replace('/^\s*(?:Chapter|Chap|Ch)[\s\.\-:]*\d+[\s\.\-:]*\s*/i', '', $title);
    $t = preg_replace('/^\s*\d{1,4}[\.\)\-:\s]+\s*/', '', $t);
    return trim($t);
}

/**
 * Clean small HTML fragment, remove obvious junk (ads, scripts) but keep structural tags.
 */
function rnf_clean_fragment_html(string $html, string $baseUrl = ''): string {
    list($doc, $xpath) = rnf_load_dom($html);
    rnf_remove_nodes_by_xpath($xpath, '//script|//style|//noscript|//comment()');
    rnf_remove_nodes_by_xpath($xpath, "//*[contains(@class,'adsbox')]");

    if ($baseUrl !== '') {
        $nodes = $xpath->query('//*[@src or @href]');
        if ($nodes) {
            foreach ($nodes as $el) {
                /** @var DOMElement $el */
                if ($el->hasAttribute('src')) {
                    $v = $el->getAttribute('src');
                    if ($v && !preg_match('#^(https?|data|mailto|tel|javascript):#i', $v)) {
                        $el->setAttribute('src', rnf_url_join($baseUrl, $v));
                    }
                }
                if ($el->hasAttribute('href')) {
                    $v = $el->getAttribute('href');
                    if ($v && !preg_match('#^(https?|data|mailto|tel|javascript):#i', $v)) {
                        $el->setAttribute('href', rnf_url_join($baseUrl, $v));
                    }
                }
            }
        }
    }

    $body = $xpath->query('//body')->item(0);
    return $body ? rnf_inner_html($body) : $html;
}


/* ---------------- Chapter List Fetching ---------------- */

/**
 * Extracts chapter list. If initial list is empty, fetches via AJAX using novelId.
 */
function rnf_get_all_chapters(DOMDocument $doc, DOMXPath $xpath, string $baseUrl): array {
    $out = array();

    // 1. Try to find existing ul.list-chapter
    $links = $xpath->query("//ul[contains(@class,'list-chapter')]//a");
    if ($links && $links->length > 0) {
        foreach ($links as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            if ($href === '') continue;
            $href = rnf_url_join($baseUrl, $href);
            $titleText = trim(preg_replace('/\s+/', ' ', $a->textContent));
            $out[] = array('name' => $titleText, 'url' => $href);
        }
    }

    // 2. If list is found, return it.
    // Note: The provided JS code says "if (0 < chapters.length) return ... else fetch".
    // So if we found chapters, we assume that's it (or that it's a static page).
    if (!empty($out)) {
        return $out;
    }

    // 3. Fallback: Fetch via AJAX using novelId from div#rating
    $ratingDiv = $xpath->query("//div[@id='rating']")->item(0);
    if ($ratingDiv instanceof DOMElement) {
        $novelId = $ratingDiv->getAttribute('data-novel-id');
        if ($novelId) {
            $ajaxUrl = "https://readnovelfull.com/ajax/chapter-archive?novelId=" . urlencode($novelId);
            try {
                // The response is usually an HTML snippet containing the <ul> list
                $html = rnf_http_get($ajaxUrl);
                list($ajaxDoc, $ajaxXpath) = rnf_load_dom($html);
                $ajaxLinks = $ajaxXpath->query("//ul[contains(@class,'list-chapter')]//a");
                if ($ajaxLinks) {
                    foreach ($ajaxLinks as $a) {
                        /** @var DOMElement $a */
                        $href = trim($a->getAttribute('href'));
                        if ($href === '') continue;
                        $href = rnf_url_join($baseUrl, $href); // AJAX response might have relative links? Usually absolute or root-relative.
                        $titleText = trim(preg_replace('/\s+/', ' ', $a->textContent));
                        $out[] = array('name' => $titleText, 'url' => $href);
                    }
                }
            } catch (Exception $e) {
                // Ignore AJAX failure
            }
        }
    }

    return $out;
}

/* ---------------- Chapter Content ---------------- */

function readnovelfull_fetch_chapter_content(string $url, float $throttle = READNOVELFULL_MINIMUM_THROTTLE): array {
    if ($throttle < READNOVELFULL_MINIMUM_THROTTLE) {
        $throttle = READNOVELFULL_MINIMUM_THROTTLE;
    }
    rnf_throttle($throttle);

    $html = rnf_http_get($url);
    list($doc, $xpath) = rnf_load_dom($html);

    // findContent: div#chr-content
    $contentNode = $xpath->query("//div[@id='chr-content']")->item(0);

    if ($contentNode) {
        rnf_remove_nodes_by_xpath($xpath, ".//*[contains(@class,'adsbox')]", $contentNode);
    }

    // findChapterTitle: a.chr-title text
    $titleNode = $xpath->query("//a[contains(@class,'chr-title')]")->item(0);
    $title = $titleNode ? trim(preg_replace('/\s+/', ' ', $titleNode->textContent)) : '';

    $contentHtml = $contentNode ? rnf_inner_html($contentNode) : '';
    $contentHtml = rnf_clean_fragment_html($contentHtml, $url);

    return array(
        'title'   => $title,
        'content' => $contentHtml
    );
}

/* ---------------- Novel Page ---------------- */

function readnovelfull_parse_novel_page(string $url, float $throttle = READNOVELFULL_MINIMUM_THROTTLE): array {
    if ($throttle < READNOVELFULL_MINIMUM_THROTTLE) {
        $throttle = READNOVELFULL_MINIMUM_THROTTLE;
    }
    $html = rnf_http_get($url);
    list($doc, $xpath) = rnf_load_dom($html);

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
    if ($titleNode) {
        $novel['title'] = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
    }

    // Author: ul.info li:nth-of-type(2) a
    // xpath index is 1-based, so li[2]
    $authorNode = $xpath->query("//ul[contains(@class,'info')]/li[2]//a")->item(0);
    if ($authorNode) {
        $novel['author'] = trim(preg_replace('/\s+/', ' ', $authorNode->textContent));
    } else {
        // Fallback (super.extractAuthor logic usually generic)
        // Let's look for "Author:" label
        $infoLis = $xpath->query("//ul[contains(@class,'info')]//li");
        if ($infoLis) {
            foreach ($infoLis as $li) {
                if (stripos($li->textContent, 'Author') !== false) {
                     $a = $xpath->query(".//a", $li)->item(0);
                     if ($a) {
                         $novel['author'] = trim(preg_replace('/\s+/', ' ', $a->textContent));
                         break;
                     }
                }
            }
        }
    }

    // Cover: div.book img
    $coverNode = $xpath->query("//div[contains(@class,'book')]//img")->item(0);
    if ($coverNode instanceof DOMElement) {
        $src = $coverNode->getAttribute('src');
        if ($src) {
            $novel['cover'] = rnf_url_join($url, $src);
        }
    }

    // Summary: div.desc-text
    $descNodes = $xpath->query("//div[contains(@class,'desc-text')]");
    if ($descNodes && $descNodes->length) {
        $parts = array();
        foreach ($descNodes as $node) {
            $txt = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($txt !== '') $parts[] = $txt;
        }
        $novel['summary'] = implode("\n\n", $parts);
    }

    // Chapters
    $novel['chapters'] = rnf_get_all_chapters($doc, $xpath, $url);

    return $novel;
}

/* ---------------- IMPORT INTO DB ---------------- */

function readnovelfull_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter = 1,
    ?int $endChapter = null,
    float $throttle = READNOVELFULL_MINIMUM_THROTTLE,
    bool $preserveTitles = false,
    ?callable $logger = null
): int {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host || !in_array(strtolower($host), READNOVELFULL_ALLOWED_HOSTS, true)) {
        throw new RuntimeException("Unsupported host for ReadNovelFull scraper: " . $host);
    }

    if ($throttle < READNOVELFULL_MINIMUM_THROTTLE) {
        $throttle = READNOVELFULL_MINIMUM_THROTTLE;
    }

    if ($logger) {
        $logger("Fetching ReadNovelFull novel page…");
    }

    $novel = readnovelfull_parse_novel_page($url, $throttle);
    if (empty($novel['chapters'])) {
        throw new RuntimeException("No chapters found on this ReadNovelFull novel page.");
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

        $stmt = $pdo->prepare("SELECT id FROM novels WHERE title = ? AND tags = 'readnovelfull' LIMIT 1");
        $stmt->execute([$baseTitle]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $novelId = (int)$existingId;
            if ($logger) {
                $logger("Reusing existing ReadNovelFull novel (ID {$novelId}) with title '{$baseTitle}'.");
            }
        } else {
            if ($logger) {
                $logger("Creating new ReadNovelFull novel with title '{$baseTitle}'.");
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
                'readnovelfull'
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
                $res = readnovelfull_fetch_chapter_content($ch['url'], $throttle);
                if (!empty($res['title'])) {
                    $ch['name'] = $res['title'];
                }
                $contentHtml = $res['content'] ?: '<p><em>(empty chapter)</em></p>';
            }

            $rawTitle = isset($ch['name']) ? trim($ch['name']) : '';
            if (!$preserveTitles) {
                $short = rnf_strip_leading_chapter_prefix($rawTitle);
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
            $logger("ReadNovelFull import complete for novel ID {$novelId}.");
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
