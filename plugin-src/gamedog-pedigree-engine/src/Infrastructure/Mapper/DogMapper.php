<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Mapper;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Infrastructure\Config\JetEngineFieldMap;
use WP_Post;

final class DogMapper
{
    /**
     * Maps a WP_Post plus its meta (as returned by get_post_meta($id))
     * into a Domain Dog entity.
     *
     * @param array<string, mixed> $meta Post meta map: key => array of values.
     */
    public function toDomain(WP_Post $post, array $meta): Dog
    {
        $father = $this->firstMetaValue($meta, JetEngineFieldMap::META_SIRE_ID);
        $mother = $this->firstMetaValue($meta, JetEngineFieldMap::META_DAM_ID);

        return new Dog(
            new DogId((int) $post->ID),
            (string) $post->post_title,
            get_permalink($post),
            $father ? new DogId((int) $father) : null,
            $mother ? new DogId((int) $mother) : null,
        );
    }

    /**
     * Returns the first stored value for a meta key, or null when absent.
     *
     * @param array<string, mixed> $meta Post meta map: key => array of values.
     */
    private function firstMetaValue(array $meta, string $key): mixed
    {
        if (!isset($meta[$key])) {
            return null;
        }

        $value = $meta[$key];

        return is_array($value)
            ? ($value[0] ?? null)
            : $value;
    }
}