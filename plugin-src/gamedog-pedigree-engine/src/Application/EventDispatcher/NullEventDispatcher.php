<?php

declare(strict_types=1);

namespace GDPE\Application\EventDispatcher;

/**
 * No-op EventDispatcherInterface implementation.
 *
 * Serves as the default binding until an Infrastructure-level
 * dispatcher (e.g. one bridging to WordPress hooks) is registered,
 * so Application use cases never require a concrete event backend
 * to function.
 */
final class NullEventDispatcher implements EventDispatcherInterface
{
    /**
     * Intentionally does nothing.
     *
     * @param object $event Event instance to dispatch.
     */
    public function dispatch(object $event): void
    {
        // No-op by design.
    }
}