# Upgrading to Horde Cache PSR-16

This guide helps you migrate from the legacy Horde Cache implementation to the modern PSR-16 architecture.

## Overview

Horde Cache has been completely rewritten to:
- Implement PSR-16 Simple Cache interface
- Use constructor injection instead of array parameters
- Separate smart facade logic from minimal storage backends
- Provide modern PHP 8.2+ syntax with strict types

## Breaking Changes

### 1. Storage Constructor Signatures

**Old (array-based):**
```php
$storage = new \Horde\Cache\SqlStorage([
    'db' => $dbAdapter,
    'table' => 'my_cache'
]);

$storage = new \Horde\Cache\FileStorage([
    'dir' => '/tmp/cache',
    'prefix' => 'my_cache_',
    'no_gc' => true
]);

$storage = new \Horde\Cache\MemcacheStorage([
    'memcache' => $memcacheInstance,
    'prefix' => 'app_'
]);
```

**New (constructor injection):**
```php
use Psr\Log\NullLogger;

$storage = new \Horde\Cache\SqlStorage(
    db: $dbAdapter,
    logger: new NullLogger(),  // Optional, defaults to NullLogger
    table: 'my_cache'
);

$storage = new \Horde\Cache\FileStorage(
    logger: new NullLogger(),  // Optional
    dir: '/tmp/cache',
    prefix: 'my_cache_',
    sub: 0,
    no_gc: true
);

$storage = new \Horde\Cache\MemcacheStorage(
    memcache: $memcacheApiInstance,  // Now requires MemcacheApi, not Horde_Memcache
    logger: new NullLogger(),        // Optional
    prefix: 'app_'
);
```

### 2. PSR-16 Semantic Changes

#### TTL=0 Behavior Changed

**Old Horde semantics:**
```php
// ttl=0 meant "store forever" (no expiration)
$cache->set('key', 'value', 0);
```

**New PSR-16 semantics:**
```php
// ttl=0 means "delete immediately"
$cache->set('key', 'value', 0);  // Returns false, key deleted

// For "store forever", omit ttl or use large value:
$cache->set('key', 'value');           // Uses default lifetime
$cache->set('key', 'value', 86400*365); // 1 year
```

#### get() Returns null on Miss

**Old:**
```php
$value = $cache->get('missing_key');
// Returns: false
```

**New:**
```php
$value = $cache->get('missing_key');
// Returns: null

// Use default parameter:
$value = $cache->get('missing_key', 'default_value');
```

#### set() Returns bool

**Old:**
```php
$cache->set('key', 'value');
// Returns: void (null)
```

**New:**
```php
$result = $cache->set('key', 'value');
// Returns: true on success, false on failure
```

### 3. Age Filtering Methods Changed

Age filtering (per-retrieval lifetime checks) is now explicit and only available on backends that support it.

**Old:**
```php
// get() with lifetime parameter
$value = $cache->get('key', 3600);  // Only return if < 1 hour old

// exists() with lifetime parameter
if ($cache->exists('key', 3600)) {
    // ...
}
```

**New:**
```php
// Use explicit methods
try {
    $value = $cache->getWithLifetime('key', 3600);
    
    if ($cache->hasWithLifetime('key', 3600)) {
        // ...
    }
} catch (\Horde\Cache\UnsupportedOperationException $e) {
    // Backend doesn't support age filtering
    // (Memcache, Redis, APCu don't track timestamps)
}

// For PSR-16 standard behavior (no age filtering):
$value = $cache->get('key');
if ($cache->has('key')) {
    // ...
}
```

**Backends not supporting age filtering:**
- SqlStorage (tracks timestamp + expiration)
- FileStorage (uses file mtime)
- SessionStorage (tracks timestamp + expiration)
- HashtableStorage (tracks timestamp + expiration)
- MongoStorage (tracks timestamp + expiration)

- MemcacheStorage (TTL only, no timestamp)
- ApcuStorage (TTL only, no timestamp)
- MemoryStorage (simple in-memory, no timestamps)

### 4. Deprecated Methods

Legacy methods are still available but deprecated:

```php
// Deprecated (still work, but discouraged)
$cache->cacheGet($key, $lifetime);
$cache->cacheSet($key, $data, $lifetime);
$cache->cacheExists($key, $lifetime);
$cache->cacheExpire($key);
$cache->cacheClear();

// Use instead
$cache->get($key);
$cache->set($key, $data, $ttl);
$cache->has($key);
$cache->delete($key);
$cache->clear();
```

### 5. MemcacheStorage Now Requires MemcacheApi

**Old:**
```php
use Horde_Memcache;

$memcache = new Horde_Memcache([
    'hostspec' => ['localhost'],
    'port' => [11211]
]);

$storage = new \Horde\Cache\MemcacheStorage([
    'memcache' => $memcache
]);
```

