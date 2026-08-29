<?php

declare(strict_types=1);

namespace GDPE\Application\Validator;

use GDPE\Application\DTO\PluginSettingsDTO;
use InvalidArgumentException;

/**
 * Validates a PluginSettingsDTO's primitive values before it is
 * mapped into the Domain PluginSettings value object.
 *
 * Fails fast on invalid input so construction errors surface as a
 * single, clear validation exception rather than a Domain-level
 * exception thrown deep inside the mapper.
 */
final class PluginSettingsValidator
{
    /** @var array<int, int> */
    private const ALLOWED_GENERATIONS = [4, 5, 6, 8];

    private const MIN_CACHE_TTL_SECONDS = 0;

    /**
     * Validates the given DTO.
     *
     * @param PluginSettingsDTO $dto DTO to validate.
     *
     * @throws InvalidArgumentException If any field is invalid.
     */
    public function validate(PluginSettingsDTO $dto): void
    {
        if (!in_array($dto->defaultGeneration, self::ALLOWED_GENERATIONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Default generation must be one of [%s], got %d.',
                implode(', ', self::ALLOWED_GENERATIONS),
                $dto->defaultGeneration,
            ));
        }

        if ($dto->cacheTtlSeconds < self::MIN_CACHE_TTL_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'Cache TTL must be %d or higher, got %d.',
                self::MIN_CACHE_TTL_SECONDS,
                $dto->cacheTtlSeconds,
            ));
        }
    }
}