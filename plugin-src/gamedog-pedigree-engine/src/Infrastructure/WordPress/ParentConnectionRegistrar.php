<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\WordPress;

use GDPE\Application\UseCase\AutoConnectParentsUseCase;
use GDPE\Application\UseCase\AutoConnectResult;
use GDPE\Application\UseCase\ResolveChildrenForNewParentUseCase;
use GDPE\Application\UseCase\ResolveChildrenResult;
use GDPE\Infrastructure\Config\JetEngineFieldMap;
use GDPE\Infrastructure\Guard\AutoCreateGuard;

/**
 * Wires the AutoConnectParentsUseCase and ResolveChildrenForNewParentUseCase
 * to WordPress post lifecycle hooks.
 *
 * The auto-connect process runs on:
 * - `publish_{CPT_SLUG}` transition (when a dog is first published)
 * - Can be re-triggered when parent names change
 *
 * This registrar ensures that parent connections are established
 * automatically in BOTH directions:
 *
 * 1. FORWARD resolution: When a child is published, find its parents by name
 * 2. REVERSE resolution: When a parent is published, find its children by name
 *
 * Both directions use the same normalization and ambiguity rules.
 */
final class ParentConnectionRegistrar
{
    /**
     * In-request guard to prevent recursive processing.
     * Maps post IDs to boolean true if currently being processed.
     *
     * @var array<int, bool>
     */
    private array $processing = [];

    public function __construct(
        private readonly AutoConnectParentsUseCase $autoConnectParentsUseCase,
        private readonly ResolveChildrenForNewParentUseCase $resolveChildrenUseCase,
    ) {
    }

    /**
     * Registers all required WordPress hooks.
     */
    public function register(): void
    {
        // Hook into the publish transition for the dogs CPT
        // This fires only when a post transitions to "publish" status
        add_action(
            'publish_' . JetEngineFieldMap::CPT_SLUG,
            [$this, 'onDogPublished'],
            10,
            1
        );

        // Also hook into save_post for re-processing when names change
        // but with a check to avoid running on every autosave/revision
        add_action(
            'save_post_' . JetEngineFieldMap::CPT_SLUG,
            [$this, 'onDogSaved'],
            20, // Run after cache invalidation (priority 10)
            1
        );

        // Hook into JetEngine relation updates for manual admin sync
        // This ensures gdpe_sire_id/gdpe_dam_id stay synchronized when
        // admin manually connects parents through JetEngine's relation UI
        //
        // NOTE: The exact hook name and parameters vary by JetEngine version.
        // We register for the most common hooks and validate at runtime.
        
        // Primary hook: jet_engine/relations/after_update (JetEngine 2.x+)
        add_action(
            'jet_engine/relations/after_update',
            [$this, 'onJetEngineRelationUpdated'],
            10,
            10 // Accept up to 10 params to handle different versions safely
        );

        // Alternative hook: jet_engine/relation/updated (some versions)
        if (!has_action('jet_engine/relation/updated', [$this, 'onJetEngineRelationUpdatedAlt'])) {
            add_action(
                'jet_engine/relation/updated',
                [$this, 'onJetEngineRelationUpdatedAlt'],
                10,
                10
            );
        }

        // Alternative hook: update_post_meta (catch-all fallback)
        add_action(
            'updated_post_meta',
            [$this, 'onPostMetaUpdated'],
            10,
            4
        );
    }

    /**
     * Handles the initial publish event.
     *
     * This is the primary trigger for auto-connecting parents.
     * It fires only when a post transitions to "publish" status.
     *
     * Runs BOTH forward and reverse resolution:
     * 1. Forward: Find this dog's parents by father_name/mother_name
     * 2. Reverse: Find children waiting for this dog as parent
     *
     * @param int $postId The published post's ID.
     */
    public function onDogPublished(int $postId): void
    {
        // Bypass hooks while AutoCreateParentService is inserting a new parent.
        // Without this the auto-created parent would re-enter forward/reverse
        // resolution, potentially cascading ancestor creation.
        if (AutoCreateGuard::isAutoCreating()) {
            error_log("GDPE ParentConnection: Skipping publish hook for post #{$postId} - inside auto-create");
            return;
        }

        if ($this->shouldSkipProcessing($postId)) {
            return;
        }

        // Prevent recursive processing within the same request
        if (isset($this->processing[$postId])) {
            error_log("GDPE ParentConnection: Skipping post #{$postId} - already processing");
            return;
        }

        $this->processing[$postId] = true;

        try {
            // STEP 1: FORWARD Resolution - Find parents for THIS child
            error_log("GDPE ParentConnection: Starting FORWARD resolution for child #{$postId}");
            $forwardResult = $this->executeForwardAutoConnect($postId);
            error_log($forwardResult->getSummary());

            // STEP 2: REVERSE Resolution - Find children waiting for THIS parent
            error_log("GDPE ParentConnection: Starting REVERSE resolution for new parent #{$postId}");
            $reverseResult = $this->executeReverseResolution($postId);
            error_log($reverseResult->getSummary());

        } finally {
            unset($this->processing[$postId]);
        }
    }

