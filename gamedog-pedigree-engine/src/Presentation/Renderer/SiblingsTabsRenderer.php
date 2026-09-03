<?php
/**
 * Renders the siblings tabbed module (full / same sire / same dam).
 *
 * @package GameDog\PedigreeEngine\Presentation\Renderer
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Renderer;

use GameDog\PedigreeEngine\Domain\Entity\Dog;

final class SiblingsTabsRenderer
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data): string
    {
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('gamedog-pedigree');
        }

        $dogId  = isset($data['subject_id']) ? (int) $data['subject_id'] : 0;
        $full   = isset($data['full']) && is_array($data['full']) ? $data['full'] : [];
        $sire   = isset($data['sire']) && is_array($data['sire']) ? $data['sire'] : [];
        $dam    = isset($data['dam']) && is_array($data['dam']) ? $data['dam'] : [];

        $tabs = [
            'full' => ['label' => 'Siblings', 'items' => $full],
            'sire' => ['label' => 'Same sire', 'items' => $sire],
            'dam'  => ['label' => 'Same dam', 'items' => $dam],
        ];

        $html = '<div class="gd-siblings-tabs" data-dog-id="' . esc_attr((string) $dogId) . '">';

        $html .= '<div class="gd-tabs-nav" role="tablist" aria-label="Sibling groups">';

        $first = true;
        foreach ($tabs as $key => $tab) {
            $active = $first ? ' gd-tab-active' : '';
            $first  = false;
            $html  .= '<button type="button" role="tab" class="gd-tab-btn' . $active . '" '
                . 'data-gd-tab="' . esc_attr($key) . '" '
                . 'aria-selected="' . ($active !== '' ? 'true' : 'false') . '">'
                . esc_html($tab['label'])
                . ' <span class="gd-tab-count">' . count($tab['items']) . '</span>'
                . '</button>';
        }

        $html .= '</div>';

        $html .= '<div class="gd-tabs-panels">';

        $first = true;
        foreach ($tabs as $key => $tab) {
            $active = $first ? ' gd-tab-panel-active' : '';
            $first  = false;

            $html .= '<div class="gd-tab-panel' . $active . '" role="tabpanel" data-gd-panel="' . esc_attr($key) . '">';
            $html .= $this->renderList($tab['items']);
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int, Dog> $items
     */
    private function renderList(array $items): string
    {
        if ($items === []) {
            return '<p class="gd-siblings-empty">No siblings found.</p>';
        }

        $html = '<ul class="gd-siblings-list">';

        foreach ($items as $dog) {
            if (!$dog instanceof Dog) {
                continue;
            }

            $name = $dog->registeredName();
            $url  = $dog->permalink() !== '' && $dog->permalink() !== '#'
                ? $dog->permalink()
                : '';

            $html .= '<li class="gd-sibling-item">';
            if ($url !== '') {
                $html .= '<a class="gd-sibling-name" href="' . esc_url($url) . '">' . esc_html($name) . '</a>';
            } else {
                $html .= '<span class="gd-sibling-name">' . esc_html($name) . '</span>';
            }
            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
