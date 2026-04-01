<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Cache;

/**
 * Horde-specific cache interface with per-retrieval age filtering.
 *
 * This interface defines Horde's unique cache methods that extend beyond
 * PSR-16. The Cache facade implements both PSR-16 CacheInterface and this
 * HordeCacheInterface, using the dual interface pattern like Memcache.
 *
 * Methods with "WithLifetime" suffix support per-retrieval age filtering,
 * only available when storage backend implements HordeCacheStorage interface
 * (SQL, File - not Redis/Memcache/APCu).
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
interface HordeCacheInterface
{
    /**
     * Get cached value with per-retrieval age filtering.
     *
     * Only returns value if it was cached within last $lifetime seconds,
     * even if TTL hasn't expired yet.
     *
     * @param string $key       Cache key
     * @param int $lifetime     Only return if cached within last N seconds (0 = ignore age, use expiration only)
     * @return mixed|false      Cached data or false if not found/too old
     * @throws \Psr\SimpleCache\InvalidArgumentException  If key is invalid
     * @throws UnsupportedOperationException              If storage doesn't support age filtering
     */
    public function getWithLifetime(string $key, int $lifetime);

    /**
     * Check existence with per-retrieval age filtering.
     *
     * @param string $key       Cache key
     * @param int $lifetime     Only return true if cached within last N seconds (0 = ignore age)
     * @return bool             True if exists and not too old
     * @throws \Psr\SimpleCache\InvalidArgumentException  If key is invalid
     * @throws UnsupportedOperationException              If storage doesn't support age filtering
     */
    public function hasWithLifetime(string $key, int $lifetime): bool;

    /**
     * Get cache entry lifetime.
     *
     * Returns the number of seconds until expiration, or false if not found.
     *
     * @param string $key  Cache key
     * @return int|false   Seconds until expiration, or false if not found
     * @throws \Psr\SimpleCache\InvalidArgumentException  If key is invalid
     */
    public function lifetime(string $key);
}
