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

use Horde\Cache\ApcuStorage;
use Horde\Cache\Cache;

/**
 * This class tests the APC backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
class ApcuTest extends TestBase
{
    protected function _getCache($params = [])
    {
        if (!extension_loaded('apcu')) {
            $this->reason = 'APCu extension not loaded';
            return;
        }
        $cache = new Cache(
            new ApcuStorage([
                'prefix' => 'horde_cache_test'
            ])
        );
        if (!$cache->testReadWrite()) {
            $this->reason = 'APCu extension did not pass basic read/write test, setup issues?';
            return;
        }
        return $cache;
    }
}
