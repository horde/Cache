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

namespace Horde\Cache\Test\Integration\Sql\Pdo;

use Horde\Cache\Test\Integration\Sql\Base;
use Horde\Test\Factory\Db as DbFactory;
use Horde\Test\Exception;

/**
 * This class test a PDO SQLite backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 * @coversNothing
 */
class SqliteTest extends Base
{
    protected $db;

    protected function _getCache($params = [])
    {
        $factory_db = new DbFactory();
        try {
            if (class_exists('Horde_Db_Adapter_Pdo_Sqlite')) {
                $this->db = $factory_db->create();
            }
        } catch (TestException $e) {
            $this->reason = 'Sqlite not available';
            return;
        }
        return parent::_getCache($params);
    }
}
