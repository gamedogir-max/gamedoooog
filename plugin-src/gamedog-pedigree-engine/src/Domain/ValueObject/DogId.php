<?php

declare(strict_types=1);

namespace GDPE\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Identifies a single dog post.
 *
 * Wraps the raw WordPress post ID so a plain `int` representing, say, a
 * generation count or a relation ID can never be passed where a dog
 * identifier is expected — the type checker catches it instead of a
 * runtime bug surfacing deep inside a pedigree tree.
 *
 * Instances are immutable and side-effect free; two DogId instances
 * wrapping the same integer are considered equal via {@see self::equals()}.
 */
final readonly class DogId
{
    /**
     * @param int $value The underlying WordPress post ID. Must be a
     *                    positive integer, since post ID 0 (or negative)
     *                    never identifies a real post in WordPress.
     *
     * @throws InvalidArgumentException If $value is not a positive integer.
     */
    public function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('DogId must be a positive integer.');
        }
    }

    /**
     * Returns the underlying WordPress post ID as a plain integer.
     *
     * Intended for boundaries that require a raw ID, such as calling
     * WordPress core functions (`get_permalink()`, `get_post()`, etc.)
     * from the Infrastructure layer.
     *
     * @return int The wrapped post ID.
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Determines whether two DogId instances refer to the same dog.
     *
     * @param self $other The DogId to compare against.
     *
     * @return bool True if both instances wrap the same post ID.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Returns the string representation of the wrapped post ID.
     *
     * Useful for building cache keys and log context without callers
     * needing to call {@see self::toInt()} and cast manually.
     *
     * @return string The post ID as a string.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}