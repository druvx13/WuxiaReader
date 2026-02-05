<?php
/**
 * ReadNovelFull Scraper (Backward Compatibility Wrapper)
 *
 * This file provides backward compatibility with the old procedural interface.
 * The actual implementation is now in ReadNovelFullScraper class.
 *
 * @package    WuxiaReader
 * @subpackage Services
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 * @deprecated Use ReadNovelFullScraper class instead
 */

declare(strict_types=1);

require_once __DIR__ . '/ReadNovelFullScraper.php';

const READNOVELFULL_ALLOWED_HOSTS = [
    'readnovelfull.com', 'www.readnovelfull.com'
];

const READNOVELFULL_MINIMUM_THROTTLE = 1.0;

// All functions are now implemented in ReadNovelFullScraper.php
// This file exists only for backward compatibility
