<?php

declare(strict_types=1);

namespace GDPE\Domain\Repository;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\ValueObject\DogId;

/**
 * Domain-level port for retrieving Dog aggregates.
 *
 * This interface has ZERO knowledge of JetEngine, WordPress or any
 * other infrastructure concern. Infrastructure adapters (e.g. a
 * JetEngine-backed repository) must implement this contract without
 * the Domain layer ever depending on them.
 */
interface DogRepositoryInterface
{
    /**
     * Finds a single dog by its identifier.
     *
     * @param DogId $id The dog identifier.
     *
     * @return Dog|null The dog, or null if none exists with this id.
     */
    public function findById(DogId $id): ?Dog;

    /**
     * Finds all dogs whose sire matches the given identifier.
     *
     * @param DogId $sireId The sire's identifier.
     *
     * @return array<int, Dog> List of dogs sharing this sire.
     */
    public function findBySireId(DogId $sireId): array;

    /**
     * Finds all dogs whose dam matches the given identifier.
     *
     * @param DogId $damId The dam's identifier.
     *
     * @return array<int, Dog> List of dogs sharing this dam.
     */
    public function findByDamId(DogId $damId): array;

    /**
     * Finds all published dogs whose post title exactly matches the given name.
     *
     * Used by AutoConnectParentsUseCase to resolve parent dogs from
     * plain-text name fields (father_name / mother_name).
     *
     * @param string      $name      The exact dog name to search for.
     * @param DogId|null  $excludeId Optional dog ID to exclude (prevents self-parent).
     *
     * @return array<int, Dog> All published dogs matching the name (may be empty or multiple).
     */
    public function findPublishedByName(string $name, ?DogId $excludeId = null): array;

    /**
     * Finds all published dogs that are waiting for a parent with the given name.
     *
     * Used for REVERSE/DEFERRED parent resolution: when a new parent dog is
     * published, we search for existing children whose father_name or mother_name
     * matches this new parent's dog_name but whose gdpe_sire_id/gdpe_dam_id is still empty.
     *
     * @param string   $parentName  The normalized parent name to search for.
     * @param string   $parentType  Either 'father' or 'mother' - which meta field to check.
     * @param int|null $excludeId   Optional dog ID to exclude (prevents self-reference).
     *
     * @return array<int, Dog> All published dogs waiting for this parent (may be empty or multiple).
     */
    public function findChildrenWaitingForParent(string $parentName, string $parentType, ?int $excludeId = null): array;
}
