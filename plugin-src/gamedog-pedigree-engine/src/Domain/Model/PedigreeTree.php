<?php

declare(strict_types=1);

namespace GDPE\Domain\Model;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\ValueObject\Generation;
use GDPE\Domain\ValueObject\PedigreeNode;

final readonly class PedigreeTree
{
    /**
     * @param list<PedigreeNode> $nodes
     */
    public function __construct(
        private Dog $rootDog,
        private Generation $generation,
        private array $nodes,
    ) {
    }

    public function rootDog(): Dog
    {
        return $this->rootDog;
    }

    public function generation(): Generation
    {
        return $this->generation;
    }

    /**
     * @return list<PedigreeNode>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return list<PedigreeNode>
     */
    public function generationNodes(int $index): array
    {
        return array_values(
            array_filter(
                $this->nodes,
                static fn (PedigreeNode $node): bool =>
                    $node->generationIndex() === $index,
            ),
        );
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }
}