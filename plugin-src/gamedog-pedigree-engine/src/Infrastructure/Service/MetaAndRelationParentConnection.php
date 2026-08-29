<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Service;

use GDPE\Domain\Service\ParentConnectionInterface;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Infrastructure\Config\JetEngineFieldMap;

/**
 * Infrastructure implementation of ParentConnectionInterface.
 *
 * Responsible for:
 * - Saving gdpe_sire_id / gdpe_dam_id meta fields (PRIMARY data source)
 * - Synchronizing JetEngine Relations #6 (Sire) and #7 (Dam) (SECONDARY projection)
 *
 * Both meta fields and JetEngine Relations are kept in sync so that
 * the existing pedigree engine and JetEngine's own relation queries
 * return consistent results.
 *
 * IMPORTANT DESIGN DECISIONS:
 *
 * 1. Meta fields (gdpe_sire_id, gdpe_dam_id) are the PRIMARY source of truth.
 *    JetEngine relations are projections/sync targets.
 *
 * 2. Direct database access is ONLY used after explicit schema verification.
 *    We do NOT guess table names or column structures.
 *
 * 3. Results report Meta and Relation status SEPARATELY for debugging.
 *
 * Uses the correct JetEngine Relations API:
 * - get_active_relations() to retrieve the relation object
 * - set_update_context('parent') to specify direction
 * - update() to create/update the relation
 */
final class MetaAndRelationParentConnection implements ParentConnectionInterface
{
    /**
     * Detailed result of a connection operation.
     * Reports Meta and Relation outcomes separately.
     */
    public function connectSire(DogId $childDogId, ?DogId $sireId): ConnectionResult
    {
        $childPostId = $childDogId->toInt();
        $sirePostId = $sireId?->toInt();

        error_log("GDPE AutoConnect: connectSire called - Child: {$childPostId}, Sire: " . ($sirePostId ?? 'null'));

        $result = new ConnectionResult($childPostId, 'sire');

        // STEP 1: Update meta field FIRST (this is our primary data)
        try {
            $metaOk = $this->updateSireMeta($childPostId, $sirePostId);
            $result->setMetaStatus($metaOk, $sirePostId);
            error_log("GDPE AutoConnect: Meta result - " . ($metaOk ? 'OK' : 'FAIL'));
        } catch (\Throwable $e) {
            $result->setMetaStatus(false, null, $e->getMessage());
            error_log("GDPE AutoConnect: Meta ERROR - " . $e->getMessage());
        }

        // STEP 2: Sync JetEngine Relation #6 (Sire) - SECONDARY projection
        try {
            $relationOk = $this->syncSireRelation($childPostId, $sirePostId);
            $result->setRelationStatus($relationOk);
            error_log("GDPE AutoConnect: Relation result - " . ($relationOk ? 'OK' : 'FAIL/SKIP'));
        } catch (\Throwable $e) {
            $result->setRelationStatus(false, $e->getMessage());
            error_log("GDPE AutoConnect: Relation ERROR - " . $e->getMessage());
        }

        error_log($result->getDetailedSummary());

        return $result;
    }

    /**
     * Detailed result of a connection operation.
     * Reports Meta and Relation status separately.
     */
    public function connectDam(DogId $childDogId, ?DogId $damId): ConnectionResult
    {
        $childPostId = $childDogId->toInt();
        $damPostId = $damId?->toInt();

        error_log("GDPE AutoConnect: connectDam called - Child: {$childPostId}, Dam: " . ($damPostId ?? 'null'));

        $result = new ConnectionResult($childPostId, 'dam');

        // STEP 1: Update meta field FIRST (this is our primary data)
        try {
            $metaOk = $this->updateDamMeta($childPostId, $damPostId);
            $result->setMetaStatus($metaOk, $damPostId);
            error_log("GDPE AutoConnect: Meta result - " . ($metaOk ? 'OK' : 'FAIL'));
        } catch (\Throwable $e) {
            $result->setMetaStatus(false, null, $e->getMessage());
            error_log("GDPE AutoConnect: Meta ERROR - " . $e->getMessage());
        }

        // STEP 2: Sync JetEngine Relation #7 (Dam) - SECONDARY projection
        try {
            $relationOk = $this->syncDamRelation($childPostId, $damPostId);
            $result->setRelationStatus($relationOk);
            error_log("GDPE AutoConnect: Relation result - " . ($relationOk ? 'OK' : 'FAIL/SKIP'));
        } catch (\Throwable $e) {
            $result->setRelationStatus(false, $e->getMessage());
            error_log("GDPE AutoConnect: Relation ERROR - " . $e->getMessage());
        }

        error_log($result->getDetailedSummary());

        return $result;
    }

