<?php

declare(strict_types=1);

namespace GDPE\Domain\Service;

use GDPE\Domain\ValueObject\DogId;
use GDPE\Infrastructure\Service\ConnectionResult;

/**
 * Domain-level contract for connecting parent dogs to their offspring.
 *
 * Implementations are responsible for persisting parent relationships
 * through both meta fields and (optionally) external relation systems
 * such as JetEngine Relations.
 */
interface ParentConnectionInterface
{
    /**
     * Connects a father (sire) to the given child dog.
     *
     * @param DogId      $childDogId The child dog's identifier.
     * @param DogId|null $sireId     The father's identifier, or null to disconnect.
     *
     * @return ConnectionResult Detailed result with Meta and Relation status reported separately.
     */
    public function connectSire(DogId $childDogId, ?DogId $sireId): ConnectionResult;

    /**
     * Connects a mother (dam) to the given child dog.
     *
     * @param DogId      $childDogId The child dog's identifier.
     * @param DogId|null $damId      The mother's identifier, or null to disconnect.
     *
     * @return ConnectionResult Detailed result with Meta and Relation status reported separately.
     */
    public function connectDam(DogId $childDogId, ?DogId $damId): ConnectionResult;

    /**
     * Removes all existing parent connections for a dog.
     *
     * Useful when reparenting or when parent names change.
     *
     * @param DogId $childDogId The dog whose parents should be disconnected.
     *
     * @return bool True if disconnection was successful.
     */
    public function disconnectAllParents(DogId $childDogId): bool;
}
