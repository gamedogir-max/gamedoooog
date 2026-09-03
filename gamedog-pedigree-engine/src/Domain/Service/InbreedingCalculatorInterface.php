<?php
/**
 * Contract for Wright (or alternate) inbreeding coefficient calculators.
 *
 * @package GameDog\PedigreeEngine\Domain\Service
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Service;

use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeTree;
use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;

interface InbreedingCalculatorInterface
{
    /**
     * Compute Wright's coefficient of inbreeding for a fully built pedigree tree.
     *
     * The tree root is the subject; sire and dam branches must already be attached.
     */
    public function calculateFromTree(PedigreeTree $tree): CoiPercentage;

    /**
     * Compute COI from independent sire and dam ancestral branches.
     *
     * @param PedigreeNode|null $sireBranch Root of the sire lineage (generation 1 relative to subject).
     * @param PedigreeNode|null $damBranch  Root of the dam lineage (generation 1 relative to subject).
     * @param array<int, float> $knownAncestorCoi Optional map of dogId => F_A ratio already known.
     */
    public function calculateFromBranches(
        ?PedigreeNode $sireBranch,
        ?PedigreeNode $damBranch,
        array $knownAncestorCoi = []
    ): CoiPercentage;

    /**
     * Identify dog IDs that appear on both the sire and dam sides of the tree.
     *
     * @return array<int, int> Map of dogId => dogId
     */
    public function findCommonAncestorIds(?PedigreeNode $sireBranch, ?PedigreeNode $damBranch): array;
}
