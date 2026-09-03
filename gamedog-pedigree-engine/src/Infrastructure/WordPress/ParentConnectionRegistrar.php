<?php
/**
 * Hooks that react to parent relation changes and refresh stored COI.
 *
 * @package GameDog\PedigreeEngine\Infrastructure\WordPress
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Infrastructure\WordPress;

use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeTreeUseCase;
use GameDog\PedigreeEngine\Application\UseCase\CalculateDogCoiUseCase;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

final class ParentConnectionRegistrar
{
    /** @var CalculateDogCoiUseCase */
    private $coiUseCase;

    /** @var BuildPedigreeTreeUseCase|null */
    private $treeUseCase;

    public function __construct(
        CalculateDogCoiUseCase $coiUseCase,
        ?BuildPedigreeTreeUseCase $treeUseCase = null
    ) {
        $this->coiUseCase  = $coiUseCase;
        $this->treeUseCase = $treeUseCase;
    }

    /**
     * Register WordPress / JetEngine hooks.
     */
    public function register(): void
    {
        // After a dog post is saved, recalculate its COI.
        add_action('save_post_' . GD_PEDIGREE_POST_TYPE, [$this, 'onDogSaved'], 20, 3);
        add_action('save_post_dog', [$this, 'onDogSaved'], 20, 3);

        // JetEngine relation update hooks (best-effort; names vary by version).
        add_action('jet-engine/relations/update-relation-items', [$this, 'onRelationUpdated'], 10, 4);
        add_action('jet-engine/relations/after-set-new-relation', [$this, 'onRelationUpdated'], 10, 4);

        // Generic meta updates for parent meta keys.
        add_action('updated_post_meta', [$this, 'onMetaUpdated'], 10, 4);
        add_action('added_post_meta', [$this, 'onMetaUpdated'], 10, 4);
    }

    /**
     * @param int      $postId
     * @param \WP_Post $post
     * @param bool     $update
     */
    public function onDogSaved($postId, $post = null, $update = true): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $postId = (int) $postId;
        if ($postId <= 0) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision($postId)) {
            return;
        }

        $this->recalculate($postId);
    }

    /**
     * @param mixed ...$args Variable JetEngine callback signature.
     */
    public function onRelationUpdated(...$args): void
    {
        $candidateIds = [];

        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                $candidateIds[] = (int) $arg;
            } elseif (is_array($arg)) {
                foreach ($arg as $v) {
                    if (is_numeric($v)) {
                        $candidateIds[] = (int) $v;
                    }
                }
            }
        }

        $candidateIds = array_unique(array_filter($candidateIds, static function ($id) {
            return $id > 0;
        }));

        foreach ($candidateIds as $id) {
            if ($this->looksLikeDog($id)) {
                $this->recalculate($id);
            }
        }
    }

    /**
     * @param int    $metaId
     * @param int    $postId
     * @param string $metaKey
     * @param mixed  $metaValue
     */
    public function onMetaUpdated($metaId, $postId, $metaKey, $metaValue): void
    {
        $parentKeys = [
            '_dog_sire_id',
            '_dog_dam_id',
            'dog_sire',
            'dog_dam',
            'sire_id',
            'dam_id',
            'jet_rel_' . GD_PEDIGREE_SIRE_RELATION_ID,
            'jet_rel_' . GD_PEDIGREE_DAM_RELATION_ID,
            '_jet_rel_' . GD_PEDIGREE_SIRE_RELATION_ID,
            '_jet_rel_' . GD_PEDIGREE_DAM_RELATION_ID,
        ];

        if (!in_array((string) $metaKey, $parentKeys, true)) {
            // Also match dynamic jet_rel_* keys.
            if (strpos((string) $metaKey, 'jet_rel_') === false) {
                return;
            }
        }

        $postId = (int) $postId;
        if ($postId > 0 && $this->looksLikeDog($postId)) {
            $this->recalculate($postId);
        }
    }

    private function recalculate(int $postId): void
    {
        $id = DogId::fromMixed($postId);
        if ($id === null) {
            return;
        }

        try {
            $this->coiUseCase->invalidate($id);
            if ($this->treeUseCase !== null) {
                $this->treeUseCase->invalidate($id);
            }

            // Force fresh calculation and persistence to _dog_coi.
            $this->coiUseCase->execute($id, GD_PEDIGREE_COI_DEPTH, true);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('[GameDog Pedigree] COI recalculation failed for #' . $postId . ': ' . $e->getMessage());
            }
        }
    }

    private function looksLikeDog(int $postId): bool
    {
        if (!function_exists('get_post_type')) {
            return true;
        }

        $type = get_post_type($postId);

        return in_array($type, [GD_PEDIGREE_POST_TYPE, 'dog', 'dogs'], true);
    }
}
