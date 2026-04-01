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
 * PSR-16 compatible storage backend interface.
 *
 * This is the minimal interface that all storage backends must implement.
 * Expiration is set once at write time, checked at read time (TTL model).
 * No per-retrieval age filtering.
 *
 * Storage implementations are "minimal" - just CRUD operations on native backends.
 * No validation, no compression, no namespace handling - facade handles those.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
interface SimpleCacheStorage
{
    /**
     * Get cached value.
     *
     * Returns value if exists and not expired (based on TTL set at write time).
     *
     * @param string $key  Cache key (pre-validated, pre-namespaced by facade)
     * @return mixed|false Value (possibly compressed) or false if not found/expired
     */
    public function get(string $key);

    /**
     * Check if value exists and not expired.
     *
     * @param string $key  Cache key (pre-validated, pre-namespaced by facade)
     * @return bool True if exists and not expired
     */
    public function has(string $key): bool;

    /**
     * Store value with TTL.
     *
     * @param string $key   Cache key (pre-validated, pre-namespaced by facade)
     * @param mixed $data   Data to store (pre-compressed by facade if enabled)
     * @param int $ttl      Seconds until expiration (0 = never expire)
     * @return bool Success
     */
    public function set(string $key, mixed $data, int $ttl): bool;

    /**
     * Delete cached value.
     *
     * @param string $key  Cache key (pre-validated, pre-namespaced by facade)
     * @return bool Success
     */
    public function delete(string $key): bool;

    /**
     * Clear all cached values.
     *
     * @return bool Success
     */
    public function clear(): bool;
}
