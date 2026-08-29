<?php

declare(strict_types=1);

namespace GDPE\Presentation\ServiceProvider;

use GDPE\Application\UseCase\BuildPedigreeTreeUseCase;
use GDPE\Application\UseCase\FindFullSiblingsUseCase;
use GDPE\Application\UseCase\FindSameDamUseCase;
use GDPE\Application\UseCase\FindSameSireUseCase;
use GDPE\Infrastructure\Container\ServiceContainer;
use GDPE\Presentation\Elementor\PedigreeAssetRegistrar;
use GDPE\Presentation\Elementor\PedigreeCategoryRegistrar;
use GDPE\Presentation\Elementor\PedigreeWidgetRegistrar;
use GDPE\Presentation\Renderer\PedigreeTreeRenderer;
use GDPE\Presentation\Renderer\SiblingListRenderer;
use GDPE\Presentation\Renderer\SiblingsBoxRenderer;
use GDPE\Presentation\Support\CurrentDogContextResolver;
use GDPE\Presentation\ViewModel\PedigreeTreeViewModelBuilder;

/**
 * Registers every Presentation-layer binding.
 *
 * Kept separate from the Infrastructure provider so the Presentation
 * integration is a self-contained slice of the composition root. The
 * template directory, CSS URL, and version are supplied by the caller
 * (the plugin's main file) so no Presentation class hardcodes a
 * filesystem path or reaches for a global plugin constant.
 */
final class ElementorServiceProvider
{
    public static function register(
        ServiceContainer $container,
        string $templateDirectory,
        string $cssUrl,
        string $version,
    ): void {
        $container->bind(
            CurrentDogContextResolver::class,
            static fn (): CurrentDogContextResolver => new CurrentDogContextResolver(),
        );

        $container->bind(
            PedigreeTreeViewModelBuilder::class,
            static fn (): PedigreeTreeViewModelBuilder => new PedigreeTreeViewModelBuilder(),
        );

        $container->bind(
            PedigreeTreeRenderer::class,
            static fn (ServiceContainer $c): PedigreeTreeRenderer => new PedigreeTreeRenderer(
                $templateDirectory,
                $c->get(PedigreeTreeViewModelBuilder::class),
            ),
        );

        $container->bind(
            PedigreeCategoryRegistrar::class,
            static fn (): PedigreeCategoryRegistrar => new PedigreeCategoryRegistrar(),
        );

        $container->bind(
            PedigreeAssetRegistrar::class,
            static fn (): PedigreeAssetRegistrar => new PedigreeAssetRegistrar(
                $cssUrl,
                $version,
            ),
        );

        $container->bind(
            SiblingListRenderer::class,
            static fn (): SiblingListRenderer => new SiblingListRenderer(
                $templateDirectory,
            ),
        );

        $container->bind(
            SiblingsBoxRenderer::class,
            static fn (): SiblingsBoxRenderer => new SiblingsBoxRenderer(
                $templateDirectory,
            ),
        );

        $container->bind(
            PedigreeWidgetRegistrar::class,
            static fn (ServiceContainer $c): PedigreeWidgetRegistrar => new PedigreeWidgetRegistrar(
                $c->get(BuildPedigreeTreeUseCase::class),
                $c->get(PedigreeTreeRenderer::class),
                $c->get(FindFullSiblingsUseCase::class),
                $c->get(FindSameSireUseCase::class),
                $c->get(FindSameDamUseCase::class),
                $c->get(SiblingListRenderer::class),
            ),
        );
    }
}
