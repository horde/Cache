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

/**
 * Null cache storage driver.
 *
 * @author    Duck <duck@obala.net>
 * @category  Horde
 * @copyright 2006-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class NullStorage extends BaseStorage
{
    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function expire($string): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
    }
}
