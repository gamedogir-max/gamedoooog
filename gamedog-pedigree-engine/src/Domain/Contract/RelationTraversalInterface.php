<?php
/**
 * Port for walking JetEngine (or other) parent relations.
 *
 * @package GameDog\PedigreeEngine\Domain\Contract
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Contract;

use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

interface RelationTraversalInterface
{
    /**
     * Resolve the sire (father) of a dog, or null if unknown.
     */
    public function getSireId(DogId $dogId): ?DogId;

    /**
     * Resolve the dam (mother) of a dog, or null if unknown.
     */
    public function getDamId(DogId $dogId): ?DogId;

    /**
     * Resolve both parents in one call.
     *
     * @return array{sire: ?DogId, dam: ?DogId}
     */
    public function getParentIds(DogId $dogId): array;

    /**
     * Direct offspring of a dog (used by siblings box).
     *
     * @return array<int, DogId>
     */
    public function getOffspringIds(DogId $dogId): array;
}
