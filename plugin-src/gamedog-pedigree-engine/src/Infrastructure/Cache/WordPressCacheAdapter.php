<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Cache;

use GDPE\Application\Port\CacheInterface;

final class WordPressCacheAdapter implements CacheInterface
{
    private const CACHE_GROUP = 'gdpe';
    private const TRANSIENT_PREFIX = 'gdpe_';
    private const INDEX_OPTION_KEY = 'gdpe_cache_key_index';

    public function get(string $key, mixed $default = null): mixed
    {
        $found = false;

        $value = wp_cache_get(
            $key,
            self::CACHE_GROUP,
            false,
            $found
        );

        if ($found) {
            return $value;
        }

        $value = get_transient(
            $this->transientKey($key)
        );

        return $value === false ? $default : $value;
    }

    public function set(
        string $key,
        mixed $value,
        int $ttl = 3600
    ): void {
        wp_cache_set(
            $key,
            $value,
            self::CACHE_GROUP,
            $ttl
        );

        set_transient(
            $this->transientKey($key),
            $value,
            $ttl
        );

        $this->rememberKey($key);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): void
    {
        wp_cache_delete(
            $key,
            self::CACHE_GROUP
        );

        delete_transient(
            $this->transientKey($key)
        );

        $this->forgetKey($key);
    }

    public function clear(): void
    {
        foreach ($this->indexedKeys() as $key) {
            wp_cache_delete(
                $key,
                self::CACHE_GROUP
            );

            delete_transient(
                $this->transientKey($key)
            );
        }

        update_option(
            self::INDEX_OPTION_KEY,
            [],
            false
        );
    }

    private function transientKey(string $key): string
    {
        return self::TRANSIENT_PREFIX . md5($key);
    }

    /**
     * @return string[]
     */
    private function indexedKeys(): array
    {
        $index = get_option(
            self::INDEX_OPTION_KEY,
            []
        );

        return is_array($index)
            ? $index
            : [];
    }

    private function rememberKey(string $key): void
    {
        $index = $this->indexedKeys();

        if (!in_array($key, $index, true)) {
            $index[] = $key;

            update_option(
                self::INDEX_OPTION_KEY,
                $index,
                false
            );
        }
    }

    private function forgetKey(string $key): void
    {
        $index = array_values(
            array_diff(
                $this->indexedKeys(),
                [$key]
            )
        );

        update_option(
            self::INDEX_OPTION_KEY,
            $index,
            false
        );
    }
}