<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Application\Port\CacheInterface;
use GDPE\Application\Port\ParentAutoCreatorInterface;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\Service\ParentConnectionInterface;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Domain\Entity\Dog;
use GDPE\Infrastructure\Config\JetEngineFieldMap;
use GDPE\Infrastructure\Service\ConnectionResult;

/**
 * Use case responsible for automatically connecting parent dogs
 * when a dog post is published.
 *
 * Workflow:
 * 1. Read father_name / mother_name meta from the published dog
 * 2. Search for matching published dogs by exact name (using dog_name meta)
 * 3. Validate matches (exactly one match required, no self-parent)
 * 4. If no published parent is found, auto-create a minimal parent post
 *    (via ParentAutoCreatorInterface) and connect to it
 * 5. Save gdpe_sire_id / gdpe_dam_id via ParentConnectionInterface
 * 6. Sync JetEngine Relations #6 and #7
 * 7. Clear pedigree cache
 *
 * Failure to resolve a parent NEVER blocks publishing. Unresolved
 * parents are logged but the dog remains publishable.
 */
final readonly class AutoConnectParentsUseCase
{
    public function __construct(
        private DogRepositoryInterface $dogRepository,
        private ParentConnectionInterface $parentConnection,
        private CacheInterface $cache,
        private ParentAutoCreatorInterface $parentAutoCreator,
    ) {
    }

    /**
     * Executes the auto-connect process for a given dog post.
     *
     * @param int $postId The WordPress post ID of the published dog.
     *
     * @return AutoConnectResult Result of the auto-connect operation.
     */
    public function execute(int $postId): AutoConnectResult
    {
        $result = new AutoConnectResult($postId);

        error_log("=====================================================");
        error_log("GDPE AutoConnect: START processing dog post #{$postId}");
        error_log("=====================================================");

        try {
            // Get the dog entity
            $dogId = new DogId($postId);
            $dog = $this->dogRepository->findById($dogId);

            if ($dog === null) {
                error_log("GDPE AutoConnect: ERROR - Dog #{$postId} not found in repository");
                $result->addError('Dog not found in repository');
                return $result;
            }

            error_log("GDPE AutoConnect: Dog found - Name: '{$dog->name()}', ID: {$dog->id()}");

            // Read parent names from meta
            $fatherNameRaw = get_post_meta($postId, JetEngineFieldMap::META_FATHER_NAME, true);
            $motherNameRaw = get_post_meta($postId, JetEngineFieldMap::META_MOTHER_NAME, true);

            $fatherName = $this->sanitizeParentName($fatherNameRaw);
            $motherName = $this->sanitizeParentName($motherNameRaw);

            error_log("GDPE AutoConnect: Raw father_name = " . ($fatherNameRaw ?: '(empty)'));
            error_log("GDPE AutoConnect: Sanitized father_name = " . ($fatherName ?: '(empty)'));
            error_log("GDPE AutoConnect: Raw mother_name = " . ($motherNameRaw ?: '(empty)'));
            error_log("GDPE AutoConnect: Sanitized mother_name = " . ($motherName ?: '(empty)'));

            // Process father (sire)
            if (!empty($fatherName)) {
                error_log("GDPE AutoConnect: Processing FATHER connection for '{$fatherName}'");
                $sireResult = $this->resolveAndConnectSire($dogId, $fatherName, $dog);
                $result->mergeFrom($sireResult);
            } else {
                error_log("GDPE AutoConnect: No father name provided, skipping sire connection");
                // Clear sire if name is empty (allows reparenting)
                $this->parentConnection->connectSire($dogId, null);
                $result->markSireSkipped('No father name provided');
            }

            // Process mother (dam)
            if (!empty($motherName)) {
                error_log("GDPE AutoConnect: Processing MOTHER connection for '{$motherName}'");
                $damResult = $this->resolveAndConnectDam($dogId, $motherName, $dog);
                $result->mergeFrom($damResult);
            } else {
                error_log("GDPE AutoConnect: No mother name provided, skipping dam connection");
                // Clear dam if name is empty (allows reparenting)
                $this->parentConnection->connectDam($dogId, null);
                $result->markDamSkipped('No mother name provided');
            }

            // Mark as processed with timestamp
            $timestamp = current_time('mysql');
            update_post_meta(
                $postId,
                JetEngineFieldMap::META_PARENTS_CONNECTED,
                $timestamp
            );
            
            error_log("GDPE AutoConnect: Marked as processed at {$timestamp}");

            // Clear pedigree cache so changes are immediately visible
            error_log("GDPE AutoConnect: Clearing pedigree cache...");
            $this->cache->clear();
            error_log("GDPE AutoConnect: ✅ Pedigree cache cleared");

            $result->markSuccess();

            error_log("GDPE AutoConnect: ✅ COMPLETED successfully for post #{$postId}");
            error_log($result->getSummary());

        } catch (\Throwable $e) {
            $errorMsg = 'Auto-connect failed: ' . $e->getMessage();
            $result->addError($errorMsg);
            error_log("GDPE AutoConnect: ❌ FATAL ERROR - {$errorMsg}");
            error_log("GDPE AutoConnect: Exception type: " . get_class($e));
            error_log("GDPE AutoConnect: File: " . $e->getFile() . ':' . $e->getLine());
            error_log("GDPE AutoConnect: Stack trace: " . $e->getTraceAsString());
        }

        error_log("=====================================================");
        error_log("GDPE AutoConnect: END processing dog post #{$postId}");
        error_log("=====================================================");

        return $result;
    }

    /**
     * Resolves and connects the sire (father).
     *
     * @param DogId   $childDogId The child's identifier.
     * @param string  $fatherName The father's name from meta.
     * @param Dog     $childDog   The child dog entity (for self-parent check).
     *
     * @return AutoConnectResult Partial result for sire connection.
     */
    private function resolveAndConnectSire(DogId $childDogId, string $fatherName, Dog $childDog): AutoConnectResult
    {
        $result = new AutoConnectResult($childDogId->toInt());

        error_log("GDPE AutoConnect [SIRE]: Searching for father named '{$fatherName}'");

        // Find matching dogs
        $matches = $this->dogRepository->findPublishedByName($fatherName, $childDogId);

        error_log("GDPE AutoConnect [SIRE]: Found " . count($matches) . " potential match(es)");

        // Ambiguity is a hard rejection: never auto-create a duplicate parent.
        if (count($matches) > 1) {
            $names = array_map(fn(Dog $d) => $d->name() . ' (ID: ' . $d->id() . ')', $matches);
            $msg = "Multiple dogs named '{$fatherName}': " . implode(', ', $names);
            error_log("GDPE AutoConnect [SIRE]: ⚠️ AMBIGUOUS - {$msg}");
            $result->markSireAmbiguous($msg);
            return $result;
        }

        $sire = null;
        $autoCreated = false;

        if (count($matches) === 1) {
            // Exactly one match - connect to existing published dog.
            $sire = $matches[0];

            error_log("GDPE AutoConnect [SIRE]: Found unique match - Post #{$sire->id()} named '{$sire->name()}'");

            // Additional self-parent safety check (belt and suspenders)
            if ($sire->id()->equals($childDogId)) {
                $msg = 'Self-parent detected';
                error_log("GDPE AutoConnect [SIRE]: ❌ REJECTED - {$msg}");
                $result->markSireRejected($msg);
                return $result;
            }
        } else {
            // No published parent exists yet → auto-create a minimal Sire post.
            error_log("GDPE AutoConnect [SIRE]: No published dog found with name '{$fatherName}' - attempting auto-create");
            $createdId = $this->parentAutoCreator->createSire($fatherName, $childDogId);

            if ($createdId !== null && !$createdId->equals($childDogId)) {
                $autoCreated = true;
                error_log("GDPE AutoConnect [SIRE]: ✅ Auto-created/using Sire Post #{$createdId->toInt()} named '{$fatherName}'");
                $sire = $this->dogRepository->findById($createdId);
            }
        }

        if ($sire === null) {
            $msg = "No published dog found with name: {$fatherName}" . ($autoCreated ? ' (auto-create failed)' : '');
            error_log("GDPE AutoConnect [SIRE]: ⚠️ UNRESOLVED - {$msg}");
            $result->markSireUnresolved($msg);

            // CRITICAL FIX (Bug #1): Explicitly write empty sire connection so the meta row exists.
            // Without this, gdpe_sire_id is never created for orphaned dogs, which breaks
            // reverse/deferred resolution because findChildrenWaitingForParent() can never
            // match a post that has NO row at all for the meta key.
            $this->parentConnection->connectSire($childDogId, null);
            error_log("GDPE AutoConnect [SIRE]: Wrote empty gdpe_sire_id (empty string) to enable future reverse resolution");

            return $result;
        }

        error_log("GDPE AutoConnect [SIRE]: Connecting sire Post #{$sire->id()} to child Post #{$childDogId->toInt()}");

        $connectionResult = $this->parentConnection->connectSire($childDogId, $sire->id());

        // Report Meta and Relation status SEPARATELY
        if ($connectionResult instanceof ConnectionResult) {
            if ($connectionResult->isMetaSuccess()) {
                error_log("GDPE AutoConnect [SIRE]: ✅ META SUCCESS - gdpe_sire_id saved");

                if ($autoCreated) {
                    $result->markSireAutoCreated($sire);
                } else {
                    $result->markSireConnected($sire);
                }

                if (!$connectionResult->isRelationSuccess()) {
                    $result->addWarning("Sire meta saved but JetEngine Relation #6 sync failed: " . ($connectionResult->relationError ?: 'Unknown error'));
                    error_log("GDPE AutoConnect [SIRE]: ⚠️ RELATION WARNING - " . ($connectionResult->relationError ?: 'JetEngine relation not synced'));
                }
            } else {
                $msg = 'Failed to save sire connection: ' . ($connectionResult->metaError ?: 'Meta update failed');
                error_log("GDPE AutoConnect [SIRE]: ❌ FAIL - {$msg}");
                $result->addError($msg);
            }
        } elseif ($connectionResult) {
            // Legacy bool response
            error_log("GDPE AutoConnect [SIRE]: ✅ SUCCESS - Father connected (legacy)");

            if ($autoCreated) {
                $result->markSireAutoCreated($sire);
            } else {
                $result->markSireConnected($sire);
            }
        } else {
            $msg = 'Failed to save sire connection';
            error_log("GDPE AutoConnect [SIRE]: ❌ FAIL - {$msg}");
            $result->addError($msg);
        }

        return $result;
    }

    /**
     * Resolves and connects the dam (mother).
     *
     * @param DogId   $childDogId The child's identifier.
     * @param string  $motherName The mother's name from meta.
     * @param Dog     $childDog   The child dog entity (for self-parent check).
     *
     * @return AutoConnectResult Partial result for dam connection.
     */
    private function resolveAndConnectDam(DogId $childDogId, string $motherName, Dog $childDog): AutoConnectResult
    {
        $result = new AutoConnectResult($childDogId->toInt());

        error_log("GDPE AutoConnect [DAM]: Searching for mother named '{$motherName}'");

        // Find matching dogs
        $matches = $this->dogRepository->findPublishedByName($motherName, $childDogId);

        error_log("GDPE AutoConnect [DAM]: Found " . count($matches) . " potential match(es)");

        // Ambiguity is a hard rejection: never auto-create a duplicate parent.
        if (count($matches) > 1) {
            $names = array_map(fn(Dog $d) => $d->name() . ' (ID: ' . $d->id() . ')', $matches);
            $msg = "Multiple dogs named '{$motherName}': " . implode(', ', $names);
            error_log("GDPE AutoConnect [DAM]: ⚠️ AMBIGUOUS - {$msg}");
            $result->markDamAmbiguous($msg);
            return $result;
        }

        $dam = null;
        $autoCreated = false;

        if (count($matches) === 1) {
            // Exactly one match - connect to existing published dog.
            $dam = $matches[0];

            error_log("GDPE AutoConnect [DAM]: Found unique match - Post #{$dam->id()} named '{$dam->name()}'");

            // Additional self-parent safety check (belt and suspenders)
            if ($dam->id()->equals($childDogId)) {
                $msg = 'Self-parent detected';
                error_log("GDPE AutoConnect [DAM]: ❌ REJECTED - {$msg}");
                $result->markDamRejected($msg);
                return $result;
            }
        } else {
            // No published parent exists yet → auto-create a minimal Dam post.
            error_log("GDPE AutoConnect [DAM]: No published dog found with name '{$motherName}' - attempting auto-create");
            $createdId = $this->parentAutoCreator->createDam($motherName, $childDogId);

            if ($createdId !== null && !$createdId->equals($childDogId)) {
                $autoCreated = true;
                error_log("GDPE AutoConnect [DAM]: ✅ Auto-created/using Dam Post #{$createdId->toInt()} named '{$motherName}'");
                $dam = $this->dogRepository->findById($createdId);
            }
        }

        if ($dam === null) {
            $msg = "No published dog found with name: {$motherName}" . ($autoCreated ? ' (auto-create failed)' : '');
            error_log("GDPE AutoConnect [DAM]: ⚠️ UNRESOLVED - {$msg}");
            $result->markDamUnresolved($msg);

            // CRITICAL FIX (Bug #1): Explicitly write empty dam connection so the meta row exists.
            // Without this, gdpe_dam_id is never created for orphaned dogs, which breaks
            // reverse/deferred resolution because findChildrenWaitingForParent() can never
            // match a post that has NO row at all for the meta key.
            $this->parentConnection->connectDam($childDogId, null);
            error_log("GDPE AutoConnect [DAM]: Wrote empty gdpe_dam_id (empty string) to enable future reverse resolution");

            return $result;
        }

        error_log("GDPE AutoConnect [DAM]: Connecting dam Post #{$dam->id()} to child Post #{$childDogId->toInt()}");

        $connectionResult = $this->parentConnection->connectDam($childDogId, $dam->id());

        // Report Meta and Relation status SEPARATELY
        if ($connectionResult instanceof ConnectionResult) {
            if ($connectionResult->isMetaSuccess()) {
                error_log("GDPE AutoConnect [DAM]: ✅ META SUCCESS - gdpe_dam_id saved");

                if ($autoCreated) {
                    $result->markDamAutoCreated($dam);
                } else {
                    $result->markDamConnected($dam);
                }

                if (!$connectionResult->isRelationSuccess()) {
                    $result->addWarning("Dam meta saved but JetEngine Relation #7 sync failed: " . ($connectionResult->relationError ?: 'Unknown error'));
                    error_log("GDPE AutoConnect [DAM]: ⚠️ RELATION WARNING - " . ($connectionResult->relationError ?: 'JetEngine relation not synced'));
                }
            } else {
                $msg = 'Failed to save dam connection: ' . ($connectionResult->metaError ?: 'Meta update failed');
                error_log("GDPE AutoConnect [DAM]: ❌ FAIL - {$msg}");
                $result->addError($msg);
            }
        } elseif ($connectionResult) {
            // Legacy bool response
            error_log("GDPE AutoConnect [DAM]: ✅ SUCCESS - Mother connected (legacy)");

            if ($autoCreated) {
                $result->markDamAutoCreated($dam);
            } else {
                $result->markDamConnected($dam);
            }
        } else {
            $msg = 'Failed to save dam connection';
            error_log("GDPE AutoConnect [DAM]: ❌ FAIL - {$msg}");
            $result->addError($msg);
        }

        return $result;
    }

    /**
     * Sanitizes a parent name input.
     *
     * @param mixed $name Raw meta value.
     *
     * @return string Sanitized name, or empty string if invalid.
     */
    private function sanitizeParentName(mixed $name): string
    {
        if (!is_string($name) || trim($name) === '') {
            return '';
        }

        $sanitized = sanitize_text_field(trim($name));

        // Reject obviously invalid names (too short or too long)
        if (strlen($sanitized) < 2 || strlen($sanitized) > 200) {
            return '';
        }

        return $sanitized;
    }
}