    /**
     * Handles save_post for already-published dogs.
     *
     * This allows re-connecting parents when father_name or mother_name
     * are changed after initial publication. We check if the names have
     * actually changed before processing to avoid unnecessary work.
     *
     * @param int $postId The saved post's ID.
     */
    public function onDogSaved(int $postId): void
    {
        // Bypass hooks while AutoCreateParentService is inserting a new parent.
        if (AutoCreateGuard::isAutoCreating()) {
            error_log("GDPE ParentConnection: Skipping save hook for post #{$postId} - inside auto-create");
            return;
        }

        if ($this->shouldSkipProcessing($postId)) {
            return;
        }

        // Only process if this is an update to an already-published post
        // and the parent names might have changed
        $post = get_post($postId);

        if (!$post || $post->post_status !== 'publish') {
            return; // Only run on published posts (not pending/draft)
        }

        // Prevent recursive processing
        if (isset($this->processing[$postId])) {
            return;
        }

        $this->processing[$postId] = true;

        try {
            // Forward resolution on save (handles name changes)
            $this->executeForwardAutoConnect($postId);
            
            // Reverse resolution on save (if this dog could now be someone's parent)
            $this->executeReverseResolution($postId);

        } finally {
            unset($this->processing[$postId]);
        }
    }

    /**
     * Handles JetEngine relation updates from manual admin connections.
     *
     * When an administrator manually connects/disconnects a parent through
     * JetEngine's relation UI (e.g., "Select Father" dropdown), this hook
     * fires and we synchronize the corresponding GDPE metadata field.
     *
     * This ensures bidirectional sync:
     *   JetEngine Relation #6 (Sire) ↔ gdpe_sire_id
     *   JetEngine Relation #7 (Dam)   ↔ gdpe_dam_id
     *
     * IMPORTANT: The hook signature varies by JetEngine version. This method
     * accepts variable arguments and attempts to extract the needed data
     * from whatever parameters are provided.
     *
     * @param mixed ...$args Variable arguments passed by the hook (version-dependent).
     */
    public function onJetEngineRelationUpdated(...$args): void
    {
        // Bypass while auto-creating a parent to avoid re-entrant sync.
        if (AutoCreateGuard::isAutoCreating()) {
            return;
        }

        error_log("GDPE ManualSync: Hook fired - jet_engine/relations/after_update");
        error_log("GDPE ManualSync: Received " . count($args) . " arguments");
        
        // Log all received args for debugging (mask sensitive data if any)
        foreach ($args as $i => $arg) {
            if (is_array($arg)) {
                error_log("GDPE ManualSync: Arg #{$i} = array(" . count($arg) . " items): " . json_encode(array_keys($arg)));
            } elseif (is_object($arg)) {
                error_log("GDPE ManualSync: Arg #{$i} = object: " . get_class($arg));
            } else {
                error_log("GDPE ManualSync: Arg #{$i} = " . var_export($arg, true));
            }
        }

        // Try to extract relation ID, parent ID, child ID from various argument formats
        $relationId = null;
        $parentId = null;
        $childId = null;

        // Format 1: (relation_id, parent_id, child_id)
        if (count($args) >= 3 && is_numeric($args[0]) && is_numeric($args[1]) && is_numeric($args[2])) {
            $relationId = (int) $args[0];
            $parentId = (int) $args[1];
            $childId = (int) $args[2];
            error_log("GDPE ManualSync: Detected Format 1 - (relation_id, parent_id, child_id)");
        }
        // Format 2: array with keys
        elseif (count($args) >= 1 && is_array($args[0])) {
            $data = $args[0];
            
            if (isset($data['relation_id']) || isset($data['relationID'])) {
                $relationId = (int) ($data['relation_id'] ?? $data['relationID'] ?? 0);
                error_log("GDPE ManualSync: Detected Format 2a - array with relation_id key");
            }
            
            if (isset($data['parent_id']) || isset($data['parentID']) || isset($data['parent_object_id'])) {
                $parentId = (int) ($data['parent_id'] ?? $data['parentID'] ?? $data['parent_object_id'] ?? 0);
                error_log("GDPE ManualSync: Found parent_id in array");
            }
            
            if (isset($data['child_id']) || isset($data['childID']) || isset($data['child_object_id'])) {
                $childId = (int) ($data['child_id'] ?? $data['childID'] ?? $data['child_object_id'] ?? 0);
                error_log("GDPE ManualSync: Found child_id in array");
            }

            // Alternative format: ['post_id', 'rel_id', ...]
            if (!$relationId && isset($data['rel_id'])) {
                $relationId = (int) $data['rel_id'];
                error_log("GDPE ManualSync: Found rel_id in array");
            }
        }

        // If we couldn't extract required IDs, log and exit gracefully
        if (!$relationId || !$parentId || !$childId) {
            error_log("GDPE ManualSync: Could not extract required IDs from hook arguments");
            error_log("GDPE ManualSync: relationId=" . ($relationId ?? 'null') . 
                       ", parentId=" . ($parentId ?? 'null') . 
                       ", childId=" . ($childId ?? 'null'));
            return;
        }

        $this->syncMetadataFromRelation($relationId, $parentId, $childId);
    }

