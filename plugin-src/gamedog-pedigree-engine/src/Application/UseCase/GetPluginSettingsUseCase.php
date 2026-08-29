<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Domain\Repository\SettingsRepositoryInterface;
use GDPE\Domain\ValueObject\PluginSettings;

/**
 * Retrieves the currently persisted plugin settings.
 */
final class GetPluginSettingsUseCase
{
    public function __construct(private readonly SettingsRepositoryInterface $settingsRepository)
    {
    }

    /**
     * Returns the current plugin settings.
     */
    public function execute(): PluginSettings
    {
        return $this->settingsRepository->get();
    }
}