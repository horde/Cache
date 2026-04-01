<?php

declare(strict_types=1);

/**
 * Copyright 1999-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Cache
 * @license  http://www.horde.org/licenses/lgpl21 LGPL-2.1
 */

namespace Horde\Cache\Test\Mock;

use Horde\Db\Adapter\Base;
use Horde_Db_Exception;
use Horde_Db_Value_Binary;

/**
 * Mock database adapter for SqlStorage unit tests.
 *
 * Only implements methods actually used by SqlStorage.
 *
 * @category  Horde
 * @copyright 1999-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package   Cache
 */
class DbAdapter extends Base
{
    /**
     * In-memory storage simulating database table.
     *
     * @var array
     */
    private array $storage = [];

    /**
     * Track method calls for verification.
     *
     * @var array
     */
    private array $calls = [];

    /**
     * Simulate exception throwing.
     *
     * @var bool
     */
    public bool $shouldThrowException = false;

    /**
     * Exception to throw if shouldThrowException is true.
     *
     * @var string
     */
    public string $exceptionMessage = 'Mock database exception';

    /**
     * Simulate columns() response.
     *
     * @var array
     */
    private array $columnsMock;

    public function __construct(array $config = [])
    {
        // Initialize mock column for binary data handling
        $this->columnsMock = [
            'cache_data' => new class {
                public function binaryToString($data)
                {
                    if ($data instanceof Horde_Db_Value_Binary) {
                        return $data->value;
                    }
                    return $data;
                }
            },
        ];
    }

    /**
     * Get all method calls for verification.
     *
     * @return array
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * Clear method call history.
     */
    public function clearCalls(): void
    {
        $this->calls = [];
    }

    /**
     * Get internal storage for verification.
     *
     * @return array
     */
    public function getStorage(): array
    {
        return $this->storage;
    }

    /**
     * Clear all stored data.
     */
    public function clearStorage(): void
    {
        $this->storage = [];
    }

    /**
     * Directly set a storage record for testing.
     */
    public function setStorageRecord(string $key, array $record): void
    {
        $this->storage[$key] = $record;
    }

    /**
     * Set exception behavior.
     */
    public function setException(bool $throw, string $message = 'Mock exception'): void
    {
        $this->shouldThrowException = $throw;
        $this->exceptionMessage = $message;
    }

    /**
     * SELECT a single value from database.
     * Used by SqlStorage::get() and SqlStorage::exists().
     */
    public function selectValue($sql, $arg1 = null, $arg2 = null)
    {
        $values = is_array($arg1) ? $arg1 : [];
        $this->calls[] = ['selectValue', $sql, $values];

        if ($this->shouldThrowException) {
            throw new Horde_Db_Exception($this->exceptionMessage);
        }

        $key = $values[0] ?? null;
        if (!isset($this->storage[$key])) {
            return null;
        }

        $record = $this->storage[$key];

        // Check if querying for existence (SELECT 1)
        if (strpos($sql, 'SELECT 1') !== false) {
            // Has timestamp filter? (3 values: key, maxage, current_time)
            if (count($values) > 2) {
                $minTimestamp = $values[1];
                if ($record['timestamp'] < $minTimestamp) {
                    return null;
                }
            }

            // Check expiration (last value is always current_time)
            $currentTime = $values[count($values) - 1];
            if ($record['expiration'] > 0 && $record['expiration'] <= $currentTime) {
                return null;
            }

            return 1;
        }

        // Returning cache_data
        // Has timestamp filter? (3 values: key, maxage, current_time)
        if (count($values) > 2) {
            $minTimestamp = $values[1];
            if ($record['timestamp'] < $minTimestamp) {
                return null;
            }
        }

        // Check expiration (last value is always current_time)
        $currentTime = $values[count($values) - 1];
        if ($record['expiration'] > 0 && $record['expiration'] <= $currentTime) {
            return null;
        }

        return $record['data'];
    }

    /**
     * Get column metadata for a table.
     * Used by SqlStorage::get() for binary data handling.
     */
    public function columns($table)
    {
        $this->calls[] = ['columns', $table];

        if ($this->shouldThrowException) {
            throw new Horde_Db_Exception($this->exceptionMessage);
        }

        return $this->columnsMock;
    }