**New:**
```php
use Horde\Memcache\MemcacheApi;

$memcache = new MemcacheApi([
    'hostspec' => ['localhost'],
    'port' => [11211]
]);

$storage = new \Horde\Cache\MemcacheStorage(
    memcache: $memcache,
    prefix: 'horde_'
);
```

### 6. BaseStorage Removed

Storage backends no longer extend `BaseStorage`. If you have custom storage implementations:

**Old:**
```php
class CustomStorage extends \Horde\Cache\BaseStorage
{
    public function get(string $key, int $lifetime = 0)
    {
        // ...
    }

    public function set(string $key, $data, int $lifetime = 0)
    {
        // ...
    }
}
```

**New:**
```php
use Horde\Cache\SimpleCacheStorage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CustomStorage implements SimpleCacheStorage
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function get(string $key)
    {
        // Returns value or false on miss
    }

    public function set(string $key, mixed $data, int $ttl): bool
    {
        // Returns true on success
    }

    public function delete(string $key): bool
    {
        // ...
    }

    public function has(string $key): bool
    {
        // ...
    }

    public function clear(): bool
    {
        // ...
    }
}

// For age filtering support also implement HordeCacheStorage:
use Horde\Cache\HordeCacheStorage;

class CustomStorage implements SimpleCacheStorage, HordeCacheStorage
{
    // ... SimpleCacheStorage methods ...

    public function getWithLifetime(string $key, int $lifetime)
    {
        // ...
    }

    public function hasWithLifetime(string $key, int $lifetime): bool
    {
        // ...
    }
}
```

## Migration Steps

### Step 1: Update Dependencies

```bash
composer require psr/simple-cache:^3.0 psr/log:^3.0
```

### Step 2: Update Storage Instantiation

Replace array-based constructors with named parameters:

```php
// Before
$cache = new \Horde\Cache\Cache(
    new \Horde\Cache\SqlStorage([
        'db' => $db,
        'table' => 'cache'
    ])
);

// After
$cache = new \Horde\Cache\Cache(
    new \Horde\Cache\SqlStorage(
        db: $db,
        table: 'cache'
    )
);
```

### Step 3: Update TTL Usage

Replace `0` with omitted parameter or large value:

```php
// Before
$cache->set('permanent', 'value', 0);

// After
$cache->set('permanent', 'value');  // Uses default lifetime
// OR
$cache->set('permanent', 'value', 86400*365);  // Explicit 1 year
```

### Step 4: Update get() Null Handling

```php
// Before
$value = $cache->get('key');
if ($value === false) {
    // Not found
}

// After
$value = $cache->get('key');
if ($value === null) {
    // Not found
}

// Or use default:
$value = $cache->get('key', 'default');
```

### Step 5: Update Age Filtering Calls

```php
// Before
$value = $cache->get('key', 3600);

// After - Option 1: Use explicit method
try {
    $value = $cache->getWithLifetime('key', 3600);
} catch (\Horde\Cache\UnsupportedOperationException $e) {
    // Fallback for backends without age filtering
    $value = $cache->get('key');
}

// After - Option 2: Just use PSR-16 get() without age check
$value = $cache->get('key');
```

### Step 6: Update exists() to has()

```php
// Before
if ($cache->exists('key', 0)) {
    // ...
}

// After
if ($cache->has('key')) {
    // ...
}
```

### Step 7: Update expire() to delete()

```php
// Before
$cache->expire('key');

// After
$cache->delete('key');
```

## Configuration Examples

### SQL Storage

```php
use Horde\Cache\Cache;
use Horde\Cache\SqlStorage;
use Psr\Log\LoggerInterface;

$storage = new SqlStorage(
    db: $dbAdapter,              // Required: Horde_Db_Adapter instance
    logger: $logger,             // Optional: PSR-3 logger (defaults to NullLogger)
    table: 'horde_cache'         // Optional: table name (defaults to 'horde_cache')
);

$cache = new Cache($storage, [
    'compress' => false,         // Optional: compress data (default: false)
    'lifetime' => 86400,         // Optional: default TTL in seconds (default: 86400)
    'namespace' => 'myapp_'      // Optional: key prefix (default: '')
]);
```

### File Storage

```php
use Horde\Cache\FileStorage;

$storage = new FileStorage(
    logger: $logger,             // Optional: PSR-3 logger
    dir: '/var/cache/horde',     // Optional: cache directory (defaults to sys_get_temp_dir())
    prefix: 'cache_',            // Optional: filename prefix (default: 'cache_')
    sub: 2,                      // Optional: subdirectory levels (default: 0)
    no_gc: false,                // Optional: disable garbage collection (default: false)
    umask: 0022                  // Optional: file permission umask (default: null)
);

$cache = new Cache($storage);
```

### Memcache Storage

