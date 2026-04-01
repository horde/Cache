<?php

declare(strict_types=1);

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

/**
 * Cache storage in PHP memory.
 *
 * Persists only during a script run and ignores the object lifetime.
 * Implements SimpleCacheStorage (PSR-16 compatible).
 *
 * No logger needed - in-memory cache is trivial.
 *
 * @author    Gunnar Wrobel <wrobel@pardus.de>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 * @since     2.5.0
 */
class MemoryStorage implements SimpleCacheStorage
{
    /**
     * Constructor.
     */
    public function __construct(
        private array $cache = []
    ) {}

    /**
     * Get cached value.
     *
     * @param string $key  Cache key
     * @return mixed|false Value or false if not found
     */
    public function get(string $key)
    {
        return $this->cache[$key] ?? false;
    }

    /**
     * Check if value exists.
     *
     * @param string $key  Cache key
     * @return bool True if exists
     */
    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    /**
     * Store value.
     *
     * @param string $key   Cache key
     * @param mixed $data   Data to store
     * @param int $ttl      TTL in seconds (ignored - in-memory cache has no expiration)
     * @return bool Success
     */
    public function set(string $key, mixed $data, int $ttl): bool
    {
        $this->cache[$key] = $data;
        return true;
    }

    /**
     * Delete value.
     *
     * @param string $key  Cache key
     * @return bool Success
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    /**
     * Clear all values.
     *
     * @return bool Success
     */
    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }
}
