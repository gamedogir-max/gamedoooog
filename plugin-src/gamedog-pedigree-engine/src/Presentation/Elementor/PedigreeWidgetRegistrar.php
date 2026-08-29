<?php

declare(strict_types=1);

namespace GDPE\Presentation\Elementor;

use Elementor\Widgets_Manager;
use GDPE\Application\UseCase\BuildPedigreeTreeUseCase;
use GDPE\Application\UseCase\FindFullSiblingsUseCase;
use GDPE\Application\UseCase\FindSameDamUseCase;
use GDPE\Application\UseCase\FindSameSireUseCase;
use GDPE\Presentation\Renderer\PedigreeTreeRenderer;
use GDPE\Presentation\Renderer\SiblingListRenderer;

/**
 * Registers GDPE widgets with Elementor.
 */
final class PedigreeWidgetRegistrar
{
    public function __construct(
        private readonly BuildPedigreeTreeUseCase $buildPedigreeTree,
        private readonly PedigreeTreeRenderer $renderer,
        private readonly FindFullSiblingsUseCase $findFullSiblings,
        private readonly FindSameSireUseCase $findSameSire,
        private readonly FindSameDamUseCase $findSameDam,
        private readonly SiblingListRenderer $siblingListRenderer,
    ) {
    }

    public function register(): void
    {
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
    }

    public function registerWidgets(Widgets_Manager $widgetsManager): void
    {
        // Bootstrap calls are deferred to here so Elementor\Widget_Base is
        // guaranteed to exist before the widget class files are autoloaded.
        PedigreeTreeWidget::bootstrap($this->buildPedigreeTree, $this->renderer);
        FullSiblingsWidget::bootstrap($this->findFullSiblings, $this->siblingListRenderer);
        SameSireWidget::bootstrap($this->findSameSire, $this->siblingListRenderer);
        SameDamWidget::bootstrap($this->findSameDam, $this->siblingListRenderer);

        try {
            $widgetsManager->register(new PedigreeTreeWidget());
            $widgetsManager->register(new FullSiblingsWidget());
            $widgetsManager->register(new SameSireWidget());
            $widgetsManager->register(new SameDamWidget());
        } catch (\Throwable $e) {
            error_log('GDPE -> Widget registration FAILED: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}