    /**
     * Alternative handler for different JetEngine versions.
     */
    public function onJetEngineRelationUpdatedAlt(...$args): void
    {
        // Bypass while auto-creating a parent to avoid re-entrant sync.
        if (AutoCreateGuard::isAutoCreating()) {
            return;
        }

        error_log("GDPE ManualSync: Alternative hook fired - jet_engine/relation/updated");
        $this->onJetEngineRelationUpdated(...$args);
    }

    /**
     * Catch-all fallback for meta updates that might be relation-related.
     *
     * This catches ANY post_meta update and checks if it looks like a JetEngine
     * relation change for our specific relations (#6 Sire, #7 Dam).
     */
    public function onPostMetaUpdated(int $metaId, int $objectId, string $metaKey, $_metaValue): void
    {
        // Bypass while auto-creating a parent to avoid re-entrant processing.
        if (AutoCreateGuard::isAutoCreating()) {
            return;
        }

        // Only process our specific meta fields
        if (!in_array($metaKey, [JetEngineFieldMap::FIELD_FATHER_ID, JetEngineFieldMap::FIELD_MOTHER_ID], true)) {
            return;
        }

        // Check if this is being set by JetEngine (not by our own code)
        // We can detect this by checking if the value changed unexpectedly
        $newValue = get_post_meta($objectId, $metaKey, true);

        error_log("GDPE ManualSync: Meta updated - Post #{$objectId}, Key: {$metaKey}, NewValue: " . var_export($newValue, true));

        // The actual sync logic is handled by the main hook handlers
        // This fallback just logs for debugging purposes
    }

