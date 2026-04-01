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
 * Cache storage in the Alternative PHP Cache (APCu).
 *
 * This is a minimal adapter over APCu native functions. APCu is PSR-16
 * compatible with TTL-based expiration, so this storage does NOT support
 * HordeCacheStorage (per-retrieval age filtering).
 *
 * @author    Duck <duck@obala.net>
 * @category  Horde
 * @copyright 2006-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class ApcuStorage implements SimpleCacheStorage
{
    /**
     * Constructor.
     *
     * @param LoggerInterface $logger  Logger (defaults to NullLogger)
     * @param string $prefix           Key prefix for namespacing
     */
    public function __construct(
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

        $this->logger->debug(sprintf('APCu get: %s', $key));

        $result = apcu_fetch($prefixedKey);

        // Convert false to false (APCu returns false on miss)
        return $result;
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

        $this->logger->debug(sprintf('APCu set: %s (ttl=%d)', $key, $ttl));

        // APCu is PSR-16 native
        return apcu_store($prefixedKey, $data, $ttl);
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

        $this->logger->debug(sprintf('APCu delete: %s', $key));

        return apcu_delete($prefixedKey);
    }

    /**
     * Clear all cached values (PSR-16 semantics).
     *
     * @return bool Success
     */
    public function clear(): bool
    {
        $this->logger->debug('APCu clear all');

        // Flush entire APCu cache
        return apcu_clear_cache();
    }
}