```php
use Horde\Cache\MemcacheStorage;
use Horde\Memcache\MemcacheApi;

$memcacheApi = new MemcacheApi([
    'hostspec' => ['localhost', '192.168.1.100'],
    'port' => [11211, 11211],
    'persistent' => false
]);

$storage = new MemcacheStorage(
    memcache: $memcacheApi,      // Required: MemcacheApi instance
    logger: $logger,             // Optional: PSR-3 logger
    prefix: 'horde_'             // Optional: key prefix (default: '')
);

$cache = new Cache($storage);
```

### Session Storage

```php
use Horde\Cache\SessionStorage;

$storage = new SessionStorage(
    logger: $logger,             // Optional: PSR-3 logger
    sess_name: 'app_cache'       // Optional: session key (default: 'hordecachesession')
);

$cache = new Cache($storage);
```

### Memory Storage

```php
use Horde\Cache\MemoryStorage;

// Simple in-memory cache for request lifetime
$storage = new MemoryStorage(
    logger: $logger              // Optional: PSR-3 logger
);

$cache = new Cache($storage);
```

## Testing Your Migration

### Unit Tests

```php
use Horde\Cache\Cache;
use Horde\Cache\MemoryStorage;
use PHPUnit\Framework\TestCase;

class CacheMigrationTest extends TestCase
{
    public function testPsr16Semantics(): void
    {
        $cache = new Cache(new MemoryStorage());

        // Test set() returns bool
        $this->assertTrue($cache->set('key', 'value'));

        // Test get() returns null on miss
        $this->assertNull($cache->get('nonexistent'));

        // Test get() with default
        $this->assertEquals('default', $cache->get('nonexistent', 'default'));

        // Test has()
        $this->assertTrue($cache->has('key'));
        $this->assertFalse($cache->has('nonexistent'));

        // Test delete() returns bool
        $this->assertTrue($cache->delete('key'));
        $this->assertNull($cache->get('key'));

        // Test clear() returns bool
        $cache->set('a', '1');
        $cache->set('b', '2');
        $this->assertTrue($cache->clear());
        $this->assertNull($cache->get('a'));
    }

    public function testAgeFiltering(): void
    {
        $cache = new Cache(new MemoryStorage());

        // MemoryStorage doesn't support age filtering
        $this->expectException(\Horde\Cache\UnsupportedOperationException::class);
        $cache->getWithLifetime('key', 3600);
    }
}
```

## Backward Compatibility

The facade maintains backward compatibility through deprecated methods:

```php
// These still work but will be removed in future versions:
$cache->exists('key', 0);        // Use: $cache->has('key')
$cache->expire('key');           // Use: $cache->delete('key')
$cache->cacheGet('key', 0);      // Use: $cache->get('key')
$cache->cacheSet('key', 'val');  // Use: $cache->set('key', 'val')
$cache->cacheClear();            // Use: $cache->clear()
```

## Common Issues

### Issue: "UnsupportedOperationException: getWithLifetime() requires storage backend with timestamp support"

**Cause:** Trying to use age filtering on a backend that doesn't support it (Memcache, APCu).

**Solution:** Either switch to a backend that supports timestamps (SQL, File, Session) or remove the age filtering:

```php
// Option 1: Catch and fallback
try {
    $value = $cache->getWithLifetime('key', 3600);
} catch (\Horde\Cache\UnsupportedOperationException $e) {
    $value = $cache->get('key');
}

// Option 2: Just use standard PSR-16 get()
$value = $cache->get('key');
```

### Issue: "TypeError: Argument #1 must be of type MemcacheApi, Horde_Memcache given"

**Cause:** Passing old `Horde_Memcache` instance instead of new `MemcacheApi`.

**Solution:**
```php
// Change from:
$memcache = new Horde_Memcache($config);

// To:
use Horde\Memcache\MemcacheApi;
$memcache = new MemcacheApi($config);
```

### Issue: Keys with ttl=0 are being deleted

**Cause:** PSR-16 semantics changed - `ttl=0` now means "delete immediately".

**Solution:**
```php
// Change from:
$cache->set('key', 'value', 0);  // Old: store forever

// To:
$cache->set('key', 'value');     // New: uses default lifetime
```

### Issue: get() returning null instead of false

**Cause:** PSR-16 standard - null indicates cache miss.

**Solution:**
```php
// Change from:
if ($cache->get('key') === false) {
    // Not found
}

// To:
if ($cache->get('key') === null) {
    // Not found
}

// Or use strict null check:
if (($value = $cache->get('key')) !== null) {
    // Found
}
```

## Performance Notes

1. **Constructor injection** allows better dependency injection and testing
2. **Minimal storage backends** reduce overhead - validation/compression/namespace logic is now in the facade only
3. **Age filtering** is explicit - backends without timestamp support avoid unnecessary overhead
4. **PSR-16 compliance** enables interoperability with other PSR-16 compatible libraries

## Getting Help

- Check the [test suite](test/) for usage examples
- Review the [API documentation](src/)
- File issues at [horde/Cache on GitHub](https://github.com/horde/Cache/issues)
