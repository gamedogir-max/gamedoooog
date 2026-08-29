<?php

declare(strict_types=1);

namespace GDPE\Domain\Repository;

use GDPE\Domain\ValueObject\PluginSettings;

interface SettingsRepositoryInterface
{
    public function get(): PluginSettings;

    public function save(PluginSettings $settings): void;
}