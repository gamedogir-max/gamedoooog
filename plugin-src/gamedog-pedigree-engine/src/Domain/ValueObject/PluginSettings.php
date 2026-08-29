<?php

declare(strict_types=1);

namespace GDPE\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Immutable value object representing the plugin's admin-configured
 * defaults (as opposed to RelationConfig, which describes a single
 * render request).
 *
 * Validates its invariants at construction time so an invalid
 * PluginSettings instance can never exist.
 */
final class PluginSettings
{
    /** @var array<int, int> */
    private const ALLOWED_GENERATIONS = [4, 5, 6, 8];

    private const MIN_CACHE_TTL_SECONDS = 0;

    /**
     * @param Generation $defaultGeneration   Default number of generations to render.
     * @param bool       $fullSiblingsEnabled Whether Full Siblings is enabled by default.
     * @param bool       $sameSireEnabled     Whether Same Sire is enabled by default.
     * @param bool       $sameDamEnabled      Whether Same Dam is enabled by default.
     * @param int        $cacheTtlSeconds     Cache lifetime for computed pedigree data, in seconds.
     *
     * @throws InvalidArgumentException If the generation depth is unsupported or the TTL is negative.
     */
    public function __construct(
        private readonly Generation $defaultGeneration,
        private readonly bool $fullSiblingsEnabled,
        private readonly bool $sameSireEnabled,
        private readonly bool $sameDamEnabled,
        private readonly int $cacheTtlSeconds,
    ) {
        if (!in_array($this->defaultGeneration->toInt(), self::ALLOWED_GENERATIONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Default generation must be one of [%s], got %d.',
                implode(', ', self::ALLOWED_GENERATIONS),
                $this->defaultGeneration->toInt(),
            ));
        }

        if ($this->cacheTtlSeconds < self::MIN_CACHE_TTL_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'Cache TTL must be %d or higher, got %d.',
                self::MIN_CACHE_TTL_SECONDS,
                $this->cacheTtlSeconds,
            ));
        }
    }

    /**
     * Returns the default number of generations to render.
     */
    public function getDefaultGeneration(): Generation
    {
        return $this->defaultGeneration;
    }

    /**
     * Returns whether Full Siblings is enabled by default.
     */
    public function isFullSiblingsEnabled(): bool
    {
        return $this->fullSiblingsEnabled;
    }

    /**
     * Returns whether Same Sire is enabled by default.
     */
    public function isSameSireEnabled(): bool
    {
        return $this->sameSireEnabled;
    }

    /**
     * Returns whether Same Dam is enabled by default.
     */
    public function isSameDamEnabled(): bool
    {
        return $this->sameDamEnabled;
    }

    /**
     * Returns the cache lifetime for computed pedigree data, in seconds.
     */
    public function getCacheTtlSeconds(): int
    {
        return $this->cacheTtlSeconds;
    }

    /**
     * Returns the hard-coded fallback settings used when no admin
     * configuration has been persisted yet.
     */
    public static function defaults(): self
    {
        return new self(
            Generation::fromInt(4),
            true,
            true,
            true,
            3600,
        );
    }
}