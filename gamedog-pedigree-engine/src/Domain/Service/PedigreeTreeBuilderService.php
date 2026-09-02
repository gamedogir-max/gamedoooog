<?php
/**
 * Domain service that assembles a PedigreeTree from repository + relation ports.
 *
 * Does not calculate COI itself — that is delegated to InbreedingCalculatorInterface.
 *
 * @package GameDog\PedigreeEngine\Domain\Service
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Service;

use GameDog\PedigreeEngine\Domain\Contract\RelationTraversalInterface;
use GameDog\PedigreeEngine\Domain\Entity\Dog;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeTree;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Domain\ValueObject\GenerationDepth;

final class PedigreeTreeBuilderService
{
    /** @var DogRepositoryInterface */
    private $dogs;

    /** @var RelationTraversalInterface */
    private $relations;

    public function __construct(DogRepositoryInterface $dogs, RelationTraversalInterface $relations)
    {
        $this->dogs      = $dogs;
        $this->relations = $relations;
    }

    /**
     * Build a pedigree tree of the requested depth for the subject dog.
     */
    public function build(DogId $subjectId, GenerationDepth $depth): PedigreeTree
    {
        $subject = $this->dogs->findById($subjectId);

        if ($subject === null) {
            $root = PedigreeNode::empty(0, 'subject');

            return new PedigreeTree($subjectId, $root, $depth);
        }

        $root = $this->buildNode($subject, 0, $depth->toInt(), 'subject', []);

        return new PedigreeTree($subjectId, $root, $depth);
    }

    /**
     * Recursively expand a dog into a pedigree node with sire/dam children.
     *
     * @param array<int, bool> $pathVisited Dogs already on the path from the root (cycle guard).
     */
    private function buildNode(
        Dog $dog,
        int $generation,
        int $maxDepth,
        string $side,
        array $pathVisited
    ): PedigreeNode {
        $id = $dog->id()->toInt();

        $node = new PedigreeNode(
            $dog->id(),
            $dog->name(),
            $generation,
            $side,
            null,
            null,
            $dog->coi(),
            false,
            $dog->thumbnailUrl(),
            $dog->permalink(),
            $dog->gender()
        );

        if ($generation >= $maxDepth) {
            return $node;
        }

        // Cycle guard: do not expand a dog that already appears higher in this path.
        if (isset($pathVisited[$id])) {
            return $node;
        }

        $pathVisited[$id] = true;

        $parents = $this->relations->getParentIds($dog->id());
        $sireId  = $parents['sire'] ?? null;
        $damId   = $parents['dam'] ?? null;

        // Fall back to IDs already on the Dog entity when the relation port is empty.
        if ($sireId === null) {
            $sireId = $dog->sireId();
        }
        if ($damId === null) {
            $damId = $dog->damId();
        }

        if ($sireId instanceof DogId) {
            $sireDog = $this->dogs->findById($sireId);
            if ($sireDog !== null) {
                $node = $node->withSireNode(
                    $this->buildNode($sireDog, $generation + 1, $maxDepth, 'sire', $pathVisited)
                );
            } else {
                $node = $node->withSireNode(
                    new PedigreeNode($sireId, 'Dog #' . $sireId->toInt(), $generation + 1, 'sire')
                );
            }
        }

        if ($damId instanceof DogId) {
            $damDog = $this->dogs->findById($damId);
            if ($damDog !== null) {
                $node = $node->withDamNode(
                    $this->buildNode($damDog, $generation + 1, $maxDepth, 'dam', $pathVisited)
                );
            } else {
                $node = $node->withDamNode(
                    new PedigreeNode($damId, 'Dog #' . $damId->toInt(), $generation + 1, 'dam')
                );
            }
        }

        return $node;
    }

    /**
     * Mark nodes whose dog IDs appear in $commonIds with the common-ancestor flag.
     *
     * @param array<int, int> $commonIds
     */
    public function markCommonAncestors(PedigreeNode $node, array $commonIds): PedigreeNode
    {
        if ($commonIds === []) {
            return $node;
        }

        $dogId = $node->dogId();
        $flag  = false;

        if ($dogId !== null && isset($commonIds[$dogId->toInt()])) {
            $flag = true;
        }

        $sire = $node->sireNode();
        $dam  = $node->damNode();

        if ($sire !== null) {
            $sire = $this->markCommonAncestors($sire, $commonIds);
        }
        if ($dam !== null) {
            $dam = $this->markCommonAncestors($dam, $commonIds);
        }

        $result = $node->markAsCommonAncestor($flag);

        if ($sire !== $node->sireNode()) {
            $result = $result->withSireNode($sire);
        }
        if ($dam !== $node->damNode()) {
            $result = $result->withDamNode($dam);
        }

        return $result;
    }
}
