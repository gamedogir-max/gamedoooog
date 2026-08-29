<?php

declare(strict_types=1);

namespace GDPE\Presentation\Elementor;

use GDPE\Application\UseCase\FindFullSiblingsUseCase;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Presentation\Renderer\SiblingListRenderer;

/**
 * Elementor widget listing a dog's full siblings (same sire and dam).
 */
final class FullSiblingsWidget extends AbstractSiblingWidget
{
    private static ?FindFullSiblingsUseCase $useCase = null;

    private static ?SiblingListRenderer $renderer = null;

    public static function bootstrap(
        FindFullSiblingsUseCase $useCase,
        SiblingListRenderer $renderer,
    ): void {
        self::$useCase = $useCase;
        self::$renderer = $renderer;
    }

    public function get_name(): string
    {
        return 'gdpe_full_siblings';
    }

    public function get_title(): string
    {
        return esc_html__('Full Siblings', 'gdpe');
    }

    /**
     * @return string[]
     */
    public function get_keywords(): array
    {
        return ['siblings', 'full', 'dog', 'litter', 'gamedog'];
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
        return esc_html__('Full Siblings', 'gdpe');
    }

    protected function emptyMessage(): string
    {
        return esc_html__('No full siblings found.', 'gdpe');
    }
}
