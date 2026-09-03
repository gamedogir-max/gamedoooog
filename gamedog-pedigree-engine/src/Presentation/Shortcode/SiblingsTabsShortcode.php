<?php
/**
 * Shortcode: [siblings_tabs id="123"]
 *
 * @package GameDog\PedigreeEngine\Presentation\Shortcode
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Shortcode;

use GameDog\PedigreeEngine\Application\UseCase\GetSiblingsUseCase;
use GameDog\PedigreeEngine\Presentation\Renderer\SiblingsTabsRenderer;

final class SiblingsTabsShortcode
{
    public const TAG = 'siblings_tabs';

    /** @var GetSiblingsUseCase */
    private $useCase;

    /** @var SiblingsTabsRenderer */
    private $renderer;

    public function __construct(
        GetSiblingsUseCase $useCase,
        SiblingsTabsRenderer $renderer
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
            return '<!-- siblings_tabs: missing dog id -->';
        }

        try {
            $result = $this->useCase->execute($dogId);

            $data = [
                'subject_id' => $dogId,
                'full'       => $result['full'],
                'sire'       => $result['sire'],
                'dam'        => $result['dam'],
            ];

            return $this->renderer->render($data);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- siblings_tabs error: ' . esc_html($e->getMessage()) . ' -->';
            }

            return '<!-- siblings_tabs error -->';
        }
    }
}
