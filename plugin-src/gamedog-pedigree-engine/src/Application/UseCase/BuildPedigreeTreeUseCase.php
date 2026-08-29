<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Application\Port\CacheInterface;
use GDPE\Application\Service\PedigreeCacheKeyGenerator;
use GDPE\Domain\Exception\DogNotFoundException;
use GDPE\Domain\Model\PedigreeTree;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\Repository\SettingsRepositoryInterface;
use GDPE\Domain\Service\PedigreeTreeBuilderService;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Domain\ValueObject\Generation;

final class BuildPedigreeTreeUseCase
{
    public function __construct(
        private readonly DogRepositoryInterface $dogRepository,
        private readonly PedigreeTreeBuilderService $pedigreeTreeBuilder,
        private readonly CacheInterface $cache,
        private readonly SettingsRepositoryInterface $settingsRepository,
        private readonly PedigreeCacheKeyGenerator $cacheKeyGenerator,
    ) {
    }

    /**
     * @throws DogNotFoundException
     */
    public function execute(
        DogId $rootDogId,
        Generation $generation
    ): PedigreeTree {
        $cacheKey = $this->cacheKeyGenerator->forPedigreeTree(
            $rootDogId,
            $generation
        );

        $cached = $this->cache->get($cacheKey);

        if ($cached instanceof PedigreeTree) {
            return $cached;
        }

        $rootDog = $this->dogRepository->findById($rootDogId);

        if ($rootDog === null) {
            throw DogNotFoundException::forId($rootDogId);
        }

        $tree = $this->pedigreeTreeBuilder->build(
            $rootDog,
            $generation
        );

        $this->cache->set(
            $cacheKey,
            $tree,
            $this->settingsRepository
                ->get()
                ->getCacheTtlSeconds()
        );

        return $tree;
    }
}