<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Cache;

use RuntimeException;

/**
 * Exception thrown when a cache operation is not supported by the storage backend.
 *
 * For example, per-retrieval age filtering (getWithLifetime) is only supported
 * by SQL and File backends, not by Redis/Memcache/APCu.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
class UnsupportedOperationException extends RuntimeException {}
