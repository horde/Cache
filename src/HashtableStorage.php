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

use Horde_HashTable_Base;
use InvalidArgumentException;

/**
 * Cache storage using the Horde_HashTable interface.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 * @since     2.2.0
 */
class HashtableStorage extends BaseStorage
{
    /**
     * HashTable object.
     *
     * @var Horde_HashTable_Base
     */
    protected $hash;

    /**
     * @param array $params  Additional parameters:
     * <pre>
     *   - hashtable: (Horde_HashTable) [REQUIRED] A Horde_HashTable object.
     *   - prefix: (string) The prefix to use for the cache keys.
     *             DEFAULT: ''
     * </pre>
     */
    public function __construct(array $params = [])
    {
        if (!isset($params['hashtable'])) {
            throw new InvalidArgumentException('Missing hashtable parameter.');
        }

        parent::__construct(array_merge([
            'prefix' => '',
        ], $params));
    }

    /**
     */
    protected function _initOb()
    {
        $this->hash = $this->params['hashtable'];
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        $lkey = null;
        $dkey = $this->_getKey($key);
        $query = [$dkey];
        if ($lifetime) {
            $query[] = $lkey = $this->_getKey($key, true);
        }

        $res = $this->hash->get($query);

        if ($lifetime && $lkey &&
            (!$res[$lkey] || (($lifetime + $res[$lkey]) < time()))) {
            return false;
        }

        return $res[$dkey];
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        // What is this? array_filter without further arguments?
        /*$opts = array_filter([
            'expire' => $lifetime
        ]);*/
        $opts  = ['expire' => $lifetime];

        $this->hash->set($this->_getKey($key), $data, $opts);
        $this->hash->set($this->_getKey($key, true), (string) time(), $opts);
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
        return (bool) $this->hash->delete([
            $this->_getKey($key),
            $this->_getKey($key, true),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->hash->clear();
    }

    /**
     * Return the hashtable key.
     *
     * @param string $key  Object ID.
     * @param boolean $ts  Return the timestamp key?
     *
     * @return string  Hashtable key ID.
     */
    protected function _getKey(string $key, bool $ts = false): string
    {
        return $this->params['prefix'] . $key . ($ts ? '_t' : '');
    }
}
