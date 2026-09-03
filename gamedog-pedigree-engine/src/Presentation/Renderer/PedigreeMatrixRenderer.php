<?php
/**
 * Renders the 5-generation authentic pedigree matrix (32 rows, rowspans).
 *
 * @package GameDog\PedigreeEngine\Presentation\Renderer
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Renderer;

final class PedigreeMatrixRenderer
{
    /** @var int */
    private const TOTAL_ROWS = 32;

    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data): string
    {
        $cells = isset($data['cells']) && is_array($data['cells']) ? $data['cells'] : [];
        $dots  = isset($data['linebreeding']) && is_array($data['linebreeding'])
            ? $data['linebreeding']
            : [];

        $rows = $this->buildRows($cells);

        $html  = '<div class="gd-pedigree-matrix-wrap" id="gd-pedigree-matrix">';
        $html .= '<table class="gd-pedigree-matrix">';
        $html .= '<tbody>';

        for ($r = 0; $r < self::TOTAL_ROWS; $r++) {
            $html .= '<tr>';
            $rowCells = isset($rows[$r]) ? $rows[$r] : [];
            foreach ($rowCells as $cell) {
                $html .= $this->renderCell($cell, $dots);
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Group cells into the 32 table rows by their computed start row.
     *
     * @param array<int, array<string, mixed>> $cells
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildRows(array $cells): array
    {
        $rows = [];

        foreach ($cells as $cell) {
            $generation = (int) $cell['generation'];
            $rowspan    = (int) $cell['rowspan'];
            $slot       = (int) $cell['slot'];

            $start = ($slot - (int) pow(2, $generation)) * $rowspan;

            if (!isset($rows[$start])) {
                $rows[$start] = [];
            }
            $rows[$start][] = $cell;
        }

        // Cells in one row are ordered left-to-right by generation.
        foreach ($rows as &$rowCells) {
            usort($rowCells, static function (array $a, array $b): int {
                return ((int) $a['generation']) <=> ((int) $b['generation']);
            });
        }
        unset($rowCells);

        return $rows;
    }

    /**
     * @param array<string, mixed>          $cell
     * @param array<int, array<string, mixed>> $dots
     */
    private function renderCell(array $cell, array $dots): string
    {
        $generation = (int) $cell['generation'];
        $rowspan    = (int) $cell['rowspan'];
        $dog        = isset($cell['dog']) && is_array($cell['dog']) ? $cell['dog'] : null;

        $classes = ['gd-matrix-cell', 'gd-gen-' . $generation];
        if ($dog === null) {
            $classes[] = 'gd-matrix-empty';
        }

        $html  = '<td class="' . esc_attr(implode(' ', $classes)) . '" rowspan="' . esc_attr((string) $rowspan) . '">';
        $html .= $dog !== null
            ? $this->renderResolved($dog, $generation, $dots)
            : $this->renderUnknown();
        $html .= '</td>';

        return $html;
    }

    /**
     * @param array<string, mixed>          $dog
     * @param array<int, array<string, mixed>> $dots
     */
    private function renderResolved(array $dog, int $generation, array $dots): string
    {
        $dogId      = (int) $dog['id'];
        $name       = isset($dog['registered_name']) ? (string) $dog['registered_name'] : '';
        $titles     = isset($dog['titles']) && is_array($dog['titles']) ? $dog['titles'] : [];
        $permalink  = isset($dog['permalink']) ? (string) $dog['permalink'] : '';
        $thumbnail  = isset($dog['thumbnail']) ? (string) $dog['thumbnail'] : '';

        if ($name === '') {
            $name = isset($dog['name']) ? (string) $dog['name'] : 'Dog #' . $dogId;
        }

        $dot = '';
        if ($dogId > 0 && isset($dots[$dogId])) {
            $color = (string) $dots[$dogId]['color'];
            $index = isset($dots[$dogId]['index']) ? (int) $dots[$dogId]['index'] : 0;
            $dot   = '<span class="gd-linebreeding-dot gd-dot-idx-' . $index . '" data-dot-color="' . esc_attr($color) . '" title="Linebreeding: appears ' . (int) $dots[$dogId]['count'] . ' times"></span>';
        }

        $titleHtml = '';
        if ($titles !== []) {
            $titleHtml = '<div class="gd-matrix-titles">'
                . implode(' ', array_map(static function ($t): string {
                    return '<span>' . esc_html((string) $t) . '</span>';
                }, $titles))
                . '</div>';
        }

        $nameHtml = $permalink !== '' && $permalink !== '#'
            ? '<a class="gd-matrix-name" href="' . esc_url($permalink) . '">' . esc_html($name) . '</a>'
            : '<span class="gd-matrix-name">' . esc_html($name) . '</span>';

        $mediaHtml = $this->renderMedia($thumbnail, $name, $generation);

        $html  = '<div class="gd-matrix-inner">';
        $html .= $mediaHtml;
        $html .= '<div class="gd-matrix-text">' . $titleHtml . '<div class="gd-matrix-name-row">' . $dot . $nameHtml . '</div></div>';
        $html .= '</div>';

        return $html;
    }

    private function renderUnknown(): string
    {
        return '<div class="gd-matrix-inner"><div class="gd-matrix-text"><span class="gd-matrix-unknown">Unknown</span></div></div>';
    }

    private function renderMedia(string $thumbnail, string $name, int $generation): string
    {
        if ($thumbnail === '') {
            return '';
        }

        $alt = esc_attr($name);

        // Generations 1 and 2: framed rectangular photo.
        if ($generation <= 2) {
            return '<a class="gd-matrix-photo" href="' . esc_url($thumbnail) . '" target="_blank" rel="noopener">'
                . '<img src="' . esc_url($thumbnail) . '" alt="' . $alt . '" loading="lazy" />'
                . '</a>';
        }

        // Generations 3, 4, 5: compact clickable camera icon.
        return '<a class="gd-matrix-camera" href="' . esc_url($thumbnail) . '" target="_blank" rel="noopener" title="View photo of ' . $alt . '" aria-label="View photo of ' . $alt . '">'
            . '<span class="gd-matrix-camera-icon" aria-hidden="true">&#128247;</span>'
            . '</a>';
    }
}
