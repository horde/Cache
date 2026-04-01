<?php

declare(strict_types=1);

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

namespace Horde\Cache;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Cache storage in a PHP session.
 *
 * Implements both SimpleCacheStorage (PSR-16 compatible TTL model) and
 * HordeCacheStorage (per-retrieval age filtering).
 *
 * Session storage tracks both creation timestamp and expiration time,
 * allowing age filtering at read time.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Cache
 */
class SessionStorage implements SimpleCacheStorage, HordeCacheStorage
{
    /**
     * Pointer to the session entry.
     */
    private ?array $session = null;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger  Logger (defaults to NullLogger)
     * @param string $sess_name        Store session data in this entry
     */
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
        private string $sess_name = 'hordecachesession'
    ) {
        $this->_initOb();
    }

    /**
     * Do initialization tasks.
     */
    protected function _initOb(): void
    {
        if (!isset($_SESSION[$this->sess_name])) {
            $_SESSION[$this->sess_name] = [];
        }
        $this->session = &$_SESSION[$this->sess_name];
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
        $timestamp = time();
        $expiration = ($ttl === 0) ? 0 : ($timestamp + $ttl);

        $this->session[$key] = [
            'data' => $data,
            'timestamp' => $timestamp,
            'expiration' => $expiration,
        ];

        $this->logger->debug(sprintf(
            'Session cache set: %s (ttl=%d)',
            $key,
            $ttl
        ));

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
        if (isset($this->session[$key])) {
            unset($this->session[$key]);
            $this->logger->debug(sprintf('Session cache delete: %s', $key));
            return true;
        }

        return false;
    }

    /**
     * Clear all cached values (PSR-16 semantics).
     *
     * @return bool Success
     */
    public function clear(): bool
    {
        $this->session = [];
        $this->logger->debug('Session cache cleared');
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
            return false;
        }

        return $this->session[$key]['data'];
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
        if (!isset($this->session[$key])) {
            return false;
        }

        $entry = $this->session[$key];
        $timestamp = time();

        // Check expiration (always)
        if ($entry['expiration'] > 0 && $entry['expiration'] <= $timestamp) {
            unset($this->session[$key]);
            return false;
        }

        // Check age filter if requested
        if ($lifetime != 0) {
            $maxage = $timestamp - $lifetime;
            if ($entry['timestamp'] < $maxage) {
                unset($this->session[$key]);
                return false;
            }
        }

        return true;
    }
}
