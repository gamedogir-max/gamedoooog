<?php

declare(strict_types=1);

namespace GDPE\Application\EventDispatcher;

/**
 * Application-level port for dispatching domain/application events
 * to any interested listeners.
 *
 * Implementations live in the Infrastructure layer (e.g. bridging to
 * WordPress's do_action()) or, as with NullEventDispatcher, provide
 * a no-op default so use cases never depend on a concrete backend.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatches the given event object to any registered listeners.
     *
     * @param object $event Event instance to dispatch.
     */
    public function dispatch(object $event): void;
}