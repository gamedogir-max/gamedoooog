<?php

declare(strict_types=1);

namespace GDPE\Domain\Service;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Domain\ValueObject\RelationConfig;

/**
 * Domain port for resolving a parent through a configured relation.
 *
 * Infrastructure (JetEngine) implements this interface.
 * Domain services depend ONLY on this contract.
 */
interface RelationTraversalInterface
{
    /**
     * Resolves the parent of the given dog using the supplied relation.
     */
    public function findParent(
        DogId $dogId,
        RelationConfig $config
    ): ?Dog;
}