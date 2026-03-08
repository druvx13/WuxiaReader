<?php
// src/Services/test_generic_scraper.php
// CLI script to test GenericParser with mock data

require_once __DIR__ . '/generic_scraper.php';

// Mock PDO
class MockPDO extends PDO {
    public function __construct() {}
    public function beginTransaction(): bool { echo "[DB] Transaction Started\n"; return true; }
    public function commit(): bool { echo "[DB] Transaction Committed\n"; return true; }
    public function rollBack(): bool { echo "[DB] Transaction Rolled Back\n"; return true; }
    public function prepare(string $query, array $options = []): PDOStatement {
        return new MockPDOStatement($query);
    }
    public function lastInsertId(?string $name = null): string { return "123"; }
}

class MockPDOStatement extends PDOStatement {
    private $query;
    public function __construct($query) { $this->query = $query; }
    public function execute(?array $params = null): bool {
        echo "[DB] Executing: " . $this->query . "\n";
        if ($params) {
            echo "     Params: " . json_encode($params) . "\n";
        }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed { return false; } // simulate no existing novel
}

// Subclass GenericParser to mock HTTP requests
class TestGenericParser extends GenericParser {
    public function httpGet(string $url): string {
        echo "[HTTP] GET $url\n";
        if (strpos($url, 'chapter-1') !== false) {
            return '<html><head><title>Chapter 1</title></head>
            <body>
                <h1>Chapter 1: The Beginning</h1>
                <div class="content">
                    <p>This is the story.</p>
                    <p>It was a dark and stormy night.</p>
                    <div class="adsbox">Ad content</div>
                    <a href="chapter-2.html">Next Chapter</a>
                </div>
            </body></html>';
        }
        if (strpos($url, 'novel-page') !== false) {
            return '<html><head><title>My Great Novel</title></head>
            <body>
                <h1>My Great Novel</h1>
                <div class="description">A story about testing.</div>
                <div class="chapters">
                    <a href="chapter-1.html">Chapter 1</a>
                    <a href="chapter-2.html">Chapter 2</a>
                </div>
            </body></html>';
        }
        return '<html><body>Empty</body></html>';
    }
}

// Run test
echo "Running GenericParser Test...\n";
$pdo = new MockPDO();
$parser = new TestGenericParser($pdo, function($msg) { echo "[Log] $msg\n"; });

// Test Import Novel
try {
    $parser->importNovel('http://example.com/novel-page');
    echo "\nTest Completed Successfully.\n";
} catch (Exception $e) {
    echo "\nTest Failed: " . $e->getMessage() . "\n";
}
