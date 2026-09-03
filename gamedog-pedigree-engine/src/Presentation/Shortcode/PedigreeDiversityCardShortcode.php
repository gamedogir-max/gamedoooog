<?php
/**
 * Shortcode: [pedigree_diversity_card id="123"]
 *
 * @package GameDog\PedigreeEngine\Presentation\Shortcode
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Shortcode;

use GameDog\PedigreeEngine\Application\UseCase\BuildDiversityMetricsUseCase;
use GameDog\PedigreeEngine\Presentation\Renderer\DiversityCardRenderer;

final class PedigreeDiversityCardShortcode
{
    public const TAG = 'pedigree_diversity_card';

    /** @var BuildDiversityMetricsUseCase */
    private $useCase;

    /** @var DiversityCardRenderer */
    private $renderer;

    public function __construct(
        BuildDiversityMetricsUseCase $useCase,
        DiversityCardRenderer $renderer
    ) {
        $this->useCase  = $useCase;
        $this->renderer = $renderer;
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
            [
                'id'    => 0,
                'force' => '0',
            ],
            is_array($atts) ? $atts : [],
            self::TAG
        );

        $dogId = (int) $atts['id'];
        if ($dogId <= 0 && function_exists('get_the_ID')) {
            $dogId = (int) get_the_ID();
        }

        if ($dogId <= 0) {
            return '<!-- pedigree_diversity_card: missing dog id -->';
        }

        $force = in_array((string) $atts['force'], ['1', 'true', 'yes'], true);

        try {
            $data = $this->useCase->execute($dogId, $force);

            return $this->renderer->render($data);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- pedigree_diversity_card error: ' . esc_html($e->getMessage()) . ' -->';
            }

            return '<!-- pedigree_diversity_card error -->';
        }
    }
}
