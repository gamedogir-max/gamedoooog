<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Settings;

use GDPE\Domain\ValueObject\PluginSettings;

/**
 * Builds the plugin's default PluginSettings value object.
 *
 * Delegates to PluginSettings::defaults() so the single source of
 * truth for default values stays in the Domain layer, while giving
 * Infrastructure consumers (e.g. activation hooks) an injectable
 * class rather than a static call.
 */
final class DefaultPluginSettingsFactory
{
    /**
     * Creates the default plugin settings.
     */
    public function create(): PluginSettings
    {
        return PluginSettings::defaults();
    }
}