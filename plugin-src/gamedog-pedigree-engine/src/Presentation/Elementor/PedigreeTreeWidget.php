<?php

declare(strict_types=1);

namespace GDPE\Presentation\Elementor;

use DomainException;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Widget_Base;
use GDPE\Application\UseCase\BuildPedigreeTreeUseCase;
use GDPE\Domain\Exception\DogNotFoundException;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Domain\ValueObject\Generation;
use GDPE\Presentation\Renderer\PedigreeTreeRenderer;
use InvalidArgumentException;
use Throwable;

/**
 * Elementor widget that renders a dog's pedigree tree.
 */
final class PedigreeTreeWidget extends Widget_Base
{
    private static ?BuildPedigreeTreeUseCase $buildPedigreeTree = null;

    private static ?PedigreeTreeRenderer $renderer = null;

    /**
     * Injects the shared collaborators.
     */
    public static function bootstrap(
        BuildPedigreeTreeUseCase $buildPedigreeTree,
        PedigreeTreeRenderer $renderer,
    ): void {
        self::$buildPedigreeTree = $buildPedigreeTree;
        self::$renderer = $renderer;
    }

    public function get_name(): string
    {
        return 'gdpe_pedigree_tree';
    }

    public function get_title(): string
    {
        return esc_html__('Pedigree Tree', 'gdpe');
    }

    public function get_icon(): string
    {
        return 'eicon-sitemap';
    }

    /**
     * @return string[]
     */
    public function get_categories(): array
    {
        return [PedigreeCategoryRegistrar::CATEGORY_SLUG];
    }

    /**
     * @return string[]
     */
    public function get_style_depends(): array
    {
        return [PedigreeAssetRegistrar::STYLE_HANDLE];
    }

    /**
     * @return string[]
     */
    public function get_keywords(): array
    {
        return ['pedigree', 'dog', 'tree', 'ancestry', 'gamedog'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'gdpe_content_section',
            [
                'label' => esc_html__('Pedigree', 'gdpe'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'dog_id',
            [
                'label' => esc_html__('Dog ID', 'gdpe'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'dynamic' => ['active' => true],
                'description' => esc_html__(
                    'The post ID of the dog whose pedigree should be shown. Leave empty to auto-detect current dog.',
                    'gdpe'
                ),
            ]
        );

        $this->add_control(
            'generation',
            [
                'label' => esc_html__('Generations', 'gdpe'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->generationOptions(),
                'default' => '5',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Builds the generation dropdown options.
     *
     * @return array<int, string>
     */
    private function generationOptions(): array
    {
        $options = [];

        foreach (Generation::allowedValues() as $value) {
            $options[(string) $value] = sprintf(
                /* translators: %d: number of generations. */
                esc_html__('%d generations', 'gdpe'),
                $value
            );
        }

        return $options;
    }

    protected function render(): void
    {
        $renderer = self::$renderer;
        $useCase = self::$buildPedigreeTree;

        if ($renderer === null || $useCase === null) {
            return;
        }

        $settings = $this->get_settings_for_display();

        // 1. Auto-detect current post ID if empty
        $rawDogId = (int) ($settings['dog_id'] ?? 0);
        if ($rawDogId <= 0) {
            $rawDogId = (int) get_the_ID();
        }

        if ($rawDogId <= 0) {
            $rawDogId = get_queried_object_id();
        }

        // 2. Elementor editor preview mode fallback
        if ($rawDogId <= 0 && Plugin::$instance->documents->get_current()) {
            $rawDogId = (int) Plugin::$instance->documents->get_current()->get_main_id();
        }

        $rawGeneration = (int) ($settings['generation'] ?? 5);
        if ($rawGeneration <= 0) {
            $rawGeneration = 5;
        }

        if ($rawDogId <= 0) {
            echo $renderer->renderNotice(
                esc_html__('Please select a dog to display its pedigree.', 'gdpe')
            );

            return;
        }

        try {
            $dogId = new DogId($rawDogId);
            $generation = Generation::fromInt($rawGeneration);
        } catch (InvalidArgumentException | DomainException) {
            echo $renderer->renderNotice(
                esc_html__('The pedigree widget is not configured correctly.', 'gdpe')
            );

            return;
        }

        try {
            $tree = $useCase->execute($dogId, $generation);
        } catch (DogNotFoundException) {
            echo $renderer->renderNotice(
                esc_html__('No pedigree found for the selected dog.', 'gdpe')
            );

            return;
        } catch (Throwable) {
            echo $renderer->renderNotice(
                esc_html__('The pedigree could not be loaded.', 'gdpe')
            );

            return;
        }

        echo $renderer->render($tree);
    }
}