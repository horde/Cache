<?php

/**
 * @category Horde
 * @internal
 * @package  Cache
 */
class HordeCacheBaseTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (!in_array('horde_cache', $this->tables())) {
            $t = $this->createTable('horde_cache', ['autoincrementKey' => ['cache_id']]);
            $t->column('cache_id', 'string', ['limit' => 32, 'null' => false]);
            $t->column('cache_timestamp', 'bigint', ['null' => false]);
            $t->column('cache_expiration', 'bigint', ['null' => false]);
            $t->column('cache_data', 'binary');
            $t->end();
        }
    }

    public function down()
    {
        $this->dropTable('horde_cache');
    }
}