/**
 * Value object representing the result of an auto-connect operation.
 */
class AutoConnectResult
{
    /** @var int The post ID this result pertains to. */
    public readonly int $postId;

    /** @var bool Whether the overall operation succeeded. */
    public bool $success = false;

    /** @var list<string> Errors encountered during processing. */
    public array $errors = [];

    /** @var list<string> Warnings encountered during processing (non-blocking issues). */
    public array $warnings = [];

    /** @var string|null Sire status message. */
    public ?string $sireStatus = null;

    /** @var string|null Dam status message. */
    public ?string $damStatus = null;

    /** @var Dog|null Connected sire, if successful. */
    public ?Dog $connectedSire = null;

    /** @var Dog|null Connected dam, if successful. */
    public ?Dog $connectedDam = null;

    public function __construct(int $postId)
    {
        $this->postId = $postId;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Adds a non-blocking warning (e.g., meta saved but relation failed).
     */
    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function markSuccess(): void
    {
        $this->success = true;
    }

    public function markSireConnected(Dog $sire): void
    {
        $this->connectedSire = $sire;
        $this->sireStatus = "Connected to sire: {$sire->name()} (ID: {$sire->id()})";
    }

    public function markDamConnected(Dog $dam): void
    {
        $this->connectedDam = $dam;
        $this->damStatus = "Connected to dam: {$dam->name()} (ID: {$dam->id()})";
    }

    public function markSireAutoCreated(Dog $sire): void
    {
        $this->connectedSire = $sire;
        $this->sireStatus = "Auto-created sire: {$sire->name()} (new post ID: {$sire->id()})";
    }

    public function markDamAutoCreated(Dog $dam): void
    {
        $this->connectedDam = $dam;
        $this->damStatus = "Auto-created dam: {$dam->name()} (new post ID: {$dam->id()})";
    }

    public function markSireUnresolved(string $reason): void
    {
        $this->sireStatus = "Sire unresolved: {$reason}";
    }

    public function markDamUnresolved(string $reason): void
    {
        $this->damStatus = "Dam unresolved: {$reason}";
    }

    public function markSireAmbiguous(string $reason): void
    {
        $this->sireStatus = "Sire ambiguous: {$reason}";
    }

    public function markDamAmbiguous(string $reason): void
    {
        $this->damStatus = "Dam ambiguous: {$reason}";
    }

    public function markSireRejected(string $reason): void
    {
        $this->sireStatus = "Sire rejected: {$reason}";
    }

    public function markDamRejected(string $reason): void
    {
        $this->damStatus = "Dam rejected: {$reason}";
    }

    public function markSireSkipped(string $reason): void
    {
        $this->sireStatus = "Sire skipped: {$reason}";
    }

    public function markDamSkipped(string $reason): void
    {
        $this->damStatus = "Dam skipped: {$reason}";
    }

    /**
     * Merges another result into this one (for combining sire/dam results).
     */
    public function mergeFrom(AutoConnectResult $other): void
    {
        $this->errors = array_merge($this->errors, $other->errors);

        if ($other->connectedSire !== null) {
            $this->connectedSire = $other->connectedSire;
            $this->sireStatus = $other->sireStatus;
        }

        if ($other->connectedDam !== null) {
            $this->connectedDam = $other->connectedDam;
            $this->damStatus = $other->damStatus;
        }

        // Copy status messages even without connected dogs
        if ($other->sireStatus !== null && $this->sireStatus === null) {
            $this->sireStatus = $other->sireStatus;
        }
        if ($other->damStatus !== null && $this->damStatus === null) {
            $this->damStatus = $other->damStatus;
        }
    }

    /**
     * Returns a human-readable summary of the result.
     * Shows Meta/Relation status separately when available.
     */
    public function getSummary(): string
    {
        $lines = ["GDPE Auto-Connect Result for Post #{$this->postId}:"];

        if ($this->success) {
            $lines[] = '  Status: ✅ SUCCESS';
        } else {
            $lines[] = '  Status: ⚠️ COMPLETED WITH ISSUES';
        }

        if ($this->sireStatus !== null) {
            $lines[] = "  Father: {$this->sireStatus}";
        }

        if ($this->damStatus !== null) {
            $lines[] = "  Mother: {$this->damStatus}";
        }

        // Show warnings (non-blocking issues like relation sync failures)
        foreach ($this->warnings as $warning) {
            $lines[] = "  ⚠️ Warning: {$warning}";
        }

        // Show errors (blocking issues)
        foreach ($this->errors as $error) {
            $lines[] = "  ❌ Error: {$error}";
        }

        return implode("\n", $lines);
    }
}
