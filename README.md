# horde/cache

PSR-16 Simple Cache implementation with multiple storage backends.

## Key Features

- **PSR-16 Simple Cache Interface** - Standard get/set/delete/clear/has/getMultiple/setMultiple/deleteMultiple
- **Age Filtering** - Per-retrieval lifetime checks for some backends (SQL, File, Session, Hashtable, MongoDB)
- **9 Storage Backends** - Memory, File, SQL, Memcache, Redis, APCu, Session, Hashtable, MongoDB
- **Constructor Injection** - Modern dependency injection with PSR-3 logger support
- **Type Safety** - PHP 8.1+ with strict types

## Documentation

- [Changelog](doc/changelog.yml)
- [Upgrading from 3.x to 4.x](doc/UPGRADING.md)

## References

- [PSR-16: Simple Cache](https://www.php-fig.org/psr/psr-16/)
- [Horde Project](https://www.horde.org/)
