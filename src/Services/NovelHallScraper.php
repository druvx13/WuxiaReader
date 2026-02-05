<?php
/**
 * NovelHallScraper Class
 *
 * This scraper targets novelhall.com to import novels and chapters.
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
 * NovelHallScraper
 *
 * Scraper implementation for novelhall.com
 */
class NovelHallScraper extends AbstractScraper
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->minimumThrottle = 3.0;
        $this->userAgent = 'Mozilla/5.0 (compatible; NovelhallImporter/1.0)';
        $this->allowedHosts = [
            'novelhall.com',
            'www.novelhall.com'
        ];
    }

    /**
     * Extracts the chapter list from the novel page.
     *
     * Heuristic: Finds the `div.book-catalog` element with the most anchor tags.
     *
     * @param DOMDocument $doc     The DOMDocument of the novel page.
     * @param string      $baseUrl The base URL for resolving relative links.
     * @return array List of chapters, each as ['name' => string, 'url' => string].
     */
    private function extractChapterList(DOMDocument $doc, string $baseUrl): array
    {
        $xpath = new DOMXPath($doc);
        $catalogs = $xpath->query("//div[contains(@class,'book-catalog')]");
        $bestAnchors = [];
        $bestCount = 0;

        if ($catalogs) {
            foreach ($catalogs as $c) {
                /** @var DOMElement $c */
                $anchors = $xpath->query(".//a", $c);
                $count = $anchors ? $anchors->length : 0;
                if ($count > $bestCount) {
                    $bestAnchors = $anchors;
                    $bestCount = $count;
                }
            }
        }

        $out = [];
        if ($bestCount === 0 || !$bestAnchors) {
            return $out;
        }

        foreach ($bestAnchors as $a) {
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

        // Content: article div.entry-content
        $contentNode = $xpath->query("//article//div[contains(@class,'entry-content')]")->item(0);
        if (!$contentNode) {
            // Small fallback
            $contentNode = $xpath->query("//div[contains(@class,'entry-content')]")->item(0);
        }
        if ($contentNode) {
            $this->removeNodesByXpath($xpath, ".//*[contains(@class,'adsbox')]", $contentNode);
        }

        // Chapter title: article div.single-header h1
        $titleNode = $xpath->query("//article//div[contains(@class,'single-header')]//h1")->item(0);
        if (!$titleNode) {
            $titleNode = $xpath->query("//h1")->item(0);
        }
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
                $novel['cover'] = $this->urlJoin($url, $src);
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
            $summaryPieces = [];
            foreach ($introNodes as $node) {
                /** @var DOMElement $node */
                $txt = trim(preg_replace('/\s+/', ' ', $node->textContent));
                if ($txt !== '') {
                    $summaryPieces[] = $txt;
                }
            }
            if (!empty($summaryPieces)) {
                $novel['summary'] = implode("\n\n", $summaryPieces);
            }
        }

        // Chapters
        $novel['chapters'] = $this->extractChapterList($doc, $url);

        if ($log) {
            $log("Parsed novel title: " . ($novel['title'] ?: '(untitled)'));
            $log("Author: " . ($novel['author'] ?: '(unknown)'));
            $log("Cover URL: " . ($novel['cover'] ?: '(none)'));
            $log("Found " . count($novel['chapters']) . " chapter links on TOC.");
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
            throw new \RuntimeException("Host not allowed for NovelHall scraper: " . $url);
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
        $stmt->execute([$title, '%novelhall%']);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $novelId = (int)$existing['id'];
            $log("Novel already exists with ID " . $novelId . ". Updating...");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO novels (title, cover_url, description, author, tags, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $cover, $summary, $author, 'novelhall']);
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
function novelhall_fetch_chapter_content(string $url, float $throttle = 3.0): array
{
    $scraper = new NovelHallScraper();
    return $scraper->fetchChapterContent($url, $throttle);
}

function novelhall_parse_novel_page(string $url, float $throttle = 3.0, ?callable $log = null): array
{
    $scraper = new NovelHallScraper();
    return $scraper->parseNovelPage($url, $throttle, $log);
}

function novelhall_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter,
    ?int $endChapter,
    float $throttle,
    bool $preserveTitles,
    ?callable $log = null
): int {
    $scraper = new NovelHallScraper();
    return $scraper->importToDb($pdo, $url, $startChapter, $endChapter, $throttle, $preserveTitles, $log);
}
