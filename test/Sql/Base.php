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
namespace Horde\Cache\Test\Sql;
use Horde\Cache\Test\TestBase;
use Horde_Log_Logger;
use Horde_Log_Handler_Cli;
use PEAR_Config;
use Horde_Db_Migration_Migrator;
use Horde\Cache\Cache;
use Horde\Cache\SqlStorage;

/**
 * This is the base test class for all SQL backends.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
class Base extends TestBase
{
    protected function _getCache($params = array())
    {
        $logger = new Horde_Log_Logger(new Horde_Log_Handler_Cli());
        $this->db->setLogger($logger);
        $dir = __DIR__ . '/../../migration/Horde/Cache';
        if (!is_dir($dir)) {
            error_reporting(E_ALL & ~E_DEPRECATED);
            $dir = PEAR_Config::singleton()
                ->get('data_dir', null, 'pear.horde.org')
                . '/Horde_Cache/migration';
            error_reporting(E_ALL | E_STRICT);
        }
        
        if (class_exists('Horde_Db_Migration_Migrator')) {
            $this->migrator = new Horde_Db_Migration_Migrator(
                        $this->db,
                        $logger,
                        array('migrationsPath' => $dir,
                            'schemaTableName' => 'horde_cache_schema_info'));
                    $this->migrator->up();
       
            return new Cache(
                new SqlStorage(array_merge(
                    ['db'   => $this->db],
                    $params
                ))
            );
        }
    }


    public function tearDown(): void
    {
        parent::tearDown();
        $this->db->delete('DELETE FROM horde_cache');
        if ($this->migrator) {
            $this->migrator->down();
        }
        if ($this->db) {
            $this->db->disconnect();
        }
        $this->db = $this->migrator = null;
    }
}
