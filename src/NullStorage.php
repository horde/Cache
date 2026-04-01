<?php

declare(strict_types=1);

/**
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Duck <duck@obala.net>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Null cache storage driver.
 *
 * This storage always misses on reads and silently accepts writes.
 * Useful for testing or disabling caching.
 *
 * @author    Duck <duck@obala.net>
 * @category  Horde
 * @copyright 2006-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class NullStorage implements SimpleCacheStorage
{
    /**
     * Constructor.
     *
     * @param LoggerInterface $logger  Logger (defaults to NullLogger)
     */
    public function __construct(
        private LoggerInterface $logger = new NullLogger()
    ) {}

    /**
     * Get cached value (PSR-16 semantics).
     *
     * Always returns false (cache miss).
     *
     * @param string $key  Cache key
     * @return false Always returns false
     */
    public function get(string $key)
    {
        $this->logger->debug(sprintf('Null cache get: %s (always miss)', $key));
        return false;
    }

    /**
     * Check existence (PSR-16 semantics).
     *
     * Always returns false.
     *
     * @param string $key  Cache key
     * @return bool Always returns false
     */
    public function has(string $key): bool
    {
        return false;
    }

    /**
     * Store value with TTL (PSR-16 semantics).
     *
     * Silently accepts but doesn't store data.
     *
     * @param string $key   Cache key
     * @param mixed $data   Data to store (ignored)
     * @param int $ttl      Seconds until expiration (ignored)
     * @return bool Always returns true
     */
    public function set(string $key, mixed $data, int $ttl): bool
    {
        $this->logger->debug(sprintf('Null cache set: %s (no-op)', $key));
        return true;
    }

    /**
     * Delete cached value (PSR-16 semantics).
     *
     * Silently accepts but has nothing to delete.
     *
     * @param string $key  Cache key
     * @return bool Always returns true
     */
    public function delete(string $key): bool
    {
        $this->logger->debug(sprintf('Null cache delete: %s (no-op)', $key));
        return true;
    }

    /**
     * Clear all cached values (PSR-16 semantics).
     *
     * Silently accepts but has nothing to clear.
     *
     * @return bool Always returns true
     */
    public function clear(): bool
    {
        $this->logger->debug('Null cache clear (no-op)');
        return true;
    }
}
