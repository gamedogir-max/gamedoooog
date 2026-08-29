<?php
/**
 * Compact pedigree tree template.
 *
 * @var array{
 *     header_title: string,
 *     header_meta: string,
 *     generation_count: int,
 *     root: array{id: int, title: string, permalink: string},
 *     branches: array{
 *         sire: array<string, mixed>,
 *         dam: array<string, mixed>
 *     }
 * } $pedigree
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gdpe_render_tree_cell_item')) {
    /**
     * @param array<string, mixed> $node
     */
    function gdpe_render_tree_cell_item(array $node, int $rowspan, int $generation): string
    {
        $palette = ['#52b788', '#9d4edd', '#2a9d8f', '#e76f51', '#c77dff', '#b5838d', '#3a86ff'];
        $isKnown = ($node['is_known'] ?? false) === true;
        $title = isset($node['title']) && is_string($node['title']) && $node['title'] !== ''
            ? $node['title']
            : __('Unknown', 'gdpe');

        if (!$isKnown || empty($node['id'])) {
            return sprintf(
                '<td rowspan="%1$d" class="gd-ped-cell gd-ped-empty gd-gen-%2$d"><span class="gd-ped-empty-text">%3$s</span></td>',
                $rowspan,
                $generation,
                esc_html($title),
            );
        }

        $id = (int) $node['id'];
        $link = isset($node['permalink']) && is_string($node['permalink']) ? $node['permalink'] : '';
        $thumb = get_the_post_thumbnail_url($id, 'thumbnail');
        $titles = (string) get_post_meta($id, 'dog_titles', true);
        $storedColor = get_post_meta($id, 'pedigree_color_dot', true);
        $colorDot = is_string($storedColor) && $storedColor !== ''
            ? $storedColor
            : $palette[$id % count($palette)];

        if ($link === '') {
            $permalink = get_permalink($id);
            $link = is_string($permalink) ? $permalink : '';
        }

        $out = '<td rowspan="' . esc_attr((string) $rowspan) . '" class="gd-ped-cell gd-gen-' . esc_attr((string) $generation) . '">';
        $out .= '<div class="gd-ped-content">';

        if ($titles !== '') {
            $out .= '<div class="gd-ped-titles">' . esc_html($titles) . '</div>';
        }

        $out .= '<div class="gd-ped-title-wrap">';

        if ($link !== '') {
            $out .= '<a href="' . esc_url($link) . '" class="gd-ped-name">' . esc_html($title) . '</a>';
        } else {
            $out .= '<span class="gd-ped-name">' . esc_html($title) . '</span>';
        }

        if (!empty($thumb)) {
            $out .= ' <span class="gd-ped-cam" title="' . esc_attr__('Photo available', 'gdpe') . '">📷</span>';
        }

        $out .= '</div>';

        if (($generation === 1 || $generation === 2) && !empty($thumb)) {
            $out .= '<div class="gd-ped-thumb">';

            if ($link !== '') {
                $out .= '<a href="' . esc_url($link) . '">';
            }

            $out .= '<img src="' . esc_url((string) $thumb) . '" alt="' . esc_attr($title) . '">';

            if ($link !== '') {
                $out .= '</a>';
            }

            $out .= '</div>';
        }

        if ($generation >= 3) {
            $out .= '<div class="gd-ped-dot" style="background-color:' . esc_attr($colorDot) . ';"></div>';
        }

        $out .= '</div>';
        $out .= '</td>';

        return $out;
    }
}

$generationCount = max(1, (int) ($pedigree['generation_count'] ?? 5));
$totalRows = (int) (2 ** $generationCount);
$branchRows = max(1, intdiv($totalRows, 2));
$rows = array_fill(0, $totalRows, '');
$sireBranch = is_array($pedigree['branches']['sire'] ?? null) ? $pedigree['branches']['sire'] : [];
$damBranch = is_array($pedigree['branches']['dam'] ?? null) ? $pedigree['branches']['dam'] : [];
$headerTitle = (string) ($pedigree['header_title'] ?? esc_html__('Pedigree', 'gdpe'));
$headerMeta = (string) ($pedigree['header_meta'] ?? '');

$unknownNode = static function (int $generation) : array {
    return [
        'id' => null,
        'title' => __('Unknown', 'gdpe'),
        'permalink' => null,
        'is_known' => false,
        'generation' => $generation,
        'children' => [],
    ];
};

$populateBranch = static function (array $node, int $startRow, int $rowSpan, int $generation) use (&$populateBranch, &$rows, $generationCount, $unknownNode): void {
    if ($generation > $generationCount || !isset($rows[$startRow])) {
        return;
    }

    $rows[$startRow] .= gdpe_render_tree_cell_item($node, $rowSpan, $generation);

    if ($generation >= $generationCount) {
        return;
    }

    $children = isset($node['children']) && is_array($node['children'])
        ? $node['children']
        : [];

    $sire = is_array($children['sire'] ?? null)
        ? $children['sire']
        : $unknownNode($generation + 1);

    $dam = is_array($children['dam'] ?? null)
        ? $children['dam']
        : $unknownNode($generation + 1);

    $half = max(1, intdiv($rowSpan, 2));

    $populateBranch($sire, $startRow, $half, $generation + 1);
    $populateBranch($dam, $startRow + $half, $half, $generation + 1);
};

$populateBranch($sireBranch, 0, $branchRows, 1);
$populateBranch($damBranch, $branchRows, $branchRows, 1);
?>
<div class="gd-pedigree-wrapper" dir="ltr" style="--gdpe-generation-count: <?php echo esc_attr((string) $generationCount); ?>;">
    <div class="gd-ped-header">
        <span class="gd-ped-header-title"><?php echo esc_html($headerTitle); ?></span>
        <?php if ($headerMeta !== '') : ?>
            <span class="gd-ped-header-meta"><?php echo esc_html($headerMeta); ?></span>
        <?php endif; ?>
    </div>
    <div class="gd-ped-table-responsive">
        <table class="gd-ped-table">
            <tbody>
                <?php foreach ($rows as $rowHtml) : ?>
                    <tr><?php echo $rowHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
