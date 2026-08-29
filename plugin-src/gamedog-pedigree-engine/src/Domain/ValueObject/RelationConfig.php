<?php

declare(strict_types=1);

namespace GDPE\Domain\ValueObject;

final readonly class RelationConfig
{
    public function __construct(
        private int|string $relationId,
        private RelationDirection $direction,
    ) {
    }

    public function relationId(): int|string
    {
        return $this->relationId;
    }

    public function direction(): RelationDirection
    {
        return $this->direction;
    }
}