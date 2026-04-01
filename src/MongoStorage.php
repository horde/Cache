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

use Horde\Log\Logger;
use MongoBinData;
use MongoCollection;
use MongoException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Horde_Mongo_Client;

/**
 * Cache storage in a MongoDB database.
 *
 * Implements both SimpleCacheStorage (PSR-16 compatible TTL model) and
 * HordeCacheStorage (per-retrieval age filtering).
 *
 * MongoDB storage tracks both creation timestamp and expiration time,
 * allowing age filtering at read time.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class MongoStorage implements SimpleCacheStorage, HordeCacheStorage
{
    /* Field names. */
    public const CID = 'cid';
    public const DATA = 'data';
    public const EXPIRE = 'expire';
    public const TIMESTAMP = 'ts';

    /**
     * The MongoDB Collection object for the cache data.
     */
    private MongoCollection $db;

    /**
     * Constructor.
     *
     * @param Horde_Mongo_Client $mongodb  MongoDB client object
     * @param LoggerInterface $logger       Logger (defaults to NullLogger)
     * @param string $collection            Collection name for cache data
     */
    public function __construct(
        private Horde_Mongo_Client $mongodb,
        private LoggerInterface $logger = new NullLogger(),
        private string $collection = 'hordecache'
    ) {
        $this->db = $this->mongodb->selectCollection(null, $this->collection);
    }

    /**
     * Destructor - garbage collection.
     */
    public function __destruct()
    {
        /* Only do garbage collection 0.1% of the time we create an object. */
        if (substr((string) time(), -3) !== '000') {
            return;
        }

        try {
            $this->db->remove([
                self::EXPIRE => [
                    '$exists' => true,
                    '$lt' => time(),
                ],
            ]);
        } catch (MongoException $e) {
            $this->logger->debug($e->getMessage());
        }
    }

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
        $cid = $this->_getCid($key);
        $timestamp = time();

        $record = [
            self::CID => $cid,
            self::DATA => new MongoBinData($data, MongoBinData::BYTE_ARRAY),
            self::TIMESTAMP => $timestamp,
        ];

        // 0 lifetime indicates the object should not be GC'd
        if ($ttl > 0) {
            $record[self::EXPIRE] = $timestamp + $ttl;
        }

        $this->logger->debug(sprintf(
            'Mongo cache set: %s (id %s set at %s%s)',
            $key,
            $cid,
            date('r', $timestamp),
            (isset($record[self::EXPIRE]) ? ' expires at ' . date('r', $record[self::EXPIRE]) : '')
        ));

        // Remove any old cache data and prevent duplicate keys
        try {
            $this->db->update(
                [self::CID => $cid],
                ['$set' => $record],
                ['upsert' => true, 'w' => 0]
            );
            return true;
        } catch (MongoException $e) {
            $this->logger->debug($e->getMessage());
            return false;
        }
    }

    /**
     * Delete cached value (PSR-16 semantics).
     *
     * @param string $key  Cache key
     * @return bool Success
     */
    public function delete(string $key): bool
    {
        $cid = $this->_getCid($key);

        try {
            $this->db->remove([self::CID => $cid]);
            $this->logger->debug(sprintf('Mongo cache delete: %s (cache ID %s)', $key, $cid));
            return true;
        } catch (MongoException $e) {
            $this->logger->debug($e->getMessage());
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
        $this->db->drop();
        $this->logger->debug('Mongo cache cleared');
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
        $cid = $this->_getCid($key);

        /* Build query */
        $query = [self::CID => $cid];

        // Add age filter if requested (0 lifetime checks for objects which have no expiration)
        if ($lifetime != 0) {
            $query[self::TIMESTAMP] = ['$gte' => time() - $lifetime];
        }

        try {
            $result = $this->db->findOne($query, [self::DATA => true]);
        } catch (MongoException $e) {
            $this->logger->debug($e->getMessage());
            return false;
        }

        if (empty($result)) {
            $this->logger->debug(sprintf('Mongo cache miss: %s (cache ID %s)', $key, $cid));
            return false;
        }

        $this->logger->debug(sprintf('Mongo cache hit: %s (cache ID %s)', $key, $cid));
        return $result[self::DATA]->bin;
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
        $cid = $this->_getCid($key);

        /* Build query */
        $query = [self::CID => $cid];

        // Add age filter if requested (0 lifetime checks for objects which have no expiration)
        if ($lifetime != 0) {
            $query[self::TIMESTAMP] = ['$gte' => time() - $lifetime];
        }

        try {
            $result = $this->db->findOne($query);
        } catch (MongoException $e) {
            $this->logger->debug($e->getMessage());
            return false;
        }

        if (is_null($result)) {
            $this->logger->debug(sprintf('Mongo cache exists() miss: %s (cache ID %s)', $key, $cid));
            return false;
        }

        $this->logger->debug(sprintf('Mongo cache exists() hit: %s (cache ID %s)', $key, $cid));
        return true;
    }

    // ========== Internal Methods ==========

    /**
     * Gets the cache ID for a key.
     *
     * @param string $key  The key
     * @return string  The cache ID (MD5 hash)
     */
    protected function _getCid(string $key): string
    {
        return hash('md5', $key);
    }
}
