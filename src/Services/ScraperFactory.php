<?php
/**
 * ScraperFactory Class
 *
 * Factory class for creating scraper instances.
 * Provides centralized scraper instantiation with lazy loading.
 *
 * @package    WuxiaReader
 * @subpackage Services
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 */

namespace App\Services;

use RuntimeException;

/**
 * ScraperFactory
 *
 * Creates and manages scraper instances
 */
class ScraperFactory
{
    /**
     * @var array Mapping of source names to scraper classes
     */
    private static array $scraperMap = [
        'readnovelfull' => 'ReadNovelFullScraper',
        'novelhall'     => 'NovelHallScraper',
        'novelfull'     => 'NovelFullScraper',
        'allnovel'      => 'NovelFullScraper', // Uses same scraper as novelfull
        'fanmtl'        => 'FanMTLScraper',
    ];

    /**
     * @var array Cache of instantiated scrapers
     */
    private static array $instances = [];

    /**
     * Creates or retrieves a scraper instance for the given source.
     *
     * @param string $source The source name (e.g., 'fanmtl', 'novelhall').
     * @return AbstractScraper The scraper instance.
     * @throws RuntimeException If the source is not supported.
     */
    public static function create(string $source): AbstractScraper
    {
        $source = strtolower($source);

        if (!isset(self::$scraperMap[$source])) {
            throw new RuntimeException("Unsupported scraper source: " . $source);
        }

        // Return cached instance if available
        if (isset(self::$instances[$source])) {
            return self::$instances[$source];
        }

        $className = self::$scraperMap[$source];
        $fullClassName = 'App\\Services\\' . $className;

        // Load the class file if not already loaded
        $classFile = __DIR__ . '/' . $className . '.php';
        if (!class_exists($fullClassName) && file_exists($classFile)) {
            require_once $classFile;
        }

        if (!class_exists($fullClassName)) {
            throw new RuntimeException("Scraper class not found: " . $fullClassName);
        }

        // Create and cache the instance
        $instance = new $fullClassName();
        self::$instances[$source] = $instance;

        return $instance;
    }

    /**
     * Gets the minimum throttle time for a source.
     *
     * @param string $source The source name.
     * @return float The minimum throttle time in seconds.
     */
    public static function getMinimumThrottle(string $source): float
    {
        $scraper = self::create($source);
        return $scraper->minimumThrottle ?? 1.0;
    }

    /**
     * Checks if a source is supported.
     *
     * @param string $source The source name.
     * @return bool True if supported, false otherwise.
     */
    public static function isSupported(string $source): bool
    {
        return isset(self::$scraperMap[strtolower($source)]);
    }

    /**
     * Gets list of all supported sources.
     *
     * @return array Array of source names.
     */
    public static function getSupportedSources(): array
    {
        return array_keys(self::$scraperMap);
    }

    /**
     * Clears the instance cache.
     * Useful for testing or forcing fresh instances.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }
}
