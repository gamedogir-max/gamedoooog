<?php
/**
 * Dog persistence port (repository interface).
 *
 * @package GameDog\PedigreeEngine\Domain\Repository
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Repository;

use GameDog\PedigreeEngine\Domain\Entity\Dog;
use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

interface DogRepositoryInterface
{
    /**
     * Load a single dog by identifier.
     */
    public function findById(DogId $id): ?Dog;

    /**
     * Load multiple dogs keyed by their integer IDs.
     *
     * @param array<int, DogId|int> $ids
     *
     * @return array<int, Dog>
     */
    public function findByIds(array $ids): array;

    /**
     * Persist the calculated COI for a dog.
     */
    public function saveCoi(DogId $id, CoiPercentage $coi): void;

    /**
     * Read previously stored COI meta, if any.
     */
    public function getStoredCoi(DogId $id): ?CoiPercentage;
}
