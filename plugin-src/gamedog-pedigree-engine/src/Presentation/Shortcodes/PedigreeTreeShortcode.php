<?php

declare(strict_types=1);

namespace GDPE\Presentation\Shortcodes;

use GDPE\Application\UseCase\BuildPedigreeTreeUseCase;
use GDPE\Domain\Exception\DogNotFoundException;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Domain\ValueObject\Generation;
use GDPE\Presentation\Elementor\PedigreeAssetRegistrar;
use GDPE\Presentation\Renderer\PedigreeTreeRenderer;
use GDPE\Presentation\Support\CurrentDogContextResolver;
use Throwable;

final class PedigreeTreeShortcode
{
    private static ?BuildPedigreeTreeUseCase $useCase = null;
    private static ?PedigreeTreeRenderer $renderer = null;
    private static ?CurrentDogContextResolver $currentDogContextResolver = null;

    public static function bootstrap(
        BuildPedigreeTreeUseCase $useCase,
        PedigreeTreeRenderer $renderer,
        CurrentDogContextResolver $currentDogContextResolver,
    ): void {
        self::$useCase = $useCase;
        self::$renderer = $renderer;
        self::$currentDogContextResolver = $currentDogContextResolver;

        add_shortcode('gamedog_pedigree_tree', [self::class, 'render']);
    }

    /**
     * @param array<string, string>|string $attributes
     */
    public static function render(array|string $attributes = []): string
    {
        if (
            self::$useCase === null
            || self::$renderer === null
            || self::$currentDogContextResolver === null
        ) {
            return '';
        }

        $currentId = self::resolveDogId($attributes);

        if ($currentId <= 0) {
            return '';
        }

        self::enqueueAssets();

        try {
            $tree = self::$useCase->execute(
                new DogId($currentId),
                Generation::fromInt(5),
            );

            return self::$renderer->render($tree);
        } catch (DogNotFoundException) {
            return self::$renderer->renderNotice(
                esc_html__('No pedigree found for the selected dog.', 'gdpe'),
            );
        } catch (Throwable) {
            return self::$renderer->renderNotice(
                esc_html__('The pedigree could not be loaded.', 'gdpe'),
            );
        }
    }

    /**
     * @param array<string, string>|string $attributes
     */
    private static function resolveDogId(array|string $attributes): int
    {
        $normalizedAttributes = shortcode_atts(
            ['dog_id' => '0'],
            is_array($attributes) ? $attributes : [],
            'gamedog_pedigree_tree',
        );

        $attributeDogId = (int) ($normalizedAttributes['dog_id'] ?? 0);

        if ($attributeDogId > 0) {
            return $attributeDogId;
        }

        return self::$currentDogContextResolver?->resolve() ?? 0;
    }

    private static function enqueueAssets(): void
    {
        wp_enqueue_style(PedigreeAssetRegistrar::STYLE_HANDLE);
    }
}
