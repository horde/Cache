<?php
/**
 * Copyright 1999-2021 Horde LLC (http://www.horde.org/)
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

use Horde_Util;
use DirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use UnexpectedValueException;
use fopen;
use fwrite;
use fclose;
use is_readable;
use time;
use unlink;
use sys_get_temp_dir;
use substr;

/**
 * Cache storage in the filesystem.
 *
 * @author    Anil Madhavapeddy <anil@recoil.org>
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 1999-2021 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class FileStorage extends BaseStorage
{
    /* Location of the garbage collection data file. */
    public const GC_FILE = 'horde_cache_gc';

    /**
     * List of key to filename mappings.
     *
     * @var array
     */
    protected $file = [];

    /**
     * Constructor.
     *
     * @param array $params  Optional parameters:
     * <pre>
     *   - dir: (string) The base directory to store the cache files in.
     *          DEFAULT: System default
     *   - no_gc: (boolean) If true, don't perform garbage collection.
     *            DEFAULT: false
     *   - prefix: (string) The filename prefix to use for the cache files.
     *             DEFAULT: 'cache_'
     *   - sub: (integer) If non-zero, the number of subdirectories to create
     *          to store the file (i.e. PHP's session.save_path).
     *          DEFAULT: 0
     * </pre>
     */
    public function __construct(array $params = [])
    {
        $params = array_merge([
            'prefix' => 'cache_',
            'sub' => 0,
        ], $params);

        if (!isset($params['dir']) || !@is_dir($params['dir'])) {
            $params['dir'] = sys_get_temp_dir();
        }

        parent::__construct($params);
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $c_time = time();

        /* Only do garbage collection 0.1% of the time we create an object. */
        if (!empty($this->params['no_gc']) ||
            (intval(substr((string) $c_time, -3)) !== 0)) {
            return;
        }

        $this->_gc();
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, int $lifetime = 0)
    {
        if (!$this->exists($key, $lifetime)) {
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
     * @inheritDoc
     */
    public function set(string $key, $data, int $lifetime = 0)
    {
        $filename = $this->_keyToFile($key, true);
        $tmpfile = Horde_Util::getTempFile('HordeCache', true, $this->params['dir']);
        if (isset($this->params['umask'])) {
            chmod($tmpfile, 0666 & ~ (int)$this->params['umask']);
        }

        if (file_put_contents($tmpfile, $data) === false) {
            throw new Exception('Cannot write to cache directory ' . $this->params['dir']);
        }

        @rename($tmpfile, $filename);

        if ($lifetime &&
            ($fp = @fopen(dirname($filename) . '/' . self::GC_FILE, 'a'))) {
            // This may result in duplicate entries in GC_FILE, but we
            // will take care of these whenever we do GC and this is quicker
            // than having to check every time we access the file.
            fwrite($fp, $filename . "\t" . (time() + $lifetime) . "\n");
            fclose($fp);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists($key, $lifetime = 0): bool
    {
        $filename = $this->_keyToFile($key);

        /* Key exists in the cache */
        if (file_exists($filename)) {
            /* 0 means no expire.
             * Also, If the file was been created after the supplied value,
             * the data is valid (fresh). */
            if (($lifetime == 0) ||
                (time() - $lifetime <= filemtime($filename))) {
                return true;
            }

            @unlink($filename);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key): bool
    {
        return @unlink($this->_keyToFile($key));
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        foreach ($this->_getCacheFiles() as $val) {
            @unlink($val);
        }
        foreach ($this->_getGCFiles() as $val) {
            @unlink($val);
        }
    }

    /**
     * Return a list of cache files.
     *
     * @param string $start  The directory to start searching.
     *
     * @return array  Pathnames to cache files.
     */
    protected function _getCacheFiles($start = null)
    {
        $paths = [];

        try {
            $it = empty($this->params['sub'])
                ? new DirectoryIterator($this->params['dir'])
                : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($start ?: $this->params['dir']), RecursiveIteratorIterator::CHILD_FIRST);
        } catch (UnexpectedValueException $e) {
            return $paths;
        }

        foreach ($it as $val) {
            if (!$val->isDir() &&
                ($fname = $val->getFilename()) &&
                (strpos($fname, $this->params['prefix']) === 0)) {
                $paths[$fname] = $val->getPathname();
            }
        }

        return $paths;
    }

    /**
     * Return a list of GC indexes.
     *
     * @return array  Pathnames to GC indexes.
     */
    protected function _getGCFiles()
    {
        $glob = $this->params['dir'];
        if (!empty($this->params['sub'])) {
            $glob .= '/'
                . implode('/', array_fill(0, $this->params['sub'], '*'));
        }
        $glob .= '/' . self::GC_FILE;
        return glob($glob);
    }

    /**
     * Map a cache key to a unique filename.
     *
     * @param string $key     Cache key.
     * @param string|bool $create  Create path if it doesn't exist?
     *
     * @return string  Fully qualified filename.
     */
    protected function _keyToFile(string $key, $create = false): string
    {
        if ($create || !isset($this->file[$key])) {
            $dir = $this->params['dir'] . '/';
            $md5 = hash('md5', $key);
            $sub = '';

            if (!empty($this->params['sub'])) {
                $max = min($this->params['sub'], strlen($md5));
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

            $this->file[$key] = $dir . $sub . $this->params['prefix'] . $md5;
        }

        return $this->file[$key];
    }

    /**
     * Garbage collector.
     */
    protected function _gc()
    {
        $c_time = time();
        if (!empty($this->params['sub']) &&
            (file_exists($this->params['dir'] . '/' . self::GC_FILE))) {
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
            }

            foreach ($this->_getCacheFiles(dirname($filename)) as $pname) {
                if (!empty($excepts[$pname]) &&
                    ($c_time > $excepts[$pname])) {
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
    protected function _migrateGc()
    {
        // Read the old GC index.
        $filename = $this->params['dir'] . '/' . self::GC_FILE;
        if (!is_readable($filename)) {
            return;
        }

        $fhs = [];
        $fp = fopen($filename, 'r');
        if (!flock($fp, LOCK_EX)) {
            throw new Exception('Cannot acquire lock for old garbage collection index');
        }

        // Loops through all cached files from the old index and write their GC
        // information to the new GC indexes.
        while (!feof($fp) && ($data = fgets($fp))) {
            [$path, $time] = explode("\t", trim($data), 2);
            $dir = dirname($path);
            if ($dir == $this->params['dir']) {
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
