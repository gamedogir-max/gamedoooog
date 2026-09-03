<?php
/**
 * Shortcode: [pedigree_statistics id="123"]
 *
 * @package GameDog\PedigreeEngine\Presentation\Shortcode
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Shortcode;

use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeStatisticsUseCase;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeStatisticsRenderer;

final class PedigreeStatisticsShortcode
{
    public const TAG = 'pedigree_statistics';

    /** @var BuildPedigreeStatisticsUseCase */
    private $useCase;

    /** @var PedigreeStatisticsRenderer */
    private $renderer;

    public function __construct(
        BuildPedigreeStatisticsUseCase $useCase,
        PedigreeStatisticsRenderer $renderer
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
            ['id' => 0],
            is_array($atts) ? $atts : [],
            self::TAG
        );

        $dogId = (int) $atts['id'];
        if ($dogId <= 0 && function_exists('get_the_ID')) {
            $dogId = (int) get_the_ID();
        }

        if ($dogId <= 0) {
            return '<!-- pedigree_statistics: missing dog id -->';
        }

        try {
            $data = $this->useCase->execute($dogId);

            return $this->renderer->render($data);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- pedigree_statistics error: ' . esc_html($e->getMessage()) . ' -->';
            }

            return '<!-- pedigree_statistics error -->';
        }
    }
}
