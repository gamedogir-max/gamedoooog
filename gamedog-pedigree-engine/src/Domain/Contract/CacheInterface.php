<?php
/**
 * Simple cache port used by application / infrastructure layers.
 *
 * @package GameDog\PedigreeEngine\Domain\Contract
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Contract;

interface CacheInterface
{
    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl Seconds. 0 = no expiry (adapter-dependent).
     */
    public function set(string $key, $value, int $ttl = 3600): bool;

    public function delete(string $key): bool;

    public function has(string $key): bool;

    /**
     * Delete every key matching a prefix (best-effort).
     */
    public function deleteByPrefix(string $prefix): void;
}
