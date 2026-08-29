<?php

declare(strict_types=1);

namespace GDPE\Domain\ValueObject;

final readonly class PedigreeNode
{
    private function __construct(
        private ?DogId $dogId,
        private string $name,
        private ?string $permalink,
        private int $generationIndex,
        private LineageSide $lineageSide,
        private bool $unknown,
    ) {
    }

    public static function known(
        DogId $dogId,
        string $name,
        string $permalink,
        int $generationIndex,
        LineageSide $lineageSide,
    ): self {
        return new self(
            dogId: $dogId,
            name: $name,
            permalink: $permalink,
            generationIndex: $generationIndex,
            lineageSide: $lineageSide,
            unknown: false,
        );
    }

    public static function unknown(
        string $label,
        int $generationIndex,
        LineageSide $lineageSide,
    ): self {
        return new self(
            dogId: null,
            name: $label,
            permalink: null,
            generationIndex: $generationIndex,
            lineageSide: $lineageSide,
            unknown: true,
        );
    }

    public function dogId(): ?DogId
    {
        return $this->dogId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function permalink(): ?string
    {
        return $this->permalink;
    }

    public function generationIndex(): int
    {
        return $this->generationIndex;
    }

    public function lineageSide(): LineageSide
    {
        return $this->lineageSide;
    }

    public function isUnknown(): bool
    {
        return $this->unknown;
    }

    public function isKnown(): bool
    {
        return !$this->unknown;
    }
}