<?php

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

use Horde\Cache\HashtableStorage;
use Horde\HashTable\HashTable;

/**
 * Cache storage using a HashTable transport.
 *
 * Accepts either:
 *   - A modern Horde\HashTable\HashTable (preferred). The class delegates to
 *     {@see HashtableStorage} for the actual storage operations and supports
 *     phpredis as well as Predis.
 *   - A legacy Horde_HashTable_Base instance (deprecated path). Operates on
 *     the legacy interface for backward compatibility while consumers
 *     migrate.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 * @since     2.2.0
 */
class Horde_Cache_Storage_Hashtable extends Horde_Cache_Storage_Base
{
    /**
     * HashTable object (legacy path).
     *
     * @var Horde_HashTable|null
     */
    protected $_hash;

    /**
     * Modern PSR-4 storage delegate, set when a modern HashTable is provided.
     *
     * @var HashtableStorage|null
     */
    protected ?HashtableStorage $_modern = null;

    /**
     * @param array $params  Additional parameters:
     * <pre>
     *   - hashtable: (Horde_HashTable|HashTable) [REQUIRED] HashTable instance.
     *                Modern Horde\HashTable\HashTable is preferred; legacy
     *                Horde_HashTable_Base is accepted for BC.
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
        $ht = $this->_params['hashtable'];

        if ($ht instanceof HashTable) {
            $this->_modern = new HashtableStorage(
                hashtable: $ht,
                prefix: (string) $this->_params['prefix'],
            );
            return;
        }

        $this->_hash = $ht;
    }

    /**
     */
    public function get($key, $lifetime = 0)
    {
        if ($this->_modern !== null) {
            return $this->_modern->getWithLifetime((string) $key, (int) $lifetime);
        }

        $dkey = $this->_getKey($key);
        $query = [$dkey];
        if ($lifetime) {
            $query[] = $lkey = $this->_getKey($key, true);
        }

        $res = $this->_hash->get($query);

        if ($lifetime
            && (!$res[$lkey] || (($lifetime + $res[$lkey]) < time()))) {
            return false;
        }

        return $res[$dkey];
    }

    /**
     */
    public function set($key, $data, $lifetime = 0)
    {
        if ($this->_modern !== null) {
            $this->_modern->set((string) $key, $data, (int) $lifetime);
            return;
        }

        $opts = array_filter([
            'expire' => $lifetime,
        ]);

        $this->_hash->set($this->_getKey($key), $data, $opts);
        $this->_hash->set($this->_getKey($key, true), time(), $opts);
    }

    /**
     */
    public function exists($key, $lifetime = 0)
    {
        if ($this->_modern !== null) {
            return $this->_modern->hasWithLifetime((string) $key, (int) $lifetime);
        }

        return ($this->get($key, $lifetime) !== false);
    }

    /**
     */
    public function expire($key)
    {
        if ($this->_modern !== null) {
            $this->_modern->delete((string) $key);
            return;
        }

        $this->_hash->delete([
            $this->_getKey($key),
            $this->_getKey($key, true),
        ]);
    }

    /**
     */
    public function clear()
    {
        if ($this->_modern !== null) {
            $this->_modern->clear();
            return;
        }

        $this->_hash->clear();
    }

    /**
     * Return the hashtable key.
     *
     * @param string $key  Object ID.
     * @param boolean $ts  Return the timestamp key?
     *
     * @return string  Hashtable key ID.
     */
    protected function _getKey($key, $ts = false)
    {
        return $this->_params['prefix'] . $key . ($ts ? '_t' : '');
    }
}
