<?php
/**
 * Collects ancestor paths (with generation distances) from a pedigree branch.
 *
 * Shared by the Wright COI and blood contribution calculations so that both
 * algorithms resolve identical paths over identical guard rules.
 *
 * @package GameDog\PedigreeEngine\Domain\Service
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Service;

use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;

final class AncestorPathCollector
{
    /**
     * Walk a pedigree branch and collect every ancestor ID with the list of
     * generation distances from the branch root.
     *
     * Distance 0 means the branch root itself (the sire or dam of the subject).
     * A path-visited set guards against circular ancestor references.
     *
     * @param PedigreeNode|null $node
     * @param int               $startDistance Distance of the given node.
     * @param int               $maxDistance   Hard depth ceiling (distance limit).
     *
     * @return array<int, array<int, int>> Map of dogId => list of distances.
     */
    public function collect(?PedigreeNode $node, int $startDistance = 0, int $maxDistance = 30): array
    {
        if ($node === null || $node->isEmpty()) {
            return [];
        }

        return $this->walk($node, $startDistance, max(0, $maxDistance), []);
    }

    /**
     * @param PedigreeNode     $node
     * @param int              $distance
     * @param int              $maxDistance
     * @param array<int, bool> $visited
     *
     * @return array<int, array<int, int>>
     */
    private function walk(PedigreeNode $node, int $distance, int $maxDistance, array $visited): array
    {
        $result = [];

        if ($distance > $maxDistance) {
            return $result;
        }

        $dogId = $node->dogId();
        if ($dogId === null) {
            return $result;
        }

        $id = $dogId->toInt();

        // Circular lineage guard: stop when this dog already sits on the path.
        if (isset($visited[$id])) {
            return $result;
        }

        $visited[$id] = true;

        $result[$id] = [$distance];

        $sireChild = $node->sireNode();
        if ($sireChild !== null && !$sireChild->isEmpty()) {
            $fromSire = $this->walk($sireChild, $distance + 1, $maxDistance, $visited);
            $result   = $this->mergePathMaps($result, $fromSire);
        }

        $damChild = $node->damNode();
        if ($damChild !== null && !$damChild->isEmpty()) {
            $fromDam = $this->walk($damChild, $distance + 1, $maxDistance, $visited);
            $result  = $this->mergePathMaps($result, $fromDam);
        }

        return $result;
    }

    /**
     * @param array<int, array<int, int>> $a
     * @param array<int, array<int, int>> $b
     *
     * @return array<int, array<int, int>>
     */
    private function mergePathMaps(array $a, array $b): array
    {
        foreach ($b as $id => $distances) {
            if (!isset($a[$id])) {
                $a[$id] = $distances;
                continue;
            }

            foreach ($distances as $d) {
                $a[$id][] = $d;
            }
        }

        return $a;
    }
}
