<?php

declare(strict_types=1);

namespace GDPE\Application\Service;

use GDPE\Domain\ValueObject\DogId;
use GDPE\Domain\ValueObject\Generation;

final class PedigreeCacheKeyGenerator
{
    private const PEDIGREE_TREE_PREFIX = 'gdpe_tree_';

    public function forPedigreeTree(
        DogId $dogId,
        Generation $generation
    ): string {
        return self::PEDIGREE_TREE_PREFIX
            . (string) $dogId
            . '_gen'
            . $generation->toInt();
    }
}