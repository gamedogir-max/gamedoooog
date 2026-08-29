<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\Exception\DogNotFoundException;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\Service\FullSiblingFinderService;
use GDPE\Domain\ValueObject\DogId;

/**
 * Orchestrates the Full Siblings lookup: resolves the reference dog
 * via the repository, then delegates the matching logic to the
 * Domain's FullSiblingFinderService.
 */
final class FindFullSiblingsUseCase
{
    public function __construct(
        private readonly DogRepositoryInterface $dogRepository,
        private readonly FullSiblingFinderService $fullSiblingFinder,
    ) {
    }

    /**
     * Returns the full siblings of the given dog.
     *
     * @param DogId $dogId Reference dog identifier.
     *
     * @return array<int, Dog>
     *
     * @throws DogNotFoundException If the reference dog does not exist.
     */
    public function execute(DogId $dogId): array
    {
        $dog = $this->dogRepository->findById($dogId);

        if ($dog === null) {
            throw DogNotFoundException::forId($dogId);
        }

        return $this->fullSiblingFinder->find($dog);
    }
}