<?php
/**
 * Strongly-typed dog identifier value object.
 *
 * @package GameDog\PedigreeEngine\Domain\ValueObject
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\ValueObject;

final class DogId
{
    /** @var int */
    private $value;

    /**
     * @param int $value Positive WordPress post ID.
     *
     * @throws \InvalidArgumentException When value is not a positive integer.
     */
    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('DogId must be a positive integer.');
        }

        $this->value = $value;
    }

    /**
     * Safe factory that returns null for invalid / empty identifiers.
     *
     * @param mixed $value Raw identifier.
     */
    public static function fromMixed($value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (is_numeric($value)) {
            $int = (int) $value;
            if ($int > 0) {
                return new self($int);
            }
        }

        return null;
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function equals(DogId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
