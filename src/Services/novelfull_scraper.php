<?php
/**
 * NovelFull Scraper (Backward Compatibility Wrapper)
 *
 * This file provides backward compatibility with the old procedural interface.
 * The actual implementation is now in NovelFullScraper class.
 *
 * @package    WuxiaReader
 * @subpackage Services
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 * @deprecated Use NovelFullScraper class instead
 */

declare(strict_types=1);

require_once __DIR__ . '/NovelFullScraper.php';

const NOVELFULL_ALLOWED_HOSTS = [
    'allnovel.org', 'www.allnovel.org',
    'novelfull.com', 'www.novelfull.com',
    'novelbin.com', 'www.novelbin.com',
    // And many more - see NovelFullScraper.php for full list
];

const NOVELFULL_MINIMUM_THROTTLE = 1.0;

// All functions are now implemented in NovelFullScraper.php
// This file exists only for backward compatibility
