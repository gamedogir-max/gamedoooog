<?php
/**
 * Application use case: build the 5-generation authentic pedigree matrix.
 *
 * Produces an ordered list of 62 ancestor cells (binary slots 2..63) plus
 * linebreeding (repeated ancestor) detection for the visual matrix renderer.
 *
 * @package GameDog\PedigreeEngine\Application\UseCase
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\UseCase;

use GameDog\PedigreeEngine\Domain\Entity\Dog;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeTree;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\Service\PedigreeTreeBuilderService;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Domain\ValueObject\GenerationDepth;

final class BuildPedigreeMatrixUseCase
{
    public const GENERATIONS = 5;

    /**
     * Fixed linebreeding dot palette (Emerald, Violet, Amber, Rose, Cyan,
     * Lime, Indigo). Assignment is deterministic: repeated ancestors sorted
     * by ID take colours in order.
     *
     * @var array<int, string>
     */
    private const PALETTE = [
        '#10b981',
        '#8b5cf6',
        '#f59e0b',
        '#f43f5e',
        '#06b6d4',
        '#84cc16',
        '#6366f1',
    ];

    /** @var PedigreeTreeBuilderService */
    private $treeBuilder;

    /** @var DogRepositoryInterface */
    private $dogs;

    public function __construct(
        PedigreeTreeBuilderService $treeBuilder,
        DogRepositoryInterface $dogs
    ) {
        $this->treeBuilder = $treeBuilder;
        $this->dogs        = $dogs;
    }

    /**
     * @param int|DogId $dogId
     *
     * @return array<string, mixed>
     */
    public function execute($dogId): array
    {
        $id = DogId::fromMixed($dogId);

        if ($id === null) {
            return $this->emptyResult();
        }

        $tree  = $this->treeBuilder->build($id, new GenerationDepth(self::GENERATIONS));
        $cells = $this->buildCells($tree);
        $dots  = $this->buildLinebreedingDots($cells);

        $root = $tree->root();

        return [
            'subject_id'    => $id->toInt(),
            'subject_name'  => $root->name(),
            'subject_thumb' => $root->thumbnailUrl(),
            'generations'   => self::GENERATIONS,
            'cells'         => $cells,
            'linebreeding'  => $dots,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(): array
    {
        return [
            'subject_id'    => 0,
            'subject_name'  => '',
            'subject_thumb' => '',
            'generations'   => self::GENERATIONS,
            'cells'         => [],
            'linebreeding'  => [],
        ];
    }

    /**
     * Breadth-first flatten of the 62 ancestor slots (nodes 2..63).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCells(PedigreeTree $tree): array
    {
        $cells = [];

        $root = $tree->root();
        if ($root === null) {
            return $cells;
        }

        // Queue entries: [slotIndex, PedigreeNode|null].
        // Both root slots are always enqueued so empty slots still render as
        // "Unknown" and the matrix keeps its exact 62-cell shape.
        $queue = [
            [2, $root->sireNode()],
            [3, $root->damNode()],
        ];

        while ($queue !== []) {
            /** @var array{0: int, 1: PedigreeNode|null} $entry */
            $entry = array_shift($queue);
            $slot  = $entry[0];
            $node  = $entry[1];

            $generation = $this->generationOfSlot($slot);
            $dog        = $this->nodeToArray($node);

            $cells[] = [
                'slot'       => $slot,
                'generation' => $generation,
                'rowspan'    => $this->rowspanForGeneration($generation),
                'dog'        => $dog,
            ];

            if ($generation >= self::GENERATIONS) {
                continue;
            }

            $childSireSlot = $slot * 2;
            $childDamSlot  = $slot * 2 + 1;

            $childSire = $node !== null ? $node->sireNode() : null;
            $childDam  = $node !== null ? $node->damNode() : null;

            // Still enqueue empty slots so the matrix keeps its exact shape.
            $queue[] = [$childSireSlot, $childSire];
            $queue[] = [$childDamSlot, $childDam];
        }

        return $cells;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nodeToArray(?PedigreeNode $node): ?array
    {
        if ($node === null || $node->isEmpty() || $node->dogId() === null) {
            return null;
        }

        $dogId = $node->dogId();
        $dog   = $this->dogs->findById($dogId);

        $titles         = [];
        $registeredName = $node->name();

        if ($dog instanceof Dog) {
            $titles         = $dog->titles();
            $registeredName = $dog->registeredName();
        }

        return [
            'id'              => $dogId->toInt(),
            'name'            => $node->name(),
            'registered_name' => $registeredName,
            'titles'          => $titles,
            'permalink'       => $node->permalink(),
            'thumbnail'       => $node->thumbnailUrl(),
            'gender'          => $node->gender(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $cells
     *
     * @return array<int, array{color: string, count: int}>
     */
    private function buildLinebreedingDots(array $cells): array
    {
        $counts = [];

        foreach ($cells as $cell) {
            if (!is_array($cell['dog'])) {
                continue;
            }

            $id = (int) $cell['dog']['id'];
            if ($id <= 0) {
                continue;
            }

            $counts[$id] = isset($counts[$id]) ? $counts[$id] + 1 : 1;
        }

        $repeated = [];
        foreach ($counts as $id => $count) {
            if ($count > 1) {
                $repeated[$id] = $count;
            }
        }

        ksort($repeated);

        $dots = [];
        $i    = 0;
        foreach ($repeated as $id => $count) {
            $dots[$id] = [
                'color' => self::PALETTE[$i % count(self::PALETTE)],
                'count' => $count,
                'index' => $i % count(self::PALETTE),
            ];
            $i++;
        }

        return $dots;
    }

    private function generationOfSlot(int $slot): int
    {
        return (int) floor(log($slot, 2));
    }

    private function rowspanForGeneration(int $generation): int
    {
        switch ($generation) {
            case 1:
                return 16;
            case 2:
                return 8;
            case 3:
                return 4;
            case 4:
                return 2;
            default:
                return 1;
        }
    }
}
