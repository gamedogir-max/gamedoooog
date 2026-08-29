<?php

declare(strict_types=1);

namespace GDPE\Domain\Service;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\Repository\DogRepositoryInterface;

final readonly class FullSiblingFinderService
{
    public function __construct(
        private DogRepositoryInterface $dogRepository
    ) {
    }

    /**
     * @return array<int,Dog>
     */
    public function find(Dog $dog): array
    {
        $fatherId = $dog->fatherId();
        $motherId = $dog->motherId();

        if ($fatherId === null || $motherId === null) {
            return [];
        }

        $sameSire = $this->dogRepository->findBySireId($fatherId);

$siblings = array_filter(
    $sameSire,
    static function (Dog $candidate) use ($dog, $motherId): bool {

        if ($candidate->id()->equals($dog->id())) {
            return false;
        }

        return $candidate->motherId()?->equals($motherId) ?? false;
    }
);

        return array_values($siblings);
    }
}