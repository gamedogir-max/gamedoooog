<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Application\Port\CacheInterface;

final readonly class ClearPedigreeCacheUseCase
{
    public function __construct(
        private CacheInterface $cache
    ) {
    }

    public function execute(): void
    {
        $this->cache->clear();
    }
}