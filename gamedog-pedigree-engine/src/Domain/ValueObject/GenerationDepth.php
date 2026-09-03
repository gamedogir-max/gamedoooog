<?php
/**
 * Generation depth value object for pedigree traversal limits.
 *
 * @package GameDog\PedigreeEngine\Domain\ValueObject
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\ValueObject;

final class GenerationDepth
{
    public const DEFAULT_COI_DEPTH = 4;

    /** @var int */
    private $value;

    public function __construct(int $value)
    {
        if ($value < 1) {
            throw new \InvalidArgumentException('Generation depth must be at least 1.');
        }

        // Hard ceiling prevents runaway recursion on corrupted data.
        if ($value > 20) {
            $value = 20;
        }

        $this->value = $value;
    }

    public static function defaultCoi(): self
    {
        return new self(self::DEFAULT_COI_DEPTH);
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
