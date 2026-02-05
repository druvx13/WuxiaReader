<?php
/**
 * FanMTL Scraper (Backward Compatibility Wrapper)
 *
 * This file provides backward compatibility with the old procedural interface.
 * The actual implementation is now in FanMTLScraper class.
 *
 * @package    WuxiaReader
 * @subpackage Services
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 * @deprecated Use FanMTLScraper class instead
 */

declare(strict_types=1);

require_once __DIR__ . '/FanMTLScraper.php';

const FMTL_ALLOWED_HOSTS = [
    'fannovel.com', 'www.fannovel.com',
    'fanmtl.com', 'www.fanmtl.com',
    'readwn.com', 'www.readwn.com',
    // And many more - see FanMTLScraper.php for full list
];

const FMTL_MINIMUM_THROTTLE = 3.0;

// All functions are now implemented in FanMTLScraper.php
// This file exists only for backward compatibility