    /**
     * DELETE records from database.
     * Used by SqlStorage::set(), SqlStorage::expire(), and SqlStorage::clear().
     */
    public function delete($sql, $arg1 = null, $arg2 = null)
    {
        $values = is_array($arg1) ? $arg1 : [];
        $this->calls[] = ['delete', $sql, $values];

        if ($this->shouldThrowException) {
            throw new Horde_Db_Exception($this->exceptionMessage);
        }

        // If no WHERE clause (clear all)
        if (empty($values)) {
            $count = count($this->storage);
            $this->storage = [];
            return $count;
        }

        // DELETE with cache_id
        if (isset($values[0])) {
            $key = $values[0];
            if (isset($this->storage[$key])) {
                unset($this->storage[$key]);
                return 1;
            }
        }

        // DELETE with expiration (garbage collection)
        if (strpos($sql, 'cache_expiration') !== false && isset($values[0])) {
            $currentTime = $values[0];
            $deleted = 0;
            foreach ($this->storage as $key => $record) {
                if ($record['expiration'] < $currentTime && $record['expiration'] != 0) {
                    unset($this->storage[$key]);
                    $deleted++;
                }
            }
            return $deleted;
        }

        return 0;
    }

    /**
     * INSERT binary data into database.
     * Used by SqlStorage::set().
     */
    public function insertBlob($table, $fields, $pk = null, $idValue = null)
    {
        $this->calls[] = ['insertBlob', $table, $fields];

        if ($this->shouldThrowException) {
            throw new Horde_Db_Exception($this->exceptionMessage);
        }

        $data = $fields['cache_data'];
        if ($data instanceof Horde_Db_Value_Binary) {
            $data = $data->value;
        }

        $this->storage[$fields['cache_id']] = [
            'data' => $data,
            'timestamp' => $fields['cache_timestamp'],
            'expiration' => $fields['cache_expiration'],
        ];
    }

    /**
     * Disconnect from database.
     * Used in tearDown.
     */
    public function disconnect()
    {
        $this->calls[] = ['disconnect'];
        // No-op for mock
    }

    // Stub implementations for interface compliance
    public function adapterName()
    {
        return 'mock';
    }
    public function supportsMigrations()
    {
        return false;
    }
    public function supportsCountDistinct()
    {
        return true;
    }
    public function prefetchPrimaryKey($tableName = null)
    {
        return false;
    }
    public function connect() {}
    public function isActive()
    {
        return true;
    }
    public function reconnect() {}
    public function rawConnection()
    {
        return null;
    }
    public function quoteString($string)
    {
        return "'" . addslashes($string) . "'";
    }
    public function select($sql, $arg1 = null, $arg2 = null)
    {
        return [];
    }
    public function selectAll($sql, $arg1 = null, $arg2 = null)
    {
        return [];
    }
    public function selectOne($sql, $arg1 = null, $arg2 = null)
    {
        return null;
    }
    public function selectValues($sql, $arg1 = null, $arg2 = null)
    {
        return [];
    }
    public function selectAssoc($sql, $arg1 = null, $arg2 = null)
    {
        return [];
    }
    public function execute($sql, $arg1 = null, $arg2 = null)
    {
        return null;
    }
    public function insert($sql, $arg1 = null, $arg2 = null, $pk = null, $idValue = null, $sequenceName = null)
    {
        return null;
    }
    public function update($sql, $arg1 = null, $arg2 = null)
    {
        return 0;
    }
    public function updateBlob($table, $fields, $where = '')
    {
        return 0;
    }
    public function transactionStarted()
    {
        return false;
    }
    public function beginDbTransaction() {}
    public function commitDbTransaction() {}
    public function rollbackDbTransaction() {}
    public function addLimitOffset($sql, $options)
    {
        return $sql;
    }
    public function addLock(&$sql, array $options = []) {}
    public function getLastQuery(): string
    {
        return '';
    }
    public function cacheWrite($key, $value) {}
}
