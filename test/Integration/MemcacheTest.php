<?php

/**
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache\Test\Integration;

use Horde\Cache\Cache;
use Horde\Cache\MemcacheStorage;
use Horde\Cache\Test\TestBase;
use Horde\Memcache\MemcacheApi;
use Exception;

/**
 * This class tests the Memcache backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 * @coversNothing
 */
class MemcacheTest extends TestBase
{
    protected function _getCache($params = [])
    {
        if (!class_exists('Horde\Memcache\MemcacheApi')) {
            $this->reason = 'Horde\Memcache\MemcacheApi not installed';
            return;
        }
        if (!(extension_loaded('memcache') || extension_loaded('memcached'))) {
            $this->reason = 'Memcache extension not loaded';
            return;
        }
        if (!($config = self::getConfig('CACHE_MEMCACHE_TEST_CONFIG'))
            || !isset($config['cache']['memcache'])) {
            $this->reason = 'Memcache configuration not available.';
            return;
        }

        try {
            $memcacheApi = new MemcacheApi($config['cache']['memcache']);
        } catch (Exception $e) {
            $this->reason = 'Cannot connect to memcached: ' . $e->getMessage();
            return;
        }

        return new Cache(
            new MemcacheStorage(
                memcache: $memcacheApi,
                prefix: 'horde_cache_test'
            )
        );
    }
}
