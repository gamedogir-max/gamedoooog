<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\WordPress;

use GDPE\Application\UseCase\ClearPedigreeCacheUseCase;
use GDPE\Infrastructure\Config\JetEngineFieldMap;

/**
 * Wires WordPress post-lifecycle hooks to pedigree cache invalidation.
 *
 * Any change to a dog post (create, update, trash, untrash, delete)
 * can alter pedigree trees and relation lists far beyond the edited
 * post itself, so the whole pedigree cache is cleared rather than
 * attempting per-dog invalidation.
 */
final class CacheInvalidationRegistrar
{
    public function __construct(
        private readonly ClearPedigreeCacheUseCase $clearPedigreeCacheUseCase,
    ) {
    }

    public function register(): void
    {
        add_action(
            'save_post_' . JetEngineFieldMap::CPT_SLUG,
            [$this, 'onDogSaved'],
            10,
            1
        );

        add_action('delete_post', [$this, 'onDogPostLifecycleChange'], 10, 1);
        add_action('trashed_post', [$this, 'onDogPostLifecycleChange'], 10, 1);
        add_action('untrashed_post', [$this, 'onDogPostLifecycleChange'], 10, 1);
    }

    /**
     * Handles "save_post_{cpt}", which already fires only for the dog
     * CPT — only autosaves and revisions need to be filtered out.
     */
    public function onDogSaved(int $postId): void
    {
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }

        $this->clearPedigreeCacheUseCase->execute();
    }

    /**
     * Handles the generic delete/trash/untrash hooks, which fire for
     * every post type and therefore need an explicit CPT check.
     */
    public function onDogPostLifecycleChange(int $postId): void
    {
        if (get_post_type($postId) !== JetEngineFieldMap::CPT_SLUG) {
            return;
        }

        $this->clearPedigreeCacheUseCase->execute();
    }
}
