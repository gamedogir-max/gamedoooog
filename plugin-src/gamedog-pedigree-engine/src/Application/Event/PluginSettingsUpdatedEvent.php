<?php

declare(strict_types=1);

namespace GDPE\Application\Event;

use GDPE\Domain\ValueObject\PluginSettings;

/**
 * Immutable event raised after plugin settings have been
 * successfully validated, persisted, and had the pedigree cache
 * cleared.
 */
final class PluginSettingsUpdatedEvent
{
    /**
     * @param PluginSettings $settings The newly persisted settings.
     */
    public function __construct(private readonly PluginSettings $settings)
    {
    }

    /**
     * Returns the newly persisted settings.
     */
    public function getSettings(): PluginSettings
    {
        return $this->settings;
    }
}