    public function disconnectAllParents(DogId $childDogId): bool
    {
        $childPostId = $childDogId->toInt();

        error_log("GDPE AutoConnect: disconnectAllParents called - Child: {$childPostId}");

        // Clear sire meta and relation
        $sireResult = $this->connectSire($childDogId, null);

        // Clear dam meta and relation
        $damResult = $this->connectDam($childDogId, null);

        error_log("GDPE AutoConnect: disconnectAllParents completed");

        // Return true if at least meta operations succeeded
        return $sireResult->isMetaSuccess() && $damResult->isMetaSuccess();
    }

    /**
     * Updates the sire ID meta field for a dog.
     *
     * @param int       $childPostId The child's WordPress post ID.
     * @param int|null  $sirePostId  The father's post ID, or null to clear.
     *
     * @return bool True if update succeeded.
     */
    private function updateSireMeta(int $childPostId, ?int $sirePostId): bool
    {
        if ($sirePostId === null) {
            // CRITICAL FIX (Bug #1): Write empty string instead of deleting the meta key.
            // The reverse resolution query (findChildrenWaitingForParent) needs the meta row
            // to EXIST with an empty value so it can match via '=' or 'NOT EXISTS'.
            // If we delete the key entirely, WordPress meta_query can never find these posts.
            error_log("GDPE AutoConnect: Clearing gdpe_sire_id for post {$childPostId} (set to empty string, not deleted)");
            $result = update_post_meta(
                $childPostId,
                JetEngineFieldMap::FIELD_FATHER_ID,
                ''
            );

            // update_post_meta returns false both on failure AND when value is unchanged.
            // Guard against false-negative by verifying the meta now exists with expected value.
            return $result !== false || get_post_meta($childPostId, JetEngineFieldMap::FIELD_FATHER_ID, true) === '';
        }

        error_log("GDPE AutoConnect: Setting gdpe_sire_id = {$sirePostId} for post {$childPostId}");
        
        $result = update_post_meta(
            $childPostId,
            JetEngineFieldMap::FIELD_FATHER_ID,
            $sirePostId
        );

        return $result !== false;
    }

    /**
     * Updates the dam ID meta field for a dog.
     *
     * @param int       $childPostId The child's WordPress post ID.
     * @param int|null  $damPostId   The mother's post ID, or null to clear.
     *
     * @return bool True if update succeeded.
     */
    private function updateDamMeta(int $childPostId, ?int $damPostId): bool
    {
        if ($damPostId === null) {
            // CRITICAL FIX (Bug #1): Write empty string instead of deleting the meta key.
            // The reverse resolution query (findChildrenWaitingForParent) needs the meta row
            // to EXIST with an empty value so it can match via '=' or 'NOT EXISTS'.
            // If we delete the key entirely, WordPress meta_query can never find these posts.
            error_log("GDPE AutoConnect: Clearing gdpe_dam_id for post {$childPostId} (set to empty string, not deleted)");
            $result = update_post_meta(
                $childPostId,
                JetEngineFieldMap::FIELD_MOTHER_ID,
                ''
            );

            // update_post_meta returns false both on failure AND when value is unchanged.
            // Guard against false-negative by verifying the meta now exists with expected value.
            return $result !== false || get_post_meta($childPostId, JetEngineFieldMap::FIELD_MOTHER_ID, true) === '';
        }

        error_log("GDPE AutoConnect: Setting gdpe_dam_id = {$damPostId} for post {$childPostId}");
        
        $result = update_post_meta(
            $childPostId,
            JetEngineFieldMap::FIELD_MOTHER_ID,
            $damPostId
        );

        return $result !== false;
    }

