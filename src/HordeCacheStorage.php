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
 * Horde-specific storage backend interface with per-retrieval age filtering.
 *
 * This is an OPTIONAL extension interface that only SQL/File backends can
 * efficiently implement (requires storing creation timestamp separately
 * from expiration time).
 *
 * Redis, Memcache, APCu cannot implement this - they don't store creation
 * timestamps, only expiration times.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
interface HordeCacheStorage
{
    /**
     * Get cached value with per-retrieval age filtering.
     *
     * This is Horde's unique feature: filter by age at READ time, not just
     * expiration. Only return value if it was cached within last $lifetime
     * seconds, even if TTL hasn't expired yet.
     *
     * @param string $key       Cache key (pre-validated, pre-namespaced by facade)
     * @param int $lifetime     Only return if cached within last N seconds (0 = no age check, use expiration only)
     * @return mixed|false      Value (possibly compressed) or false if not found/too old
     */
    public function getWithLifetime(string $key, int $lifetime);

    /**
     * Check existence with per-retrieval age filtering.
     *
     * @param string $key       Cache key (pre-validated, pre-namespaced by facade)
     * @param int $lifetime     Only return true if cached within last N seconds (0 = no age check, use expiration only)
     * @return bool             True if exists and not too old
     */
    public function hasWithLifetime(string $key, int $lifetime): bool;
}
