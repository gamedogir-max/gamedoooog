<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Repository;

use GDPE\Domain\Repository\SettingsRepositoryInterface;
use GDPE\Domain\ValueObject\Generation;
use GDPE\Domain\ValueObject\PluginSettings;
use GDPE\Infrastructure\Config\JetEngineFieldMap;

/**
 * Infrastructure adapter implementing SettingsRepositoryInterface on
 * top of the WordPress options API (get_option/update_option).
 *
 * All (de)serialization of PluginSettings to a plain array lives
 * here; the Domain layer never handles arrays or WordPress options.
 */
final class WordPressSettingsRepository implements SettingsRepositoryInterface
{
    public function get(): PluginSettings
    {
        $stored = get_option(JetEngineFieldMap::OPTION_KEY_SETTINGS, false);

        if (!is_array($stored)) {
            return PluginSettings::defaults();
        }

        return new PluginSettings(
            Generation::fromInt((int) ($stored['default_generation'] ?? 4)),
            (bool) ($stored['full_siblings_enabled'] ?? true),
            (bool) ($stored['same_sire_enabled'] ?? true),
            (bool) ($stored['same_dam_enabled'] ?? true),
            (int) ($stored['cache_ttl_seconds'] ?? 3600),
        );
    }

    public function save(PluginSettings $settings): void
    {
        update_option(
            JetEngineFieldMap::OPTION_KEY_SETTINGS,
            [
                'default_generation' => $settings->getDefaultGeneration()->toInt(),
                'full_siblings_enabled' => $settings->isFullSiblingsEnabled(),
                'same_sire_enabled' => $settings->isSameSireEnabled(),
                'same_dam_enabled' => $settings->isSameDamEnabled(),
                'cache_ttl_seconds' => $settings->getCacheTtlSeconds(),
            ],
            true,
        );
    }
}