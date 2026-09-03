<?php
/**
 * Domain service that assembles a PedigreeTree from repository + relation ports.
 *
 * Does not calculate COI itself - that is delegated to InbreedingCalculatorInterface.
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

    /** @var array<int, Dog|null> In-memory identity map for a single build. */
    private $memory = [];

    /** @var array<int, bool> IDs requested in the current build batch. */
    private $pending = [];

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
        // Fresh identity map per build so one request never serves stale data.
        $this->memory  = [];
        $this->pending = [];

        $subject = $this->dogs->findById($subjectId);

        if ($subject === null) {
            $root = PedigreeNode::empty(0, 'subject');

            return new PedigreeTree($subjectId, $root, $depth);
        }

        $this->memory[$subjectId->toInt()] = $subject;

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

        // Register the parent IDs for one batched repository lookup, avoiding
        // an N+1 pattern for every node in the tree.
        $batch = [];
        if ($sireId instanceof DogId) {
            $batch[$sireId->toInt()] = true;
        }
        if ($damId instanceof DogId) {
            $batch[$damId->toInt()] = true;
        }
        if ($batch !== []) {
            $this->queueBatch($batch);
        }

        if ($sireId instanceof DogId) {
            $node = $node->withSireNode(
                $this->resolveChildNode($sireId, $generation + 1, $maxDepth, 'sire', $pathVisited)
            );
        }

        if ($damId instanceof DogId) {
            $node = $node->withDamNode(
                $this->resolveChildNode($damId, $generation + 1, $maxDepth, 'dam', $pathVisited)
            );
        }

        return $node;
    }

    /**
     * Resolve a single child dog from the identity map (or the repository) and
     * continue the recursive expansion.
     *
     * @param array<int, bool> $pathVisited
     */
    private function resolveChildNode(
        DogId $dogId,
        int $generation,
        int $maxDepth,
        string $side,
        array $pathVisited
    ): PedigreeNode {
        $intId = $dogId->toInt();

        if (!array_key_exists($intId, $this->memory)) {
            $child = $this->dogs->findById($dogId);
            $this->memory[$intId] = $child;
        }

        $child = $this->memory[$intId];

        if ($child === null) {
            return new PedigreeNode($dogId, 'Dog #' . $intId, $generation, $side);
        }

        return $this->buildNode($child, $generation, $maxDepth, $side, $pathVisited);
    }

    /**
     * Queue IDs for a single repository batch load.
     *
     * @param array<int, bool> $ids
     */
    private function queueBatch(array $ids): void
    {
        foreach ($ids as $id => $flag) {
            if (!array_key_exists($id, $this->memory) && !isset($this->pending[$id])) {
                $this->pending[$id] = true;
            }
        }

        if ($this->pending === []) {
            return;
        }

        try {
            $loaded = $this->dogs->findByIds(array_keys($this->pending));
            foreach ($loaded as $id => $dog) {
                $this->memory[$id] = $dog;
            }
        } catch (\Throwable $e) {
            // Batch loading is an optimisation; failures fall back to per-node loads.
        }

        $this->pending = [];
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
