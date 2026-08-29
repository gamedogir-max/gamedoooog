<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Service;

use GDPE\Application\Port\ParentAutoCreatorInterface;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Infrastructure\Config\JetEngineFieldMap;
use GDPE\Infrastructure\Guard\AutoCreateGuard;
use WP_Error;

/**
 * Infrastructure implementation of {@see ParentAutoCreatorInterface}.
 *
 * Responsible for the write-side side-effect of auto-creating a minimal
 * published `dog` post when a plain-text Sire/Dam name cannot be resolved to
 * an existing dog.
 *
 * DDD boundary:
 *  - The side-effect is fully encapsulated in the Infrastructure layer.
 *  - No Domain entity is mutated; only a {@see DogId} (or null) crosses back
 *    to the Application layer.
 *  - Names are sanitized before any DB write and `is_wp_error()` is handled.
 *
 * Recursion safety:
 *  - {@see AutoCreateGuard::$isAutoCreating} is set for the entire
 *    `wp_insert_post` call so `ParentConnectionRegistrar` bails out of its own
 *    lifecycle hooks (no cascade creation / no re-entrancy).
 *  - `$_POST` is stashed and cleared during insertion so JetEngine and any
 *    other meta-box handler cannot copy the child's submitted form data into
 *    the freshly created parent.
 *
 * Parent metadata initialisation:
 *  - Auto-created Sire → `FIELD_SEX` = 'male'.
 *  - Auto-created Dam  → `FIELD_SEX` = 'female'.
 *  - `dog_name` is always set so the parent is discoverable via the existing
 *    name-matching repository queries.
 */
final class AutoCreateParentService implements ParentAutoCreatorInterface
{
    /** @var int Minimum acceptable length for a parent name. */
    private const MIN_NAME_LENGTH = 2;

    /** @var int Maximum acceptable length for a parent name. */
    private const MAX_NAME_LENGTH = 200;

    public function __construct(private readonly DogRepositoryInterface $dogRepository)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function createSire(string $name, ?DogId $excludeId = null): ?DogId
    {
        return $this->findOrCreateParent($name, JetEngineFieldMap::SEX_MALE, $excludeId);
    }

    /**
     * {@inheritDoc}
     */
    public function createDam(string $name, ?DogId $excludeId = null): ?DogId
    {
        return $this->findOrCreateParent($name, JetEngineFieldMap::SEX_FEMALE, $excludeId);
    }

    /**
     * Finds a uniquely-matching published parent, or auto-creates a minimal
     * one when none exists.
     *
     * @param string     $name      Raw plain-text parent name.
     * @param string     $sex       JetEngineFieldMap::SEX_MALE or SEX_FEMALE.
     * @param DogId|null $excludeId Dog ID to exclude (prevents self-parent).
     *
     * @return DogId|null Resolved or created parent ID, or null on failure.
     */
    private function findOrCreateParent(string $name, string $sex, ?DogId $excludeId = null): ?DogId
    {
        $sanitized = $this->sanitizeName($name);

        if ($sanitized === '') {
            error_log('GDPE AutoCreateParent: invalid parent name supplied, refusing to auto-create');
            return null;
        }

        // Find-or-create: reuse an already-existing uniquely-matching dog.
        $matches = $this->dogRepository->findPublishedByName($sanitized, $excludeId);

        if (count($matches) === 1) {
            $dog = $matches[0];
            error_log("GDPE AutoCreateParent: reusing existing parent #{$dog->id()} '{$dog->name()}'");
            return $dog->id();
        }

        if (count($matches) > 1) {
            error_log("GDPE AutoCreateParent: ambiguous parent name '{$sanitized}', refusing to auto-create");
            return null;
        }

        return $this->createParentPost($sanitized, $sex);
    }

    /**
     * Creates a minimal published parent dog post with the given name and sex.
     *
     * @param string $name Sanitized parent name.
     * @param string $sex  Sex meta value ('male' / 'female').
     *
     * @return DogId|null The newly created parent's DogId, or null on failure.
     */
    private function createParentPost(string $name, string $sex): ?DogId
    {
        if (AutoCreateGuard::isAutoCreating()) {
            // Belt-and-suspenders: never nest auto-creation.
            error_log('GDPE AutoCreateParent: already auto-creating, aborting nested create');
            return null;
        }

        AutoCreateGuard::begin();

        // Stash and clear the request globals so JetEngine and other
        // meta-box handlers cannot write the child's submitted fields into
        // this brand-new parent post (no data contamination).
        $originalPost = $_POST ?? [];
        $_POST = [];

        try {
            $postarr = [
                'post_type'   => JetEngineFieldMap::CPT_SLUG,
                'post_status' => 'publish',
                'post_title'  => $name,
                'post_name'   => sanitize_title($name),
            ];

            error_log("GDPE AutoCreateParent: inserting parent post '{$name}' (sex: {$sex})");

            $postId = wp_insert_post($postarr, true);

            if ($postId instanceof WP_Error) {
                error_log('GDPE AutoCreateParent: wp_insert_post failed - ' . $postId->get_error_message());
                return null;
            }

            error_log("GDPE AutoCreateParent: created parent post #{$postId}");

            // Minimal metadata so the parent is discoverable and correctly
            // gendered. No father/mother/sire/dam fields are copied over.
            $nameOk = update_post_meta($postId, JetEngineFieldMap::META_DOG_NAME, $name);
            $sexOk  = update_post_meta($postId, JetEngineFieldMap::FIELD_SEX, $sex);
            $marker = update_post_meta($postId, JetEngineFieldMap::META_AUTO_CREATED, '1');

            if ($nameOk === false) {
                error_log("GDPE AutoCreateParent: failed to set dog_name on #{$postId}");
            }

            if ($sexOk === false) {
                error_log("GDPE AutoCreateParent: failed to set sex on #{$postId}");
            }

            if ($marker === false) {
                error_log("GDPE AutoCreateParent: failed to set auto-created marker on #{$postId}");
            }

            return new DogId((int) $postId);
        } catch (\Throwable $e) {
            error_log('GDPE AutoCreateParent: exception during auto-create - ' . $e->getMessage());
            return null;
        } finally {
            $_POST = $originalPost;
            AutoCreateGuard::end();
        }
    }

    /**
     * Sanitizes a parent name for storage.
     *
     * @param mixed $name Raw meta value.
     *
     * @return string Sanitized name, or empty string if invalid.
     */
    private function sanitizeName(mixed $name): string
    {
        if (!is_string($name) || trim($name) === '') {
            return '';
        }

        $sanitized = sanitize_text_field(trim($name));

        if (strlen($sanitized) < self::MIN_NAME_LENGTH || strlen($sanitized) > self::MAX_NAME_LENGTH) {
            return '';
        }

        return $sanitized;
    }
}
