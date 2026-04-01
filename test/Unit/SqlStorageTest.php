<?php

declare(strict_types=1);

/**
 * Copyright 1999-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache\Test\Unit;

use Horde\Cache\Cache;
use Horde\Cache\Exception;
use Horde\Cache\SqlStorage;
use Horde\Cache\Test\Mock\DbAdapter;
use Horde\Test\TestCase;
use Horde_Db_Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

/**
 * Unit tests for SqlStorage using mocked database adapter.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
#[CoversClass(SqlStorage::class)]
#[CoversClass(Cache::class)]
class SqlStorageTest extends TestCase
{
    private DbAdapter $db;
    private Cache $cache;
    private SqlStorage $storage;

    protected function setUp(): void
    {
        $this->db = new DbAdapter();
        $this->storage = new SqlStorage($this->db);
        $this->cache = new Cache($this->storage);
    }

    protected function tearDown(): void
    {
        $this->db->clearStorage();
        $this->db->clearCalls();
    }

    #[Test]
    public function constructorRequiresDatabaseParameter(): void
    {
        $this->expectException(TypeError::class);
        // PHP 8 requires type, so missing argument causes TypeError not InvalidArgumentException
        new SqlStorage();
    }

    #[Test]
    public function constructorAcceptsCustomTableName(): void
    {
        $storage = new SqlStorage($this->db, table: 'custom_cache_table');

        $cache = new Cache($storage);
        $cache->set('key', 'value');

        $calls = $this->db->getCalls();
        $insertCall = array_filter($calls, fn($c) => $c[0] === 'insertBlob');
        $insertCall = array_values($insertCall)[0];

        $this->assertEquals('custom_cache_table', $insertCall[1]);
    }

    #[Test]
    public function setStoresDataInDatabase(): void
    {
        $this->cache->set('test_key', 'test_data', 3600);

        $calls = $this->db->getCalls();
        // Should call: delete (cleanup) + insertBlob (store)
        $this->assertCount(2, $calls);
        $this->assertEquals('delete', $calls[0][0]);
        $this->assertEquals('insertBlob', $calls[1][0]);

        // Verify data stored
        $storage = $this->db->getStorage();
        $key = hash('md5', 'test_key');
        $this->assertArrayHasKey($key, $storage);
        $this->assertEquals('test_data', $storage[$key]['data']);
    }

    #[Test]
    public function setUsesDefaultTableName(): void
    {
        $this->cache->set('key', 'value');

        $calls = $this->db->getCalls();
        $insertCall = array_filter($calls, fn($c) => $c[0] === 'insertBlob');
        $insertCall = array_values($insertCall)[0];

        $this->assertEquals('horde_cache', $insertCall[1]);
    }

    #[Test]
    public function setHashesKeyWithMd5(): void
    {
        $this->cache->set('my_key', 'data');

        $storage = $this->db->getStorage();
        $expectedKey = hash('md5', 'my_key');
        $this->assertArrayHasKey($expectedKey, $storage);
    }

    #[Test]
    public function setStoresLifetimeAsExpiration(): void
    {
        $beforeTime = time();
        $this->cache->set('key', 'data', 3600);
        $afterTime = time();

        $storage = $this->db->getStorage();
        $key = hash('md5', 'key');

        $expiration = $storage[$key]['expiration'];
        $this->assertGreaterThanOrEqual($beforeTime + 3600, $expiration);
        $this->assertLessThanOrEqual($afterTime + 3600, $expiration);
    }

    #[Test]
    public function setDeletesOldDataBeforeInserting(): void
    {
        $this->cache->set('key', 'old_data');
        $this->db->clearCalls();

        $this->cache->set('key', 'new_data');

        $calls = $this->db->getCalls();
        $this->assertEquals('delete', $calls[0][0]);
        $this->assertStringContainsString('cache_id', $calls[0][1]);
    }

    #[Test]
    public function getRetrievesDataFromDatabase(): void
    {
        $this->cache->set('test_key', 'test_data');
        $this->db->clearCalls();

        $result = $this->cache->get('test_key');

        $this->assertEquals('test_data', $result);

        $calls = $this->db->getCalls();
        $this->assertGreaterThanOrEqual(1, count($calls));
        $this->assertEquals('selectValue', $calls[0][0]);
    }

    #[Test]
    public function getReturnsNullForMissingKey(): void
    {
        $result = $this->cache->get('nonexistent_key');

        $this->assertNull($result);
    }

    #[Test]
    public function getRetrievesColumnsForBinaryDataHandling(): void
    {
        $this->cache->set('key', 'data');
        $this->db->clearCalls();

        $this->cache->get('key');

        $calls = $this->db->getCalls();
        $columnCalls = array_filter($calls, fn($c) => $c[0] === 'columns');
        $this->assertCount(1, $columnCalls);
    }

    #[Test]
    public function getRespectsLifetimeParameter(): void
    {
        // Store data with timestamp in the past
        $key = hash('md5', 'old_key');
        $this->db->getStorage()[$key] = [
            'data' => 'old_data',
            'timestamp' => time() - 3600,
            'expiration' => 0,
        ];

        // Try to get with 60 second lifetime - should fail (data is 3600s old)
        $result = $this->cache->getWithLifetime('old_key', 60);

        $this->assertFalse($result);
    }

    #[Test]
    public function getWithZeroLifetimeIgnoresAge(): void
    {
        // Store data with very old timestamp but no expiration
        $key = 'old_key';
        $hashedKey = hash('md5', $key);
        $this->db->setStorageRecord($hashedKey, [
            'data' => 'old_data',
            'timestamp' => time() - 86400, // 1 day old
            'expiration' => 0, // Never expires
        ]);

        // PSR-16 get() doesn't filter by age, only checks expiration
        $result = $this->cache->get($key);

        // Should return data since expiration=0 means never expire
        $this->assertEquals('old_data', $result);
    }

    #[Test]
    public function existsReturnsTrueForPresentKey(): void
    {
        $this->cache->set('test_key', 'test_data');

        $this->assertTrue($this->cache->exists('test_key', 0));
    }

    #[Test]
    public function existsReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->exists('nonexistent', 0));
    }

    #[Test]
    public function existsRespectsLifetimeParameter(): void
    {
        // Store data with old timestamp
        $key = hash('md5', 'old_key');
        $this->db->getStorage()[$key] = [
            'data' => 'data',
            'timestamp' => time() - 3600,
            'expiration' => 0,
        ];

        // Should not exist with 60 second lifetime
        $this->assertFalse($this->cache->exists('old_key', 60));
    }

    #[Test]
    public function expireDeletesKey(): void
    {
        $this->cache->set('key', 'data');
        $this->db->clearCalls();

        $result = $this->cache->expire('key');

        $this->assertTrue($result);

        $calls = $this->db->getCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('delete', $calls[0][0]);

        // Verify key is gone
        $this->assertFalse($this->cache->exists('key', 0));
    }

    #[Test]
    public function expireReturnsFalseOnDatabaseError(): void
    {
        $this->db->setException(true);

        $result = $this->cache->expire('key');

        $this->assertFalse($result);
    }

    #[Test]
    public function clearRemovesAllData(): void
    {
        $this->cache->set('key1', 'data1');
        $this->cache->set('key2', 'data2');
        $this->db->clearCalls();

        $this->cache->clear();

        $calls = $this->db->getCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('delete', $calls[0][0]);

        // Verify all data removed
        $this->assertEmpty($this->db->getStorage());
    }

    #[Test]
    public function clearReturnsFalseOnDatabaseError(): void
    {
        $this->db->setException(true);

        $result = $this->cache->clear();

        $this->assertFalse($result);
    }

    #[Test]
    public function setReturnsFalseOnInsertFailure(): void
    {
        $this->db->setException(true);

        $result = $this->cache->set('key', 'data');

        $this->assertFalse($result);
    }

    #[Test]
    public function getHandlesDatabaseExceptionGracefully(): void
    {
        $this->db->setException(true);

        $result = $this->cache->get('key');

        $this->assertNull($result);
    }

    #[Test]
    public function existsHandlesDatabaseExceptionGracefully(): void
    {
        $this->db->setException(true);

        $result = $this->cache->exists('key', 0);

        $this->assertFalse($result);
    }

    #[Test]
    public function testReadWriteVerifiesBasicOperations(): void
    {
        $result = $this->cache->testReadWrite();

        $this->assertTrue($result);

        // Verify it did set, exists check, and expire
        $calls = $this->db->getCalls();
        $this->assertGreaterThan(2, count($calls));
    }

    #[Test]
    public function setBinaryDataWrappedInBinaryValue(): void
    {
        $binaryData = "\x00\x01\x02\xFF";
        $this->cache->set('binary_key', $binaryData);

        $calls = $this->db->getCalls();
        $insertCall = array_filter($calls, fn($c) => $c[0] === 'insertBlob');
        $insertCall = array_values($insertCall)[0];

        $fields = $insertCall[2];
        $this->assertInstanceOf('Horde_Db_Value_Binary', $fields['cache_data']);
    }

    #[Test]
    public function storageUsesCorrectFieldNames(): void
    {
        $this->cache->set('key', 'data', 100);

        $calls = $this->db->getCalls();
        $insertCall = array_filter($calls, fn($c) => $c[0] === 'insertBlob');
        $insertCall = array_values($insertCall)[0];

        $fields = $insertCall[2];
        $this->assertArrayHasKey('cache_id', $fields);
        $this->assertArrayHasKey('cache_timestamp', $fields);
        $this->assertArrayHasKey('cache_expiration', $fields);
        $this->assertArrayHasKey('cache_data', $fields);
    }

    #[Test]
    public function getBuildsCorrectQueryForLifetime(): void
    {
        $this->cache->set('key', 'data');
        $this->db->clearCalls();

        $this->cache->getWithLifetime('key', 3600);

        $calls = $this->db->getCalls();
        $selectCall = $calls[0];

        $this->assertEquals('selectValue', $selectCall[0]);
        // Query should include timestamp constraint
        $this->assertStringContainsString('cache_timestamp', $selectCall[1]);
        // Should also check expiration
        $this->assertStringContainsString('cache_expiration', $selectCall[1]);
        // Should have 3 values: key, maxage timestamp, current timestamp for expiration
        $this->assertCount(3, $selectCall[2]);
    }

    #[Test]
    public function existsBuildsCorrectQueryForLifetime(): void
    {
        $this->cache->set('key', 'data');
        $this->db->clearCalls();

        $this->cache->exists('key', 3600);

        $calls = $this->db->getCalls();
        $selectCall = $calls[0];

        $this->assertEquals('selectValue', $selectCall[0]);
        $this->assertStringContainsString('SELECT 1', $selectCall[1]);
        $this->assertStringContainsString('cache_timestamp', $selectCall[1]);
    }

    #[Test]
    public function expireUsesHashedKey(): void
    {
        $this->cache->expire('my_key');

        $calls = $this->db->getCalls();
        $deleteCall = $calls[0];

        $key = $deleteCall[2][0];
        $this->assertEquals(hash('md5', 'my_key'), $key);
    }

    #[Test]
    public function setStoresCurrentTimestamp(): void
    {
        $beforeTime = time();
        $this->cache->set('key', 'data');
        $afterTime = time();

        $storage = $this->db->getStorage();
        $key = hash('md5', 'key');

        $timestamp = $storage[$key]['timestamp'];
        $this->assertGreaterThanOrEqual($beforeTime, $timestamp);
        $this->assertLessThanOrEqual($afterTime, $timestamp);
    }

    #[Test]
    public function clearDeletesFromCorrectTable(): void
    {
        $storage = new SqlStorage($this->db, table: 'my_cache_table');
        $cache = new Cache($storage);

        $cache->clear();

        $calls = $this->db->getCalls();
        $deleteCall = $calls[0];

        $this->assertEquals('delete', $deleteCall[0]);
        $this->assertStringContainsString('my_cache_table', $deleteCall[1]);
    }

    #[Test]
    public function multipleKeysCanCoexist(): void
    {
        $this->cache->set('key1', 'data1');
        $this->cache->set('key2', 'data2');
        $this->cache->set('key3', 'data3');

        $this->assertEquals('data1', $this->cache->get('key1'));
        $this->assertEquals('data2', $this->cache->get('key2'));
        $this->assertEquals('data3', $this->cache->get('key3'));
    }

    #[Test]
    public function setOverwritesExistingKey(): void
    {
        $this->cache->set('key', 'old_value');
        $this->cache->set('key', 'new_value');

        $result = $this->cache->get('key');

        $this->assertEquals('new_value', $result);
    }

    #[Test]
    public function getWithLifetimeCalculatesCorrectMaxAge(): void
    {
        $this->cache->set('key', 'data');
        $this->db->clearCalls();

        $lifetime = 1800; // 30 minutes
        $beforeTime = time();
        $this->cache->getWithLifetime('key', $lifetime);
        $afterTime = time();

        $calls = $this->db->getCalls();
        $selectCall = $calls[0];
        $values = $selectCall[2];

        // Second parameter should be current_time - lifetime
        $maxAge = $values[1];
        $this->assertGreaterThanOrEqual($beforeTime - $lifetime, $maxAge);
        $this->assertLessThanOrEqual($afterTime - $lifetime, $maxAge);
    }

    #[Test]
    public function expireReturnsTrueOnSuccess(): void
    {
        $this->cache->set('key', 'data');

        $result = $this->cache->expire('key');

        $this->assertTrue($result);
    }

    #[Test]
    public function handlesBinaryDataCorrectly(): void
    {
        $binaryData = pack('H*', 'deadbeef');
        $this->cache->set('binary', $binaryData);

        $result = $this->cache->get('binary');

        $this->assertEquals($binaryData, $result);
    }

    #[Test]
    public function handlesEmptyStringData(): void
    {
        $this->cache->set('empty', '');

        $result = $this->cache->get('empty');

        $this->assertEquals('', $result);
    }

    #[Test]
    public function handlesLargeDataValues(): void
    {
        $largeData = str_repeat('x', 100000);
        $this->cache->set('large', $largeData);

        $result = $this->cache->get('large');

        $this->assertEquals($largeData, $result);
    }

    #[Test]
    public function handlesSpecialCharactersInKeys(): void
    {
        // PSR-16 allows these characters: A-Z a-z 0-9 _ . -
        $specialKey = 'key_with-special.chars123';
        $this->cache->set($specialKey, 'data');

        $result = $this->cache->get($specialKey);

        $this->assertEquals('data', $result);
    }
}
