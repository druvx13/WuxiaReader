<?php
/**
 * ReadNovelFullScraper Class
 *
 * This scraper imports novels and chapters from readnovelfull.com.
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
 * ReadNovelFullScraper
 *
 * Scraper implementation for readnovelfull.com
 */
class ReadNovelFullScraper extends AbstractScraper
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->minimumThrottle = 1.0;
        $this->userAgent = 'Mozilla/5.0 (compatible; ReadNovelFullImporter/1.0)';
        $this->allowedHosts = [
            'readnovelfull.com',
            'www.readnovelfull.com'
        ];
    }

    /**
     * Extracts chapter list. If initial list is empty, fetches via AJAX using novelId.
     *
     * @param DOMDocument $doc     The document to parse.
     * @param DOMXPath    $xpath   The XPath object.
     * @param string      $baseUrl The base URL for resolving relative links.
     * @return array Array of chapters with 'name' and 'url' keys.
     */
    private function getAllChapters(DOMDocument $doc, DOMXPath $xpath, string $baseUrl): array
    {
        $out = [];

        // Try to find existing ul.list-chapter
        $links = $xpath->query("//ul[contains(@class,'list-chapter')]//a");
        if ($links && $links->length > 0) {
            foreach ($links as $a) {
                /** @var DOMElement $a */
                $href = trim($a->getAttribute('href'));
                if ($href === '') {
                    continue;
                }
                $href = $this->urlJoin($baseUrl, $href);
                $titleText = trim(preg_replace('/\s+/', ' ', $a->textContent));
                $out[] = ['name' => $titleText, 'url' => $href];
            }
        }

        // If list is found, return it
        if (!empty($out)) {
            return $out;
        }

        // Fallback: Fetch via AJAX using novelId from div#rating
        $ratingDiv = $xpath->query("//div[@id='rating']")->item(0);
        if ($ratingDiv instanceof DOMElement) {
            $novelId = $ratingDiv->getAttribute('data-novel-id');
            if ($novelId) {
                $ajaxUrl = "https://readnovelfull.com/ajax/chapter-archive?novelId=" . urlencode($novelId);
                try {
                    $html = $this->httpGet($ajaxUrl);
                    [$ajaxDoc, $ajaxXpath] = $this->loadDom($html);
                    $ajaxLinks = $ajaxXpath->query("//ul[contains(@class,'list-chapter')]//a");
                    if ($ajaxLinks) {
                        foreach ($ajaxLinks as $a) {
                            /** @var DOMElement $a */
                            $href = trim($a->getAttribute('href'));
                            if ($href === '') {
                                continue;
                            }
                            $href = $this->urlJoin($baseUrl, $href);
                            $titleText = trim(preg_replace('/\s+/', ' ', $a->textContent));
                            $out[] = ['name' => $titleText, 'url' => $href];
                        }
                    }
                } catch (Exception $e) {
                    // Ignore AJAX failure
                }
            }
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

        // Find content: div#chr-content
        $contentNode = $xpath->query("//div[@id='chr-content']")->item(0);
        if ($contentNode) {
            $this->removeNodesByXpath($xpath, ".//*[contains(@class,'adsbox')]", $contentNode);
        }

        // Find chapter title: a.chr-title text
        $titleNode = $xpath->query("//a[contains(@class,'chr-title')]")->item(0);
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
        if ($titleNode) {
            $novel['title'] = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
        }

        // Author: ul.info li:nth-of-type(2) a
        $authorNode = $xpath->query("//ul[contains(@class,'info')]/li[2]//a")->item(0);
        if ($authorNode) {
            $novel['author'] = trim(preg_replace('/\s+/', ' ', $authorNode->textContent));
        } else {
            // Fallback: look for "Author:" label
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
            $srcVal = $coverNode->getAttribute('src');
            if ($srcVal) {
                $novel['cover'] = $this->urlJoin($url, $srcVal);
            }
        }

        // Summary: div.desc-text
        $summaryNode = $xpath->query("//div[contains(@class,'desc-text')]")->item(0);
        if ($summaryNode) {
            $novel['summary'] = trim(preg_replace('/\s+/', ' ', $summaryNode->textContent));
        }

        // Get chapters
        $novel['chapters'] = $this->getAllChapters($doc, $xpath, $url);

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
            throw new \RuntimeException("Host not allowed for ReadNovelFull scraper: " . $url);
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
        $stmt->execute([$title, '%readnovelfull%']);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $novelId = (int)$existing['id'];
            $log("Novel already exists with ID " . $novelId . ". Updating...");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO novels (title, cover_url, description, author, tags, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $cover, $summary, $author, 'readnovelfull']);
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
function readnovelfull_fetch_chapter_content(string $url, float $throttle = 1.0): array
{
    $scraper = new ReadNovelFullScraper();
    return $scraper->fetchChapterContent($url, $throttle);
}

function readnovelfull_parse_novel_page(string $url, float $throttle = 1.0): array
{
    $scraper = new ReadNovelFullScraper();
    return $scraper->parseNovelPage($url, $throttle);
}

function readnovelfull_import_to_db(
    PDO $pdo,
    string $url,
    int $startChapter,
    ?int $endChapter,
    float $throttle,
    bool $preserveTitles,
    ?callable $log = null
): int {
    $scraper = new ReadNovelFullScraper();
    return $scraper->importToDb($pdo, $url, $startChapter, $endChapter, $throttle, $preserveTitles, $log);
}
