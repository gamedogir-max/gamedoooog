<?php
/**
 * Shortcode: [pedigree_tree id="123" depth="5"]
 *
 * @package GameDog\PedigreeEngine\Presentation\Shortcode
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Shortcode;

use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeTreeUseCase;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeTreeRenderer;

final class PedigreeTreeShortcode
{
    public const TAG = 'pedigree_tree';

    /** @var BuildPedigreeTreeUseCase */
    private $useCase;

    /** @var PedigreeTreeRenderer */
    private $renderer;

    public function __construct(
        BuildPedigreeTreeUseCase $useCase,
        PedigreeTreeRenderer $renderer
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
                'depth' => GD_PEDIGREE_COI_DEPTH,
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
            return '<!-- pedigree_tree: missing dog id -->';
        }

        $depth = (int) $atts['depth'];
        if ($depth < 1) {
            $depth = GD_PEDIGREE_COI_DEPTH;
        }

        $force = in_array((string) $atts['force'], ['1', 'true', 'yes'], true);

        try {
            $dto = $this->useCase->execute($dogId, $depth, $force);

            return $this->renderer->render($dto);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- pedigree_tree error: ' . esc_html($e->getMessage()) . ' -->';
            }

            return '<!-- pedigree_tree error -->';
        }
    }
}
