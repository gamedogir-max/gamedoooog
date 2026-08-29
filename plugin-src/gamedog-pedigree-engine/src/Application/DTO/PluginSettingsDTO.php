<?php

declare(strict_types=1);

namespace GDPE\Application\DTO;

/**
 * Data Transfer Object carrying plugin settings as primitive values
 * across the Application layer boundary (e.g. from an admin form
 * submission), before being validated and mapped into the Domain
 * PluginSettings value object.
 */
final class PluginSettingsDTO
{
    /**
     * @param int  $defaultGeneration     Default number of generations to render.
     * @param bool $fullSiblingsEnabled   Whether Full Siblings is enabled by default.
     * @param bool $sameSireEnabled       Whether Same Sire is enabled by default.
     * @param bool $sameDamEnabled        Whether Same Dam is enabled by default.
     * @param int  $cacheTtlSeconds       Cache lifetime for computed pedigree data.
     */
    public function __construct(
        public readonly int $defaultGeneration,
        public readonly bool $fullSiblingsEnabled,
        public readonly bool $sameSireEnabled,
        public readonly bool $sameDamEnabled,
        public readonly int $cacheTtlSeconds,
    ) {
    }
}