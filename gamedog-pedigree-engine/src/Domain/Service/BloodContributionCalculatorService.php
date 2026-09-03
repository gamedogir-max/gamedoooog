<?php
/**
 * Blood contribution domain service.
 *
 * Aggregates the genetic contribution of every ancestor appearing inside a
 * 4-generation window (parents, grandparents, great-grandparents and
 * great-great-grandparents) of the subject dog.
 *
 * Each occurrence of an ancestor contributes its generation weight:
 *   Gen 1 (parents)                  : 50%
 *   Gen 2 (grandparents)             : 25%
 *   Gen 3 (great-grandparents)       : 12.5%
 *   Gen 4 (great-great-grandparents) : 6.25%
 *
 * @package GameDog\PedigreeEngine\Domain\Service
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Service;

use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeTree;

final class BloodContributionCalculatorService
{
    public const MAX_DEPTH = 4;

    /**
     * Generation weight per ancestor generation (index = generation distance
     * from the subject, 1 = parents, 4 = great-great-grandparents).
     *
     * @var array<int, float>
     */
    private const WEIGHTS = [
        1 => 50.0,
        2 => 25.0,
        3 => 12.5,
        4 => 6.25,
    ];

    /**
     * Compute contribution rows for a subject dog.
     *
     * @return array<int, array{id: int, count: int, percent: float}>
     *         Sorted descending by percentage, then by occurrence count.
     */
    public function calculate(PedigreeTree $tree): array
    {
        $root = $tree->root();
        if ($root === null || $root->isEmpty()) {
            return [];
        }

        // Collect ancestor occurrences per generation distance from the subject.
        // The subject itself is excluded: parents start at generation 1.
        $occ = [];

        $sire = $root->sireNode();
        if ($sire !== null && !$sire->isEmpty()) {
            $occ = $this->collectOccurrences($sire, 1, self::MAX_DEPTH);
        }

        $dam = $root->damNode();
        if ($dam !== null && !$dam->isEmpty()) {
            $occ = $this->mergeOccurrences($occ, $this->collectOccurrences($dam, 1, self::MAX_DEPTH));
        }

        if ($occ === []) {
            return [];
        }

        $rows = [];

        foreach ($occ as $id => $agg) {
            $rows[$id] = [
                'id'      => $id,
                'count'   => $agg['count'],
                'percent' => round($agg['percent'], 2),
            ];
        }

        // Sort: primary by percentage desc, secondary by count desc.
        uasort($rows, static function (array $a, array $b): int {
            if ($a['percent'] === $b['percent']) {
                if ($a['count'] === $b['count']) {
                    return 0;
                }

                return $a['count'] > $b['count'] ? -1 : 1;
            }

            return $a['percent'] > $b['percent'] ? -1 : 1;
        });

        return array_values($rows);
    }

    /**
     * Recursively collect ancestor occurrences with cumulative percentage.
     *
     * @param int $depth Current generation distance from the subject (1 = parents).
     *
     * @return array<int, array{count: int, percent: float}>
     */
    private function collectOccurrences(PedigreeNode $node, int $depth, int $maxDepth): array
    {
        $result = [];

        if ($depth > $maxDepth) {
            return $result;
        }

        $dogId = $node->dogId();
        if ($dogId === null) {
            return $result;
        }

        $id     = $dogId->toInt();
        $weight = self::WEIGHTS[$depth] ?? 0.0;

        if (!isset($result[$id])) {
            $result[$id] = ['count' => 0, 'percent' => 0.0];
        }
        $result[$id]['count']++;
        $result[$id]['percent'] += $weight;

        $sire = $node->sireNode();
        if ($sire !== null && !$sire->isEmpty()) {
            $fromSire = $this->collectOccurrences($sire, $depth + 1, $maxDepth);
            $result   = $this->mergeOccurrences($result, $fromSire);
        }

        $dam = $node->damNode();
        if ($dam !== null && !$dam->isEmpty()) {
            $fromDam = $this->collectOccurrences($dam, $depth + 1, $maxDepth);
            $result  = $this->mergeOccurrences($result, $fromDam);
        }

        return $result;
    }

    /**
     * @param array<int, array{count: int, percent: float}> $a
     * @param array<int, array{count: int, percent: float}> $b
     *
     * @return array<int, array{count: int, percent: float}>
     */
    private function mergeOccurrences(array $a, array $b): array
    {
        foreach ($b as $id => $row) {
            if (!isset($a[$id])) {
                $a[$id] = $row;
                continue;
            }

            $a[$id]['count']   += $row['count'];
            $a[$id]['percent'] += $row['percent'];
        }

        return $a;
    }
}
