<?php
/**
 * JetEngine relation walker for sire (relation 6) and dam (relation 7).
 *
 * @package GameDog\PedigreeEngine\Infrastructure\JetEngine
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Infrastructure\JetEngine;

use GameDog\PedigreeEngine\Domain\Contract\RelationTraversalInterface;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

final class JetEngineRelationTraversal implements RelationTraversalInterface
{
    /** @var int */
    private $sireRelationId;

    /** @var int */
    private $damRelationId;

    /** @var array<int, array{sire: ?DogId, dam: ?DogId}> */
    private $parentCache = [];

    public function __construct(
        int $sireRelationId = GD_PEDIGREE_SIRE_RELATION_ID,
        int $damRelationId = GD_PEDIGREE_DAM_RELATION_ID
    ) {
        $this->sireRelationId = $sireRelationId;
        $this->damRelationId  = $damRelationId;
    }

    /**
     * {@inheritdoc}
     */
    public function getSireId(DogId $dogId): ?DogId
    {
        $parents = $this->getParentIds($dogId);

        return $parents['sire'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDamId(DogId $dogId): ?DogId
    {
        $parents = $this->getParentIds($dogId);

        return $parents['dam'];
    }

    /**
     * {@inheritdoc}
     */
    public function getParentIds(DogId $dogId): array
    {
        $id = $dogId->toInt();

        if (isset($this->parentCache[$id])) {
            return $this->parentCache[$id];
        }

        $sire = $this->resolveRelatedId($id, $this->sireRelationId);
        $dam  = $this->resolveRelatedId($id, $this->damRelationId);

        $result = [
            'sire' => $sire,
            'dam'  => $dam,
        ];

        $this->parentCache[$id] = $result;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getOffspringIds(DogId $dogId): array
    {
        $id      = $dogId->toInt();
        $results = [];

        foreach ([$this->sireRelationId, $this->damRelationId] as $relationId) {
            $related = $this->queryRelationChildren($id, $relationId);
            foreach ($related as $childId) {
                $vo = DogId::fromMixed($childId);
                if ($vo !== null) {
                    $results[$vo->toInt()] = $vo;
                }
            }
        }

        return array_values($results);
    }

    /**
     * Clear in-request memoization (e.g. after parent update).
     */
    public function clearMemoryCache(): void
    {
        $this->parentCache = [];
    }

    /**
     * Resolve the parent of a dog through the JetEngine relations API only.
     *
     * Parent links live in dedicated relation tables (db_table: true), so raw
     * post meta is never queried here.
     */
    private function resolveRelatedId(int $postId, int $relationId): ?DogId
    {
        if (!function_exists('jet_engine') || !isset(jet_engine()->relations)) {
            return null;
        }

        try {
            $relation = jet_engine()->relations->get_active_relations($relationId);
            if (!$relation) {
                return null;
            }

            // Child -> parent direction: the dog is the child object.
            $related = $relation->get_parents($postId, 'ids');
            if (is_array($related) && $related !== []) {
                $id = DogId::fromMixed(reset($related));
                if ($id !== null) {
                    return $id;
                }
            }

            // Parent -> child direction: some setups store the dog as parent.
            $related = $relation->get_children($postId, 'ids');
            if (is_array($related) && $related !== []) {
                $id = DogId::fromMixed(reset($related));
                if ($id !== null) {
                    return $id;
                }
            }
        } catch (\Throwable $e) {
            // Relation lookup failures degrade to "unknown parent".
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function queryRelationChildren(int $parentId, int $relationId): array
    {
        $ids = [];

        if (function_exists('jet_engine') && isset(jet_engine()->relations)) {
            try {
                $relation = jet_engine()->relations->get_active_relations($relationId);
                if ($relation) {
                    $children = $relation->get_children($parentId, 'ids');
                    if (is_array($children)) {
                        foreach ($children as $cid) {
                            $ids[] = (int) $cid;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $ids;
    }
}
