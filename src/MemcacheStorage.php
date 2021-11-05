<?php
/**
 * Copyright 2006-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Duck <duck@obala.net>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use Horde_Memcache;
use InvalidArgumentException;

/**
 * Cache storage on a memcache installation.
 *
 * @author     Duck <duck@obala.net>
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2006-2021 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Cache
 * @deprecated Use HashTable driver instead.
 */
class MemcacheStorage extends BaseStorage
{
    /**
     * Cache results of exists()/get() calls (since we will get the entire
     * object on an exists() call anyway).
     *
     * @var array
     */
    protected array $objectcache = [];

    /**
     * Memcache object.
     *
     * @var Horde_Memcache
     */
    protected Horde_Memcache $memcache;

    /**
     * Construct a new Horde_Cache_Memcache object.
     *
     * @param array $params  Parameter array:
     * <pre>
     *   - memcache: (Horde_Memcache) [REQUIRED] A Horde_Memcache object.
     *   - prefix: (string) The prefix to use for the cache keys.
     *             DEFAULT: ''
     * </pre>
     */
    public function __construct(array $params = [])
    {
        if (!isset($params['memcache'])) {
            if (isset($params['hashtable'])) {
                $params['memcache'] = $params['hashtable'];
            } else {
                throw new InvalidArgumentException('Missing memcache object');
            }
        }

        parent::__construct(array_merge([
            'prefix' => '',
        ], $params));
    }

    /**
     * @inheritDoc
     */
    protected function _initOb()
    {
        $this->memcache = $this->params['memcache'];
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        $original_key = $key;
        $key = $this->params['prefix'] . $key;
        if (isset($this->objectcache[$key])) {
            return $this->objectcache[$key];
        }

        $key_list = [$key];
        if (!empty($lifetime)) {
            $key_list[] = $key . '_e';
        }

        $res = $this->memcache->get($key_list);

        if ($res === false) {
            return $this->objectcache[$key] = false;
        }

        // If we can't find the expire time, assume we have exceeded it.
        if (empty($lifetime) ||
            (($res[$key . '_e'] !== false) &&
             ($res[$key . '_e'] + $lifetime > time()))) {
            $this->objectcache[$key] = $res[$key];
        } else {
            $this->expire($original_key);
            return false;
        }

        return $res[$key];
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        $key = $this->params['prefix'] . $key;

        if ($this->memcache->set($key . '_e', (string) time(), $lifetime) !== false) {
            $this->memcache->set($key, $data, $lifetime);
            unset($this->objectcache[$key]);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        return ($this->get($key, $lifetime) !== false);
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key): bool
    {
        $key = $this->params['prefix'] . $key;
        $this->objectcache[$key] = false;
        $this->memcache->delete($key . '_e');

        return $this->memcache->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->memcache->flush();
        $this->objectcache = [];
    }
}
