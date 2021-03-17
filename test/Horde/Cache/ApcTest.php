<?php
/**
 * Copyright 2016-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
namespace Horde\Cache;
use Horde_Cache_TestBase as TestBase;

/**
 * This class tests the APC backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
class ApcTest extends TestBase
{
    protected function _getCache($params = array())
    {
        if (!extension_loaded('apc')) {
            $this->reason = 'APC extension not loaded';
            return;
        }
        return new Horde_Cache(
            new Horde_Cache_Storage_Apc(array(
                'prefix' => 'horde_cache_test'
            ))
        );
    }
}
