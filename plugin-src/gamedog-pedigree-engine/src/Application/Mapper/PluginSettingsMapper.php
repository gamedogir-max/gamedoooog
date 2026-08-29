<?php

declare(strict_types=1);

namespace GDPE\Application\Mapper;

use GDPE\Application\DTO\PluginSettingsDTO;
use GDPE\Domain\ValueObject\Generation;
use GDPE\Domain\ValueObject\PluginSettings;

/**
 * Maps between the Application-layer PluginSettingsDTO (primitives)
 * and the Domain PluginSettings value object.
 */
final class PluginSettingsMapper
{
    /**
     * Maps a validated DTO into a Domain PluginSettings instance.
     *
     * @param PluginSettingsDTO $dto Source DTO. Must already be validated.
     */
    public function toDomain(PluginSettingsDTO $dto): PluginSettings
    {
        return new PluginSettings(
            Generation::fromInt($dto->defaultGeneration),
            $dto->fullSiblingsEnabled,
            $dto->sameSireEnabled,
            $dto->sameDamEnabled,
            $dto->cacheTtlSeconds,
        );
    }

    /**
     * Maps a Domain PluginSettings instance back into a DTO, e.g. to
     * pre-fill an admin settings form.
     *
     * @param PluginSettings $settings Source Domain value object.
     */
    public function toDto(PluginSettings $settings): PluginSettingsDTO
    {
        return new PluginSettingsDTO(
            $settings->getDefaultGeneration()->toInt(),
            $settings->isFullSiblingsEnabled(),
            $settings->isSameSireEnabled(),
            $settings->isSameDamEnabled(),
            $settings->getCacheTtlSeconds(),
        );
    }
}