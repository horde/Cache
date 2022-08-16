<?php
/**
 * Copyright 2010-2021 Horde LLC (http://www.horde.org/)
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

use Horde\Cache\CacheStorage;
use Horde_Log_Logger;
use Horde\Cache\Exception;
use serialize;
use unserialize;

/**
 * The abstract implementation of the cache storage driver.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
abstract class BaseStorage implements CacheStorage
{
    /**
     * Logger.
     *
     * @var ?Horde_Log_Logger
     */
    protected $logger;

    /**
     * Parameters.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Constructor.
     *
     * @param array $params  Configuration parameters.
     */
    public function __construct(array $params = [])
    {
        $this->params = array_merge($this->params, $params);
        $this->_initOb();
    }

    /**
     * Do initialization tasks.
     */
    protected function _initOb()
    {
    }

    /**
     * Set the logging object.
     *
     * @param Horde_Log_Logger $logger  Log object.
     */
    public function setLogger(Horde_Log_Logger $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Retrieve cached data.
     *
     * @param string $key        Object ID to query.
     * @param integer $lifetime  Lifetime of the object in seconds.
     *
     * @return mixed  Cached data, or false if none was found.
     */
    abstract public function get(string $key, int $lifetime = 0);

    /**
     * Store an object in the cache.
     *
     * @param string $key        Object ID used as the caching key.
     * @param mixed $data        Data to store in the cache.
     * @param integer $lifetime  Object lifetime - i.e. the time before the
     *                           data becomes available for garbage
     *                           collection. If 0 will not be GC'd.
     */
    abstract public function set(string $key, $data, int $lifetime = 0);

    /**
     * Checks if a given key exists in the cache, valid for the given
     * lifetime.
     *
     * @param string $key        Cache key to check.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return bool  Existence.
     */
    abstract public function exists(string $key, int $lifetime = 0): bool;

    /**
     * Expire any existing data for the given key.
     *
     * @param string $key  Cache key to expire.
     *
     * @return boolean  Success or failure.
     */
    abstract public function expire(string $key): bool;

    /**
     * Clears all data from the cache.
     *
     * @throws Exception
     */
    abstract public function clear();

    /* Serializable methods and PHP 7.4+ equivalents */

    public function serialize(): string
    {
        return serialize([
            $this->params,
            $this->logger,
        ]);
    }

    public function unserialize($data)
    {
        @[$this->params, $this->logger] = @unserialize($data);
        $this->_initOb();
    }

    public function __serialize(): array
    {
        return [
            $this->params,
            $this->logger,
        ];
    }

    public function __unserialize(array $data): void
    {
        @[$this->params, $this->logger] = $data;
        $this->_initOb();
    }
}
