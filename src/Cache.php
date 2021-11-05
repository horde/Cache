<?php
/**
 * Copyright 1999-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Anil Madhavapeddy <anil@recoil.org>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use Horde_Compress_Fast;
use Horde_Log_Logger;

/**
 * This class provides the API interface to the cache storage drivers.
 *
 * @author    Anil Madhavapeddy <anil@recoil.org>
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 1999-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class Cache
{
    /**
     * Cache parameters.
     *
     * @var array
     */
    protected $params = [
        'compress' => false,
        'lifetime' => 86400,
    ];

    /**
     * Logger.
     *
     * @var Horde_Log_Logger
     */
    protected $logger;

    /**
     * Storage object.
     *
     * @var CacheStorage
     */
    protected $storage;

    /**
     * Constructor.
     *
     * @param CacheStorage $storage  The storage object.
     * @param array $params                 Parameter array:
     * <pre>
     *   - compress: (boolean) Compress data (if possible)?
     *               DEFAULT: false
     *   - lifetime: (integer) Lifetime of data, in seconds.
     *               DEFAULT: 86400 seconds
     *   - logger: (Horde_Log_Logger) Log object to use for log/debug messages.
     * </pre>
     */
    public function __construct(
        CacheStorage $storage,
        array $params = []
    )
    {
        if (isset($params['logger'])) {
            $this->logger = $params['logger'];
            unset($params['logger']);

            $storage->setLogger($this->logger);
        }

        $this->params = array_merge($this->params, $params);
        $this->storage = $storage;
    }

    /**
     * Attempts to directly output a cached object.
     *
     * @todo Change default lifetime to 0
     *
     * @param string $key        Object ID to query.
     * @param integer $lifetime  Lifetime of the object in seconds.
     *
     * @return bool  True if output or false if no object was found.
     */
    public function output(string $key, int $lifetime = 1): bool
    {
        $data = $this->get($key, $lifetime);
        if ($data === false) {
            return false;
        }

        echo $data;
        return true;
    }

    /**
     * Retrieve cached data.
     *
     * @todo Change default lifetime to 0
     *
     * @param string $key        Object ID to query.
     * @param integer $lifetime  Lifetime of the object in seconds.
     *
     * @return mixed  Cached data, or false if none was found.
     */
    public function get(string $key, int $lifetime = 1)
    {
        $res = $this->storage->get($key, $lifetime);

        if (empty($this->params['compress']) || !is_string($res)) {
            return $res;
        }

        $compress = new Horde_Compress_Fast();
        return $compress->decompress($res);
    }

    /**
     * Store an object in the cache.
     *
     * @param string $key        Object ID used as the caching key.
     * @param string $data       Data to store in the cache.
     * @param integer $lifetime  Object lifetime - i.e. the time before the
     *                           data becomes available for garbage
     *                           collection, in seconds.  If null use the
     *                           default Horde GC time.  If 0 will not be GC'd.
     */
    public function set(string $key, $data, ?int $lifetime = null)
    {
        if (!empty($this->params['compress'])) {
            $compress = new Horde_Compress_Fast();
            $data = $compress->compress($data);
        }

        $lifetime = is_null($lifetime)
            ? $this->params['lifetime']
            : $lifetime;

        $this->storage->set($key, $data, $lifetime);
    }

    /**
     * Checks if a given key exists in the cache, valid for the given
     * lifetime.
     *
     * @param string $key        Cache key to check.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return bool  Existence.
     */
    public function exists(string $key, int $lifetime = 0)
    {
        return $this->storage->exists($key, $lifetime);
    }

    /**
     * Expire any existing data for the given key.
     *
     * @param string $key  Cache key to expire.
     *
     * @return bool  Success or failure.
     */
    public function expire(string $key): bool
    {
        return $this->storage->expire($key);
    }

    /**
     * Clears all data from the cache.
     *
     * @throws Exception
     */
    public function clear()
    {
        return $this->storage->clear();
    }

    /**
     * Tests the driver for read/write access.
     *
     * @return boolean  True if read/write is available.
     */
    public function testReadWrite(): bool
    {
        $key = '__hordecache_testkey';

        try {
            $this->storage->set($key, 1);
            if ($this->storage->exists($key)) {
                $this->storage->expire($key);
                return true;
            }
        } catch (\Exception $e) {
        }

        return false;
    }
}
