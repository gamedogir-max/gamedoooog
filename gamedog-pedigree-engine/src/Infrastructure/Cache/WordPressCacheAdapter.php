<?php
/**
 * Cache adapter backed by the WordPress object cache + transients.
 *
 * @package GameDog\PedigreeEngine\Infrastructure\Cache
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Infrastructure\Cache;

use GameDog\PedigreeEngine\Domain\Contract\CacheInterface;

final class WordPressCacheAdapter implements CacheInterface
{
    private const GROUP = 'gamedog_pedigree';

    /** @var string */
    private $prefix;

    public function __construct(string $prefix = 'gd_pe_')
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        $full = $this->fullKey($key);

        $found = false;
        if (function_exists('wp_cache_get')) {
            $value = wp_cache_get($full, self::GROUP, false, $found);
            if ($found) {
                return $value;
            }
        }

        if (function_exists('get_transient')) {
            $transient = get_transient($full);
            if ($transient !== false) {
                if (function_exists('wp_cache_set')) {
                    wp_cache_set($full, $transient, self::GROUP, 3600);
                }

                return $transient;
            }
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $full = $this->fullKey($key);
        $ok   = true;

        if (function_exists('wp_cache_set')) {
            $ok = (bool) wp_cache_set($full, $value, self::GROUP, $ttl > 0 ? $ttl : 0);
        }

        if (function_exists('set_transient')) {
            // Transients require a positive TTL; fall back to 1 day.
            $day          = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
            $transientTtl = $ttl > 0 ? $ttl : $day;
            $ok           = (bool) set_transient($full, $value, $transientTtl) && $ok;
        }

        // Track key for prefix invalidation.
        $this->rememberKey($full);

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $full = $this->fullKey($key);
        $ok   = true;

        if (function_exists('wp_cache_delete')) {
            $ok = (bool) wp_cache_delete($full, self::GROUP) && $ok;
        }

        if (function_exists('delete_transient')) {
            $ok = (bool) delete_transient($full) && $ok;
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $sentinel = new \stdClass();
        $value    = $this->get($key, $sentinel);

        return $value !== $sentinel;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByPrefix(string $prefix): void
    {
        $fullPrefix = $this->fullKey($prefix);
        $indexKey   = $this->prefix . '_key_index';

        $keys = [];
        if (function_exists('get_option')) {
            $stored = get_option($indexKey, []);
            if (is_array($stored)) {
                $keys = $stored;
            }
        }

        foreach ($keys as $fullKey) {
            if (strpos((string) $fullKey, $fullPrefix) === 0) {
                if (function_exists('wp_cache_delete')) {
                    wp_cache_delete($fullKey, self::GROUP);
                }
                if (function_exists('delete_transient')) {
                    delete_transient($fullKey);
                }
            }
        }
    }

    private function fullKey(string $key): string
    {
        // Transient keys have a 172 char limit; keep them compact.
        $full = $this->prefix . $key;
        if (strlen($full) > 160) {
            $full = $this->prefix . md5($key);
        }

        return $full;
    }

    private function rememberKey(string $fullKey): void
    {
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return;
        }

        $indexKey = $this->prefix . '_key_index';
        $keys     = get_option($indexKey, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($fullKey, $keys, true)) {
            $keys[] = $fullKey;
            // Cap index size to avoid unbounded growth.
            if (count($keys) > 500) {
                $keys = array_slice($keys, -400);
            }
            update_option($indexKey, $keys, false);
        }
    }
}