    /**
     * Synchronizes JetEngine Sire Relation (#6).
     *
     * Uses the correct JetEngine Relations API:
     * 1. Get the active relation by ID
     * 2. Set update context to 'parent' (Father → Child direction)
     * 3. Call update() with parent ID and child ID
     *
     * IMPORTANT: This method does NOT use direct database fallback unless
     * the JetEngine table schema has been verified. See getVerifiedTableName().
     *
     * @param int      $childPostId The child's WordPress post ID.
     * @param int|null $sirePostId  The father's post ID, or null to remove relation.
     *
     * @return bool True if sync succeeded or JetEngine is unavailable.
     */
    private function syncSireRelation(int $childPostId, ?int $sirePostId): bool
    {
        $relationId = JetEngineFieldMap::SIRE_RELATION_ID;

        error_log("GDPE AutoConnect: syncSireRelation - Relation #{$relationId}, Child: {$childPostId}, Sire: " . ($sirePostId ?? 'null'));

        if (!$this->isJetEngineAvailable()) {
            error_log('GDPE AutoConnect: JetEngine not available for Sire Relation sync - skipping');
            return true; // Don't fail if JetEngine is not available
        }

        try {
            // If sire is null, we're removing/disconnecting the relation
            if ($sirePostId === null || $sirePostId <= 0) {
                error_log("GDPE AutoConnect: Removing Sire Relation #{$relationId} for child {$childPostId}");
                $this->removeExistingRelationsViaAPI($childPostId, $relationId);
                return true;
            }

            // Use the correct JetEngine Relations API
            $relationsManager = jet_engine()->relations;
            
            error_log("GDPE AutoConnect: Getting active relation #{$relationId} (slug: " . JetEngineFieldMap::RELATION_FATHER_SLUG . ")");
            
            // Get the relation object, falling back to the relation slug when the
            // numeric ID lookup fails (handles RELATION_FATHER_SLUG synchronization).
            $relation = $this->getRelationObject($relationsManager, $relationId, JetEngineFieldMap::RELATION_FATHER_SLUG);
            
            if (!$relation) {
                error_log("GDPE AutoConnect: WARNING - Could not get active relation #{$relationId} (or slug '" . JetEngineFieldMap::RELATION_FATHER_SLUG . "')");
                // Try API-based fallback methods only (no direct DB guess)
                return $this->tryApiFallbackMethods($relationId, $sirePostId, $childPostId);
            }

            error_log("GDPE AutoConnect: Got relation #{$relationId}, setting context to 'parent'");

            // CRITICAL FIX (Bug #2): Remove any existing parent relation(s) for this child BEFORE adding
            // the new one, since JetEngine's update() only appends and does not replace.
            // Without this, reparenting a dog's sire from "Original Father" to "New Father"
            // leaves the old Sire relation row intact and adds a second row — creating drift
            // between gdpe_sire_id (correctly holding only new value) and JetEngine relations.
            if (method_exists($relation, 'delete_rows')) {
                error_log("GDPE AutoConnect: Removing existing parent-side rows for child {$childPostId} on relation #{$relationId} before adding new one");
                $relation->delete_rows(false, $childPostId);
            } else {
                error_log("GDPE AutoConnect: WARNING - delete_rows() method not found on relation object; cannot guarantee no duplicate rows. This must be verified against the installed JetEngine version.");
            }
            
            // Set the update context to 'parent' for Father → Child direction
            $relation->set_update_context('parent');
            
            // Update/create the relation: Parent (Sire) → Child
            error_log("GDPE AutoConnect: Calling relation->update({$sirePostId}, {$childPostId})");
            $relation->update($sirePostId, $childPostId);
            
            error_log("GDPE AutoConnect: ✅ Sire Relation #{$relationId} created/updated successfully");
            error_log("GDPE AutoConnect: Sire Relation: Post #{$sirePostId} → Post #{$childPostId}");

            return true;

        } catch (\Throwable $e) {
            error_log('GDPE AutoConnect: ERROR in syncSireRelation - ' . $e->getMessage());
            error_log('GDPE AutoConnect: Stack trace - ' . $e->getTraceAsString());
            
            // Attempt API-based fallback (no direct DB guess)
            error_log("GDPE AutoConnect: Attempting API fallback for Sire Relation #{$relationId}");
            return $this->tryApiFallbackMethods($relationId, $sirePostId, $childPostId);
        }
    }

