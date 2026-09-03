<?php
/**
 * Renders the unified sidebar analytics suite (siblings + statistic + card).
 *
 * @package GameDog\PedigreeEngine\Presentation\Renderer
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Renderer;

final class AnalyticsSuiteRenderer
{
    /** @var SiblingsTabsRenderer */
    private $siblingsRenderer;

    /** @var PedigreeStatisticsRenderer */
    private $statsRenderer;

    /** @var DiversityCardRenderer */
    private $diversityRenderer;

    public function __construct(
        SiblingsTabsRenderer $siblingsRenderer,
        PedigreeStatisticsRenderer $statsRenderer,
        DiversityCardRenderer $diversityRenderer
    ) {
        $this->siblingsRenderer  = $siblingsRenderer;
        $this->statsRenderer     = $statsRenderer;
        $this->diversityRenderer = $diversityRenderer;
    }

    /**
     * @param array<string, mixed> $data Expected keys: siblings, stats, metrics.
     */
    public function render(array $data): string
    {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('gamedog-pedigree');
            wp_enqueue_script('gamedog-pedigree');
        }

        $siblings = isset($data['siblings']) && is_array($data['siblings']) ? $data['siblings'] : [];
        $stats    = isset($data['stats']) && is_array($data['stats']) ? $data['stats'] : [];
        $metrics  = isset($data['metrics']) && is_array($data['metrics']) ? $data['metrics'] : [];

        $html  = '<div class="gd-analytics-suite">';

        $html .= '<section class="gd-suite-block gd-suite-siblings">';
        $html .= $this->siblingsRenderer->render($siblings);
        $html .= '</section>';

        $html .= '<section class="gd-suite-block gd-suite-stats">';
        $html .= $this->statsRenderer->render($stats);
        $html .= '</section>';

        $html .= '<section class="gd-suite-block gd-suite-diversity">';
        $html .= $this->diversityRenderer->render($metrics);
        $html .= '</section>';

        $html .= '</div>';

        return $html;
    }
}
