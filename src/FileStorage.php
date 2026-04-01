<?php

declare(strict_types=1);

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Anil Madhavapeddy <anil@recoil.org>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use DirectoryIterator;
use Horde\Util\Util;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

/**
 * Cache storage in the filesystem.
 *
 * Implements both SimpleCacheStorage (PSR-16 compatible TTL model) and
 * HordeCacheStorage (per-retrieval age filtering).
 *
 * @author    Anil Madhavapeddy <anil@recoil.org>
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 1999-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class FileStorage implements SimpleCacheStorage, HordeCacheStorage
{
    /* Location of the garbage collection data file. */
    public const GC_FILE = 'horde_cache_gc';

    /**
     * List of key to filename mappings.
     */
    private array $file = [];

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger  Logger (defaults to NullLogger)
     * @param string $dir              Base directory to store cache files
     * @param string $prefix           Filename prefix for cache files
     * @param int $sub                 Number of subdirectories to create (0 = none)
     * @param bool $no_gc              If true, don't perform garbage collection
     * @param int|null $umask          Optional umask for file permissions
     */
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
        private string $dir = '',
        private string $prefix = 'cache_',
        private int $sub = 0,
        private bool $no_gc = false,
        private ?int $umask = null
    ) {
        if ($this->dir === '' || !@is_dir($this->dir)) {
            $this->dir = sys_get_temp_dir();
        }
    }

    /**
     * Destructor - garbage collection.
     */
    public function __destruct()
    {
        $c_time = time();

        /* Only do garbage collection 0.1% of the time we create an object. */
        if ($this->no_gc || (intval(substr((string) $c_time, -3)) !== 0)) {
            return;
        }

        $this->_gc();
    }

    // ========== SimpleCacheStorage Interface (PSR-16 Compatible) ==========

    /**
     * Get cached value (PSR-16 semantics).
     *
     * Checks expiration only, no age filtering.
     *
     * @param string $key  Cache key
     * @return mixed|false Value or false if not found/expired
     */
    public function get(string $key)
    {
        // Delegate to Horde method with lifetime=0 (no age filtering)
        return $this->getWithLifetime($key, 0);
    }

    /**
     * Check existence (PSR-16 semantics).
     *
     * @param string $key  Cache key
     * @return bool True if exists and not expired
     */
    public function has(string $key): bool
    {
        return $this->hasWithLifetime($key, 0);
    }

    /**
     * Store value with TTL (PSR-16 semantics).
     *
     * @param string $key   Cache key
     * @param mixed $data   Data to store
     * @param int $ttl      Seconds until expiration (0 = never)
     * @return bool Success
     */
    public function set(string $key, mixed $data, int $ttl): bool
    {
        $filename = $this->_keyToFile($key, true);
        $tmpfile = Util::getTempFile('HordeCache', true, $this->dir);

        if ($this->umask !== null) {
            chmod($tmpfile, 0o666 & ~$this->umask);
        }

        if (file_put_contents($tmpfile, $data) === false) {
            $this->logger->error(sprintf(
                'Cannot write to cache directory %s',
                $this->dir
            ));
            return false;
        }

        @rename($tmpfile, $filename);

        if ($ttl > 0
            && ($fp = @fopen(dirname($filename) . '/' . self::GC_FILE, 'a'))) {
            // This may result in duplicate entries in GC_FILE, but we
            // will take care of these whenever we do GC and this is quicker
            // than having to check every time we access the file.
            fwrite($fp, $filename . "\t" . (time() + $ttl) . "\n");
            fclose($fp);
        }

        $this->logger->debug(sprintf('File cache set: %s', $key));
        return true;
    }

    /**
     * Delete cached value (PSR-16 semantics).
     *
     * @param string $key  Cache key
     * @return bool Success
     */
    public function delete(string $key): bool
    {
        $this->logger->debug(sprintf('File cache delete: %s', $key));
        return @unlink($this->_keyToFile($key));
    }

    /**
     * Clear all cached values (PSR-16 semantics).
     *
     * @return bool Success
     */
    public function clear(): bool
    {
        foreach ($this->_getCacheFiles() as $val) {
            @unlink($val);
        }
        foreach ($this->_getGCFiles() as $val) {
            @unlink($val);
        }

        $this->logger->debug('File cache cleared');
        return true;
    }

    // ========== HordeCacheStorage Interface (Age Filtering) ==========

    /**
     * Get cached value with per-retrieval age filtering (Horde semantics).
     *
     * @param string $key       Cache key
     * @param int $lifetime     Only return if cached within last N seconds (0 = no age check)
     * @return mixed|false      Value or false if not found/too old
     */
    public function getWithLifetime(string $key, int $lifetime)
    {
        if (!$this->hasWithLifetime($key, $lifetime)) {
            /* Nothing cached, return failure. */
            return false;
        }

        $filename = $this->_keyToFile($key);
        $size = filesize($filename);

        return $size
            ? @file_get_contents($filename)
            : '';
    }

    /**
     * Check existence with per-retrieval age filtering (Horde semantics).
     *
     * @param string $key       Cache key
     * @param int $lifetime     Only return true if cached within last N seconds (0 = no age check)
     * @return bool             True if exists and not too old
     */
    public function hasWithLifetime(string $key, int $lifetime): bool
    {
        $filename = $this->_keyToFile($key);

        /* Key exists in the cache */
        if (file_exists($filename)) {
            /* 0 means no expire.
             * Also, If the file was been created after the supplied value,
             * the data is valid (fresh). */
            if (($lifetime == 0)
                || (time() - $lifetime <= filemtime($filename))) {
                return true;
            }

            @unlink($filename);
        }

        return false;
    }

    // ========== Internal Methods ==========

    /**
     * Return a list of cache files.
     *
     * @param string|null $start  The directory to start searching
     * @return array  Pathnames to cache files
     */
    protected function _getCacheFiles(?string $start = null): array
    {
        $paths = [];

        try {
            $it = empty($this->sub)
                ? new DirectoryIterator($this->dir)
                : new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($start ?: $this->dir),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
        } catch (UnexpectedValueException $e) {
            return $paths;
        }

        foreach ($it as $val) {
            if (!$val->isDir()
                && ($fname = $val->getFilename())
                && (strpos($fname, $this->prefix) === 0)) {
                $paths[$fname] = $val->getPathname();
            }
        }

        return $paths;
    }

    /**
     * Return a list of GC indexes.
     *
     * @return array  Pathnames to GC indexes
     */
    protected function _getGCFiles(): array
    {
        $glob = $this->dir;
        if (!empty($this->sub)) {
            $glob .= '/'
                . implode('/', array_fill(0, $this->sub, '*'));
        }
        $glob .= '/' . self::GC_FILE;
        return glob($glob) ?: [];
    }

    /**
     * Map a cache key to a unique filename.
     *
     * @param string $key     Cache key
     * @param bool $create    Create path if it doesn't exist?
     * @return string  Fully qualified filename
     */
    protected function _keyToFile(string $key, bool $create = false): string
    {
        if ($create || !isset($this->file[$key])) {
            $dir = $this->dir . '/';
            $md5 = hash('md5', $key);
            $sub = '';

            if (!empty($this->sub)) {
                $max = min($this->sub, strlen($md5));
                for ($i = 0; $i < $max; $i++) {
                    $sub .= $md5[$i];
                    if ($create && !is_dir($dir . $sub)) {
                        if (!mkdir($dir . $sub)) {
                            $sub = '';
                            break;
                        }
                    }
                    $sub .= '/';
                }
            }

            $this->file[$key] = $dir . $sub . $this->prefix . $md5;
        }

        return $this->file[$key];
    }

    /**
     * Garbage collector.
     */
    protected function _gc(): void
    {
        $c_time = time();
        if (!empty($this->sub)
            && (file_exists($this->dir . '/' . self::GC_FILE))) {
            // If we cannot migrate, we cannot GC either, because we expect the
            // new format.
            try {
                $this->_migrateGc();
            } catch (Exception $e) {
                return;
            }
        }

        foreach ($this->_getGCFiles() as $filename) {
            $excepts = [];
            if (is_readable($filename)) {
                $fp = fopen($filename, 'r');
                while (!feof($fp) && ($data = fgets($fp))) {
                    $parts = explode("\t", trim($data), 2);
                    $excepts[$parts[0]] = $parts[1];
                }
                fclose($fp);
            }

            foreach ($this->_getCacheFiles(dirname($filename)) as $pname) {
                if (!empty($excepts[$pname])
                    && ($c_time > $excepts[$pname])) {
                    @unlink($pname);
                    unset($excepts[$pname]);
                }
            }

            if ($fp = @fopen($filename, 'w')) {
                foreach ($excepts as $key => $val) {
                    fwrite($fp, $key . "\t" . $val . "\n");
                }
                fclose($fp);
            }
        }
    }

    /**
     * Migrates single GC indexes to per-directory indexes.
     */
    protected function _migrateGc(): void
    {
        // Read the old GC index.
        $filename = $this->dir . '/' . self::GC_FILE;
        if (!is_readable($filename)) {
            return;
        }

        $fhs = [];
        $fp = fopen($filename, 'r');
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new Exception('Cannot acquire lock for old garbage collection index');
        }

        // Loops through all cached files from the old index and write their GC
        // information to the new GC indexes.
        while (!feof($fp) && ($data = fgets($fp))) {
            [$path, $time] = explode("\t", trim($data), 2);
            $dir = dirname($path);
            if ($dir == $this->dir) {
                continue;
            }
            if (!isset($fhs[$dir])) {
                $fhs[$dir] = @fopen($dir . '/' . self::GC_FILE, 'a');
                // Maybe too many open file handles?
                if (!$fhs[$dir] && count($fhs)) {
                    unset($fhs[$dir]);
                    foreach ($fhs as $fh) {
                        fclose($fh);
                    }
                    $fhs = [];
                    $fhs[$dir] = @fopen($dir . '/' . self::GC_FILE, 'a');
                }
                if (!$fhs[$dir]) {
                    fclose($fp);
                    throw new Exception('Cannot migrate to new garbage collection index format');
                }
            }
            fwrite($fhs[$dir], $path . "\t" . $time . "\n");
        }

        // Clean up.
        foreach ($fhs as $fh) {
            fclose($fh);
        }
        fclose($fp);
        unlink($filename);
    }
}
