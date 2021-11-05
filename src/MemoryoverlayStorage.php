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

use InvalidArgumentException;

/**
 * A memory overlay for a cache backend. Caches results in PHP memory for the
 * current access so the underlying cache backend is not continually hit.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2013-2021 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Cache
 * @deprecated Use Memory driver as first backend in stack driver instead.
 */
class MemoryoverlayStorage extends BaseStorage
{
    /**
     * The memory cache.
     *
     * @var array
     */
    private $cache = [];

    /**
     * Constructor.
     *
     * @param array $params  Parameters:
     * <pre>
     *   - backend: (Horde_Cache_Storage_Base) [REQUIRED] The master storage
     *              backend.
     * </pre>
     */
    public function __construct(array $params = [])
    {
        if (!isset($params['backend'])) {
            throw new InvalidArgumentException('Missing backend parameter.');
        }

        parent::__construct($params);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->params['backend']->get($key, $lifetime);
        }

        return $this->cache[$key];
    }

    /**
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        $this->cache[$key] = $data;
        $this->params['backend']->set($key, $data, $lifetime);
    }

    /**
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        return isset($this->cache[$key])
            ? true
            : $this->params['backend']->exists($key, $lifetime);
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key): bool
    {
        unset($this->cache[$key]);
        return $this->params['backend']->expire($key);
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->cache = [];
        $this->params['backend']->clear();
    }
}
