<?php

declare(strict_types=1);

namespace GDPE\Domain\Service;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\Repository\DogRepositoryInterface;

final readonly class SameDamFinderService
{
    public function __construct(
        private DogRepositoryInterface $dogRepository
    ) {
    }

    /**
     * @return array<int, Dog>
     */
    public function find(Dog $dog): array
    {
        $damId = $dog->motherId();

        if ($damId === null) {
            return [];
        }

        $matches = array_filter(
            $this->dogRepository->findByDamId($damId),
            static fn (Dog $candidate): bool =>
                !$candidate->id()->equals($dog->id())
        );

        return array_values($matches);
    }
}