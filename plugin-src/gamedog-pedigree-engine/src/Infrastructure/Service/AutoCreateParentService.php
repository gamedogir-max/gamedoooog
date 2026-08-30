<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Service;

use GDPE\Application\Port\ParentAutoCreatorInterface;
use GDPE\Domain\Entity\Dog;
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
 * Duplicate prevention:
 *  - Before any new parent post is inserted, {@see DogRepositoryInterface::findByNameAcrossStatuses()}
 *    is used to search publish, pending and draft dog posts.
 *  - If exactly one publish/pending/draft match exists, it is reused. Pending
 *    or draft matches are automatically promoted to publish with
 *    `wp_update_post()` and linked as the parent instead of creating a
 *    duplicate post.
 *  - If more than one match exists, auto-creation is refused as ambiguous.
 *  - Auto-creation is only triggered when ZERO matching dog posts exist across
 *    all relevant statuses.
 *
 * Orphan authoring:
 *  - Auto-created parents are always created with `post_author => 0`.
 *  - They are NEVER assigned to the submitting user (`get_current_user_id()`)
 *    or to the source child's author. This guarantees auto-generated
 *    Sire/Dam placeholder posts never appear in a user's personal front-end
 *    dashboard dog listing.
 *
 * Recursion safety:
 *  - {@see AutoCreateGuard::$isAutoCreating} is set for the entire
 *    `wp_insert_post` / `wp_update_post` call so `ParentConnectionRegistrar`
 *    bails out of its own lifecycle hooks (no cascade creation / no
 *    re-entrancy).
 *  - Request payload globals (`$_POST`, `$_REQUEST`, and `$_FILES`) are
 *    stashed and cleared during insertion so JetEngine and other meta-box
 *    handlers cannot copy the child's submitted form data into the freshly
 *    created or promoted parent.
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
     * Finds a uniquely-matching parent across publish/pending/draft, or
     * auto-creates a minimal one only when zero matches exist.
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

        // Find-or-create: reuse an already-existing uniquely-matching dog in
        // publish, pending or draft. Auto-creation must be isolated and only
        // happen when zero matches exist across ALL relevant statuses.
        $matches = $this->dogRepository->findByNameAcrossStatuses($sanitized, $excludeId);

        if (count($matches) === 1) {
            $dog = $matches[0];
            $postId = $dog->id()->toInt();

            if (get_post_status($postId) !== 'publish') {
                error_log("GDPE AutoCreateParent: existing parent #{$postId} '{$dog->name()}' is not published - promoting to publish");
                $publishedId = $this->publishExistingParent($dog);

                if ($publishedId === null) {
                    error_log("GDPE AutoCreateParent: failed to promote existing parent #{$postId}, refusing to auto-create a duplicate");
                    return null;
                }

                error_log("GDPE AutoCreateParent: reusing promoted parent #{$publishedId->toInt()} '{$dog->name()}'");
                return $publishedId;
            }

            error_log("GDPE AutoCreateParent: reusing existing parent #{$postId} '{$dog->name()}'");
            return $dog->id();
        }

        if (count($matches) > 1) {
            error_log("GDPE AutoCreateParent: ambiguous parent name '{$sanitized}' across publish/pending/draft, refusing to auto-create");
            return null;
        }

        return $this->createParentPost($sanitized, $sex);
    }

    /**
     * Promotes an existing pending/draft dog to published and returns its ID.
     *
     * The promotion is performed under {@see AutoCreateGuard} and with request
     * payload globals cleared, for the same re-entrancy/copy-protection reasons
     * as a brand-new auto-created parent.
     *
     * @param Dog $dog The existing pending/draft dog to promote.
     *
     * @return DogId|null The promoted DogId, or null on failure.
     */
    private function publishExistingParent(Dog $dog): ?DogId
    {
        if (AutoCreateGuard::isAutoCreating()) {
            // Belt-and-suspenders: never nest auto-creation.
            error_log('GDPE AutoCreateParent: already auto-creating, aborting nested parent promotion');
            return null;
        }

        AutoCreateGuard::begin();

        try {
            return $this->withIsolatedRequestPayload(function () use ($dog): ?DogId {
                $postId = wp_update_post(
                    [
                        'ID'          => $dog->id()->toInt(),
                        'post_status' => 'publish',
                    ],
                    true
                );

                if ($postId instanceof WP_Error) {
                    error_log('GDPE AutoCreateParent: wp_update_post failed - ' . $postId->get_error_message());
                    return null;
                }

                error_log("GDPE AutoCreateParent: promoted existing parent post #{$postId} to publish");

                return new DogId((int) $postId);
            });
        } catch (\Throwable $e) {
            error_log('GDPE AutoCreateParent: exception during parent promotion - ' . $e->getMessage());
            return null;
        } finally {
            AutoCreateGuard::end();
        }
    }

    /**
     * Creates a minimal published parent dog post with the given name and sex.
     *
     * IMPORTANT: the post is created with `post_author => 0` (an orphan post).
     * It is never assigned to `get_current_user_id()` or to the source child's
     * author, so the auto-generated Sire/Dam placeholder does not appear in the
     * submitting user's personal front-end dashboard dog listing.
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

        try {
            return $this->withIsolatedRequestPayload(function () use ($name, $sex): ?DogId {
                $postarr = [
                    'post_type'   => JetEngineFieldMap::CPT_SLUG,
                    'post_status' => 'publish',
                    'post_title'  => $name,
                    'post_name'   => sanitize_title($name),
                    // Orphan post: explicitly unassigned to any user.
                    'post_author' => 0,
                ];

                error_log("GDPE AutoCreateParent: inserting parent post '{$name}' (sex: {$sex}, author: 0)");

                $postId = wp_insert_post($postarr, true);

                if ($postId instanceof WP_Error) {
                    error_log('GDPE AutoCreateParent: wp_insert_post failed - ' . $postId->get_error_message());
                    return null;
                }

                error_log("GDPE AutoCreateParent: created parent post #{$postId} (orphan author 0)");

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
            });
        } catch (\Throwable $e) {
            error_log('GDPE AutoCreateParent: exception during auto-create - ' . $e->getMessage());
            return null;
        } finally {
            AutoCreateGuard::end();
        }
    }

    /**
     * Stashes and clears every request payload source while the callback
     * performs a WordPress write.
     *
     * Some meta-box integrations read `$_REQUEST` or `$_FILES` instead of
     * `$_POST`; clearing only `$_POST` lets the child's Gallery, DOB, Country,
     * Titles, and similar fields leak into the freshly created or promoted
     * parent post.
     *
     * @param callable $callback Work to run with empty request payloads.
     *
     * @return mixed Whatever the callback returns.
     */
    private function withIsolatedRequestPayload(callable $callback): mixed
    {
        $originalPost = $_POST ?? [];
        $originalRequest = $_REQUEST ?? [];
        $originalFiles = $_FILES ?? [];
        $_POST = [];
        $_REQUEST = [];
        $_FILES = [];

        try {
            return $callback();
        } finally {
            $_POST = $originalPost;
            $_REQUEST = $originalRequest;
            $_FILES = $originalFiles;
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
