<?php
/**
 * Copyright 2016-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
namespace Horde\Cache\Test;
use Horde\Cache\Cache;
use Horde\Cache\HashtableStorage;
use Horde_HashTable_Memory;
/**
 * This class tests the Horde_Hashtable backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
class HashtableTest extends TestBase
{
    protected function _getCache($params = [])
    {
        if (!class_exists('Horde_HashTable_Memory')) {
            $this->reason = 'Horde_HashTable not installed';
            return;
        }
        return new Cache(
            new HashtableStorage([
                'hashtable' => new Horde_HashTable_Memory(),
                'prefix' => 'horde_cache_test'
            ])
        );
    }
}
