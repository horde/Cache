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

/**
 * Cache storage in a PHP session.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class SessionStorage extends BaseStorage
{
    /**
     * Pointer to the session entry.
     *
     * @var array
     */
    protected $session;

    /**
     * Constructor.
     *
     * @param array $params  Optional parameters:
     * <pre>
     *   - session: (string) Store session data in this entry.
     *              DEFAULT: 'hordecachesessionion'
     * </pre>
     */
    public function __construct(array $params = [])
    {
        $params = array_merge([
            'sess_name' => 'hordecachesessionion',
        ], $params);

        parent::__construct($params);
    }

    /**
     * Do initialization tasks.
     */
    protected function _initOb()
    {
        if (!isset($_SESSION[$this->params['sess_name']])) {
            $_SESSION[$this->params['sess_name']] = [];
        }
        $this->session = &$_SESSION[$this->params['sess_name']];
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        return $this->exists($key, $lifetime)
            ? $this->session[$key]['d']
            : false;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        $this->session[$key] = [
            'd' => $data,
            'l' => $lifetime,
        ];
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key, int $lifetime = 0): bool
    {
        if (isset($this->session[$key])) {
            /* 0 means no expire. */
            if (($lifetime == 0) ||
                ((time() - $lifetime) <= $this->session[$key]['l'])) {
                return true;
            }

            unset($this->session[$key]);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key): bool
    {
        if (isset($this->session[$key])) {
            unset($this->session[$key]);
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->session = [];
    }
}
