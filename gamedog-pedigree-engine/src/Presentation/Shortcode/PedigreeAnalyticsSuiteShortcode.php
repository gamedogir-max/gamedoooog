<?php
/**
 * Shortcode: [pedigree_analytics_suite id="123"]
 *
 * Stacks the siblings tabs, pedigree statistic and diversity card.
 *
 * @package GameDog\PedigreeEngine\Presentation\Shortcode
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Shortcode;

use GameDog\PedigreeEngine\Application\UseCase\BuildDiversityMetricsUseCase;
use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeStatisticsUseCase;
use GameDog\PedigreeEngine\Application\UseCase\GetSiblingsUseCase;
use GameDog\PedigreeEngine\Presentation\Renderer\AnalyticsSuiteRenderer;

final class PedigreeAnalyticsSuiteShortcode
{
    public const TAG = 'pedigree_analytics_suite';

    /** @var GetSiblingsUseCase */
    private $siblingsUseCase;

    /** @var BuildPedigreeStatisticsUseCase */
    private $statsUseCase;

    /** @var BuildDiversityMetricsUseCase */
    private $metricsUseCase;

    /** @var AnalyticsSuiteRenderer */
    private $renderer;

    public function __construct(
        GetSiblingsUseCase $siblingsUseCase,
        BuildPedigreeStatisticsUseCase $statsUseCase,
        BuildDiversityMetricsUseCase $metricsUseCase,
        AnalyticsSuiteRenderer $renderer
    ) {
        $this->siblingsUseCase = $siblingsUseCase;
        $this->statsUseCase    = $statsUseCase;
        $this->metricsUseCase  = $metricsUseCase;
        $this->renderer        = $renderer;
    }

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render($atts = []): string
    {
        $atts = shortcode_atts(
            ['id' => 0],
            is_array($atts) ? $atts : [],
            self::TAG
        );

        $dogId = (int) $atts['id'];
        if ($dogId <= 0 && function_exists('get_the_ID')) {
            $dogId = (int) get_the_ID();
        }

        if ($dogId <= 0) {
            return '<!-- pedigree_analytics_suite: missing dog id -->';
        }

        try {
            $siblings = $this->siblingsUseCase->execute($dogId);
            $stats    = $this->statsUseCase->execute($dogId);
            $metrics  = $this->metricsUseCase->execute($dogId);

            $data = [
                'siblings' => [
                    'subject_id' => $dogId,
                    'full'       => $siblings['full'],
                    'sire'       => $siblings['sire'],
                    'dam'        => $siblings['dam'],
                ],
                'stats'    => $stats,
                'metrics'  => $metrics,
            ];

            return $this->renderer->render($data);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- pedigree_analytics_suite error: ' . esc_html($e->getMessage()) . ' -->';
            }

            return '<!-- pedigree_analytics_suite error -->';
        }
    }
}