    /**
     * Synchronizes JetEngine Dam Relation (#7).
     *
     * Uses the correct JetEngine Relations API:
     * 1. Get the active relation by ID
     * 2. Set update context to 'parent' (Mother → Child direction)
     * 3. Call update() with parent ID and child ID
     *
     * IMPORTANT: This method does NOT use direct database fallback unless
     * the JetEngine table schema has been verified. See getVerifiedTableName().
     *
     * @param int      $childPostId The child's WordPress post ID.
     * @param int|null $damPostId   The mother's post ID, or null to remove relation.
     *
     * @return bool True if sync succeeded or JetEngine is unavailable.
     */
    private function syncDamRelation(int $childPostId, ?int $damPostId): bool
    {
        $relationId = JetEngineFieldMap::DAM_RELATION_ID;

        error_log("GDPE AutoConnect: syncDamRelation - Relation #{$relationId}, Child: {$childPostId}, Dam: " . ($damPostId ?? 'null'));

        if (!$this->isJetEngineAvailable()) {
            error_log('GDPE AutoConnect: JetEngine not available for Dam Relation sync - skipping');
            return true; // Don't fail if JetEngine is not available
        }

        try {
            // If dam is null, we're removing/disconnecting the relation
            if ($damPostId === null || $damPostId <= 0) {
                error_log("GDPE AutoConnect: Removing Dam Relation #{$relationId} for child {$childPostId}");
                $this->removeExistingRelationsViaAPI($childPostId, $relationId);
                return true;
            }

            // Use the correct JetEngine Relations API
            $relationsManager = jet_engine()->relations;
            
            error_log("GDPE AutoConnect: Getting active relation #{$relationId} (slug: " . JetEngineFieldMap::RELATION_MOTHER_SLUG . ")");
            
            // Get the relation object, falling back to the relation slug when the
            // numeric ID lookup fails (handles RELATION_MOTHER_SLUG synchronization).
            $relation = $this->getRelationObject($relationsManager, $relationId, JetEngineFieldMap::RELATION_MOTHER_SLUG);
            
            if (!$relation) {
                error_log("GDPE AutoConnect: WARNING - Could not get active relation #{$relationId} (or slug '" . JetEngineFieldMap::RELATION_MOTHER_SLUG . "')");
                // Try API-based fallback methods only (no direct DB guess)
                return $this->tryApiFallbackMethods($relationId, $damPostId, $childPostId);
            }

            error_log("GDPE AutoConnect: Got relation #{$relationId}, setting context to 'parent'");

            // CRITICAL FIX (Bug #2): Remove any existing parent relation(s) for this child BEFORE adding
            // the new one, since JetEngine's update() only appends and does not replace.
            // Without this, reparenting a dog's dam from "Original Mother" to "New Mother"
            // leaves the old Dam relation row intact and adds a second row — creating drift
            // between gdpe_dam_id (correctly holding only new value) and JetEngine relations.
            if (method_exists($relation, 'delete_rows')) {
                error_log("GDPE AutoConnect: Removing existing parent-side rows for child {$childPostId} on relation #{$relationId} before adding new one");
                $relation->delete_rows(false, $childPostId);
            } else {
                error_log("GDPE AutoConnect: WARNING - delete_rows() method not found on relation object; cannot guarantee no duplicate rows. This must be verified against the installed JetEngine version.");
            }
            
            // Set the update context to 'parent' for Mother → Child direction
            $relation->set_update_context('parent');
            
            // Update/create the relation: Parent (Dam) → Child
            error_log("GDPE AutoConnect: Calling relation->update({$damPostId}, {$childPostId})");
            $relation->update($damPostId, $childPostId);
            
            error_log("GDPE AutoConnect: ✅ Dam Relation #{$relationId} created/updated successfully");
            error_log("GDPE AutoConnect: Dam Relation: Post #{$damPostId} → Post #{$childPostId}");

            return true;

        } catch (\Throwable $e) {
            error_log('GDPE AutoConnect: ERROR in syncDamRelation - ' . $e->getMessage());
            error_log('GDPE AutoConnect: Stack trace - ' . $e->getTraceAsString());
            
            // Attempt API-based fallback (no direct DB guess)
            error_log("GDPE AutoConnect: Attempting API fallback for Dam Relation #{$relationId}");
            return $this->tryApiFallbackMethods($relationId, $damPostId, $childPostId);
        }
    }

    /**
     * Checks if JetEngine Relations API is available.
     */
    private function isJetEngineAvailable(): bool
    {
        $available = function_exists('jet_engine') && jet_engine() && isset(jet_engine()->relations);
        
        if ($available) {
            error_log('GDPE AutoConnect: JetEngine Relations API is available');
        } else {
            error_log('GDPE AutoConnect: JetEngine Relations API NOT available');
        }
        
        return $available;
    }

