<?php
/**
 * Coefficient of Inbreeding percentage value object.
 *
 * Stores Wright's F_X both as a float ratio (0..1) and as a display percentage.
 *
 * @package GameDog\PedigreeEngine\Domain\ValueObject
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\ValueObject;

final class CoiPercentage
{
    /** @var float Ratio in range [0, 1]. */
    private $ratio;

    /**
     * @param float $ratio Inbreeding coefficient as a fraction (0 = none, 1 = 100%).
     */
    public function __construct(float $ratio)
    {
        if ($ratio < 0.0) {
            $ratio = 0.0;
        }

        // Guard against floating point noise slightly above 1.
        if ($ratio > 1.0) {
            $ratio = 1.0;
        }

        $this->ratio = $ratio;
    }

    /**
     * Build from a percentage number (e.g. 12.5 means 12.5%).
     */
    public static function fromPercent(float $percent): self
    {
        return new self($percent / 100.0);
    }

    /**
     * Parse stored meta such as "12.50%" or "12.5".
     */
    public static function fromStored($raw): self
    {
        if ($raw instanceof self) {
            return $raw;
        }

        if ($raw === null || $raw === '' || $raw === false) {
            return self::zero();
        }

        if (is_numeric($raw)) {
            $num = (float) $raw;
            // Values > 1 are treated as percentage points.
            return $num > 1.0 ? self::fromPercent($num) : new self($num);
        }

        $cleaned = str_replace(['%', ' '], '', (string) $raw);
        if (!is_numeric($cleaned)) {
            return self::zero();
        }

        return self::fromPercent((float) $cleaned);
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public function ratio(): float
    {
        return $this->ratio;
    }

    public function percent(): float
    {
        return round($this->ratio * 100.0, 2);
    }

    /**
     * Formatted percentage string, always two decimal places (e.g. "6.25%").
     */
    public function formatted(): string
    {
        return number_format($this->percent(), 2, '.', '') . '%';
    }

    public function isZero(): bool
    {
        return $this->ratio < 0.0000001;
    }

    public function equals(CoiPercentage $other): bool
    {
        return abs($this->ratio - $other->ratio) < 0.0000001;
    }

    public function __toString(): string
    {
        return $this->formatted();
    }
}
