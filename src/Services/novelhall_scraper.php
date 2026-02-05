<?php
/**
 * NovelHall Scraper (Backward Compatibility Wrapper)
 *
 * This file provides backward compatibility with the old procedural interface.
 * The actual implementation is now in NovelHallScraper class.
 *
 * @package    WuxiaReader
 * @subpackage Services
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 * @deprecated Use NovelHallScraper class instead
 */

declare(strict_types=1);

require_once __DIR__ . '/NovelHallScraper.php';

const NOVELHALL_ALLOWED_HOSTS = [
    'novelhall.com',
    'www.novelhall.com'
];

const NOVELHALL_MINIMUM_THROTTLE = 3.0;

// All functions are now implemented in NovelHallScraper.php
// This file exists only for backward compatibility
