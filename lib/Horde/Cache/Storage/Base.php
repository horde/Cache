<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

/**
 * The abstract implementation of the cache storage driver.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
abstract class Horde_Cache_Storage_Base implements Serializable
{
    /**
     * Logger.
     *
     * @var Horde_Log_Logger
     */
    protected $_logger;

    /**
     * Parameters.
     *
     * @var array
     */
    protected $_params = array();

    /**
     * Constructor.
     *
     * @param array $params  Configuration parameters.
     */
    public function __construct(array $params = array())
    {
        $this->_params = array_merge($this->_params, $params);
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
    public function setLogger($logger)
    {
        $this->_logger = $logger;
    }

    /**
     * Retrieve cached data.
     *
     * @param string $key        Object ID to query.
     * @param integer $lifetime  Lifetime of the object in seconds.
     *
     * @return mixed  Cached data, or false if none was found.
     */
    abstract public function get($key, $lifetime = 0);

    /**
     * Store an object in the cache.
     *
     * @param string $key        Object ID used as the caching key.
     * @param mixed $data        Data to store in the cache.
     * @param integer $lifetime  Object lifetime - i.e. the time before the
     *                           data becomes available for garbage
     *                           collection. If 0 will not be GC'd.
     */
    abstract public function set($key, $data, $lifetime = 0);

    /**
     * Checks if a given key exists in the cache, valid for the given
     * lifetime.
     *
     * @param string $key        Cache key to check.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return boolean  Existence.
     */
    abstract public function exists($key, $lifetime = 0);

    /**
     * Expire any existing data for the given key.
     *
     * @param string $key  Cache key to expire.
     *
     * @return boolean  Success or failure.
     */
    abstract public function expire($key);

    /**
     * Clears all data from the cache.
     *
     * @throws Horde_Cache_Exception
     */
    abstract public function clear();

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        return serialize(array(
            $this->_params,
            $this->_logger
        ));
    }
    public function __serialize(): array
    {
        return [
            $this->_params,
            $this->_logger
        ];
    }

    /**
     */
    public function unserialize($data)
    {
        @list($this->_params, $this->_logger) = @unserialize($data);
        $this->_initOb();
    }
    public function __unserialize(array $data): void
    {
        list($this->_params, $this->_logger) = $data;
        $this->_initOb();
    }

}
