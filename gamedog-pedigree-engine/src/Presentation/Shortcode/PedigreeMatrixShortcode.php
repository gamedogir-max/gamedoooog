<?php
/**
 * Shortcode: [pedigree_tree id="123"]
 *
 * Renders the 5-generation authentic pedigree matrix.
 *
 * @package GameDog\PedigreeEngine\Presentation\Shortcode
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Shortcode;

use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeMatrixUseCase;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeMatrixRenderer;

final class PedigreeMatrixShortcode
{
    public const TAG = 'pedigree_tree';

    /** @var BuildPedigreeMatrixUseCase */
    private $useCase;

    /** @var PedigreeMatrixRenderer */
    private $renderer;

    public function __construct(
        BuildPedigreeMatrixUseCase $useCase,
        PedigreeMatrixRenderer $renderer
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
            return '<!-- pedigree_tree: missing dog id -->';
        }

        try {
            $data = $this->useCase->execute($dogId);

            return $this->renderer->render($data);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- pedigree_tree error: ' . esc_html($e->getMessage()) . ' -->';
            }

            return '<!-- pedigree_tree error -->';
        }
    }
}
