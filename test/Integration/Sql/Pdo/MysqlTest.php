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
use PDO;
use Horde_Db_Adapter_Pdo_Mysql;
use Exception;

/**
 * This class test a PDO MySQL backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 * @coversNothing
 */
class MysqlTest extends Base
{
    protected function _getCache($params = [])
    {
        if (!extension_loaded('pdo')
            || !in_array('mysql', PDO::getAvailableDrivers())) {
            $this->reason = 'No pdo_mysql extension';
            return;
        }
        $config = self::getConfig(
            'CACHE_SQL_PDO_MYSQL_TEST_CONFIG',
            __DIR__ . '/../../..'
        );
        if ($config && !empty($config['cache']['sql']['pdo_mysql'])) {
            try {
                $this->db = new Horde_Db_Adapter_Pdo_Mysql($config['cache']['sql']['pdo_mysql']);
                return parent::_getCache($params);
            } catch (Exception $e) {
                $this->reason = 'Cannot connect to MySQL: ' . $e->getMessage();
                return;
            }
        } else {
            $this->reason = 'No pdo_mysql configuration';
        }
    }
}
