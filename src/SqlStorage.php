<?php
/**
 * Copyright 2007-2021 Horde LLC (http://www.horde.org/)
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

use Horde\Cache\Cache;
use Horde_Db_Adapter;
use InvalidArgumentException;
use Horde_Db_Exception;
use Horde_Db_Value_Binary;
use Horde_Log;

/**
 * Cache storage in a SQL databsae.
 *
 * The table structure for the cache is as follows:
 * <pre>
 * CREATE TABLE hordecache (
 *     cache_id          VARCHAR(32) NOT NULL,
 *     cache_timestamp   BIGINT NOT NULL,
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
 * @copyright 2007-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class SqlStorage extends BaseStorage
{
    /**
     * Handle for the current database connection.
     *
     * @var Horde_Db_Adapter
     */
    protected $db;

    /**
     * Constructor.
     *
     * @param array $params  Parameters:
     * <pre>
     *   - db: (Horde_Db_Adapter) [REQUIRED] The DB instance.
     *   - table: (string) The name of the cache table.
     *            DEFAULT: 'hordecache'
     * </pre>
     */
    public function __construct($params = [])
    {
        if (!isset($params['db'])) {
            throw new InvalidArgumentException('Missing db parameter.');
        }

        parent::__construct(array_merge([
            'table' => 'horde_cache',
        ], $params));
    }

    /**
     * @inheritDoc
     */
    protected function _initOb()
    {
        $this->db = $this->params['db'];
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        /* Only do garbage collection 0.1% of the time we create an object. */
        if (substr((string) time(), -3) !== '000') {
            return;
        }

        $query = 'DELETE FROM ' . $this->params['table'] .
                 ' WHERE cache_expiration < ? AND cache_expiration <> 0';
        $values = [time()];

        try {
            $this->db->delete($query, $values);
        } catch (Horde_Db_Exception $e) {
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        $okey = $key;
        $key = hash('md5', $key);

        $timestamp = time();
        $maxage = $timestamp - $lifetime;

        /* Build SQL query. */
        $query = 'SELECT cache_data FROM ' . $this->params['table'] .
                 ' WHERE cache_id = ?';
        $values = [$key];

        // 0 lifetime checks for objects which have no expiration
        if ($lifetime != 0) {
            $query .= ' AND cache_timestamp >= ?';
            $values[] = $maxage;
        }

        try {
            $result = $this->db->selectValue($query, $values);
            $columns = $this->db->columns($this->params['table']);
        } catch (Horde_Db_Exception $e) {
            return false;
        }

        if (!$result) {
            /* No rows were found - cache miss */
            if ($this->logger) {
                $this->logger->log(sprintf('Cache miss: %s (Id %s newer than %d)', $okey, $key, $maxage), Horde_Log::DEBUG);
            }
            return false;
        }

        if ($this->logger) {
            $this->logger->log(sprintf('Cache hit: %s (Id %s newer than %d)', $okey, $key, $maxage), Horde_Log::DEBUG);
        }

        return $columns['cache_data']->binaryToString($result);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        $okey = $key;
        $key = hash('md5', $key);

        $timestamp = time();

        // 0 lifetime indicates the object should not be GC'd.
        $expiration = ($lifetime === 0)
            ? 0
            : ($lifetime + $timestamp);

        if ($this->logger) {
            $this->logger->log(sprintf('Cache set: %s (Id %s set at %d expires at %d)', $okey, $key, $timestamp, $expiration), Horde_Log::DEBUG);
        }

        // Remove any old cache data and prevent duplicate keys
        $query = 'DELETE FROM ' . $this->params['table'] . ' WHERE cache_id = ?';
        $values = [$key];
        try {
            $this->db->delete($query, $values);
        } catch (Horde_Db_Exception $e) {
        }

        /* Build SQL query. */
        $values = [
            'cache_id' => $key,
            'cache_timestamp' => $timestamp,
            'cache_expiration' => $expiration,
            'cache_data' => new Horde_Db_Value_Binary($data),
        ];

        try {
            $this->db->insertBlob($this->params['table'], $values);
        } catch (Horde_Db_Exception $e) {
            throw new Exception($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        $okey = $key;
        $key = hash('md5', $key);

        /* Build SQL query. */
        $query = 'SELECT 1 FROM ' . $this->params['table'] .
                 ' WHERE cache_id = ?';
        $values = [$key];

        // 0 lifetime checks for objects which have no expiration
        if ($lifetime != 0) {
            $query .= ' AND cache_timestamp >= ?';
            $values[] = time() - $lifetime;
        }

        try {
            $result = $this->db->selectValue($query, $values);
        } catch (Horde_Db_Exception $e) {
            return false;
        }

        $timestamp = time();
        if (empty($result)) {
            if ($this->logger) {
                $this->logger->log(sprintf('Cache exists() miss: %s (Id %s newer than %d)', $okey, $key, $timestamp), Horde_Log::DEBUG);
            }
            return false;
        }

        if ($this->logger) {
            $this->logger->log(sprintf('Cache exists() hit: %s (Id %s newer than %d)', $okey, $key, $timestamp), Horde_Log::DEBUG);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key): bool
    {
        $key = hash('md5', $key);

        $query = 'DELETE FROM ' . $this->params['table'] .
                 ' WHERE cache_id = ?';
        $values = [$key];

        try {
            $this->db->delete($query, $values);
        } catch (Horde_Db_Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $query = 'DELETE FROM ' . $this->params['table'];

        try {
            $this->db->delete($query);
        } catch (Horde_Db_Exception $e) {
            throw new Exception($e);
        }
    }
}
