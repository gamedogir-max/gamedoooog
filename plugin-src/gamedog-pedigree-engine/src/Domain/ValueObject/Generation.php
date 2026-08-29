<?php

declare(strict_types=1);

namespace GDPE\Domain\ValueObject;

use DomainException;

/**
 * Represents how many generations of ancestors a pedigree tree should
 * display.
 *
 * The UI (Elementor widget control) only ever offers a fixed set of depths
 * — 4, 5, 6, or 8 generations — because deeper trees grow exponentially
 * (2^n ancestor slots) and unrestricted values would let an editor
 * accidentally request a tree expensive enough to degrade page load time.
 * Restricting the allowed values here, at the domain boundary, means
 * every layer that consumes a Generation can trust it is always safe to
 * build without re-validating it.
 *
 * Instances are immutable: once constructed, a Generation cannot change
 * value, so it is safe to share across a request without defensive
 * copying. Construction only happens through {@see self::fromInt()}; the
 * class exposes no other way to obtain an instance with an invalid value.
 */
final readonly class Generation
{
    /**
     * The only generation depths the pedigree tree builder supports.
     *
     * @var int[]
     */
    private const ALLOWED = [4, 5, 6, 8];

    /**
     * Private to force construction through {@see self::fromInt()}, which
     * gives the validation failure a descriptive, intention-revealing
     * entry point instead of a bare `new Generation(...)` call.
     *
     * @param int $value One of the allowed generation depths (4, 5, 6, or 8).
     *                    Callers must validate via {@see self::fromInt()};
     *                    this constructor assumes the value is already valid.
     */
    private function __construct(private int $value)
    {
    }

    /**
     * Creates a Generation from a raw integer, validating it against the
     * set of supported pedigree depths.
     *
     * This is the only way to obtain a Generation instance, so any code
     * holding one can trust its value is always safe to use.
     *
     * @param int $value The requested generation depth.
     *
     * @return self A Generation wrapping the validated depth.
     *
     * @throws DomainException If $value is not one of the allowed
     *                          generation depths.
     */
    public static function fromInt(int $value): self
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new DomainException(
                sprintf(
                    'Generation must be one of: %s. Received: %d.',
                    implode(', ', self::ALLOWED),
                    $value
                )
            );
        }

        return new self($value);
    }

    /**
     * Returns the full list of generation depths the plugin supports.
     *
     * Intended for populating UI controls (e.g. the Elementor widget's
     * generation-count dropdown) without duplicating the allowed values
     * in the Presentation layer.
     *
     * @return int[] The allowed generation depths, in ascending order.
     */
    public static function allowedValues(): array
    {
        return self::ALLOWED;
    }

    /**
     * Returns the generation depth as a plain integer.
     *
     * Intended for boundaries that need a raw depth value, such as loop
     * bounds inside the pedigree tree builder or an Elementor control's
     * saved setting.
     *
     * @return int The validated generation depth.
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Determines whether two Generation instances represent the same depth.
     *
     * @param self $other The Generation to compare against.
     *
     * @return bool True if both instances wrap the same depth value.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Returns the string representation of the wrapped generation depth.
     *
     * Useful for building cache keys and log context without callers
     * needing to call {@see self::toInt()} and cast manually.
     *
     * @return string The generation depth as a string.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}