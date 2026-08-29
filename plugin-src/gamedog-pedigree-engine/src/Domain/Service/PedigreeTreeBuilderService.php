<?php

declare(strict_types=1);

namespace GDPE\Domain\Service;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\Model\PedigreeTree;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\ValueObject\Generation;
use GDPE\Domain\ValueObject\LineageSide;
use GDPE\Domain\ValueObject\PedigreeNode;

final readonly class PedigreeTreeBuilderService
{
    public function __construct(
        private DogRepositoryInterface $dogRepository
    ) {
    }

    public function build(
        Dog $rootDog,
        Generation $generation
    ): PedigreeTree {

        $nodes = [];

        $this->walk(
            $rootDog->fatherId(),
            0,
            $generation->toInt(),
            LineageSide::PATERNAL,
            $nodes
        );

        $this->walk(
            $rootDog->motherId(),
            0,
            $generation->toInt(),
            LineageSide::MATERNAL,
            $nodes
        );

        return new PedigreeTree(
            $rootDog,
            $generation,
            $nodes
        );
    }

    /**
     * @param list<PedigreeNode> $nodes
     */
    private function walk(
        mixed $dogId,
        int $depth,
        int $maxDepth,
        LineageSide $side,
        array &$nodes
    ): void {

        if ($depth >= $maxDepth) {
            return;
        }

        if ($dogId === null) {
            $nodes[] = PedigreeNode::unknown(
                'Unknown',
                $depth,
                $side
            );
            return;
        }

        $dog = $this->dogRepository->findById($dogId);

        if ($dog === null) {
            $nodes[] = PedigreeNode::unknown(
                'Unknown',
                $depth,
                $side
            );
            return;
        }

        $nodes[] = PedigreeNode::known(
            $dog->id(),
            $dog->name(),
            $dog->permalink(),
            $depth,
            $side
        );

        $this->walk(
            $dog->fatherId(),
            $depth + 1,
            $maxDepth,
            LineageSide::PATERNAL,
            $nodes
        );

        $this->walk(
            $dog->motherId(),
            $depth + 1,
            $maxDepth,
            LineageSide::MATERNAL,
            $nodes
        );
    }
}