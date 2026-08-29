<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Repository;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\Service\RelationTraversalInterface;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Domain\ValueObject\RelationConfig;

/**
 * Infrastructure implementation of RelationTraversalInterface.
 *
 * NOTE:
 * This class is intentionally left as a placeholder until the
 * real JetEngine Relations API integration is implemented.
 *
 * The architecture is correct, but the actual JetEngine methods
 * for traversing relations must be wired once the plugin is
 * installed and the relation API is available.
 */
final class JetEngineRelationTraversal implements RelationTraversalInterface
{
    /**
     * Resolves the parent of the given dog using the configured relation.
     *
     * @throws \RuntimeException
     */
    public function findParent(
        DogId $dogId,
        RelationConfig $config,
    ): ?Dog {
        throw new \RuntimeException(
            'JetEngineRelationTraversal is not implemented yet. '
            . 'Replace this placeholder with the real JetEngine Relations API integration.'
        );
    }
}