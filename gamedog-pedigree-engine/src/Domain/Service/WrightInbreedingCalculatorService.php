<?php
/**
 * Wright's Coefficient of Inbreeding (COI) domain service.
 *
 * Formula:
 *   F_X = sum( (1/2)^(n1 + n2 + 1) * (1 + F_A) )
 *
 * where the sum is over every common ancestor A that appears on both the
 * sire and dam ancestral paths, n1 / n2 are the generation distances from
 * the sire / dam to A, and F_A is the inbreeding of A (0 when unknown).
 *
 * Circular lineage references are guarded with a visited-set so traversal
 * never recurses infinitely.
 *
 * @package GameDog\PedigreeEngine\Domain\Service
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Service;

use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeTree;
use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;

final class WrightInbreedingCalculatorService implements InbreedingCalculatorInterface
{
    /**
     * Wright COI is computed over a 4-generation window: the sire and dam
     * branches are each walked three levels deep (great-grandparents).
     */
    private const MAX_BRANCH_DISTANCE = 3;

    /** @var AncestorPathCollector */
    private $pathCollector;

    public function __construct(?AncestorPathCollector $pathCollector = null)
    {
        $this->pathCollector = $pathCollector !== null
            ? $pathCollector
            : new AncestorPathCollector();
    }

    /**
     * {@inheritdoc}
     */
    public function calculateFromTree(PedigreeTree $tree): CoiPercentage
    {
        return $this->calculateFromBranches(
            $tree->sireBranch(),
            $tree->damBranch()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function calculateFromBranches(
        ?PedigreeNode $sireBranch,
        ?PedigreeNode $damBranch,
        array $knownAncestorCoi = []
    ): CoiPercentage {
        if ($sireBranch === null || $damBranch === null) {
            return CoiPercentage::zero();
        }

        if ($sireBranch->isEmpty() || $damBranch->isEmpty()) {
            return CoiPercentage::zero();
        }

        // Paths from the subject dog: subject -> sire (n=1) -> ... ancestor
        // and subject -> dam (n=1) -> ... ancestor.
        // n1 / n2 in Wright's formula are generations from SIRE / DAM to A,
        // so path length from subject to A is n+1; we store distance from
        // the branch root (sire or dam) which is exactly n1 / n2.
        $sirePaths = $this->pathCollector->collect($sireBranch, 0, self::MAX_BRANCH_DISTANCE);
        $damPaths  = $this->pathCollector->collect($damBranch, 0, self::MAX_BRANCH_DISTANCE);

        if ($sirePaths === [] || $damPaths === []) {
            return CoiPercentage::zero();
        }

        $sireIds = array_keys($sirePaths);
        $damIds  = array_keys($damPaths);
        $common  = array_intersect($sireIds, $damIds);

        if ($common === []) {
            return CoiPercentage::zero();
        }

        $fx = 0.0;

        foreach ($common as $ancestorId) {
            // Wright's formula sums over every independent path pair that
            // reaches this common ancestor from the sire and dam sides.
            $sireDistances = $sirePaths[$ancestorId];
            $damDistances  = $damPaths[$ancestorId];

            $fa = 0.0;
            if (isset($knownAncestorCoi[$ancestorId]) && is_numeric($knownAncestorCoi[$ancestorId])) {
                $fa = (float) $knownAncestorCoi[$ancestorId];
                if ($fa < 0.0) {
                    $fa = 0.0;
                }
                if ($fa > 1.0) {
                    $fa = 1.0;
                }
            }

            foreach ($sireDistances as $n1) {
                foreach ($damDistances as $n2) {
                    // F contribution of this path pair:
                    // (1/2)^(n1 + n2 + 1) * (1 + F_A)
                    $exponent      = $n1 + $n2 + 1;
                    $pathFactor    = pow(0.5, $exponent);
                    $contribution  = $pathFactor * (1.0 + $fa);
                    $fx           += $contribution;
                }
            }
        }

        if ($fx < 0.0) {
            $fx = 0.0;
        }

        // Theoretical upper bound is 1.0; clamp floating noise.
        if ($fx > 1.0) {
            $fx = 1.0;
        }

        return new CoiPercentage($fx);
    }

    /**
     * {@inheritdoc}
     */
    public function findCommonAncestorIds(?PedigreeNode $sireBranch, ?PedigreeNode $damBranch): array
    {
        if ($sireBranch === null || $damBranch === null) {
            return [];
        }

        $sireIds = $sireBranch->collectDogIds();
        $damIds  = $damBranch->collectDogIds();

        $common = array_intersect_key($sireIds, $damIds);

        return $common;
    }

    // Ancestor path resolution now lives in AncestorPathCollector so the COI
    // and blood-contribution algorithms share identical traversal + guards.
}
