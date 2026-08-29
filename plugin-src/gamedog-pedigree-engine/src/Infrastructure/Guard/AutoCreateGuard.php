<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Guard;

/**
 * Static recursion lock used during auto-creation of parent dog posts.
 *
 * When {@see \GDPE\Infrastructure\Service\AutoCreateParentService} inserts a
 * brand-new parent post, WordPress fires the very same post lifecycle hooks
 * (`save_post_dogs`, `publish_dogs`, JetEngine relation/meta hooks) that
 * {@see \GDPE\Infrastructure\WordPress\ParentConnectionRegistrar} listens to.
 *
 * Without a guard this would re-enter forward/reverse parent resolution for
 * the freshly created post, potentially:
 *  1. cascading ancestor creation, or
 *  2. copying the child's submitted form data into the new parent.
 *
 * The registrar checks {@see self::isAutoCreating()} at the entry of every
 * handler and bails out immediately, effectively "bypassing" the hooks for
 * the duration of the insert. The flag is toggled around `wp_insert_post` in
 * a try/finally so it is always restored, even on failure.
 */
final class AutoCreateGuard
{
    /**
     * Whether we are currently inside an auto-create operation.
     *
     * @var bool
     */
    private static bool $isAutoCreating = false;

    /**
     * Marks the start of an auto-create operation.
     */
    public static function begin(): void
    {
        self::$isAutoCreating = true;
    }

    /**
     * Marks the end of an auto-create operation (always call in a finally).
     */
    public static function end(): void
    {
        self::$isAutoCreating = false;
    }

    /**
     * Reports whether an auto-create operation is currently in progress.
     */
    public static function isAutoCreating(): bool
    {
        return self::$isAutoCreating;
    }

    private function __construct()
    {
        // Static state holder — never instantiated.
    }
}
