<?php
/**
 * Builds presentation ViewModels from PedigreeTreeDto / raw arrays.
 *
 * Ensures the COI metric is always present for templates.
 *
 * @package GameDog\PedigreeEngine\Presentation\ViewModel
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\ViewModel;

use GameDog\PedigreeEngine\Application\DTO\PedigreeTreeDto;

final class PedigreeTreeViewModelBuilder
{
    /**
     * @param PedigreeTreeDto|array<string, mixed> $source
     *
     * @return array<string, mixed>
     */
    public function build($source): array
    {
        if ($source instanceof PedigreeTreeDto) {
            $data = $source->toArray();
        } elseif (is_array($source)) {
            $data = $source;
        } else {
            $data = [];
        }

        $coi = isset($data['coi']) ? (string) $data['coi'] : '0.00%';
        if ($coi === '') {
            $coi = '0.00%';
        }

        // Normalise common ancestor list to int map for O(1) lookups in templates.
        $commonList = [];
        if (isset($data['common_ancestor_ids']) && is_array($data['common_ancestor_ids'])) {
            foreach ($data['common_ancestor_ids'] as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    $commonList[$cid] = $cid;
                }
            }
        }

        $root = isset($data['root']) && is_array($data['root']) ? $data['root'] : null;
        $root = $this->decorateNode($root, $commonList);

        return [
            'subject_id'           => isset($data['subject_id']) ? (int) $data['subject_id'] : 0,
            'subject_name'         => isset($data['subject_name']) ? (string) $data['subject_name'] : '',
            'coi'                  => $coi,
            'coi_value'            => $coi,
            'coi_percent'          => isset($data['coi_percent']) ? (float) $data['coi_percent'] : 0.0,
            'coi_ratio'            => isset($data['coi_ratio']) ? (float) $data['coi_ratio'] : 0.0,
            'depth'                => isset($data['depth']) ? (int) $data['depth'] : GD_PEDIGREE_COI_DEPTH,
            'common_ancestor_ids'  => $commonList,
            'root'                 => $root,
            'has_tree'             => $root !== null && empty($root['is_empty']),
        ];
    }

    /**
     * @param array<string, mixed>|null $node
     * @param array<int, int>           $commonList
     *
     * @return array<string, mixed>|null
     */
    private function decorateNode(?array $node, array $commonList): ?array
    {
        if ($node === null) {
            return null;
        }

        $id = isset($node['id']) ? (int) $node['id'] : 0;
        $isCommon = !empty($node['is_common_ancestor']) || ($id > 0 && isset($commonList[$id]));

        $node['is_common_ancestor'] = $isCommon;
        $node['css_class']          = $this->buildNodeCssClass($node, $isCommon);

        if (isset($node['sire']) && is_array($node['sire'])) {
            $node['sire'] = $this->decorateNode($node['sire'], $commonList);
        }
        if (isset($node['dam']) && is_array($node['dam'])) {
            $node['dam'] = $this->decorateNode($node['dam'], $commonList);
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function buildNodeCssClass(array $node, bool $isCommon): string
    {
        $classes = ['gd-pedigree-node'];

        if (!empty($node['is_empty'])) {
            $classes[] = 'gd-node-empty';
        }

        if (isset($node['side']) && is_string($node['side'])) {
            $classes[] = 'gd-side-' . preg_replace('/[^a-z0-9_-]/i', '', $node['side']);
        }

        if (isset($node['generation'])) {
            $classes[] = 'gd-gen-' . (int) $node['generation'];
        }

        if (isset($node['gender']) && $node['gender'] !== '') {
            $gender = strtolower((string) $node['gender']);
            if (in_array($gender, ['male', 'm', 'sire', 'dog'], true)) {
                $classes[] = 'gd-gender-male';
            } elseif (in_array($gender, ['female', 'f', 'dam', 'bitch'], true)) {
                $classes[] = 'gd-gender-female';
            }
        }

        if ($isCommon) {
            $classes[] = 'gd-node-inbred';
            $classes[] = 'gd-node-common-ancestor';
        }

        return implode(' ', $classes);
    }
}
