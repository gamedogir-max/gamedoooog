<?php
/**
 * Renders the pedigree statistic (blood contribution) table.
 *
 * @package GameDog\PedigreeEngine\Presentation\Renderer
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Renderer;

final class PedigreeStatisticsRenderer
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data): string
    {
        $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : [];

        $html  = '<div class="gd-pedigree-stats">';
        $html .= '<h3 class="gd-stats-header">Pedigree statistic</h3>';

        if ($rows === []) {
            $html .= '<p class="gd-stats-empty">No ancestor data available.</p>';
            $html .= '</div>';

            return $html;
        }

        $html .= '<table class="gd-stats-table"><tbody>';

        foreach ($rows as $row) {
            $name      = isset($row['name']) ? (string) $row['name'] : '';
            $permalink = isset($row['permalink']) ? (string) $row['permalink'] : '';
            $count     = isset($row['count']) ? (int) $row['count'] : 0;
            $percent   = isset($row['percent']) ? (float) $row['percent'] : 0.0;

            $html .= '<tr>';

            $html .= '<td class="gd-stats-name">';
            if ($permalink !== '' && $permalink !== '#') {
                $html .= '<a href="' . esc_url($permalink) . '">' . esc_html(strtoupper($name)) . '</a>';
            } else {
                $html .= '<span>' . esc_html(strtoupper($name)) . '</span>';
            }
            $html .= '</td>';

            $html .= '<td class="gd-stats-count">' . esc_html($count . 'x') . '</td>';
            $html .= '<td class="gd-stats-percent">' . esc_html($this->formatPercent($percent)) . '</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }

    private function formatPercent(float $percent): string
    {
        if (abs($percent - round($percent)) < 0.0001) {
            return (string) (int) round($percent) . '%';
        }

        $formatted = number_format($percent, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . '%';
    }
}
