<?php
/**
 * Lightweight settings holder for relation IDs and COI options.
 *
 * @package GameDog\PedigreeEngine\Infrastructure\Settings
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Infrastructure\Settings;

final class PluginSettings
{
    public function postType(): string
    {
        $value = $this->get('post_type', GD_PEDIGREE_POST_TYPE);

        return is_string($value) && $value !== '' ? $value : GD_PEDIGREE_POST_TYPE;
    }

    public function sireRelationId(): int
    {
        return (int) $this->get('sire_relation_id', GD_PEDIGREE_SIRE_RELATION_ID);
    }

    public function damRelationId(): int
    {
        return (int) $this->get('dam_relation_id', GD_PEDIGREE_DAM_RELATION_ID);
    }

    public function coiMetaKey(): string
    {
        $value = $this->get('coi_meta_key', GD_PEDIGREE_COI_META_KEY);

        return is_string($value) && $value !== '' ? $value : GD_PEDIGREE_COI_META_KEY;
    }

    public function coiDepth(): int
    {
        $depth = (int) $this->get('coi_depth', GD_PEDIGREE_COI_DEPTH);

        return $depth > 0 ? $depth : GD_PEDIGREE_COI_DEPTH;
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    private function get(string $key, $default = null)
    {
        if (!function_exists('get_option')) {
            return $default;
        }

        $all = get_option('gd_pedigree_settings', []);
        if (!is_array($all) || !array_key_exists($key, $all)) {
            return $default;
        }

        return $all[$key];
    }
}
