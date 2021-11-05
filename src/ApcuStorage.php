<?php
/**
 * Copyright 2006-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Duck <duck@obala.net>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use apcu_fetch;
use apcu_store;
use apcu_clear_cache;
use time;
use apcu_delete;

/**
 * Cache storage in the Alternative PHP Cache (APC) or APCu.
 *
 * @author    Duck <duck@obala.net>
 * @category  Horde
 * @copyright 2006-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class ApcuStorage extends BaseStorage
{
    /**
     * Constructor.
     *
     * @param array $params  Optional parameters:
     * <pre>
     *   - prefix: (string) The prefix to use for the cache keys.
     *             DEFAULT: ''
     * </pre>
     */
    public function __construct(array $params = [])
    {
        parent::__construct(array_merge(['prefix' => ''], $params));
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        $key = $this->params['prefix'] . $key;
        $this->_setExpire($key, $lifetime);
        return apcu_fetch($key);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        $key = $this->params['prefix'] . $key;
        if (apcu_store($key . '_expire', time(), $lifetime)) {
            apcu_store($key, $data, $lifetime);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        $key = $this->params['prefix'] . $key;
        $this->_setExpire($key, $lifetime);
        return (apcu_fetch($key) !== false);
    }

    /**
     */
    public function expire(string $key): bool
    {
        $key = $this->params['prefix'] . $key;
        apcu_delete($key . '_expire');
        return apcu_delete($key);
    }

    /**
     */
    public function clear()
    {
        if (!apcu_clear_cache()) {
            throw new Exception('Clearing APCu cache failed');
        }
    }

    /**
     * Set expire time on each call since APC sets it on cache creation.
     *
     * @param string $key        Cache key to expire.
     * @param integer $lifetime  Lifetime of the data in seconds.
     */
    protected function _setExpire(string $key, int $lifetime)
    {
        if ($lifetime == 0) {
            // Don't expire.
            return;
        }

        $expire = apcu_fetch($key . '_expire');

        // Set prune period.
        if ($expire + $lifetime < time()) {
            // Expired
            apcu_delete($key);
            apcu_delete($key . '_expire');
        }
    }
}
