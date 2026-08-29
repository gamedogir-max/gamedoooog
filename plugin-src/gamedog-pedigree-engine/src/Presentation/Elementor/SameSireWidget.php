<?php

declare(strict_types=1);

namespace GDPE\Presentation\Elementor;

use GDPE\Application\UseCase\FindSameSireUseCase;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Presentation\Renderer\SiblingListRenderer;

/**
 * Elementor widget listing dogs that share the reference dog's sire.
 */
final class SameSireWidget extends AbstractSiblingWidget
{
    private static ?FindSameSireUseCase $useCase = null;

    private static ?SiblingListRenderer $renderer = null;

    public static function bootstrap(
        FindSameSireUseCase $useCase,
        SiblingListRenderer $renderer,
    ): void {
        self::$useCase = $useCase;
        self::$renderer = $renderer;
    }

    public function get_name(): string
    {
        return 'gdpe_same_sire';
    }

    public function get_title(): string
    {
        return esc_html__('Same Sire', 'gdpe');
    }

    /**
     * @return string[]
     */
    public function get_keywords(): array
    {
        return ['sire', 'father', 'siblings', 'dog', 'gamedog'];
    }

    protected function findRelatedDogs(DogId $dogId): array
    {
        return self::$useCase?->execute($dogId) ?? [];
    }

    protected function renderer(): ?SiblingListRenderer
    {
        return self::$renderer;
    }

    protected function defaultHeading(): string
    {
        return esc_html__('Same Sire', 'gdpe');
    }

    protected function emptyMessage(): string
    {
        return esc_html__('No dogs with the same sire found.', 'gdpe');
    }
}
