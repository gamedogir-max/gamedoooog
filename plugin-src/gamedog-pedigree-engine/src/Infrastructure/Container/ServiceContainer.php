<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Container;

use RuntimeException;

/**
 * Minimal service container: binds an interface/class name to a
 * factory closure and lazily instantiates + memoizes it on first
 * use. Deliberately small — this is plumbing, not a feature, and
 * exists only so ServiceProviders can wire the Infrastructure layer
 * without the Domain/Application layers ever referencing concrete
 * classes.
 */
final class ServiceContainer
{
    /** @var array<string, callable(self):mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Registers a factory for the given identifier (typically an
     * interface's fully-qualified class name).
     *
     * @param string             $id      Identifier, usually an interface FQCN.
     * @param callable(self):mixed $factory Factory receiving this container.
     */
    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Resolves the given identifier, instantiating and memoizing it
     * on first call.
     *
     * @param string $id Identifier to resolve.
     *
     * @throws RuntimeException If no factory was registered for $id.
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException(sprintf('No service registered for "%s".', $id));
        }

        $instance = ($this->factories[$id])($this);
        $this->instances[$id] = $instance;

        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}