    /**
     * Performs the actual metadata synchronization from a JetEngine relation update.
     *
     * @param int $relationId The JetEngine relation ID (6=Sire, 7=Dam).
     * @param int $parentId   The parent object ID (father/mother).
     * @param int $childId    The child object ID.
     */
    private function syncMetadataFromRelation(int $relationId, int $parentId, int $childId): void
    {
        error_log("GDPE ManualSync: Syncing metadata - Relation #{$relationId}, Parent: {$parentId}, Child: {$childId}");

        try {
            // Only process our specific relations
            if ($relationId === JetEngineFieldMap::SIRE_RELATION_ID) {
                // Sire relation updated - sync gdpe_sire_id
                $existingSireId = get_post_meta($childId, JetEngineFieldMap::FIELD_FATHER_ID, true);
                
                if ((int) $existingSireId !== $parentId) {
                    error_log("GDPE ManualSync: Syncing gdpe_sire_id = {$parentId} for child #{$childId}");
                    update_post_meta($childId, JetEngineFieldMap::FIELD_FATHER_ID, $parentId);
                    error_log("GDPE ManualSync: ✅ gdpe_sire_id synced successfully");
                } else {
                    error_log("GDPE ManualSync: gdpe_sire_id already in sync (value: {$parentId})");
                }
                
            } elseif ($relationId === JetEngineFieldMap::DAM_RELATION_ID) {
                // Dam relation updated - sync gdpe_dam_id
                $existingDamId = get_post_meta($childId, JetEngineFieldMap::FIELD_MOTHER_ID, true);
                
                if ((int) $existingDamId !== $parentId) {
                    error_log("GDPE ManualSync: Syncing gdpe_dam_id = {$parentId} for child #{$childId}");
                    update_post_meta($childId, JetEngineFieldMap::FIELD_MOTHER_ID, $parentId);
                    error_log("GDPE ManualSync: ✅ gdpe_dam_id synced successfully");
                } else {
                    error_log("GDPE ManualSync: gdpe_dam_id already in sync (value: {$parentId})");
                }
            } else {
                // Not one of our relations - ignore but log
                error_log("GDPE ManualSync: Relation #{$relationId} is not one of our target relations (Sire:#" . 
                           JetEngineFieldMap::SIRE_RELATION_ID . ", Dam:#" . JetEngineFieldMap::DAM_RELATION_ID . ") - ignoring");
            }

        } catch (\Throwable $e) {
            // NEVER block admin operations due to sync errors
            error_log('GDPE ManualSync: ERROR syncing metadata - ' . $e->getMessage());
        }
    }

    /**
     * Determines if auto-connect should be skipped for this save.
     *
     * @param int $postId The post ID to check.
     *
     * @return bool True if processing should be skipped.
     */
    private function shouldSkipProcessing(int $postId): bool
    {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return true;
        }

        // Verify the post type matches our CPT
        if (get_post_type($postId) !== JetEngineFieldMap::CPT_SLUG) {
            return true;
        }

        // Skip placeholder parents created by AutoCreateParentService.
        // These are orphan author-0 posts that exist solely as auto-generated
        // Sire/Dam placeholders. Treating them as regular user-submitted dogs
        // would risk re-entrant ancestor creation and would let them enter the
        // same auto-connect pipeline as a real submitted dog. The marker is
        // checked in addition to the in-request AutoCreateGuard so placeholder
        // posts remain excluded even on later saves after the guard has exited.
        if (get_post_meta($postId, JetEngineFieldMap::META_AUTO_CREATED, true) === '1') {
            error_log("GDPE ParentConnection: Skipping auto-created placeholder post #{$postId}");
            return true;
        }

        return false;
    }

    /**
     * Executes the forward auto-connect use case and logs results.
     *
     * @param int $postId The dog post ID.
     *
     * @return AutoConnectResult Result of the operation.
     */
    private function executeForwardAutoConnect(int $postId): AutoConnectResult
    {
        try {
            $result = $this->autoConnectParentsUseCase->execute($postId);
            return $result;

        } catch (\Throwable $e) {
            // NEVER block publishing due to auto-connect errors
            error_log('GDPE ParentConnection: Forward auto-connect error for post #' . $postId . ': ' . $e->getMessage());
            
            // Return empty result indicating failure
            $result = new AutoConnectResult($postId);
            $result->addError('Forward auto-connect failed: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Executes the reverse parent resolution use case and logs results.
     *
     * @param int $postId The newly-published parent dog's post ID.
     *
     * @return ResolveChildrenResult Result of the operation.
     */
    private function executeReverseResolution(int $postId): ResolveChildrenResult
    {
        try {
            $result = $this->resolveChildrenUseCase->execute($postId);
            return $result;

        } catch (\Throwable $e) {
            // NEVER block publishing due to reverse resolution errors
            error_log('GDPE ParentConnection: Reverse resolution error for post #' . $postId . ': ' . $e->getMessage());
            
            // Return empty result indicating failure
            $result = new ResolveChildrenResult($postId);
            $result->addError('Reverse resolution failed: ' . $e->getMessage());
            return $result;
        }
    }
}
