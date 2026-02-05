<?php
/**
 * AllNovel Scraper (Backward Compatibility Wrapper)
 *
 * This file provides backward compatibility with the old procedural interface.
 * AllNovel sites are now handled by NovelFullScraper which supports allnovel.org.
 *
 * @package    WuxiaReader
 * @subpackage Services
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 * @deprecated Use NovelFullScraper class instead (allnovel.org is included)
 */

declare(strict_types=1);

require_once __DIR__ . '/NovelFullScraper.php';

const ALLNOVEL_ALLOWED_HOSTS = [
    'allnovel.org', 'www.allnovel.org'
];

const ALLNOVEL_MINIMUM_THROTTLE = 1.0;

// All functions are now implemented in NovelFullScraper.php
// AllNovel sites use the same parsing logic as NovelFull
// This file exists only for backward compatibility
