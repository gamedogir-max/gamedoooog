<?php
/**
 * Dog repository backed by the WordPress `dogs` CPT and JetEngine meta/relations.
 *
 * Stores calculated COI under the `_dog_coi` post meta key.
 *
 * @package GameDog\PedigreeEngine\Infrastructure\Repository
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Infrastructure\Repository;

use GameDog\PedigreeEngine\Domain\Contract\RelationTraversalInterface;
use GameDog\PedigreeEngine\Domain\Entity\Dog;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

final class JetEngineDogRepository implements DogRepositoryInterface
{
    /** @var RelationTraversalInterface|null */
    private $relations;

    /** @var string */
    private $postType;

    /** @var string */
    private $coiMetaKey;

    /** @var array<int, Dog|null> */
    private $memory = [];

    public function __construct(
        ?RelationTraversalInterface $relations = null,
        string $postType = GD_PEDIGREE_POST_TYPE,
        string $coiMetaKey = GD_PEDIGREE_COI_META_KEY
    ) {
        $this->relations  = $relations;
        $this->postType   = $postType;
        $this->coiMetaKey = $coiMetaKey;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(DogId $id): ?Dog
    {
        $intId = $id->toInt();

        if (array_key_exists($intId, $this->memory)) {
            return $this->memory[$intId];
        }

        if (!function_exists('get_post')) {
            $this->memory[$intId] = null;

            return null;
        }

        $post = get_post($intId);
        if (!$post || $post->post_status === 'trash') {
            $this->memory[$intId] = null;

            return null;
        }

        // Allow any post type if the configured CPT is missing, but prefer dogs.
        if ($this->postType !== '' && $post->post_type !== $this->postType && $post->post_type !== 'dog') {
            // Still accept if meta suggests it is a dog pedigree subject.
            $force = get_post_meta($intId, '_is_dog', true);
            if (!$force) {
                // Soft accept: many installs use custom slugs; only reject attachments/revisions.
                if (in_array($post->post_type, ['attachment', 'revision', 'nav_menu_item'], true)) {
                    $this->memory[$intId] = null;

                    return null;
                }
            }
        }

        $sireId = null;
        $damId  = null;

        if ($this->relations !== null) {
            $parents = $this->relations->getParentIds($id);
            $sireId  = $parents['sire'] ?? null;
            $damId   = $parents['dam'] ?? null;
        }

        $coi        = $this->getStoredCoi($id);
        $thumbnail  = $this->resolveThumbnail($intId);
        $permalink  = function_exists('get_permalink') ? (string) get_permalink($intId) : '';
        $gender     = $this->resolveGender($intId);

        $dog = new Dog(
            $id,
            (string) $post->post_title,
            $sireId,
            $damId,
            $coi,
            $gender,
            $thumbnail,
            $permalink
        );

        $this->memory[$intId] = $dog;

        return $dog;
    }

    /**
     * {@inheritdoc}
     */
    public function findByIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $raw) {
            $id = DogId::fromMixed($raw);
            if ($id === null) {
                continue;
            }
            $dog = $this->findById($id);
            if ($dog !== null) {
                $result[$id->toInt()] = $dog;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function saveCoi(DogId $id, CoiPercentage $coi, int $depth = 4): void
    {
        if (!function_exists('update_post_meta')) {
            return;
        }

        $formatted = $coi->formatted();
        update_post_meta($id->toInt(), $this->coiMetaKey, $formatted);

        // Also store raw float for sorting / queries.
        update_post_meta($id->toInt(), $this->coiMetaKey . '_raw', $coi->percent());

        // Track the depth the value was computed at.
        update_post_meta($id->toInt(), $this->coiMetaKey . '_depth', max(1, $depth));

        // Bust memory cache so subsequent reads see the new value.
        unset($this->memory[$id->toInt()]);

        if (function_exists('clean_post_cache')) {
            clean_post_cache($id->toInt());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStoredCoi(DogId $id): ?CoiPercentage
    {
        if (!function_exists('get_post_meta')) {
            return null;
        }

        $raw = get_post_meta($id->toInt(), $this->coiMetaKey, true);
        if ($raw === '' || $raw === null || $raw === false) {
            return null;
        }

        return CoiPercentage::fromStored($raw);
    }

    /**
     * {@inheritdoc}
     */
    public function getStoredCoiDepth(DogId $id): int
    {
        if (!function_exists('get_post_meta')) {
            return 0;
        }

        $raw = get_post_meta($id->toInt(), $this->coiMetaKey . '_depth', true);
        if ($raw === '' || $raw === null || $raw === false) {
            return 0;
        }

        $depth = (int) $raw;

        return $depth > 0 ? $depth : 0;
    }

    /**
     * Clear in-request identity map.
     */
    public function clearMemoryCache(): void
    {
        $this->memory = [];
    }

    private function resolveThumbnail(int $postId): string
    {
        if (!function_exists('get_the_post_thumbnail_url')) {
            return '';
        }

        $url = get_the_post_thumbnail_url($postId, 'thumbnail');

        return is_string($url) ? $url : '';
    }

    private function resolveGender(int $postId): string
    {
        if (!function_exists('get_post_meta')) {
            return '';
        }

        foreach (['gender', 'dog_gender', '_dog_gender', 'sex'] as $key) {
            $val = get_post_meta($postId, $key, true);
            if (is_string($val) && $val !== '') {
                return strtolower($val);
            }
        }

        return '';
    }
}
