<?php

declare(strict_types=1);

namespace GDPE\Application\Port;

use GDPE\Domain\ValueObject\DogId;

/**
 * Application port for auto-creating missing parent dog posts.
 *
 * When a child dog is published with a plain-text Sire/Dam name whose post
 * does not exist yet, the Infrastructure implementation is responsible for
 * the write-side side-effect of creating a minimal published dog post.
 *
 * DDD boundary:
 *  - The Application layer (use cases) depends on this port, never on a
 *    concrete WordPress/JetEngine implementation.
 *  - The Domain is never mutated to support the creation.
 *  - The Infrastructure layer owns the actual `wp_insert_post` side-effect.
 *
 * Implementations should be recursion-safe (see AutoCreateParentService).
 */
interface ParentAutoCreatorInterface
{
    /**
     * Ensures a published male dog matching the given name exists; creates a
     * minimal published `dog` post when none does.
     *
     * @param string      $name      The plain-text Sire name (sanitized by the implementation).
     * @param DogId|null  $excludeId Optional dog ID to exclude (prevents resolving to the child itself).
     *
     * @return DogId|null The parent's DogId, or null when the name is invalid,
     *                    ambiguous, the recursive guard is active, or creation failed.
     */
    public function createSire(string $name, ?DogId $excludeId = null): ?DogId;

    /**
     * Ensures a published female dog matching the given name exists; creates a
     * minimal published `dog` post when none does.
     *
     * @param string      $name      The plain-text Dam name (sanitized by the implementation).
     * @param DogId|null  $excludeId Optional dog ID to exclude (prevents resolving to the child itself).
     *
     * @return DogId|null The parent's DogId, or null on failure.
     */
    public function createDam(string $name, ?DogId $excludeId = null): ?DogId;
}
