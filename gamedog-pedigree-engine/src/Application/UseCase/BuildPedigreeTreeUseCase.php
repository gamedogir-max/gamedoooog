<?php
/**
 * Application use case: build a pedigree tree and attach Wright COI.
 *
 * Integrates CalculateDogCoiUseCase (or the calculator directly) so the
 * returned tree DTO always carries the COI metric for presentation.
 *
 * @package GameDog\PedigreeEngine\Application\UseCase
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\UseCase;

use GameDog\PedigreeEngine\Application\DTO\PedigreeTreeDto;
use GameDog\PedigreeEngine\Application\Mapper\PedigreeTreeMapper;
use GameDog\PedigreeEngine\Domain\Contract\CacheInterface;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\Service\InbreedingCalculatorInterface;
use GameDog\PedigreeEngine\Domain\Service\PedigreeTreeBuilderService;
use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Domain\ValueObject\GenerationDepth;

final class BuildPedigreeTreeUseCase
{
    private const CACHE_PREFIX = 'gd_tree_';
    private const CACHE_TTL    = 3600;

    /** @var PedigreeTreeBuilderService */
    private $treeBuilder;

    /** @var InbreedingCalculatorInterface */
    private $calculator;

    /** @var PedigreeTreeMapper */
    private $mapper;

    /** @var DogRepositoryInterface */
    private $dogs;

    /** @var CalculateDogCoiUseCase|null */
    private $coiUseCase;

    /** @var CacheInterface|null */
    private $cache;

    public function __construct(
        PedigreeTreeBuilderService $treeBuilder,
        InbreedingCalculatorInterface $calculator,
        PedigreeTreeMapper $mapper,
        DogRepositoryInterface $dogs,
        ?CalculateDogCoiUseCase $coiUseCase = null,
        ?CacheInterface $cache = null
    ) {
        $this->treeBuilder = $treeBuilder;
        $this->calculator  = $calculator;
        $this->mapper      = $mapper;
        $this->dogs        = $dogs;
        $this->coiUseCase  = $coiUseCase;
        $this->cache       = $cache;
    }

    /**
     * @param int|DogId $dogId
     * @param int|null  $depth
     * @param bool      $forceRecalculateCoi
     */
    public function execute($dogId, ?int $depth = null, bool $forceRecalculateCoi = false): PedigreeTreeDto
    {
        $id = DogId::fromMixed($dogId);
        if ($id === null) {
            return new PedigreeTreeDto(0, '', '0.00%', 0.0, 0.0, 5, [], null);
        }

        $depthVo = $depth !== null
            ? new GenerationDepth($depth)
            : GenerationDepth::defaultCoi();

        $cacheKey = self::CACHE_PREFIX . $id->toInt() . '_d' . $depthVo->toInt();

        if (!$forceRecalculateCoi && $this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached) && isset($cached['subject_id'], $cached['root'])) {
                return new PedigreeTreeDto(
                    (int) $cached['subject_id'],
                    isset($cached['subject_name']) ? (string) $cached['subject_name'] : '',
                    isset($cached['coi']) ? (string) $cached['coi'] : '0.00%',
                    isset($cached['coi_percent']) ? (float) $cached['coi_percent'] : 0.0,
                    isset($cached['coi_ratio']) ? (float) $cached['coi_ratio'] : 0.0,
                    isset($cached['depth']) ? (int) $cached['depth'] : $depthVo->toInt(),
                    isset($cached['common_ancestor_ids']) && is_array($cached['common_ancestor_ids'])
                        ? $cached['common_ancestor_ids']
                        : [],
                    is_array($cached['root']) ? $cached['root'] : null
                );
            }
        }

        $tree = $this->treeBuilder->build($id, $depthVo);

        // Identify common ancestors for visual highlighting.
        $common = $this->calculator->findCommonAncestorIds(
            $tree->sireBranch(),
            $tree->damBranch()
        );

        // Prefer dedicated COI use case (handles persistence + cache).
        if ($this->coiUseCase !== null) {
            $coiResult = $this->coiUseCase->execute($id, $depthVo->toInt(), $forceRecalculateCoi);
            $coi       = new CoiPercentage($coiResult->ratio);
            if ($coiResult->commonAncestorIds !== []) {
                $common = $coiResult->commonAncestorIds;
            }
        } else {
            $coi = $this->calculator->calculateFromBranches(
                $tree->sireBranch(),
                $tree->damBranch()
            );
            // Persist when possible.
            try {
                $this->dogs->saveCoi($id, $coi);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $markedRoot = $this->treeBuilder->markCommonAncestors($tree->root(), $common);
        $tree       = $tree
            ->withRoot($markedRoot)
            ->withCoi($coi)
            ->withCommonAncestors($common);

        $dto = $this->mapper->toDto($tree);

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $dto->toArray(), self::CACHE_TTL);
        }

        return $dto;
    }

    /**
     * @param int|DogId $dogId
     */
    public function invalidate($dogId): void
    {
        $id = DogId::fromMixed($dogId);
        if ($id === null || $this->cache === null) {
            return;
        }

        $this->cache->deleteByPrefix(self::CACHE_PREFIX . $id->toInt());

        if ($this->coiUseCase !== null) {
            $this->coiUseCase->invalidate($id);
        }
    }
}