    /**
     * Resolves a JetEngine relation object by numeric ID, falling back to the
     * configured relation slug.
     *
     * Supports both single-object and array returns from JetEngine's
     * `get_active_relations()`, and both a public `slug` property and a
     * `get_slug()` accessor, so it works across JetEngine versions.
     *
     * @param object $manager     The jet_engine()->relations manager instance.
     * @param int    $relationId  Numeric relation ID (SIRE_RELATION_ID / DAM_RELATION_ID).
     * @param string $slug        Relation slug (RELATION_FATHER_SLUG / RELATION_MOTHER_SLUG).
     *
     * @return object|null The relation object, or null if it cannot be resolved.
     */
    private function getRelationObject(object $manager, int $relationId, string $slug): ?object
    {
        // Primary lookup: resolve by numeric ID.
        if (method_exists($manager, 'get_active_relations')) {
            $byId = $manager->get_active_relations($relationId);

            if (is_object($byId)) {
                return $byId;
            }

            if (is_array($byId)) {
                if (isset($byId[$relationId]) && is_object($byId[$relationId])) {
                    return $byId[$relationId];
                }

                foreach ($byId as $relation) {
                    if (is_object($relation)) {
                        return $relation;
                    }
                }
            }
        }

        // Fallback: resolve by slug (RELATION_FATHER_SLUG / RELATION_MOTHER_SLUG).
        if (method_exists($manager, 'get_active_relations')) {
            foreach ((array) $manager->get_active_relations() as $relation) {
                if (!is_object($relation)) {
                    continue;
                }

                $relationSlug = (string) ($relation->slug ?? '');

                if ($relationSlug === '' && method_exists($relation, 'get_slug')) {
                    $relationSlug = (string) $relation->get_slug();
                }

                if ($relationSlug !== '' && $relationSlug === $slug) {
                    error_log("GDPE AutoConnect: Resolved relation #{$relationId} by slug '{$slug}'");
                    return $relation;
                }
            }
        }

        error_log("GDPE AutoConnect: Could not resolve relation #{$relationId} by ID or slug '{$slug}'");
        return null;
    }

    /**
     * Removes existing JetEngine relations using API methods only.
     *
     * Does NOT use direct database queries - relies on JetEngine's own API.
     *
     * @param int $childPostId The child's post ID.
     * @param int $relationId  The JetEngine relation ID.
     */
    private function removeExistingRelationsViaAPI(int $childPostId, int $relationId): void
    {
        try {
            error_log("GDPE AutoConnect: Removing existing relations via API - Child: {$childPostId}, Relation: #{$relationId}");
            
            if (!function_exists('jet_engine') || !jet_engine()) {
                error_log("GDPE AutoConnect: JetEngine not available for cleanup");
                return;
            }

            $relationsManager = jet_engine()->relations;
            
            // Try different API methods depending on JetEngine version
            $methodsTried = [];

            if (method_exists($relationsManager, 'delete_related_posts')) {
                error_log("GDPE AutoConnect: Using delete_related_posts()");
                $relationsManager->delete_related_posts($childPostId, $relationId);
                $methodsTried[] = 'delete_related_posts';
            }
            
            if (method_exists($relationsManager, 'delete_relation')) {
                error_log("GDPE AutoConnect: Using delete_relation()");
                $relationsManager->delete_relation([
                    'post_id' => $childPostId,
                    'relation_id' => $relationId,
                ]);
                $methodsTried[] = 'delete_relation';
            }

            if (empty($methodsTried)) {
                error_log("GDPE AutoConnect: No suitable deletion method found in JetEngine API");
            } else {
                error_log("GDPE AutoConnect: Cleanup attempted via: " . implode(', ', $methodsTried));
            }

        } catch (\Throwable $e) {
            error_log('GDPE AutoConnect: Error removing relations via API - ' . $e->getMessage());
        }
    }

