<?php

declare(strict_types=1);

/**
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Duck <duck@obala.net>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use Horde\Memcache\MemcacheApi;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Cache storage on a memcache installation.
 *
 * This is a minimal adapter over Horde\Memcache\MemcacheApi, which is itself
 * PSR-16 compliant. We simply delegate to it with optional key prefixing.
 *
 * @author     Duck <duck@obala.net>
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2006-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Cache
 * @deprecated Use HashTable driver instead.
 */
class MemcacheStorage implements SimpleCacheStorage
{
    /**
     * Constructor.
     *
     * @param MemcacheApi $memcache     Memcache API instance
     * @param LoggerInterface $logger   Logger (defaults to NullLogger)
     * @param string $prefix            Key prefix for namespacing
     */
    public function __construct(
        private MemcacheApi $memcache,
        private LoggerInterface $logger = new NullLogger(),
        private string $prefix = ''
    ) {}

    /**
     * Get cached value (PSR-16 semantics).
     *
     * @param string $key  Cache key
     * @return mixed|false Value or false if not found/expired
     */
    public function get(string $key)
    {
        $prefixedKey = $this->prefix . $key;

        $this->logger->debug(sprintf('Memcache get: %s', $key));

        // MemcacheApi is PSR-16 native, returns null on miss
        $result = $this->memcache->get($prefixedKey);

        // Convert null to false for storage interface compatibility
        return $result ?? false;
    }

    /**
     * Check existence (PSR-16 semantics).
     *
     * @param string $key  Cache key
     * @return bool True if exists and not expired
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== false;
    }

    /**
     * Store value with TTL (PSR-16 semantics).
     *
     * @param string $key   Cache key
     * @param mixed $data   Data to store
     * @param int $ttl      Seconds until expiration (0 = never)
     * @return bool Success
     */
    public function set(string $key, mixed $data, int $ttl): bool
    {
        $prefixedKey = $this->prefix . $key;

        $this->logger->debug(sprintf('Memcache set: %s (ttl=%d)', $key, $ttl));

        // MemcacheApi is PSR-16 native
        return $this->memcache->set($prefixedKey, $data, $ttl);
    }

    /**
     * Delete cached value (PSR-16 semantics).
     *
     * @param string $key  Cache key
     * @return bool Success
     */
    public function delete(string $key): bool
    {
        $prefixedKey = $this->prefix . $key;

        $this->logger->debug(sprintf('Memcache delete: %s', $key));

        return $this->memcache->delete($prefixedKey);
    }

    /**
     * Clear all cached values (PSR-16 semantics).
     *
     * @return bool Success
     */
    public function clear(): bool
    {
        $this->logger->debug('Memcache clear all');

        // Flush entire memcache server
        return $this->memcache->clear();
    }
}
