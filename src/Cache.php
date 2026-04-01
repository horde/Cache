<?php

declare(strict_types=1);

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Anil Madhavapeddy <anil@recoil.org>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use DateInterval;
use Horde\Compress\Fast\CompressFast;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use DateTime;
use Exception;

/**
 * Smart cache facade implementing PSR-16 Simple Cache + Horde extensions.
 *
 * This is the "smart" layer that provides:
 * - PSR-16 compliance (validation, batch operations, exception handling)
 * - Cross-cutting concerns (compression, namespace, default lifetime)
 * - Horde extensions (per-retrieval age filtering when storage supports it)
 *
 * Storage backends are "minimal" - just CRUD operations.
 *
 * @author    Anil Madhavapeddy <anil@recoil.org>
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 1999-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class Cache implements CacheInterface, HordeCacheInterface
{
    /**
     * Cache parameters.
     */
    protected array $params = [
        'compress' => false,
        'lifetime' => 86400,
        'namespace' => '',
    ];

    /**
     * Storage object.
     */
    protected SimpleCacheStorage $storage;

    /**
     * Constructor.
     *
     * @param SimpleCacheStorage $storage  The storage backend
     * @param array $params                Parameter array:
     * <pre>
     *   - compress: (boolean) Compress data? DEFAULT: false
     *   - lifetime: (integer) Default TTL in seconds. DEFAULT: 86400
     *   - namespace: (string) Key prefix for isolation. DEFAULT: ''
     * </pre>
     */
    public function __construct(
        SimpleCacheStorage $storage,
        array $params = []
    ) {
        $this->params = array_merge($this->params, $params);
        $this->storage = $storage;
    }

    // ========== PSR-16 CacheInterface Methods ==========

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache
     * @param mixed  $default Default value to return if the key does not exist
     * @return mixed The value of the item from the cache, or $default
     * @throws InvalidArgumentException If the $key string is not a legal value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        $storageKey = $this->applyNamespace($key);

        $data = $this->storage->get($storageKey);
        if ($data === false) {
            return $default;
        }

        return $this->decompress($data);
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store
     * @param mixed                  $value The value of the item to store
     * @param null|int|DateInterval  $ttl   Optional. The TTL value of this item
     * @return bool True on success and false on failure
     * @throws InvalidArgumentException If the $key string is not a legal value
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->validateKey($key);

        // Convert DateInterval to seconds
        if ($ttl instanceof DateInterval) {
            $ttl = $this->dateIntervalToSeconds($ttl);
        }

        // PSR-16: ttl=0 means delete immediately
        if ($ttl !== null && $ttl <= 0) {
            return $this->delete($key);
        }

        // Apply default lifetime
        $ttl ??= $this->params['lifetime'];

        // Compress if enabled
        $data = $this->compress($value);

        // Apply namespace
        $storageKey = $this->applyNamespace($key);

        return $this->storage->set($storageKey, $data, $ttl);
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete
     * @return bool True if the item was successfully removed. False if there was an error.
     * @throws InvalidArgumentException If the $key string is not a legal value
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
        $storageKey = $this->applyNamespace($key);

        return $this->storage->delete($storageKey);
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure
     */
    public function clear(): bool
    {
        return $this->storage->clear();
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can be obtained in a single operation
     * @param mixed    $default Default value to return for keys that do not exist
     * @return iterable A list of key => value pairs
     * @throws InvalidArgumentException If $keys is neither an array nor a Traversable
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation
     * @param null|int|DateInterval  $ttl    Optional. The TTL value of this item
     * @return bool True on success and false on failure
     * @throws InvalidArgumentException If $values is neither an array nor a Traversable
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            $success = $this->set((string) $key, $value, $ttl) && $success;
        }
        return $success;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted
     * @return bool True if the items were successfully removed. False if there was an error.
     * @throws InvalidArgumentException If $keys is neither an array nor a Traversable
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }
        return $success;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key The cache item key
     * @return bool
     * @throws InvalidArgumentException If the $key string is not a legal value
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);
        $storageKey = $this->applyNamespace($key);

        return $this->storage->has($storageKey);
    }

    // ========== HordeCacheInterface Methods ==========

    /**
     * Get cached value with per-retrieval age filtering.
     *
     * This is Horde's unique feature: filter by age at READ time.
     * Only works when storage implements HordeCacheStorage.
     *
     * @param string $key       Cache key
     * @param int $lifetime     Only return if cached within last N seconds (0 = no age filter)
     * @return mixed|false      Cached data or false if not found/too old
     * @throws InvalidArgumentException If the $key string is not a legal value
     * @throws UnsupportedOperationException If storage doesn't support age filtering
     */
    public function getWithLifetime(string $key, int $lifetime)
    {
        $this->validateKey($key);

        if (!$this->storage instanceof HordeCacheStorage) {
            throw new UnsupportedOperationException(
                'getWithLifetime() requires storage backend with timestamp support. '
                . 'Redis, Memcache, and APCu do not support per-retrieval age filtering.'
            );
        }

        $storageKey = $this->applyNamespace($key);
        $data = $this->storage->getWithLifetime($storageKey, $lifetime);

        if ($data === false) {
            return false;
        }

        return $this->decompress($data);
    }

    /**
     * Check existence with per-retrieval age filtering.
     *
     * @param string $key       Cache key
     * @param int $lifetime     Only return true if cached within last N seconds (0 = no age filter)
     * @return bool             True if exists and not too old
     * @throws InvalidArgumentException If the $key string is not a legal value
     * @throws UnsupportedOperationException If storage doesn't support age filtering
     */
    public function hasWithLifetime(string $key, int $lifetime): bool
    {
        $this->validateKey($key);

        if (!$this->storage instanceof HordeCacheStorage) {
            throw new UnsupportedOperationException(
                'hasWithLifetime() requires storage backend with timestamp support.'
            );
        }

        $storageKey = $this->applyNamespace($key);
        return $this->storage->hasWithLifetime($storageKey, $lifetime);
    }

    /**
     * Get cache entry lifetime.
     *
     * Returns the number of seconds until expiration, or false if not found.
     *
     * @param string $key  Cache key
     * @return int|false   Seconds until expiration, or false if not found
     * @throws InvalidArgumentException If the $key string is not a legal value
     */
    public function lifetime(string $key)
    {
        // This would require storage backends to expose expiration time
        // Not currently implemented - would need storage interface extension
        throw new UnsupportedOperationException(
            'lifetime() method not yet implemented'
        );
    }

    // ========== Helper Methods (Smart Facade Logic) ==========

    /**
     * Validate cache key according to PSR-16 rules.
     *
     * @throws InvalidArgumentException If key is invalid
     */
    protected function validateKey(string $key): void
    {
        if ($key === '') {
            throw new CacheInvalidArgumentException('Cache key cannot be empty');
        }

        if (strlen($key) > 64) {
            throw new CacheInvalidArgumentException('Cache key exceeds 64 characters');
        }

        if (preg_match('/[{}()\\/\\\\@:]/', $key)) {
            throw new CacheInvalidArgumentException(
                'Cache key contains reserved characters: {}()/\@:'
            );
        }
    }

    /**
     * Apply namespace prefix to key.
     */
    protected function applyNamespace(string $key): string
    {
        if ($this->params['namespace'] !== '') {
            return $this->params['namespace'] . ':' . $key;
        }
        return $key;
    }

    /**
     * Compress data if compression enabled.
     */
    protected function compress(mixed $data): mixed
    {
        if (!$this->params['compress'] || !is_string($data)) {
            return $data;
        }

        $compress = new CompressFast();
        return $compress->compress($data);
    }

    /**
     * Decompress data if compression enabled.
     */
    protected function decompress(mixed $data): mixed
    {
        if (!$this->params['compress'] || !is_string($data)) {
            return $data;
        }

        $compress = new CompressFast();
        return $compress->decompress($data);
    }

    /**
     * Convert DateInterval to seconds.
     */
    protected function dateIntervalToSeconds(DateInterval $interval): int
    {
        // Create reference points
        $reference = new DateTime();
        $endTime = (clone $reference)->add($interval);

        return $endTime->getTimestamp() - $reference->getTimestamp();
    }

    // ========== Legacy Methods (Deprecated) ==========

    /**
     * @deprecated Use get() or getWithLifetime() instead
     */
    public function cacheGet(string $key, int $lifetime = 1)
    {
        if ($lifetime === 1 || $lifetime === 0) {
            // Use PSR-16 method
            return $this->get($key, false);
        }

        // Use Horde method
        return $this->getWithLifetime($key, $lifetime);
    }

    /**
     * @deprecated Use set() instead
     */
    public function cacheSet(string $key, mixed $data, ?int $lifetime = null): void
    {
        $this->set($key, $data, $lifetime);
    }

    /**
     * @deprecated Use has() or hasWithLifetime() instead
     */
    public function cacheExists(string $key, int $lifetime = 0): bool
    {
        if ($lifetime === 0) {
            return $this->has($key);
        }
        return $this->hasWithLifetime($key, $lifetime);
    }

    /**
     * @deprecated Use delete() instead
     */
    public function cacheExpire(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * @deprecated Use clear() instead
     */
    public function cacheClear(): bool
    {
        return $this->clear();
    }

    /**
     * @deprecated Use has() instead
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        if ($lifetime === 0) {
            return $this->has($key);
        }
        return $this->hasWithLifetime($key, $lifetime);
    }

    /**
     * @deprecated Use delete() instead
     */
    public function expire(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * Attempts to directly output a cached object.
     *
     * @deprecated Use get() and echo instead
     */
    public function output(string $key, int $lifetime = 1): bool
    {
        $data = $this->cacheGet($key, $lifetime);
        if ($data === false) {
            return false;
        }

        echo $data;
        return true;
    }

    /**
     * Tests the driver for read/write access.
     */
    public function testReadWrite(): bool
    {
        $key = '__hordecache_testkey';

        try {
            $this->set($key, 1, 60);
            if ($this->has($key)) {
                $this->delete($key);
                return true;
            }
        } catch (Exception $e) {
        }

        return false;
    }
}
