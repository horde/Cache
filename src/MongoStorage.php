<?php
/**
 * Copyright 2013-2021 Horde LLC (http://www.horde.org/)
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

use MongoCollection;
use InvalidArgumentException;
use MongoException;
use MongoBinData;
use Horde_Log;

/**
 *
 * Cache storage in a MongoDB database.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class MongoStorage extends BaseStorage
{
    /* Field names. */
    public const CID = 'cid';
    public const DATA = 'data';
    public const EXPIRE = 'expire';
    public const TIMESTAMP = 'ts';

    /**
     * The MongoDB Collection object for the cache data.
     *
     * @var MongoCollection
     */
    protected MongoCollection $db;

    /**
     * Constructor.
     *
     * @param array $params  Parameters:
     * <pre>
     *   - collection: (string) The collection name.
     *   - mongodb: [REQUIRED] (Horde_Mongo_Client) A MongoDB client object.
     * </pre>
     */
    public function __construct(array $params = [])
    {
        if (!isset($params['mongodb'])) {
            throw new InvalidArgumentException('Missing mongodb parameter.');
        }

        parent::__construct(array_merge([
            'collection' => 'hordecache',
        ], $params));
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        /* Only do garbage collection 0.1% of the time we create an object. */
        if (substr((string) time(), -3) === '000') {
            try {
                $this->db->remove([
                    self::EXPIRE => [
                        '$exists' => true,
                        '$lt' => time(),
                    ],
                ]);
            } catch (MongoException $e) {
                $this->logger->log($e->getMessage(), Horde_Log::DEBUG);
            }
        }
    }

    /**
     */
    protected function _initOb()
    {
        $this->db = $this->params['mongodb']->selectCollection(null, $this->params['collection']);
    }

    /**
     */
    public function get(string $key, int $lifetime = 0)
    {
        $okey = $key;
        $key = $this->_getCid($key);

        /* Build SQL query. */
        $query = [
            self::CID => $key,
        ];

        // 0 lifetime checks for objects which have no expiration
        if ($lifetime != 0) {
            $query[self::TIMESTAMP] = ['$gte' => time() - $lifetime];
        }

        try {
            $result = $this->db->findOne($query, [self::DATA => true]);
        } catch (MongoException $e) {
            $this->logger->log($e->getMessage(), Horde_Log::DEBUG);
            return false;
        }

        if (empty($result)) {
            /* No rows were found - cache miss */
            if ($this->logger) {
                $this->logger->log(sprintf('Cache miss: %s (cache ID %s)', $okey, $key), Horde_Log::DEBUG);
            }
            return false;
        }

        if ($this->logger) {
            $this->logger->log(sprintf('Cache hit: %s (cache ID %s)', $okey, $key), Horde_Log::DEBUG);
        }

        return $result[self::DATA]->bin;
    }

    /**
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        $okey = $key;
        $key = $this->_getCid($key);
        $curr = time();

        $data = [
            self::CID => $key,
            self::DATA => new MongoBinData($data, MongoBinData::BYTE_ARRAY),
            self::TIMESTAMP => $curr,
        ];

        // 0 lifetime indicates the object should not be GC'd.
        if (!empty($lifetime)) {
            $data[self::EXPIRE] = intval($lifetime) + $curr;
        }

        if ($this->logger) {
            $this->logger->log(sprintf(
                'Cache set: %s (id %s set at %s%s)',
                $okey,
                $key,
                date('r', $curr),
                (isset($data[self::EXPIRE]) ? ' expires at ' . date('r', $data[self::EXPIRE]) : '')
            ), Horde_Log::DEBUG);
        }

        // Remove any old cache data and prevent duplicate keys
        try {
            $this->db->update(
                [self::CID => $key],
                ['$set' => $data],
                ['upsert' => true, 'w' => 0]
            );
        } catch (MongoException $e) {
            $this->logger->log($e->getMessage(), Horde_Log::DEBUG);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        $okey = $key;
        $key = $this->_getCid($key);

        /* Build SQL query. */
        $query = [self::CID => $key];


        // 0 lifetime checks for objects which have no expiration
        if ($lifetime != 0) {
            $query[self::TIMESTAMP] = ['$gte' => time() - $lifetime];
        }

        try {
            $result = $this->db->findOne($query);
        } catch (MongoException $e) {
            $this->logger->log($e->getMessage(), Horde_Log::DEBUG);
            return false;
        }

        if (is_null($result)) {
            if ($this->logger) {
                $this->logger->log(sprintf('Cache exists() miss: %s (cache ID %s)', $okey, $key), Horde_Log::DEBUG);
            }
            return false;
        }

        if ($this->logger) {
            $this->logger->log(sprintf('Cache exists() hit: %s (cache ID %s)', $okey, $key), Horde_Log::DEBUG);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key): bool
    {
        $okey = $key;
        $key = $this->_getCid($key);

        try {
            $this->db->remove([self::CID => $key]);
            if ($this->logger) {
                $this->logger->log(sprintf('Cache expire: %s (cache ID %s)', $okey, $key), Horde_Log::DEBUG);
            }
            return true;
        } catch (MongoException $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->db->drop();
    }

    /**
     * Gets the cache ID for a key.
     *
     * @param string $key  The key.
     *
     * @return string  The cache ID.
     */
    protected function _getCid(string $key): string
    {
        return hash('md5', $key);
    }
}
