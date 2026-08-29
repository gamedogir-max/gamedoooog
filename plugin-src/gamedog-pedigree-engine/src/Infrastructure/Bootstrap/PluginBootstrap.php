<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Bootstrap;

use GDPE\Application\EventDispatcher\EventDispatcherInterface;
use GDPE\Application\Port\CacheInterface;
use GDPE\Application\Port\ParentAutoCreatorInterface;
use GDPE\Application\Service\PedigreeCacheKeyGenerator;
use GDPE\Application\UseCase\AutoConnectParentsUseCase;
use GDPE\Application\UseCase\BuildPedigreeTreeUseCase;
use GDPE\Application\UseCase\ResolveChildrenForNewParentUseCase;
use GDPE\Application\UseCase\ClearPedigreeCacheUseCase;
use GDPE\Application\UseCase\FindFullSiblingsUseCase;
use GDPE\Application\UseCase\FindSameDamUseCase;
use GDPE\Application\UseCase\FindSameSireUseCase;
use GDPE\Application\UseCase\GetPluginSettingsUseCase;
use GDPE\Application\UseCase\SavePluginSettingsUseCase;
use GDPE\Application\UseCase\UpdatePluginSettingsUseCase;
use GDPE\Application\Mapper\PluginSettingsMapper;
use GDPE\Application\Validator\PluginSettingsValidator;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\Repository\SettingsRepositoryInterface;
use GDPE\Domain\Service\FullSiblingFinderService;
use GDPE\Domain\Service\PedigreeTreeBuilderService;
use GDPE\Domain\Service\SameDamFinderService;
use GDPE\Domain\Service\SameSireFinderService;
use GDPE\Domain\Service\ParentConnectionInterface;
use GDPE\Infrastructure\Container\ServiceContainer;
use GDPE\Infrastructure\EventDispatcher\WordPressEventDispatcher;
use GDPE\Infrastructure\Guard\DependencyGuard;
use GDPE\Infrastructure\Guard\MissingDependencyException;
use GDPE\Infrastructure\ServiceProvider\InfrastructureServiceProvider;
use GDPE\Infrastructure\Settings\DefaultPluginSettingsFactory;
use GDPE\Infrastructure\WordPress\AdminSettingsRegistrar;
use GDPE\Infrastructure\WordPress\CacheInvalidationRegistrar;
use GDPE\Infrastructure\WordPress\ParentConnectionRegistrar;
use GDPE\Presentation\Elementor\PedigreeAssetRegistrar;
use GDPE\Presentation\Elementor\PedigreeCategoryRegistrar;
use GDPE\Presentation\Elementor\PedigreeWidgetRegistrar;
use GDPE\Presentation\Renderer\PedigreeTreeRenderer;
use GDPE\Presentation\Renderer\SiblingsBoxRenderer;
use GDPE\Presentation\ServiceProvider\ElementorServiceProvider;
use GDPE\Presentation\Shortcodes\AgeShortcode;
use GDPE\Presentation\Shortcodes\SiblingsUnifiedShortcode;
use GDPE\Presentation\Shortcodes\PedigreeTreeShortcode;
use GDPE\Presentation\Support\CurrentDogContextResolver;

final class PluginBootstrap
{
    private readonly ServiceContainer $container;
    private readonly string $templateDirectory;

    public function __construct(
        ?string $templateDirectory = null,
        private readonly string $cssUrl = '',
        private readonly string $version = '1.0.0',
    ) {
        $this->templateDirectory = $templateDirectory
            ?? dirname(__DIR__, 3) . '/templates/pedigree';

        $this->container = new ServiceContainer();
        $this->registerBindings();
    }

