<?php
/**
 * NovelFullScraper Class
 *
 * This scraper targets Novelfull-style sites including novelfull, allnovel, novelnext, and other clones.
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

require_once __DIR__ . '/AbstractScraper.php';

/**
 * NovelFullScraper
 *
 * Scraper implementation for novelfull.com and related clone sites
 */
class NovelFullScraper extends AbstractScraper
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->minimumThrottle = 1.0;
        $this->userAgent = 'Mozilla/5.0 (compatible; NovelfullImporter/1.0)';
        $this->allowedHosts = [
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
        ];
    }

    /**
     * Builds TOC page URL with page number.
     *
     * @param string $href Base href.
     * @param int    $i    Page number.
     * @return string The constructed URL.
     */
    private function buildTocPageUrl(string $href, int $i): string
    {
        $href = rtrim($href, '/');
        if (strpos($href, '?') !== false) {
            return $href . '&page_num=' . $i;
        }
        return $href . '?page=' . $i;
    }

    /**
     * Gets all TOC page URLs including pagination.
     *
     * @param DOMDocument $doc     The document.
     * @param string      $baseUrl Base URL.
     * @return array Array of TOC page URLs.
     */
    private function getTocPageUrls(DOMDocument $doc, string $baseUrl): array
    {
        $xpath = new DOMXPath($doc);
        $linkNode = $xpath->query("//ul[contains(@class,'pagination')]//li[contains(@class,'last')]//a")->item(0);

        if (!$linkNode instanceof DOMElement) {
            return [$baseUrl];
        }

        $href = trim($linkNode->getAttribute('href'));
        if ($href === '') {
            return [$baseUrl];
        }
        $href = $this->urlJoin($baseUrl, $href);

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
        $limit = $limit + 1;

        if ($limit <= 0) {
            return [$baseUrl];
        }

        $urls = [];
        for ($i = 1; $i <= $limit; $i++) {
            $urls[] = $this->buildTocPageUrl($href, $i);
        }
        return $urls;
    }

    /**
     * Extracts partial chapter list from a single page.
     *
     * @param DOMDocument $doc     The document.
     * @param string      $baseUrl Base URL.
     * @return array Array of chapters.
     */
    private function extractPartialChapterList(DOMDocument $doc, string $baseUrl): array
    {
        $xpath = new DOMXPath($doc);
        $links = $xpath->query("//ul[contains(@class,'list-chapter')]//a");
        $out = [];

        if (!$links) {
            return $out;
        }

        foreach ($links as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $href = $this->urlJoin($baseUrl, $href);

            $titleText = trim(preg_replace('/\s+/', ' ', $a->textContent));
            if ($titleText === '') {
                $titleText = 'Chapter';
            }

            $out[] = [
                'name' => $titleText,
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

        $contentNode = $xpath->query("//*[@id='chr-content']")->item(0);
        if (!$contentNode) {
            $contentNode = $xpath->query("//*[@id='chapter-content']")->item(0);
        }

        if ($contentNode) {
            $this->removeNodesByXpath($xpath, ".//*[contains(@class,'adsbox')]", $contentNode);
            $this->removeNodesByXpath($xpath, ".//*[contains(@class,'novel_online') or contains(@class,'unlock-buttons')]", $contentNode);
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
                $novel['cover'] = $this->urlJoin($url, $src);
            }
        }

        // Summary: div.desc-text
        $summaryNode = $xpath->query("//div[contains(@class,'desc-text')]")->item(0);
        if ($summaryNode) {
            $novel['summary'] = trim(preg_replace('/\s+/', ' ', $summaryNode->textContent));
        } else {
            // Fallback to div.info
            $infoNode = $xpath->query("//div[contains(@class,'info')]")->item(0);
            if ($infoNode) {
                $novel['summary'] = trim(preg_replace('/\s+/', ' ', $infoNode->textContent));
            }
        }

        // Get TOC pages and merge chapters
        $tocUrls = $this->getTocPageUrls($doc, $url);
        $allChapters = $this->extractPartialChapterList($doc, $url);

        // Fetch additional pages if needed
        for ($i = 1; $i < count($tocUrls); $i++) {
            $this->throttle($throttle);
            $pageHtml = $this->httpGet($tocUrls[$i]);
            [$pageDoc] = $this->loadDom($pageHtml);
            $pageChapters = $this->extractPartialChapterList($pageDoc, $tocUrls[$i]);
            $allChapters = array_merge($allChapters, $pageChapters);
        }

        $novel['chapters'] = $allChapters;

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
            throw new \RuntimeException("Host not allowed for NovelFull scraper: " . $url);
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
        $stmt->execute([$title, '%novelfull%']);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $novelId = (int)$existing['id'];
            $log("Novel already exists with ID " . $novelId . ". Updating...");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO novels (title, cover_url, description, author, tags, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $cover, $summary, $author, 'novelfull']);
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
function novelfull_fetch_chapter_content(string $url, float $throttle = 1.0): array
{
    $scraper = new NovelFullScraper();
    return $scraper->fetchChapterContent($url, $throttle);
}

function novelfull_parse_novel_page(string $url, float $throttle = 1.0): array
{
    $scraper = new NovelFullScraper();
    return $scraper->parseNovelPage($url, $throttle);
}

function novelfull_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter,
    ?int $endChapter,
    float $throttle,
    bool $preserveTitles,
    ?callable $log = null
): int {
    $scraper = new NovelFullScraper();
    return $scraper->importToDb($pdo, $url, $startChapter, $endChapter, $throttle, $preserveTitles, $log);
}

// AllNovel aliases (since AllNovel sites are included in NovelFull)
function allnovel_fetch_chapter_content(string $url, float $throttle = 1.0): array
{
    return novelfull_fetch_chapter_content($url, $throttle);
}

function allnovel_parse_novel_page(string $url, float $throttle = 1.0): array
{
    return novelfull_parse_novel_page($url, $throttle);
}

function allnovel_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter,
    ?int $endChapter,
    float $throttle,
    bool $preserveTitles,
    ?callable $log = null
): int {
    return novelfull_import_to_db($pdo, $url, $startChapter, $endChapter, $throttle, $preserveTitles, $log);
}
