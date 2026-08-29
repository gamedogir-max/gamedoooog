<?php

declare(strict_types=1);

namespace GDPE\Presentation\Elementor;

use GDPE\Application\UseCase\FindSameDamUseCase;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Presentation\Renderer\SiblingListRenderer;

/**
 * Elementor widget listing dogs that share the reference dog's dam.
 */
final class SameDamWidget extends AbstractSiblingWidget
{
    private static ?FindSameDamUseCase $useCase = null;

    private static ?SiblingListRenderer $renderer = null;

    public static function bootstrap(
        FindSameDamUseCase $useCase,
        SiblingListRenderer $renderer,
    ): void {
        self::$useCase = $useCase;
        self::$renderer = $renderer;
    }

    public function get_name(): string
    {
        return 'gdpe_same_dam';
    }

    public function get_title(): string
    {
        return esc_html__('Same Dam', 'gdpe');
    }

    /**
     * @return string[]
     */
    public function get_keywords(): array
    {
        return ['dam', 'mother', 'siblings', 'dog', 'gamedog'];
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
        return esc_html__('Same Dam', 'gdpe');
    }

    protected function emptyMessage(): string
    {
        return esc_html__('No dogs with the same dam found.', 'gdpe');
    }
}
