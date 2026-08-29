<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Domain\Repository\SettingsRepositoryInterface;
use GDPE\Domain\ValueObject\PluginSettings;

/**
 * Persists updated plugin settings and invalidates the pedigree
 * cache, since a settings change (e.g. default generation depth,
 * cache TTL, enabled relations) can affect previously cached
 * results.
 */
final class SavePluginSettingsUseCase
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settingsRepository,
        private readonly ClearPedigreeCacheUseCase $clearPedigreeCacheUseCase,
    ) {
    }

    /**
     * Saves the given settings and flushes the pedigree cache.
     *
     * @param PluginSettings $settings Settings to persist.
     */
    public function execute(PluginSettings $settings): void
    {
        $this->settingsRepository->save($settings);
        $this->clearPedigreeCacheUseCase->execute();
    }
}