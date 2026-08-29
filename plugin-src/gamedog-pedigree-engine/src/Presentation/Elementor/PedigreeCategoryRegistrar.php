<?php

declare(strict_types=1);

namespace GDPE\Presentation\Elementor;

use Elementor\Elements_Manager;

/**
 * Registers the "GameDog Pedigree Engine" widget category so all GDPE
 * widgets are grouped together in the Elementor panel.
 */
final class PedigreeCategoryRegistrar
{
    public const CATEGORY_SLUG = 'gdpe';

    public function register(): void
    {
        add_action(
            'elementor/elements/categories_registered',
            [$this, 'registerCategory']
        );
    }

    public function registerCategory(Elements_Manager $elementsManager): void
    {
        $elementsManager->add_category(
            self::CATEGORY_SLUG,
            [
                'title' => esc_html__('GameDog Pedigree Engine', 'gdpe'),
                'icon' => 'fa fa-paw',
            ]
        );
    }
}
