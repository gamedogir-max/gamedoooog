<?php
/**
 * Application use case: compute the COI + AVK genetic diversity metrics.
 *
 * COI (Wright) is delegated to CalculateDogCoiUseCase which also persists the
 * value to the `_dog_coi` post meta. AVK (Ancestor Loss) is computed from the
 * same 4-generation tree.
 *
 * @package GameDog\PedigreeEngine\Application\UseCase
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\UseCase;

use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeTree;
use GameDog\PedigreeEngine\Domain\Service\PedigreeTreeBuilderService;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Domain\ValueObject\GenerationDepth;

final class BuildDiversityMetricsUseCase
{
    public const DEPTH = 4;

    /** Total theoretical ancestors across a 4-generation window. */
    public const THEORETICAL_ANCESTORS = 30;

    /** @var PedigreeTreeBuilderService */
    private $treeBuilder;

    /** @var CalculateDogCoiUseCase */
    private $coiUseCase;

    public function __construct(
        PedigreeTreeBuilderService $treeBuilder,
        CalculateDogCoiUseCase $coiUseCase
    ) {
        $this->treeBuilder = $treeBuilder;
        $this->coiUseCase  = $coiUseCase;
    }

    /**
     * @param int|DogId $dogId
     * @param bool      $force Bypass cached COI.
     *
     * @return array<string, mixed>
     */
    public function execute($dogId, bool $force = false): array
    {
        $empty = [
            'subject_id'     => 0,
            'coi_percent'    => 0.0,
            'coi_formatted'  => '0.00%',
            'avk_percent'    => 0.0,
            'avk_formatted'  => '0.00%',
            'depth'          => self::DEPTH,
            'has_coi'        => false,
            'has_avk'        => false,
        ];

        $id = DogId::fromMixed($dogId);
        if ($id === null) {
            return $empty;
        }

        $empty['subject_id'] = $id->toInt();

        // COI is delegated so the result is persisted to _dog_coi meta.
        $coiResult = $this->coiUseCase->execute($id, self::DEPTH, $force);

        $tree = $this->treeBuilder->build($id, new GenerationDepth(self::DEPTH));

        $avk = $this->computeAvk($tree);

        return [
            'subject_id'     => $id->toInt(),
            'coi_percent'    => round($coiResult->percent, 2),
            'coi_formatted'  => $coiResult->formatted,
            'avk_percent'    => round($avk, 2),
            'avk_formatted'  => number_format($avk, 2, '.', '') . '%',
            'depth'          => self::DEPTH,
            'has_coi'        => true,
            'has_avk'        => true,
        ];
    }

    /**
     * AVK = (unique ancestors / theoretical ancestors) * 100.
     */
    private function computeAvk(PedigreeTree $tree): float
    {
        $root = $tree->root();
        if ($root === null || $root->isEmpty()) {
            return 0.0;
        }

        $unique = [];

        $sire = $root->sireNode();
        if ($sire !== null) {
            $this->collectUnique($sire, 1, self::DEPTH, $unique);
        }

        $dam = $root->damNode();
        if ($dam !== null) {
            $this->collectUnique($dam, 1, self::DEPTH, $unique);
        }

        if ($unique === []) {
            return 0.0;
        }

        return (count($unique) / self::THEORETICAL_ANCESTORS) * 100.0;
    }

    /**
     * @param array<int, bool> $unique Reference accumulator.
     */
    private function collectUnique(PedigreeNode $node, int $generation, int $maxDepth, array &$unique): void
    {
        if ($node->isEmpty()) {
            return;
        }

        $dogId = $node->dogId();
        if ($dogId !== null) {
            $unique[$dogId->toInt()] = true;
        }

        if ($generation >= $maxDepth) {
            return;
        }

        $sire = $node->sireNode();
        if ($sire !== null) {
            $this->collectUnique($sire, $generation + 1, $maxDepth, $unique);
        }

        $dam = $node->damNode();
        if ($dam !== null) {
            $this->collectUnique($dam, $generation + 1, $maxDepth, $unique);
        }
    }
}
