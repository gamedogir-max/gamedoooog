<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Repository;

use GDPE\Domain\Entity\Dog;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Infrastructure\Config\JetEngineFieldMap;
use GDPE\Infrastructure\Mapper\DogMapper;
use WP_Post;
use WP_Query;

/**
 * Infrastructure adapter implementing DogRepositoryInterface on top
 * of WordPress's WP_Query against the JetEngine-managed "dogs"
 * Custom Post Type.
 *
 * JetEngine itself is treated purely as the tool that generated and
 * manages this CPT/meta box schema; no JetEngine runtime API is
 * required here, only standard WordPress core functions, keeping the
 * adapter portable even if the CPT is later re-registered by hand.
 *
 * Name matching uses the `dog_name` meta field as the PRIMARY source
 * of truth, with fallback to `post_title` for backward compatibility.
 */
final class JetEngineDogRepository implements DogRepositoryInterface
{
    public function __construct(private readonly DogMapper $mapper)
    {
    }

    public function findById(DogId $id): ?Dog
    {
        $post = get_post($id->toInt());

        if (!$post instanceof WP_Post || $post->post_type !== JetEngineFieldMap::CPT_SLUG) {
            return null;
        }

        return $this->mapper->toDomain($post, get_post_meta($post->ID));
    }

    public function findBySireId(DogId $sireId): array
    {
        return $this->findByMetaField(JetEngineFieldMap::META_SIRE_ID, $sireId);
    }

    public function findByDamId(DogId $damId): array
    {
        return $this->findByMetaField(JetEngineFieldMap::META_DAM_ID, $damId);
    }

    /**
     * Finds all published dogs whose dog_name meta field matches the given name.
     *
     * Uses `dog_name` meta field as PRIMARY source of truth (as specified by
     * the existing JetEngine form structure). Falls back to `post_title` if
     * no dogs match via meta field.
     *
     * Matching is case-insensitive and trims whitespace to avoid mismatches.
     *
     * @param string     $name      The exact dog name to search for.
     * @param DogId|null $excludeId Optional dog ID to exclude (prevents self-parent).
     *
     * @return array<int, Dog> All matching published dogs (may be empty or multiple).
     */
    public function findPublishedByName(string $name, ?DogId $excludeId = null): array
    {
        error_log("GDPE AutoConnect: findPublishedByName - Searching for '{$name}'" . 
                   ($excludeId ? ", excluding ID: {$excludeId->toInt()}" : ''));

        // Normalize the search name
        $normalizedName = $this->normalizeName($name);
        
        // Primary search: Use dog_name meta field
        $metaMatches = $this->findByDogNameMeta($normalizedName, $excludeId);
        
        error_log("GDPE AutoConnect: Found " . count($metaMatches) . " matches via dog_name meta");
        
        // If we found matches via meta, use those (primary source)
        if (!empty($metaMatches)) {
            return $metaMatches;
        }

        // Fallback: Search by post_title (backward compatibility)
        error_log("GDPE AutoConnect: No matches via dog_name, trying post_title fallback");
        $titleMatches = $this->findByPostTitle($normalizedName, $excludeId);
        
        error_log("GDPE AutoConnect: Found " . count($titleMatches) . " matches via post_title");
        
        return $titleMatches;
    }

    /**
     * Searches for dogs using the dog_name meta field (PRIMARY method).
     *
     * @param string     $normalizedName The normalized name to search for.
     * @param DogId|null $excludeId      Optional ID to exclude.
     *
     * @return array<int, Dog> Matching dogs.
     */
    private function findByDogNameMeta(string $normalizedName, ?DogId $excludeId = null): array
    {
        // MINOR FIX #1 (Whitespace Normalization Mismatch):
        // Changed from exact '=' match to 'EXISTS' check on dog_name meta field.
        // The SQL-level query now only filters to "published dogs that have a dog_name set",
        // and the existing PHP-side normalizeName() comparison does the authoritative
        // matching. This avoids missing dogs with irregular internal whitespace
        // (e.g. "Test  Father") that would not match the exact SQL '=' comparison.
        
        // MINOR FIX #2 (posts_per_page limit):
        // Raised from 10 to 50 for better ambiguity detection coverage.
        // If more than 10 dogs share the same name, the ambiguity log would be
        // incomplete, understating the true number of duplicates.
        $queryArgs = [
            'post_type' => JetEngineFieldMap::CPT_SLUG,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => 'dog_name',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        // Exclude a specific dog ID (prevents self-parent)
        if ($excludeId !== null) {
            $queryArgs['post__not_in'] = [$excludeId->toInt()];
        }

        $query = new WP_Query($queryArgs);

        $dogs = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            // Get the actual stored dog_name value for validation
            $storedName = get_post_meta($post->ID, 'dog_name', true);
            $storedNormalized = $this->normalizeName($storedName);

            // PHP-side authoritative normalization check (handles whitespace correctly)
            if ($storedNormalized === $normalizedName) {
                $dogs[] = $this->mapper->toDomain($post, get_post_meta($post->ID));
                error_log("GDPE AutoConnect: Meta match found - Post #{$post->ID}: '{$storedName}'");
            }
        }

        return $dogs;
    }

