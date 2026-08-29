<?php

declare(strict_types=1);

namespace GDPE\Presentation\Shortcodes;

use GDPE\Application\UseCase\FindFullSiblingsUseCase;
use GDPE\Application\UseCase\FindSameDamUseCase;
use GDPE\Application\UseCase\FindSameSireUseCase;
use GDPE\Domain\Exception\DogNotFoundException;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Presentation\Elementor\PedigreeAssetRegistrar;
use GDPE\Presentation\Renderer\SiblingsBoxRenderer;
use GDPE\Presentation\Support\CurrentDogContextResolver;
use Throwable;

final class SiblingsUnifiedShortcode
{
    private static ?FindFullSiblingsUseCase $fullSiblingsUseCase = null;
    private static ?FindSameSireUseCase $sameSireUseCase = null;
    private static ?FindSameDamUseCase $sameDamUseCase = null;
    private static ?SiblingsBoxRenderer $renderer = null;
    private static ?CurrentDogContextResolver $currentDogContextResolver = null;

    public static function bootstrap(
        FindFullSiblingsUseCase $fullSiblingsUseCase,
        FindSameSireUseCase $sameSireUseCase,
        FindSameDamUseCase $sameDamUseCase,
        SiblingsBoxRenderer $renderer,
        CurrentDogContextResolver $currentDogContextResolver,
    ): void {
        self::$fullSiblingsUseCase = $fullSiblingsUseCase;
        self::$sameSireUseCase = $sameSireUseCase;
        self::$sameDamUseCase = $sameDamUseCase;
        self::$renderer = $renderer;
        self::$currentDogContextResolver = $currentDogContextResolver;

        add_shortcode('gamedog_siblings_box', [self::class, 'render']);
    }

    /**
     * @param array<string, string>|string $attributes
     */
    public static function render(array|string $attributes = []): string
    {
        if (
            self::$fullSiblingsUseCase === null
            || self::$sameSireUseCase === null
            || self::$sameDamUseCase === null
            || self::$renderer === null
            || self::$currentDogContextResolver === null
        ) {
            return '';
        }

        $currentDogId = self::resolveDogId($attributes);

        if ($currentDogId <= 0) {
            return '';
        }

        self::enqueueAssets();

        try {
            $dogId = new DogId($currentDogId);

            $fullSiblings = self::$fullSiblingsUseCase->execute($dogId);
            $sameSire = self::safeResolve(static fn (DogId $resolvedDogId): array => self::$sameSireUseCase?->execute($resolvedDogId) ?? [], $dogId);
            $sameDam = self::safeResolve(static fn (DogId $resolvedDogId): array => self::$sameDamUseCase?->execute($resolvedDogId) ?? [], $dogId);

            return self::$renderer->render($currentDogId, $fullSiblings, $sameSire, $sameDam);
        } catch (DogNotFoundException) {
            return self::$renderer->renderNotice(
                esc_html__('The selected dog could not be found.', 'gdpe'),
            );
        } catch (Throwable) {
            return self::$renderer->renderNotice(
                esc_html__('The related dogs could not be loaded.', 'gdpe'),
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
            'gamedog_siblings_box',
        );

        $attributeDogId = (int) ($normalizedAttributes['dog_id'] ?? 0);

        if ($attributeDogId > 0) {
            return $attributeDogId;
        }

        return self::$currentDogContextResolver?->resolve() ?? 0;
    }

    /**
     * @param callable(DogId): array<int, mixed> $resolver
     *
     * @return array<int, mixed>
     */
    private static function safeResolve(callable $resolver, DogId $dogId): array
    {
        try {
            return $resolver($dogId);
        } catch (Throwable) {
            return [];
        }
    }

    private static function enqueueAssets(): void
    {
        wp_enqueue_style(PedigreeAssetRegistrar::STYLE_HANDLE);
    }
}
