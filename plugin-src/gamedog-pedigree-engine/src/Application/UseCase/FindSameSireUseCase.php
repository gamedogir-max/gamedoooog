<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\Exception\DogNotFoundException;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\Service\SameSireFinderService;
use GDPE\Domain\ValueObject\DogId;

/**
 * Orchestrates the Same Sire lookup: resolves the reference dog via
 * the repository, then delegates the matching logic to the Domain's
 * SameSireFinderService.
 */
final class FindSameSireUseCase
{
    public function __construct(
        private readonly DogRepositoryInterface $dogRepository,
        private readonly SameSireFinderService $sameSireFinder,
    ) {
    }

    /**
     * Returns the dogs sharing the reference dog's sire.
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

        return $this->sameSireFinder->find($dog);
    }
}