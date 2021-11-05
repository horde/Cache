<?php
/**
 * Copyright 2016-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2016-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
namespace Horde\Cache\Test\Stub;
use Horde\Cache\FileStorage;
/**
 * Stub for cache storage in the filesystem.
 *
 * @author    Jan Schneider <slusarz@horde.org>
 * @category  Horde
 * @copyright 2016-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class File extends FileStorage
{
    /**
     * Enforces garbage collection.
     */
    public function gc()
    {
        $this->_gc();
    }
}
