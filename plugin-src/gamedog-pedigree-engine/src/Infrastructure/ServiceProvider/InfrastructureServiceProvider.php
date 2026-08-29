<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\ServiceProvider;

use GDPE\Application\Port\CacheInterface;
use GDPE\Application\Port\ParentAutoCreatorInterface;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\Repository\SettingsRepositoryInterface;
use GDPE\Domain\Service\ParentConnectionInterface;
use GDPE\Infrastructure\Cache\WordPressCacheAdapter;
use GDPE\Infrastructure\Container\ServiceContainer;
use GDPE\Infrastructure\Guard\DependencyGuard;
use GDPE\Infrastructure\Mapper\DogMapper;
use GDPE\Infrastructure\Repository\JetEngineDogRepository;
use GDPE\Infrastructure\Repository\WordPressSettingsRepository;
use GDPE\Infrastructure\Service\AutoCreateParentService;
use GDPE\Infrastructure\Service\MetaAndRelationParentConnection;

/**
 * Registers every Infrastructure-layer binding the plugin needs.
 */
final class InfrastructureServiceProvider
{
    /**
     * Registers all Infrastructure bindings on the given container.
     */
    public static function register(ServiceContainer $container): void
    {
        $container->bind(
            DogMapper::class,
            static fn (): DogMapper => new DogMapper(),
        );

        $container->bind(
            DogRepositoryInterface::class,
            static fn (ServiceContainer $c): DogRepositoryInterface => new JetEngineDogRepository(
                $c->get(DogMapper::class),
            ),
        );

        $container->bind(
            SettingsRepositoryInterface::class,
            static fn (): SettingsRepositoryInterface => new WordPressSettingsRepository(),
        );

        $container->bind(
            CacheInterface::class,
            static fn (): CacheInterface => new WordPressCacheAdapter(),
        );

        $container->bind(
            DependencyGuard::class,
            static fn (): DependencyGuard => new DependencyGuard(),
        );

        // Parent Connection service - handles meta + JetEngine relation sync
        $container->bind(
            ParentConnectionInterface::class,
            static fn (): ParentConnectionInterface => new MetaAndRelationParentConnection(),
        );

        // Auto-Create parent service - handles the write-side side-effect of
        // creating missing Sire/Dam posts (recursion-safe via AutoCreateGuard).
        // Lives in the Infrastructure layer and is injected through a port so
        // the Application layer never depends on the concrete WordPress writes.
        $container->bind(
            ParentAutoCreatorInterface::class,
            static fn (ServiceContainer $c): ParentAutoCreatorInterface => new AutoCreateParentService(
                $c->get(DogRepositoryInterface::class),
            ),
        );
    }
}
