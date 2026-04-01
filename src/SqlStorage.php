<?php

declare(strict_types=1);

/**
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ben Klang <ben@alkaloid.net>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use Horde_Db_Adapter;
use Horde_Db_Exception;
use Horde_Db_Value_Binary;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Cache storage in a SQL database.
 *
 * Implements both SimpleCacheStorage (PSR-16 compatible TTL model) and
 * HordeCacheStorage (per-retrieval age filtering).
 *
 * The table structure for the cache is as follows:
 * <pre>
 * CREATE TABLE horde_cache (
 *     cache_id          VARCHAR(32) NOT NULL,
 *     cache_timestamp   BIGINT NOT NULL,
 *     cache_expiration  BIGINT NOT NULL,
 *     cache_data        LONGBLOB,
 *     (Or on PostgreSQL:)
 *     cache_data        TEXT,
 *     (Or on some other DBMS systems:)
 *     cache_data        IMAGE,
 *
 *     PRIMARY KEY (cache_id)
 * );
 * </pre>
 *
 * @author    Ben Klang <ben@alkaloid.net>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2007-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class SqlStorage implements SimpleCacheStorage, HordeCacheStorage
{
    /**
     * Constructor.
     *
     * @param Horde_Db_Adapter $db      Database adapter
     * @param LoggerInterface $logger   Logger (defaults to NullLogger)
     * @param string $table             Cache table name
     */
    public function __construct(
        private Horde_Db_Adapter $db,
        private LoggerInterface $logger = new NullLogger(),
        private string $table = 'horde_cache'
    ) {}

    /**
     * Destructor - garbage collection.
     */
    public function __destruct()
    {
        /* Only do garbage collection 0.1% of the time we create an object. */
        if (substr((string) time(), -3) !== '000') {
            return;
        }

        $query = 'DELETE FROM ' . $this->table
                 . ' WHERE cache_expiration < ? AND cache_expiration <> 0';
        $values = [time()];

        try {
            $this->db->delete($query, $values);
        } catch (Horde_Db_Exception $e) {
        }
    }

    // ========== SimpleCacheStorage Interface (PSR-16 Compatible) ==========

    /**
     * Get cached value (PSR-16 semantics).
     *
     * Checks expiration only, no age filtering.
     *
     * @param string $key  Cache key (pre-validated by facade)
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
     * @param string $key  Cache key (pre-validated by facade)
     * @return bool True if exists and not expired
     */
    public function has(string $key): bool
    {
        return $this->hasWithLifetime($key, 0);
    }

    /**
     * Store value with TTL (PSR-16 semantics).
     *
     * @param string $key   Cache key (pre-validated by facade)
     * @param mixed $data   Data to store
     * @param int $ttl      Seconds until expiration (0 = never)
     * @return bool Success
     */
    public function set(string $key, mixed $data, int $ttl): bool
    {
        // Hash key for SQL storage (for index performance)
        $hashedKey = hash('md5', $key);

        $timestamp = time();
        $expiration = ($ttl === 0) ? 0 : ($timestamp + $ttl);

        $this->logger->debug(sprintf(
            'Cache set: %s set at %d expires at %d',
            $key,
            $timestamp,
            $expiration
        ));

        // Remove old cache data
        $query = 'DELETE FROM ' . $this->table . ' WHERE cache_id = ?';
        try {
            $this->db->delete($query, [$hashedKey]);
        } catch (Horde_Db_Exception $e) {
        }

        // Insert new data
        $values = [
            'cache_id' => $hashedKey,
            'cache_timestamp' => $timestamp,
            'cache_expiration' => $expiration,
            'cache_data' => new Horde_Db_Value_Binary($data),
        ];

        try {
            $this->db->insertBlob($this->table, $values);
            return true;
        } catch (Horde_Db_Exception $e) {
            return false;
        }
    }

    /**
     * Delete cached value (PSR-16 semantics).
     *
     * @param string $key  Cache key (pre-validated by facade)
     * @return bool Success
     */
    public function delete(string $key): bool
    {
        $hashedKey = hash('md5', $key);
        $query = 'DELETE FROM ' . $this->table . ' WHERE cache_id = ?';

        try {
            $this->db->delete($query, [$hashedKey]);
            return true;
        } catch (Horde_Db_Exception $e) {
            return false;
        }
    }

    /**
     * Clear all cached values (PSR-16 semantics).
     *
     * @return bool Success
     */
    public function clear(): bool
    {
        $query = 'DELETE FROM ' . $this->table;

        try {
            $this->db->delete($query);
            return true;
        } catch (Horde_Db_Exception $e) {
            return false;
        }
    }

    // ========== HordeCacheStorage Interface (Age Filtering) ==========

    /**
     * Get cached value with per-retrieval age filtering (Horde semantics).
     *
     * @param string $key       Cache key (pre-validated by facade)
     * @param int $lifetime     Only return if cached within last N seconds (0 = no age check)
     * @return mixed|false      Value or false if not found/too old
     */
    public function getWithLifetime(string $key, int $lifetime)
    {
        $hashedKey = hash('md5', $key);
        $timestamp = time();
        $maxage = $timestamp - $lifetime;

        // Build query
        $query = 'SELECT cache_data FROM ' . $this->table . ' WHERE cache_id = ?';
        $values = [$hashedKey];

        // Add age filter if requested
        if ($lifetime != 0) {
            $query .= ' AND cache_timestamp >= ?';
            $values[] = $maxage;
        }

        // Check expiration (always)
        $query .= ' AND (cache_expiration = 0 OR cache_expiration > ?)';
        $values[] = $timestamp;

        try {
            $result = $this->db->selectValue($query, $values);
            $columns = $this->db->columns($this->table);
        } catch (Horde_Db_Exception $e) {
            return false;
        }

        if (!$result) {
            $this->logger->debug(sprintf(
                'Cache miss: %s (newer than %d)',
                $key,
                $maxage
            ));
            return false;
        }

        $this->logger->debug(sprintf(
            'Cache hit: %s (newer than %d)',
            $key,
            $maxage
        ));

        return $columns['cache_data']->binaryToString($result);
    }

    /**
     * Check existence with per-retrieval age filtering (Horde semantics).
     *
     * @param string $key       Cache key (pre-validated by facade)
     * @param int $lifetime     Only return true if cached within last N seconds (0 = no age check)
     * @return bool             True if exists and not too old
     */
    public function hasWithLifetime(string $key, int $lifetime): bool
    {
        $hashedKey = hash('md5', $key);
        $timestamp = time();
        $maxage = $timestamp - $lifetime;

        // Build query
        $query = 'SELECT 1 FROM ' . $this->table . ' WHERE cache_id = ?';
        $values = [$hashedKey];

        // Add age filter if requested
        if ($lifetime != 0) {
            $query .= ' AND cache_timestamp >= ?';
            $values[] = $maxage;
        }

        // Check expiration (always)
        $query .= ' AND (cache_expiration = 0 OR cache_expiration > ?)';
        $values[] = $timestamp;

        try {
            $result = $this->db->selectValue($query, $values);
        } catch (Horde_Db_Exception $e) {
            return false;
        }

        if (empty($result)) {
            $this->logger->debug(sprintf(
                'Cache exists() miss: %s (newer than %d)',
                $key,
                $maxage
            ));
            return false;
        }

        $this->logger->debug(sprintf(
            'Cache exists() hit: %s (newer than %d)',
            $key,
            $maxage
        ));

        return true;
    }
}
