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

namespace Horde\Cache\Test\Unit;

use Horde\Cache\Cache;
use Horde\Cache\MemoryStorage;
use Horde\Test\TestCase;

/**
 * This class tests the memory backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 * @coversNothing
 */
class MemoryTest extends TestCase
{
    private Cache $cache;

    protected function setUp(): void
    {
        $this->cache = $this->_getCache();
    }

    protected function _getCache($params = [])
    {
        return new Cache(
            new MemoryStorage()
        );
    }

    /**
     * The Memory backend doesn't support lifetimes, so cannot test these like
     * in the TestBase class.
     */
    public function testExists()
    {
        $this->assertFalse($this->cache->exists('key1', 0));
        $this->cache->set('key1', 'data1'); // null = use default lifetime
        $this->assertTrue($this->cache->exists('key1', 0));
    }

    /**
     * The Memory backend doesn't support lifetimes, so cannot test these like
     * in the TestBase class.
     */
    public function testGet()
    {
        $this->assertNull($this->cache->get('key1'));
        $this->cache->set('key1', 'data1'); // null = use default lifetime
        $this->assertEquals('data1', $this->cache->get('key1'));
    }
}
