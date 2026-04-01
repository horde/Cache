<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use Horde_HashTable_Base;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Cache storage using the Horde_HashTable interface.
 *
 * Implements both SimpleCacheStorage (PSR-16 compatible TTL model) and
 * HordeCacheStorage (per-retrieval age filtering).
 *
 * HashTable storage tracks both creation timestamp and expiration time,
 * allowing age filtering at read time.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 * @since     2.2.0
 */
class HashtableStorage implements SimpleCacheStorage, HordeCacheStorage
{
    /**
     * Constructor.
     *
     * @param Horde_HashTable_Base $hashtable  HashTable instance
     * @param LoggerInterface $logger          Logger (defaults to NullLogger)
     * @param string $prefix                   Key prefix for namespacing
     */
    public function __construct(
        private Horde_HashTable_Base $hashtable,
        private LoggerInterface $logger = new NullLogger(),
        private string $prefix = ''
    ) {}

    // ========== SimpleCacheStorage Interface (PSR-16 Compatible) ==========

    /**
     * Get cached value (PSR-16 semantics).
     *
     * Checks expiration only, no age filtering.
     *
     * @param string $key  Cache key
     * @return mixed|false Value or false if not found/expired
     */
    public function get(string $key)
    {
        // Delegate to Horde method with lifetime=0 (no age filtering)
        return $this->getWithLifetime($key, 0);
    }

    /**
     * Check existence (PSR-16 semantics).
     *
     * @param string $key  Cache key
     * @return bool True if exists and not expired
     */
    public function has(string $key): bool
    {
        return $this->hasWithLifetime($key, 0);
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
        $opts = ['expire' => $ttl];

        // Store data and timestamp
        $this->hashtable->set($this->_getKey($key), $data, $opts);
        $this->hashtable->set($this->_getKey($key, true), (string) time(), $opts);

        $this->logger->debug(sprintf('HashTable cache set: %s (ttl=%d)', $key, $ttl));
        return true;
    }

    /**
     * Delete cached value (PSR-16 semantics).
     *
     * @param string $key  Cache key
     * @return bool Success
     */
    public function delete(string $key): bool
    {
        $result = (bool) $this->hashtable->delete([
            $this->_getKey($key),
            $this->_getKey($key, true),
        ]);

        $this->logger->debug(sprintf('HashTable cache delete: %s', $key));
        return $result;
    }

    /**
     * Clear all cached values (PSR-16 semantics).
     *
     * @return bool Success
     */
    public function clear(): bool
    {
        $this->hashtable->clear();
        $this->logger->debug('HashTable cache cleared');
        return true;
    }

    // ========== HordeCacheStorage Interface (Age Filtering) ==========

    /**
     * Get cached value with per-retrieval age filtering (Horde semantics).
     *
     * @param string $key       Cache key
     * @param int $lifetime     Only return if cached within last N seconds (0 = no age check)
     * @return mixed|false      Value or false if not found/too old
     */
    public function getWithLifetime(string $key, int $lifetime)
    {
        if (!$this->hasWithLifetime($key, $lifetime)) {
            return false;
        }

        $dkey = $this->_getKey($key);
        $res = $this->hashtable->get([$dkey]);

        return $res[$dkey] ?? false;
    }

    /**
     * Check existence with per-retrieval age filtering (Horde semantics).
     *
     * @param string $key       Cache key
     * @param int $lifetime     Only return true if cached within last N seconds (0 = no age check)
     * @return bool             True if exists and not too old
     */
    public function hasWithLifetime(string $key, int $lifetime): bool
    {
        $lkey = null;
        $dkey = $this->_getKey($key);
        $query = [$dkey];

        if ($lifetime) {
            $query[] = $lkey = $this->_getKey($key, true);
        }

        $res = $this->hashtable->get($query);

        // Check if data exists
        if (!isset($res[$dkey]) || $res[$dkey] === false) {
            return false;
        }

        // Check age filter if requested
        if ($lifetime && $lkey) {
            if (!isset($res[$lkey]) || (($lifetime + (int) $res[$lkey]) < time())) {
                return false;
            }
        }

        return true;
    }

    // ========== Internal Methods ==========

    /**
     * Return the hashtable key.
     *
     * @param string $key  Object ID
     * @param bool $ts     Return the timestamp key?
     * @return string  Hashtable key ID
     */
    protected function _getKey(string $key, bool $ts = false): string
    {
        return $this->prefix . $key . ($ts ? '_t' : '');
    }
}
