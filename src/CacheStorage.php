<?php
/**
 * Copyright 2010-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <lang@b1-systems.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
declare(strict_types=1);

namespace Horde\Cache;

use Horde_Log_Logger;
use Serializable;
use serialize;
use unserialize;

/**
 * The interface of the cache storage driver.
 *
 * @author    Ralf Lang <lang@b1-systems.de>
 * @category  Horde
 * @copyright 2010-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
interface CacheStorage extends Serializable
{
    /**
     * Set the logging object.
     *
     * @param Horde_Log_Logger $logger  Log object.
     */
    public function setLogger(Horde_Log_Logger $logger): void;

    /**
     * Retrieve cached data.
     *
     * @param string $key        Object ID to query.
     * @param integer $lifetime  Lifetime of the object in seconds.
     *
     * @return mixed  Cached data, or false if none was found.
     */
    public function get(string $key, int $lifetime = 0);

    /**
     * Store an object in the cache.
     *
     * @param string $key        Object ID used as the caching key.
     * @param mixed $data        Data to store in the cache.
     * @param integer $lifetime  Object lifetime - i.e. the time before the
     *                           data becomes available for garbage
     *                           collection. If 0 will not be GC'd.
     */
    public function set(string $key, $data, int $lifetime = 0);

    /**
     * Checks if a given key exists in the cache, valid for the given
     * lifetime.
     *
     * @param string $key        Cache key to check.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return bool  Existence.
     */
    public function exists(string $key, int $lifetime = 0): bool;

    /**
     * Expire any existing data for the given key.
     *
     * @param string $key  Cache key to expire.
     *
     * @return bool  Success or failure.
     */
    public function expire(string $key): bool;

    /**
     * Clears all data from the cache.
     *
     * @throws Exception
     */
    public function clear();
}
