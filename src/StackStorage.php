<?php
/**
 * Copyright 2010-2021 Horde LLC (http://www.horde.org/)
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
 * Driver that loops through a given list of storage drivers to search for a
 * cached value. Allows for use of caching backends on top of persistent
 * backends.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class StackStorage extends BaseStorage
{
    /**
     * Stack of cache drivers.
     *
     * @var array
     */
    protected array $stack = [];

    /**
     * Constructor.
     *
     * @param array $params  Parameters:
     * <pre>
     *   - stack: (array) [REQUIRED] An array of storage instances to loop
     *            through, in order of priority. The last entry is considered
     *            the 'master' driver, for purposes of writes.
     * </pre>
     */
    public function __construct(array $params = [])
    {
        if (!isset($params['stack'])) {
            throw new InvalidArgumentException('Missing stack parameter.');
        }

        parent::__construct($params);
    }

    /**
     * @inheritDoc
     */
    protected function _initOb()
    {
        $this->stack = array_values($this->params['stack']);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        foreach ($this->stack as $val) {
            if (($result = $val->get($key, $lifetime)) !== false) {
                return $result;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        /* Do writes in *reverse* order, since a failure on the master should
         * not allow writes on the other backends. */
        foreach (array_reverse($this->stack) as $k => $v) {
            if (($result = $v->set($key, $data, $lifetime)) === false) {
                if ($k === 0) {
                    return;
                }

                /* Invalidate cache if write failed. */
                $v->expire($k);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        $result = false;
        foreach ($this->stack as $val) {
            if (($result = $val->exists($key, $lifetime)) === true) {
                break;
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key): bool
    {
        $success = false;
        foreach ($this->stack as $val) {
            $success = $val->expire($key);
        }

        /* Success is reported from last (master) expire() call. */
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $ex = null;
        foreach ($this->stack as $val) {
            try {
                $val->clear();
                $ex = null;
            } catch (Exception $e) {
                $ex = $e;
            }
        }

        if ($ex) {
            throw $ex;
        }
    }
}
