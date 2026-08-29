<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\EventDispatcher;

use GDPE\Application\EventDispatcher\EventDispatcherInterface;

/**
 * Bridges Application-layer events onto WordPress's action hook
 * system via do_action(), so themes/other plugins can react to
 * GDPE events using standard WordPress conventions.
 *
 * The hook name is derived deterministically from the event's short
 * class name, converted to snake_case and prefixed, so
 * PluginSettingsUpdatedEvent fires "gdpe_plugin_settings_updated".
 */
final class WordPressEventDispatcher implements EventDispatcherInterface
{
    private const HOOK_PREFIX = 'gdpe_';

    private const EVENT_SUFFIX = 'Event';

    /**
     * Dispatches the given event via do_action(), passing the event
     * object itself as the hook argument.
     *
     * @param object $event Event instance to dispatch.
     */
    public function dispatch(object $event): void
    {
        do_action($this->hookNameFor($event), $event);
    }

    /**
     * Derives the WordPress hook name for the given event object.
     *
     * @param object $event Event instance.
     */
    private function hookNameFor(object $event): string
    {
        $shortName = $this->shortClassName($event::class);

        if (str_ends_with($shortName, self::EVENT_SUFFIX)) {
            $shortName = substr($shortName, 0, -strlen(self::EVENT_SUFFIX));
        }

        return self::HOOK_PREFIX . $this->toSnakeCase($shortName);
    }

    /**
     * Extracts the short (unqualified) class name from an FQCN.
     *
     * @param string $fqcn Fully qualified class name.
     */
    private function shortClassName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false ? $fqcn : substr($fqcn, $position + 1);
    }

    /**
     * Converts a PascalCase/camelCase string into snake_case.
     *
     * @param string $value Input string.
     */
    private function toSnakeCase(string $value): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

        return strtolower($snake ?? $value);
    }
}