<?php
/**
 * Elementor widget for the pedigree tree.
 *
 * This file is only required from PluginBootstrap after Elementor has loaded,
 * so Widget_Base is guaranteed to exist.
 *
 * @package GameDog\PedigreeEngine\Presentation\Elementor
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Elementor;

use GameDog\PedigreeEngine\Application\Port\ContainerInterface;
use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeTreeUseCase;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeTreeRenderer;

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

/**
 * Elementor widget: GameDog Pedigree Tree.
 */
class PedigreeTreeWidget extends \Elementor\Widget_Base
{
    /** @var ContainerInterface|null */
    private $container;

    /**
     * @param ContainerInterface|array $data Container or Elementor data array.
     * @param array|null               $args
     */
    public function __construct($data = [], $args = null)
    {
        if ($data instanceof ContainerInterface) {
            $this->container = $data;
            parent::__construct([], null);

            return;
        }

        parent::__construct(is_array($data) ? $data : [], $args);
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @return string
     */
    public function get_name()
    {
        return 'gamedog_pedigree_tree';
    }

    /**
     * @return string
     */
    public function get_title()
    {
        return 'GameDog Pedigree Tree';
    }

    /**
     * @return string
     */
    public function get_icon()
    {
        return 'eicon-sitemap';
    }

    /**
     * @return array<int, string>
     */
    public function get_categories()
    {
        return ['general'];
    }

    /**
     * @return array<int, string>
     */
    public function get_keywords()
    {
        return ['pedigree', 'dog', 'coi', 'inbreeding', 'gamedog'];
    }

    /**
     * @return void
     */
    protected function register_controls()
    {
        $this->start_controls_section('section_content', [
            'label' => 'Pedigree',
        ]);

        $this->add_control('dog_id', [
            'label'       => 'Dog ID',
            'type'        => \Elementor\Controls_Manager::NUMBER,
            'default'     => 0,
            'description' => 'Leave 0 to use the current post.',
        ]);

        $this->add_control('depth', [
            'label'   => 'Generations',
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => defined('GD_PEDIGREE_COI_DEPTH') ? GD_PEDIGREE_COI_DEPTH : 5,
            'min'     => 1,
            'max'     => 10,
        ]);

        $this->end_controls_section();
    }

    /**
     * @return void
     */
    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $dogId    = isset($settings['dog_id']) ? (int) $settings['dog_id'] : 0;
        $depth    = isset($settings['depth']) ? (int) $settings['depth'] : 5;

        if ($dogId <= 0 && function_exists('get_the_ID')) {
            $dogId = (int) get_the_ID();
        }

        if ($dogId <= 0) {
            echo '<!-- gamedog pedigree: no dog id -->';

            return;
        }

        if ($depth < 1) {
            $depth = defined('GD_PEDIGREE_COI_DEPTH') ? GD_PEDIGREE_COI_DEPTH : 5;
        }

        try {
            $container = $this->resolveContainer();
            /** @var BuildPedigreeTreeUseCase $useCase */
            $useCase = $container->get(BuildPedigreeTreeUseCase::class);
            /** @var PedigreeTreeRenderer $renderer */
            $renderer = $container->get(PedigreeTreeRenderer::class);

            $dto = $useCase->execute($dogId, $depth);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $renderer->render($dto);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<!-- gamedog pedigree error: ' . esc_html($e->getMessage()) . ' -->';
            }
        }
    }

    private function resolveContainer(): ContainerInterface
    {
        if ($this->container instanceof ContainerInterface) {
            return $this->container;
        }

        return \GameDog\PedigreeEngine\Infrastructure\WordPress\PluginBootstrap::instance()->container();
    }
}
