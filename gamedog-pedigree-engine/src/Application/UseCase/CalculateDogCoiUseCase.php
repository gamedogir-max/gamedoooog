<?php
/**
 * Application use case: calculate Wright's COI for a dog (5 generations default).
 *
 * Resolves ancestors via DogRepositoryInterface + RelationTraversalInterface,
 * builds temporary sire/dam branches, runs WrightInbreedingCalculatorService,
 * persists the result to post meta, and caches it.
 *
 * @package GameDog\PedigreeEngine\Application\UseCase
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\UseCase;

use GameDog\PedigreeEngine\Application\DTO\CoiResultDto;
use GameDog\PedigreeEngine\Domain\Contract\CacheInterface;
use GameDog\PedigreeEngine\Domain\Contract\RelationTraversalInterface;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\Service\InbreedingCalculatorInterface;
use GameDog\PedigreeEngine\Domain\Service\PedigreeTreeBuilderService;
use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Domain\ValueObject\GenerationDepth;

final class CalculateDogCoiUseCase
{
    private const CACHE_PREFIX = 'gd_coi_';
    private const CACHE_TTL    = 86400; // 24 hours

    /** @var DogRepositoryInterface */
    private $dogs;

    /** @var RelationTraversalInterface */
    private $relations;

    /** @var InbreedingCalculatorInterface */
    private $calculator;

    /** @var PedigreeTreeBuilderService */
    private $treeBuilder;

    /** @var CacheInterface|null */
    private $cache;

    public function __construct(
        DogRepositoryInterface $dogs,
        RelationTraversalInterface $relations,
        InbreedingCalculatorInterface $calculator,
        PedigreeTreeBuilderService $treeBuilder,
        ?CacheInterface $cache = null
    ) {
        $this->dogs        = $dogs;
        $this->relations   = $relations;
        $this->calculator  = $calculator;
        $this->treeBuilder = $treeBuilder;
        $this->cache       = $cache;
    }

    /**
     * Calculate (or return cached) COI for the given dog.
     *
     * @param int|DogId $dogId
     * @param int|null  $depth  Generation depth (default 5).
     * @param bool      $force  Bypass cache and stored meta.
     */
    public function execute($dogId, ?int $depth = null, bool $force = false): CoiResultDto
    {
        $id = DogId::fromMixed($dogId);
        if ($id === null) {
            return new CoiResultDto(0, '0.00%', 0.0, 0.0, [], false);
        }

        $depthVo = $depth !== null
            ? new GenerationDepth($depth)
            : GenerationDepth::defaultCoi();

        $cacheKey = self::CACHE_PREFIX . $id->toInt() . '_d' . $depthVo->toInt();

        if (!$force && $this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached) && isset($cached['formatted'], $cached['ratio'])) {
                return new CoiResultDto(
                    $id->toInt(),
                    (string) $cached['formatted'],
                    isset($cached['percent']) ? (float) $cached['percent'] : 0.0,
                    (float) $cached['ratio'],
                    isset($cached['common']) && is_array($cached['common']) ? $cached['common'] : [],
                    false
                );
            }
        }

        // Fast path: stored meta when not forcing recalculation.
        // A stored "0.00%" is a valid prior result and must not trigger a recompute loop.
        // Only trust the stored value when it was computed at the requested depth.
        if (!$force && $this->dogs->getStoredCoiDepth($id) === $depthVo->toInt()) {
            $stored = $this->dogs->getStoredCoi($id);
            if ($stored instanceof CoiPercentage) {
                $tree   = $this->treeBuilder->build($id, $depthVo);
                $common = $this->calculator->findCommonAncestorIds($tree->sireBranch(), $tree->damBranch());
                $result = new CoiResultDto(
                    $id->toInt(),
                    $stored->formatted(),
                    $stored->percent(),
                    $stored->ratio(),
                    $common,
                    false
                );
                $this->storeCache($cacheKey, $result);

                return $result;
            }
        }

        $dog = $this->dogs->findById($id);
        if ($dog === null) {
            return new CoiResultDto($id->toInt(), '0.00%', 0.0, 0.0, [], true);
        }

        // Resolve parents defensively.
        $parents = $this->relations->getParentIds($id);
        $sireId  = $parents['sire'] ?? null;
        $damId   = $parents['dam'] ?? null;

        if ($sireId === null) {
            $sireId = $dog->sireId();
        }
        if ($damId === null) {
            $damId = $dog->damId();
        }

        if ($sireId === null || $damId === null) {
            $zero = CoiPercentage::zero();
            $this->persist($id, $zero, $depthVo->toInt());
            $result = new CoiResultDto($id->toInt(), $zero->formatted(), 0.0, 0.0, [], true);
            $this->storeCache($cacheKey, $result);

            return $result;
        }

        // Build full tree up to depth so both branches share the same traversal rules.
        $tree   = $this->treeBuilder->build($id, $depthVo);
        $common = $this->calculator->findCommonAncestorIds($tree->sireBranch(), $tree->damBranch());
        $coi    = $this->calculator->calculateFromBranches($tree->sireBranch(), $tree->damBranch());

        $this->persist($id, $coi, $depthVo->toInt());

        $result = new CoiResultDto(
            $id->toInt(),
            $coi->formatted(),
            $coi->percent(),
            $coi->ratio(),
            $common,
            true
        );
        $this->storeCache($cacheKey, $result);

        return $result;
    }

    private function persist(DogId $id, CoiPercentage $coi, int $depth): void
    {
        try {
            $this->dogs->saveCoi($id, $coi, $depth);
        } catch (\Throwable $e) {
            // Persistence failures must not break rendering.
        }
    }

    private function storeCache(string $key, CoiResultDto $result): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->set(
            $key,
            [
                'formatted' => $result->formatted,
                'percent'   => $result->percent,
                'ratio'     => $result->ratio,
                'common'    => $result->commonAncestorIds,
            ],
            self::CACHE_TTL
        );
    }

    /**
     * Invalidate cached COI for a dog (e.g. after parent change).
     *
     * @param int|DogId $dogId
     */
    public function invalidate($dogId): void
    {
        $id = DogId::fromMixed($dogId);
        if ($id === null || $this->cache === null) {
            return;
        }

        $this->cache->deleteByPrefix(self::CACHE_PREFIX . $id->toInt());
    }
}