    public function boot(): void
    {
        $this->container->get(PedigreeCategoryRegistrar::class)->register();
        $this->container->get(PedigreeAssetRegistrar::class)->register();
        $this->container->get(PedigreeWidgetRegistrar::class)->register();

        $this->container->get(AgeShortcode::class)->register();

        PedigreeTreeShortcode::bootstrap(
            $this->container->get(BuildPedigreeTreeUseCase::class),
            $this->container->get(PedigreeTreeRenderer::class),
            $this->container->get(CurrentDogContextResolver::class),
        );

        SiblingsUnifiedShortcode::bootstrap(
            $this->container->get(FindFullSiblingsUseCase::class),
            $this->container->get(FindSameSireUseCase::class),
            $this->container->get(FindSameDamUseCase::class),
            $this->container->get(SiblingsBoxRenderer::class),
            $this->container->get(CurrentDogContextResolver::class),
        );

        $this->container->get(DependencyGuard::class)->guard();
        $this->container->get(AdminSettingsRegistrar::class)->register();
        $this->container->get(CacheInvalidationRegistrar::class)->register();
        $this->container->get(ParentConnectionRegistrar::class)->register();
    }

    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    private function registerBindings(): void
    {
        InfrastructureServiceProvider::register($this->container);

        $this->container->bind(
            EventDispatcherInterface::class,
            static fn (): EventDispatcherInterface => new WordPressEventDispatcher(),
        );

        $this->container->bind(
            PedigreeTreeBuilderService::class,
            static fn (ServiceContainer $c): PedigreeTreeBuilderService => new PedigreeTreeBuilderService(
                $c->get(DogRepositoryInterface::class),
            ),
        );

        $this->container->bind(
            FullSiblingFinderService::class,
            static fn (ServiceContainer $c): FullSiblingFinderService => new FullSiblingFinderService(
                $c->get(DogRepositoryInterface::class),
            ),
        );

        $this->container->bind(
            SameSireFinderService::class,
            static fn (ServiceContainer $c): SameSireFinderService => new SameSireFinderService(
                $c->get(DogRepositoryInterface::class),
            ),
        );

        $this->container->bind(
            SameDamFinderService::class,
            static fn (ServiceContainer $c): SameDamFinderService => new SameDamFinderService(
                $c->get(DogRepositoryInterface::class),
            ),
        );

        $this->container->bind(
            PedigreeCacheKeyGenerator::class,
            static fn (): PedigreeCacheKeyGenerator => new PedigreeCacheKeyGenerator(),
        );

        $this->container->bind(
            BuildPedigreeTreeUseCase::class,
            static fn (ServiceContainer $c): BuildPedigreeTreeUseCase => new BuildPedigreeTreeUseCase(
                $c->get(DogRepositoryInterface::class),
                $c->get(PedigreeTreeBuilderService::class),
                $c->get(CacheInterface::class),
                $c->get(SettingsRepositoryInterface::class),
                $c->get(PedigreeCacheKeyGenerator::class),
            ),
        );

        $this->container->bind(
            FindFullSiblingsUseCase::class,
            static fn (ServiceContainer $c): FindFullSiblingsUseCase => new FindFullSiblingsUseCase(
                $c->get(DogRepositoryInterface::class),
                $c->get(FullSiblingFinderService::class),
            ),
        );

        $this->container->bind(
            FindSameSireUseCase::class,
            static fn (ServiceContainer $c): FindSameSireUseCase => new FindSameSireUseCase(
                $c->get(DogRepositoryInterface::class),
                $c->get(SameSireFinderService::class),
            ),
        );

        $this->container->bind(
            FindSameDamUseCase::class,
            static fn (ServiceContainer $c): FindSameDamUseCase => new FindSameDamUseCase(
                $c->get(DogRepositoryInterface::class),
                $c->get(SameDamFinderService::class),
            ),
        );

        $this->container->bind(
            GetPluginSettingsUseCase::class,
            static fn (ServiceContainer $c): GetPluginSettingsUseCase => new GetPluginSettingsUseCase(
                $c->get(SettingsRepositoryInterface::class),
            ),
        );

        $this->container->bind(
            ClearPedigreeCacheUseCase::class,
            static fn (ServiceContainer $c): ClearPedigreeCacheUseCase => new ClearPedigreeCacheUseCase(
                $c->get(CacheInterface::class),
            ),
        );

        $this->container->bind(
            SavePluginSettingsUseCase::class,
            static fn (ServiceContainer $c): SavePluginSettingsUseCase => new SavePluginSettingsUseCase(
                $c->get(SettingsRepositoryInterface::class),
                $c->get(ClearPedigreeCacheUseCase::class),
            ),
        );

        $this->container->bind(
            PluginSettingsValidator::class,
            static fn (): PluginSettingsValidator => new PluginSettingsValidator(),
        );

        $this->container->bind(
            PluginSettingsMapper::class,
            static fn (): PluginSettingsMapper => new PluginSettingsMapper(),
        );

        $this->container->bind(
            UpdatePluginSettingsUseCase::class,
            static fn (ServiceContainer $c): UpdatePluginSettingsUseCase => new UpdatePluginSettingsUseCase(
                $c->get(PluginSettingsValidator::class),
                $c->get(PluginSettingsMapper::class),
                $c->get(SavePluginSettingsUseCase::class),
                $c->get(EventDispatcherInterface::class),
            ),
        );

        $this->container->bind(
            DefaultPluginSettingsFactory::class,
            static fn (): DefaultPluginSettingsFactory => new DefaultPluginSettingsFactory(),
        );

        $this->container->bind(
            AdminSettingsRegistrar::class,
            static fn (ServiceContainer $c): AdminSettingsRegistrar => new AdminSettingsRegistrar(
                $c->get(GetPluginSettingsUseCase::class),
                $c->get(UpdatePluginSettingsUseCase::class),
                $c->get(PluginSettingsMapper::class),
            ),
        );

        $this->container->bind(
            CacheInvalidationRegistrar::class,
            static fn (ServiceContainer $c): CacheInvalidationRegistrar => new CacheInvalidationRegistrar(
                $c->get(ClearPedigreeCacheUseCase::class),
            ),
        );

        $this->container->bind(
            AutoConnectParentsUseCase::class,
            static fn (ServiceContainer $c): AutoConnectParentsUseCase => new AutoConnectParentsUseCase(
                $c->get(DogRepositoryInterface::class),
                $c->get(ParentConnectionInterface::class),
                $c->get(CacheInterface::class),
                $c->get(ParentAutoCreatorInterface::class),
            ),
        );

        $this->container->bind(
            ResolveChildrenForNewParentUseCase::class,
            static fn (ServiceContainer $c): ResolveChildrenForNewParentUseCase => new ResolveChildrenForNewParentUseCase(
                $c->get(DogRepositoryInterface::class),
                $c->get(ParentConnectionInterface::class),
                $c->get(CacheInterface::class),
            ),
        );

        $this->container->bind(
            ParentConnectionRegistrar::class,
            static fn (ServiceContainer $c): ParentConnectionRegistrar => new ParentConnectionRegistrar(
                $c->get(AutoConnectParentsUseCase::class),
                $c->get(ResolveChildrenForNewParentUseCase::class),
            ),
        );

        $this->container->bind(
            AgeShortcode::class,
            static fn (): AgeShortcode => new AgeShortcode(),
        );

        ElementorServiceProvider::register(
            $this->container,
            $this->templateDirectory,
            $this->cssUrl,
            $this->version,
        );
    }
}