    /**
     * Searches for dogs using post_title (FALLBACK method).
     *
     * @param string     $normalizedName The normalized name to search for.
     * @param DogId|null $excludeId      Optional ID to exclude.
     *
     * @return array<int, Dog> Matching dogs.
     */
    private function findByPostTitle(string $normalizedName, ?DogId $excludeId = null): array
    {
        // MINOR FIX #2 (posts_per_page limit):
        // Raised from 10 to 50 for better ambiguity detection coverage.
        // Consistent with findByDogNameMeta() and findChildrenWaitingForParent().
        $queryArgs = [
            'post_type' => JetEngineFieldMap::CPT_SLUG,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'no_found_rows' => true,
        ];

        // Exclude a specific dog ID (prevents self-parent)
        if ($excludeId !== null) {
            $queryArgs['post__not_in'] = [$excludeId->toInt()];
        }

        $query = new WP_Query($queryArgs);

        $dogs = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            // Normalize and compare post title
            $titleNormalized = $this->normalizeName($post->post_title);

            // Case-insensitive exact match
            if ($titleNormalized === $normalizedName) {
                $dogs[] = $this->mapper->toDomain($post, get_post_meta($post->ID));
                error_log("GDPE AutoConnect: Title match found - Post #{$post->ID}: '{$post->post_title}'");
            }
        }

        return $dogs;
    }

    /**
     * Finds all published dogs that are waiting for a parent with the given name.
     *
     * This is used for REVERSE/DEFERRED parent resolution:
     * When a new parent (e.g., "Rex") is published, we search for existing children
     * whose father_name or mother_name matches "Rex" but whose gdpe_sire_id/gdpe_dam_id
     * is still empty (meaning they haven't been connected yet).
     *
     * @param string   $parentName  The normalized parent name to search for.
     * @param string   $parentType  Either 'father' or 'mother'.
     * @param int|null $excludeId   Optional dog ID to exclude (prevents self-reference).
     *
     * @return array<int, Dog> All published dogs waiting for this parent.
     */
    public function findChildrenWaitingForParent(string $parentName, string $parentType, ?int $excludeId = null): array
    {
        error_log("GDPE ReverseConnect: findChildrenWaitingForParent - Searching for children waiting for '{$parentName}' as {$parentType}" . 
                   ($excludeId ? ", excluding ID: {$excludeId}" : ''));

        // Normalize the search name
        $normalizedName = $this->normalizeName($parentName);

        // Determine which meta field to search based on parent type
        $nameMetaField = ($parentType === 'father') 
            ? JetEngineFieldMap::META_FATHER_NAME 
            : JetEngineFieldMap::META_MOTHER_NAME;

        $idMetaField = ($parentType === 'father')
            ? JetEngineFieldMap::META_SIRE_ID
            : JetEngineFieldMap::META_DAM_ID;

        // Build query args - find published dogs with matching father_name/mother_name
        // AND empty gdpe_sire_id/gdpe_dam_id (meaning not yet connected)
        //
        // CRITICAL FIX (Bug #1 - Reverse Resolution):
        // Added 'NOT EXISTS' as an alternative match alongside the empty string '=' check.
        // This makes the reverse lookup robust both:
        //   1. GOING FORWARD: Meta row always exists with empty string (after this fix)
        //   2. HISTORICAL DATA: Pre-existing Dog posts where the meta key may still be absent
        //          (created before Bug #1 fix was deployed)
        // Without NOT EXISTS, posts with no meta row at all can never satisfy the query.
        $queryArgs = [
            'post_type' => JetEngineFieldMap::CPT_SLUG,
            'post_status' => 'publish',
            'posts_per_page' => 50, // Reasonable limit to prevent performance issues
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => $nameMetaField,
                    'value' => $normalizedName,
                    'compare' => '=',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => $idMetaField,
                        'value' => '',
                        'compare' => '=',
                    ],
                    [
                        'key' => $idMetaField,
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ],
        ];

        // Exclude specific ID if provided (prevents self-reference)
        if ($excludeId !== null) {
            $queryArgs['post__not_in'] = [$excludeId];
        }

        $query = new WP_Query($queryArgs);

        $children = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            // Get the actual stored value and validate with normalization
            $storedName = get_post_meta($post->ID, $nameMetaField, true);
            $storedNormalized = $this->normalizeName((string) $storedName);

            // Case-insensitive exact match validation
            if ($storedNormalized === $normalizedName) {
                // Double-check that the ID field is actually empty
                $currentParentId = get_post_meta($post->ID, $idMetaField, true);
                
                if (empty($currentParentId)) {
                    $children[] = $this->mapper->toDomain($post, get_post_meta($post->ID));
                    error_log("GDPE ReverseConnect: Found waiting child - Post #{$post->ID}: '{$storedName}' (waiting for {$parentType})");
                }
            }
        }

        error_log("GDPE ReverseConnect: Found " . count($children) . " children waiting for '{$normalizedName}' as {$parentType}");

        return $children;
    }

    /**
     * Normalizes a dog name for safe comparison.
     *
     * - Trims leading/trailing whitespace
     * - Converts to consistent case (lowercase for comparison)
     * - Collapses multiple spaces
     *
     * @param string $name The raw name to normalize.
     *
     * @return string The normalized name.
     */
    private function normalizeName(string $name): string
    {
        // Trim whitespace
        $normalized = trim($name);
        
        // Convert to lowercase for case-insensitive comparison
        $normalized = strtolower($normalized);
        
        // Collapse multiple internal spaces to single space
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return $normalized;
    }

    /**
     * Runs a WP_Query for all dogs whose given meta field equals the
     * given parent id, mapping the results to Domain Dog entities.
     *
     * @param string $metaKey  Meta key to filter by (sire or dam id).
     * @param DogId  $parentId Identifier the meta field must match.
     *
     * @return array<int, Dog>
     */
    private function findByMetaField(string $metaKey, DogId $parentId): array
    {
        $query = new WP_Query([
            'post_type' => JetEngineFieldMap::CPT_SLUG,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => $metaKey,
                    'value' => $parentId->toInt(),
                    'compare' => '=',
                ],
            ],
        ]);

        $dogs = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $dogs[] = $this->mapper->toDomain($post, get_post_meta($post->ID));
        }

        return $dogs;
    }
}