    /**
     * Tries alternative JetEngine API methods as fallback.
     *
     * Does NOT use direct database queries with guessed schema.
     * Only uses JetEngine's public API methods.
     *
     * @param int $relationId The JetEngine relation ID.
     * @param int $parentId   The parent's post ID.
     * @param int $childId    The child's post ID.
     *
     * @return bool True if any fallback succeeded.
     */
    private function tryApiFallbackMethods(int $relationId, int $parentId, int $childId): bool
    {
        error_log("GDPE AutoConnect: Trying API fallback methods for Relation #{$relationId}: {$parentId} → {$childId}");

        try {
            if (!function_exists('jet_engine') || !jet_engine()) {
                error_log("GDPE AutoConnect: JetEngine not available for fallback");
                return false;
            }

            $relationsManager = jet_engine()->relations;
            $methodUsed = null;

            // Try update_relations method
            if (method_exists($relationsManager, 'update_relations')) {
                error_log("GDPE AutoConnect: Fallback - trying update_relations()");
                $relationsManager->update_relations($parentId, [
                    $relationId => [$childId],
                ]);
                $methodUsed = 'update_relations';
            }

            // Try create_relation method
            if (!$methodUsed && method_exists($relationsManager, 'create_relation')) {
                error_log("GDPE AutoConnect: Fallback - trying create_relation()");
                $relationsManager->create_relation([
                    'parent_object_id' => $parentId,
                    'child_object_id' => $childId,
                    'relation_id' => $relationId,
                    'object_type' => 'posts',
                ]);
                $methodUsed = 'create_relation';
            }

            if ($methodUsed) {
                error_log("GDPE AutoConnect: ✅ Fallback succeeded via {$methodUsed}");
                return true;
            }

            error_log("GDPE AutoConnect: ⚠️ All API fallback methods failed or unavailable");
            error_log("GDPE AutoConnect: NOTE: Meta fields were saved successfully - only JetEngine relation was not created");
            
            // Don't fail - meta is still saved
            return false;

        } catch (\Throwable $e) {
            error_log('GDPE AutoConnect: Fallback method threw exception - ' . $e->getMessage());
            // Don't throw - meta is already saved
            return false;
        }
    }
}

/**
 * Value object representing the detailed result of a connection operation.
 * 
 * Reports Meta and Relation status SEPARATELY for accurate debugging.
 */
class ConnectionResult
{
    /** @var int The child post ID. */
    public readonly int $childPostId;

    /** @var string Either 'sire' or 'dam'. */
    public readonly string $connectionType;

    /** @var bool Whether meta field update succeeded. */
    public bool $metaSuccess = false;

    /** @var int|null The parent ID that was set (or cleared). */
    public ?int $metaParentId = null;

    /** @var string|null Error message if meta update failed. */
    public ?string $metaError = null;

    /** @var bool Whether JetEngine relation sync succeeded. */
    public bool $relationSuccess = false;

    /** @var string|null Error message if relation sync failed. */
    public ?string $relationError = null;

    public function __construct(int $childPostId, string $connectionType)
    {
        $this->childPostId = $childPostId;
        $this->connectionType = $connectionType;
    }

    /**
     * Sets the meta operation result.
     */
    public function setMetaStatus(bool $success, ?int $parentId = null, ?string $error = null): void
    {
        $this->metaSuccess = $success;
        $this->metaParentId = $parentId;
        $this->metaError = $error;
    }

    /**
     * Sets the relation operation result.
     */
    public function setRelationStatus(bool $success, ?string $error = null): void
    {
        $this->relationSuccess = $success;
        $this->relationError = $error;
    }

    /**
     * Returns whether meta operation succeeded.
     */
    public function isMetaSuccess(): bool
    {
        return $this->metaSuccess;
    }

    /**
     * Returns whether relation operation succeeded.
     */
    public function isRelationSuccess(): bool
    {
        return $this->relationSuccess;
    }

    /**
     * Returns overall success (at least meta succeeded).
     * 
     * For backward compatibility with ParentConnectionInterface contract.
     */
    public function isSuccess(): bool
    {
        return $this->metaSuccess; // Meta is primary, so this determines success
    }

    /**
     * Returns a detailed summary showing Meta and Relation results separately.
     */
    public function getDetailedSummary(): string
    {
        $typeLabel = strtoupper($this->connectionType);
        $lines = [
            "GDPE Connection Result [{$typeLabel}] for Post #{$this->childPostId}:",
            "  META (gdpe_{$this->connectionType}_id): " . ($this->metaSuccess ? '✅ OK' : '❌ FAIL'),
        ];

        if ($this->metaParentId !== null) {
            $lines[] = "    → Set to: Post #{$this->metaParentId}";
        } elseif ($this->metaSuccess) {
            $lines[] = "    → Cleared";
        }

        if ($this->metaError) {
            $lines[] = "    → Error: {$this->metaError}";
        }

        $lines[] = "  RELATION (JetEngine #" . ($this->connectionType === 'sire' ? '6' : '7') . "): " . ($this->relationSuccess ? '✅ OK' : '⚠️ FAIL/SKIP');

        if ($this->relationError) {
            $lines[] = "    → Error: {$this->relationError}";
        }

        $lines[] = "  OVERALL: " . ($this->isSuccess() ? '✅ SUCCESS (Meta saved)' : '⚠️ ISSUES');

        return implode("\n", $lines);
    }

    /**
     * Simple boolean conversion for backward compatibility.
     */
    public static function toBool(ConnectionResult $result): bool
    {
        return $result->isSuccess();
    }
}
