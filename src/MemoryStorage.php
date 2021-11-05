<?php
/**
 * Copyright 2010-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

/**
 * Cache storage in PHP memory.
 *
 * It persists only during a script run and ignores the object lifetime
 * because of that.
 *
 * @author    Gunnar Wrobel <wrobel@pardus.de>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 * @since     2.5.0
 */
class MemoryStorage extends BaseStorage
{
    /**
     * Storage for this cache.
     *
     * @var array
     */
    private array $cache = [];

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        return $this->cache[$key]
            ?? false;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        $this->cache[$key] = $data;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        return isset($this->cache[$key]);
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->cache = [];
    }
}
