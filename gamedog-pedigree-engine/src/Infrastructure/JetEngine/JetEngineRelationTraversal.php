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

        $sire = $this->resolveRelatedId($id, $this->sireRelationId, 'sire');
        $dam  = $this->resolveRelatedId($id, $this->damRelationId, 'dam');

        // Meta fallbacks commonly used alongside JetEngine.
        if ($sire === null) {
            $sire = $this->metaDogId($id, ['_dog_sire_id', 'dog_sire', 'sire_id', 'father_id']);
        }
        if ($dam === null) {
            $dam = $this->metaDogId($id, ['_dog_dam_id', 'dog_dam', 'dam_id', 'mother_id']);
        }

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

    private function resolveRelatedId(int $postId, int $relationId, string $role): ?DogId
    {
        // JetEngine relations API (modern).
        if (function_exists('jet_engine') && isset(jet_engine()->relations)) {
            try {
                $relation = jet_engine()->relations->get_active_relations($relationId);
                if ($relation) {
                    // For child->parent: dog is typically the child object.
                    $related = $relation->get_parents($postId, 'ids');
                    if (is_array($related) && $related !== []) {
                        return DogId::fromMixed(reset($related));
                    }

                    // Some setups store the dog as parent of the relation.
                    $related = $relation->get_children($postId, 'ids');
                    if (is_array($related) && $related !== []) {
                        return DogId::fromMixed(reset($related));
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to legacy / meta paths.
            }
        }

        // Legacy jet_rel_N meta keys.
        $legacyKeys = [
            'jet_rel_' . $relationId,
            '_jet_rel_' . $relationId,
            'relation_' . $relationId,
        ];

        return $this->metaDogId($postId, $legacyKeys);
    }

    /**
     * @param array<int, string> $keys
     */
    private function metaDogId(int $postId, array $keys): ?DogId
    {
        if (!function_exists('get_post_meta')) {
            return null;
        }

        foreach ($keys as $key) {
            $raw = get_post_meta($postId, $key, true);
            if ($raw === '' || $raw === null || $raw === false) {
                continue;
            }

            if (is_array($raw)) {
                $raw = reset($raw);
            }

            $id = DogId::fromMixed($raw);
            if ($id !== null) {
                return $id;
            }
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
