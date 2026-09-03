<?php
/**
 * Renders the COI / AVK genetic diversity metrics card.
 *
 * @package GameDog\PedigreeEngine\Presentation\Renderer
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Renderer;

final class DiversityCardRenderer
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data): string
    {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('gamedog-pedigree');
        }

        $coiPercent = isset($data['coi_percent']) ? (float) $data['coi_percent'] : 0.0;
        $avkPercent = isset($data['avk_percent']) ? (float) $data['avk_percent'] : 0.0;
        $depth      = isset($data['depth']) ? (int) $data['depth'] : 4;

        $html  = '<div class="gd-diversity-card">';
        $html .= '<div class="gd-diversity-grid">';

        $html .= '<div class="gd-diversity-col">';
        $html .= '<div class="gd-diversity-label">Coefficient of inbreeding (COI) <span class="gd-info" title="Wright coefficient of inbreeding">&#8505;</span></div>';
        $html .= '<div class="gd-diversity-value">' . esc_html($this->formatValue($coiPercent)) . '</div>';
        $html .= '</div>';

        $html .= '<div class="gd-diversity-col">';
        $html .= '<div class="gd-diversity-label">Ancestor loss (AVK) <span class="gd-info" title="Ancestor loss coefficient">&#8505;</span></div>';
        $html .= '<div class="gd-diversity-value">' . esc_html($this->formatValue($avkPercent)) . '</div>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<p class="gd-diversity-note">This COI and AVK is calculated from ' . (int) $depth . ' generations pedigree.</p>';
        $html .= '<a class="gd-diversity-cta" href="#gd-pedigree-matrix">View detailed pedigree analysis</a>';
        $html .= '</div>';

        return $html;
    }

    private function formatValue(float $percent): string
    {
        return number_format($percent, 2, '.', '') . ' %';
    }
}
