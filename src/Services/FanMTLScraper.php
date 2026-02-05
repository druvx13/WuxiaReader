<?php
/**
 * FanMTLScraper Class
 *
 * This scraper targets fanmtl.com and related clones (fannovel, fansmtl, novelmt, readwn, etc.).
 * Extends AbstractScraper for common functionality.
 *
 * @package    WuxiaReader
 * @subpackage Services
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 */

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use PDO;
use Throwable;

require_once __DIR__ . '/AbstractScraper.php';

/**
 * FanMTLScraper
 *
 * Scraper implementation for fanmtl.com and related sites
 */
class FanMTLScraper extends AbstractScraper
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->minimumThrottle = 3.0;
        $this->userAgent = 'Mozilla/5.0 (compatible; ReadwnImporter/1.0)';
        $this->allowedHosts = [
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
        ];
    }

    /**
     * Gets TOC page URLs from pagination.
     *
     * @param DOMDocument $doc     The document.
     * @param string      $baseUrl Base URL.
     * @return array Array of TOC page URLs.
     */
    private function getTocPageUrls(DOMDocument $doc, string $baseUrl): array
    {
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query("//ul[contains(@class,'pagination')]//li//a");
        if (!$nodes || !$nodes->length) {
            return [$baseUrl];
        }

        $links = [];
        foreach ($nodes as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $full = $this->urlJoin($baseUrl, $href);
            $parts = parse_url($full);
            if (!empty($parts['query']) && strpos($parts['query'], 'page=') !== false) {
                $links[] = $full;
            }
        }

        if (!$links) {
            return [$baseUrl];
        }

        $pageIds = [];
        foreach ($links as $full) {
            $parts = parse_url($full);
            $q = [];
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $q);
                if (isset($q['page'])) {
                    $pageIds[] = (int)$q['page'];
                }
            }
        }

        if (!$pageIds) {
            return [$baseUrl];
        }

        $maxPage = max($pageIds);
        $baseParts = parse_url($baseUrl);
        $scheme = $baseParts['scheme'] ?? 'https';
        $host = $baseParts['host'] ?? '';
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $path = $baseParts['path'] ?? '/';

        $urls = [];
        for ($p = 1; $p <= $maxPage; $p++) {
            $urls[] = $scheme . '://' . $host . $port . $path . '?page=' . $p;
        }
        return $urls;
    }

    /**
     * Extracts partial chapter list from a page.
     *
     * @param DOMDocument $doc     The document.
     * @param string      $baseUrl Base URL.
     * @return array Array of chapters.
     */
    private function extractPartialChapterList(DOMDocument $doc, string $baseUrl): array
    {
        $xpath = new DOMXPath($doc);
        $chLinks = $xpath->query("//ul[contains(@class,'chapter-list')]//li//a");
        $out = [];

        if (!$chLinks || !$chLinks->length) {
            return $out;
        }

        foreach ($chLinks as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $href = $this->urlJoin($baseUrl, $href);

            $chNum = $a->getAttribute('data-chapter');
            $titleEls = $xpath->query(".//p[@class='chapter-title']", $a);
            $titleText = $titleEls && $titleEls->length 
                ? trim($titleEls->item(0)->textContent) 
                : '';

            $title = '';
            if ($chNum !== '' && $titleText !== '') {
                $num = trim($chNum);
                if (preg_match('/^\d+$/', $num)) {
                    $title = $num . ': ' . $titleText;
                } else {
                    $title = $num . ' ' . $titleText;
                }
            } elseif ($chNum !== '') {
                $title = trim($chNum);
            } elseif ($titleText !== '') {
                $title = $titleText;
            } else {
                $title = trim(preg_replace('/\s+/', ' ', $a->textContent));
                if ($title === '') {
                    $title = 'Chapter';
                }
            }

            $out[] = [
                'name' => $title,
                'url'  => $href
            ];
        }
        return $out;
    }

    /**
     * Fetches chapter content from a URL.
     *
     * @param string $url      The chapter URL.
     * @param float  $throttle Throttle time in seconds.
     * @return array{title: string, content: string} Chapter data.
     */
    public function fetchChapterContent(string $url, float $throttle): array
    {
        if ($throttle < $this->minimumThrottle) {
            $throttle = $this->minimumThrottle;
        }
        $this->throttle($throttle);

        $html = $this->httpGet($url);
        [$doc, $xpath] = $this->loadDom($html);

        $contentNode = $xpath->query("//div[contains(@class,'chapter-content')]")->item(0);
        if (!$contentNode) {
            $contentNode =
                $xpath->query("//div[@id='chapter-content']")->item(0) ?:
                $xpath->query("//article")->item(0) ?:
                $xpath->query("//main")->item(0);
        }
        if ($contentNode) {
            $this->removeNodesByXpath($xpath, ".//*[contains(@class,'adsbox')]", $contentNode);
        }

        $titleNode = $xpath->query("//h2")->item(0);
        $title = $titleNode ? trim(preg_replace('/\s+/', ' ', $titleNode->textContent)) : '';

        $contentHtml = $contentNode ? $this->innerHtml($contentNode) : '';
        $contentHtml = $this->cleanFragmentHtml($contentHtml, $url);

        return [
            'title'   => $title,
            'content' => $contentHtml
        ];
    }

    /**
     * Parses novel page to extract metadata and chapter list.
     *
     * @param string        $url      The novel page URL.
     * @param float         $throttle Throttle time in seconds.
     * @param callable|null $log      Optional logging callback.
     * @return array Novel metadata and chapter list.
     */
    public function parseNovelPage(string $url, float $throttle, ?callable $log = null): array
    {
        if ($throttle < $this->minimumThrottle) {
            $throttle = $this->minimumThrottle;
        }

        if ($log) {
            $log("Fetching novel page: $url");
        }

        $html = $this->httpGet($url);
        [$doc, $xpath] = $this->loadDom($html);

        $novel = [
            'url'      => $url,
            'title'    => '',
            'author'   => '',
            'summary'  => '',
            'cover'    => '',
            'chapters' => []
        ];

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
        if ($coverNode) {
            $src = $coverNode->getAttribute('src');
            if ($src === null) {
                $src = '';
            }
            if ($src !== '') {
                $novel['cover'] = $this->urlJoin($url, $src);
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

        // Chapters - initial TOC block
        $seen = [];
        $partials = $this->extractPartialChapterList($doc, $url);
        foreach ($partials as $p) {
            if (!$p['url']) {
                continue;
            }
            if (isset($seen[$p['url']])) {
                continue;
            }
            $seen[$p['url']] = true;
            $novel['chapters'][] = [
                'name' => $p['name'],
                'url'  => $p['url']
            ];
        }

        // Additional TOC pages via pagination
        $tocUrls = $this->getTocPageUrls($doc, $url);
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
            $this->throttle($throttle);
            try {
                $tocHtml = $this->httpGet($tocUrl);
                [$tDoc] = $this->loadDom($tocHtml);
                $partials = $this->extractPartialChapterList($tDoc, $tocUrl);
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
                    $novel['chapters'][] = [
                        'name' => $p['name'],
                        'url'  => $p['url']
                    ];
                }
            } catch (Throwable $e) {
                if ($log) {
                    $log("Warning: failed to fetch TOC page $tocUrl – " . $e->getMessage());
                }
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

    /**
     * Imports novel and chapters to database.
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
    public function importToDb(
        PDO $pdo,
        string $url,
        int $startChapter,
        ?int $endChapter,
        float $throttle,
        bool $preserveTitles,
        ?callable $log = null
    ): int {
        $log = $log ?? function ($msg) { };

        if (!$this->isAllowedHost($url)) {
            throw new \RuntimeException("Host not allowed for FanMTL scraper: " . $url);
        }

        $log("Parsing novel page: " . $url);
        $novelData = $this->parseNovelPage($url, $throttle, $log);

        $title = $novelData['title'];
        $author = $novelData['author'];
        $summary = $novelData['summary'];
        $cover = $novelData['cover'];
        $chapters = $novelData['chapters'];

        $log("Novel: " . $title . " by " . $author);
        $log("Total chapters found: " . count($chapters));

        // Find or create novel
        $stmt = $pdo->prepare("SELECT id FROM novels WHERE title = ? AND tags LIKE ? LIMIT 1");
        $stmt->execute([$title, '%fanmtl%']);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $novelId = (int)$existing['id'];
            $log("Novel already exists with ID " . $novelId . ". Updating...");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO novels (title, cover_url, description, author, tags, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $cover, $summary, $author, 'fanmtl']);
            $novelId = (int)$pdo->lastInsertId();
            $log("Created new novel with ID " . $novelId);
        }

        // Import chapters
        $actualEnd = ($endChapter !== null && $endChapter < count($chapters)) ? $endChapter : count($chapters);
        $imported = 0;

        for ($i = $startChapter; $i <= $actualEnd; $i++) {
            $idx = $i - 1;
            if (!isset($chapters[$idx])) {
                continue;
            }

            $ch = $chapters[$idx];
            $chTitle = $preserveTitles ? $ch['name'] : $this->stripLeadingChapterPrefix($ch['name']);
            $chUrl = $ch['url'];

            $log("Fetching chapter " . $i . ": " . $chTitle);

            try {
                $chData = $this->fetchChapterContent($chUrl, $throttle);
                $content = $chData['content'];

                // Check if chapter already exists
                $stmt = $pdo->prepare("
                    SELECT id FROM chapters 
                    WHERE novel_id = ? AND order_index = ?
                ");
                $stmt->execute([$novelId, $i]);
                $existingCh = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingCh) {
                    $log("  Chapter " . $i . " already exists. Skipping.");
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO chapters (novel_id, title, content, order_index, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$novelId, $chTitle, $content, $i]);
                    $imported++;
                    $log("  Imported chapter " . $i);
                }
            } catch (Exception $e) {
                $log("  Error fetching chapter " . $i . ": " . $e->getMessage());
            }
        }

        $log("Import complete. " . $imported . " new chapters imported.");
        return $novelId;
    }
}

// Backward compatibility wrapper functions
function fmtl_fetch_chapter_content(string $url, float $throttle = 3.0): array
{
    $scraper = new FanMTLScraper();
    return $scraper->fetchChapterContent($url, $throttle);
}

function fmtl_parse_novel_page(string $url, float $throttle = 3.0, ?callable $log = null): array
{
    $scraper = new FanMTLScraper();
    return $scraper->parseNovelPage($url, $throttle, $log);
}

function fanmtl_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter,
    ?int $endChapter,
    float $throttle,
    bool $preserveTitles,
    ?callable $log = null
): int {
    $scraper = new FanMTLScraper();
    return $scraper->importToDb($pdo, $url, $startChapter, $endChapter, $throttle, $preserveTitles, $log);
}
