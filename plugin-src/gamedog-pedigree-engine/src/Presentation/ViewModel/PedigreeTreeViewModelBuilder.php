<?php

declare(strict_types=1);

namespace GDPE\Presentation\ViewModel;

use GDPE\Domain\Model\PedigreeTree;
use GDPE\Domain\ValueObject\PedigreeNode;

/**
 * Rebuilds the Domain's flat preorder pedigree-node list into a
 * presentation-friendly nested tree without changing the Domain model.
 */
final class PedigreeTreeViewModelBuilder
{
    /**
     * @return array{
     *     header_title: string,
     *     header_meta: string,
     *     generation_count: int,
     *     root: array{id: int, title: string, permalink: string},
     *     branches: array{
     *         sire: array<string, mixed>,
     *         dam: array<string, mixed>
     *     }
     * }
     */
    public function build(PedigreeTree $tree): array
    {
        $nodes = $tree->nodes();
        $cursor = 0;
        $generationCount = $tree->generation()->toInt();
        $rootDog = $tree->rootDog();

        $sireBranch = $this->consumeBranch($nodes, $cursor, 0, $generationCount);
        $damBranch = $this->consumeBranch($nodes, $cursor, 0, $generationCount);

        return [
            'header_title' => sprintf(
                /* translators: %s: dog name. */
                __('Pedigree: %s', 'gdpe'),
                $rootDog->name(),
            ),
            'header_meta' => sprintf(
                /* translators: %d: number of generations. */
                _n('%d Generation', '%d Generations', $generationCount, 'gdpe'),
                $generationCount,
            ),
            'generation_count' => $generationCount,
            'root' => [
                'id' => $rootDog->id()->toInt(),
                'title' => $rootDog->name(),
                'permalink' => $rootDog->permalink(),
            ],
            'branches' => [
                'sire' => $sireBranch,
                'dam' => $damBranch,
            ],
        ];
    }

    /**
     * @param list<PedigreeNode> $nodes
     *
     * @return array<string, mixed>
     */
    private function consumeBranch(array $nodes, int &$cursor, int $depth, int $maxDepth): array
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        $currentNode = $nodes[$cursor] ?? null;

        if (!$currentNode instanceof PedigreeNode || $currentNode->generationIndex() !== $depth) {
            return $this->buildUnknownSubtree($depth, $maxDepth);
        }

        ++$cursor;

        if ($currentNode->isKnown()) {
            $node = [
                'id' => $currentNode->dogId()?->toInt(),
                'title' => $currentNode->name(),
                'permalink' => $currentNode->permalink(),
                'is_known' => true,
                'generation' => $depth + 1,
                'children' => [],
            ];

            if ($depth + 1 < $maxDepth) {
                $node['children'] = [
                    'sire' => $this->consumeBranch($nodes, $cursor, $depth + 1, $maxDepth),
                    'dam' => $this->consumeBranch($nodes, $cursor, $depth + 1, $maxDepth),
                ];
            }

            return $node;
        }

        return $this->buildUnknownSubtree($depth, $maxDepth, $currentNode->name());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnknownSubtree(int $depth, int $maxDepth, string $label = ''): array
    {
        $resolvedLabel = $label !== ''
            ? $label
            : __('Unknown', 'gdpe');

        $node = [
            'id' => null,
            'title' => $resolvedLabel,
            'permalink' => null,
            'is_known' => false,
            'generation' => $depth + 1,
            'children' => [],
        ];

        if ($depth + 1 < $maxDepth) {
            $node['children'] = [
                'sire' => $this->buildUnknownSubtree($depth + 1, $maxDepth, $resolvedLabel),
                'dam' => $this->buildUnknownSubtree($depth + 1, $maxDepth, $resolvedLabel),
            ];
        }

        return $node;
    }
}
