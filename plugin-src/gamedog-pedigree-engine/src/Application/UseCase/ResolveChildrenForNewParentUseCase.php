<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Application\Port\CacheInterface;
use GDPE\Domain\Repository\DogRepositoryInterface;
use GDPE\Domain\Service\ParentConnectionInterface;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Domain\Entity\Dog;
use GDPE\Infrastructure\Config\JetEngineFieldMap;

/**
 * Use case responsible for REVERSE/DEFERRED parent resolution.
 *
 * When a new parent dog (e.g., "Rex") is published, this use case searches
 * for existing published children whose father_name or mother_name matches
 * Rex's dog_name but whose gdpe_sire_id/gdpe_dam_id is still empty.
 *
 * This enables the "Day 30" scenario:
 * - Day 1: Child "Max" is published with father_name = "Rex" (Rex doesn't exist yet)
 * - Day 30: Parent "Rex" is finally published
 * - System automatically connects Rex → Max and sets Max.gdpe_sire_id = Rex's ID
 *
 * Workflow:
 * 1. Read dog_name from the newly-published parent
 * 2. Search for children waiting for a parent with this name (father or mother)
 * 3. For each child found, connect them using ParentConnectionInterface
 * 4. Clear pedigree cache so changes are visible immediately
 *
 * Failure to resolve children NEVER blocks publication of the new parent.
 */
final readonly class ResolveChildrenForNewParentUseCase
{
    public function __construct(
        private DogRepositoryInterface $dogRepository,
        private ParentConnectionInterface $parentConnection,
        private CacheInterface $cache,
    ) {
    }

    /**
     * Executes reverse parent resolution for a newly-published dog.
     *
     * This should be called AFTER forward auto-connect has run for this dog,
     * as it checks if THIS dog can serve as a parent for any existing children.
     *
     * @param int $postId The WordPress post ID of the newly-published parent dog.
     *
     * @return ResolveChildrenResult Result of the reverse resolution operation.
     */
    public function execute(int $postId): ResolveChildrenResult
    {
        $result = new ResolveChildrenResult($postId);

        error_log("=====================================================");
        error_log("GDPE ReverseConnect: START processing new parent #{$postId}");
        error_log("=====================================================");

        try {
            // Get the dog entity
            $parentDogId = new DogId($postId);
            $parentDog = $this->dogRepository->findById($parentDogId);

            if ($parentDog === null) {
                error_log("GDPE ReverseConnect: ERROR - Parent Dog #{$postId} not found in repository");
                $result->addError('Parent dog not found in repository');
                return $result;
            }

            // Get the dog's name from meta (the authoritative name field)
            $parentDogName = get_post_meta($postId, JetEngineFieldMap::META_DOG_NAME, true);

            if (empty($parentDogName)) {
                error_log("GDPE ReverseConnect: No dog_name meta for post #{$postId}, skipping reverse resolution");
                $result->markSkipped('No dog_name meta found');
                return $result;
            }

            // Normalize the name for matching
            $normalizedName = $this->normalizeName($parentDogName);

            // FIX v5: Initialize $sex from meta BEFORE using it (was undefined, causing PHP warning)
            $sex = (string) get_post_meta($postId, JetEngineFieldMap::META_SEX, true);

            error_log("GDPE ReverseConnect: New parent published - Name: '{$parentDogName}' (normalized: '{$normalizedName}')");
            error_log("GDPE ReverseConnect: Post ID: {$postId}, Sex: " . ($sex ?: 'unknown/not set'));

            // Determine if this dog could be a father or mother based on sex
            // IMPORTANT: Use flexible sex matching since actual JetEngine field values
            // may vary (e.g., 'male'/'female', 'm'/'f', 'Male'/'Female', etc.)
            
            $isPotentiallyMale = $this->couldBeMale($sex);
            $isPotentiallyFemale = $this->couldBeFemale($sex);
            
            error_log("GDPE ReverseConnect: Sex analysis - Could be male: " . ($isPotentiallyMale ? 'yes' : 'no') . 
                       ", Could be female: " . ($isPotentiallyFemale ? 'yes' : 'no'));

            // Search for children waiting for this parent as FATHER
            if ($isPotentiallyMale) {
                error_log("GDPE ReverseConnect: Searching for children waiting for father named '{$normalizedName}'");
                $fatherResult = $this->resolveAsFather($parentDogId, $normalizedName, $parentDog);
                $result->mergeFrom($fatherResult);
            } else {
                error_log("GDPE ReverseConnect: Skipping father search - sex indicates not male ('{$sex}')");
            }

            // Search for children waiting for this parent as MOTHER
            if ($isPotentiallyFemale) {
                error_log("GDPE ReverseConnect: Searching for children waiting for mother named '{$normalizedName}'");
                $motherResult = $this->resolveAsMother($parentDogId, $normalizedName, $parentDog);
                $result->mergeFrom($motherResult);
            } else {
                error_log("GDPE ReverseConnect: Skipping mother search - sex indicates not female ('{$sex}')");
            }

            // If sex was empty/unset, search BOTH directions to be safe
            if (empty($sex)) {
                error_log("GDPE ReverseConnect: Sex field empty - searched both father and mother directions");
            }

            // If we connected any children, clear cache
            if ($result->getTotalConnected() > 0) {
                error_log("GDPE ReverseConnect: Clearing pedigree cache after connecting {$result->getTotalConnected()} child(ren)");
                $this->cache->clear();
                error_log("GDPE ReverseConnect: ✅ Pedigree cache cleared");
            }

            $result->markSuccess();

            error_log("GDPE ReverseConnect: ✅ COMPLETED for post #{$postId}");
            error_log($result->getSummary());

        } catch (\Throwable $e) {
            $errorMsg = 'Reverse connection failed: ' . $e->getMessage();
            $result->addError($errorMsg);
            error_log("GDPE ReverseConnect: ❌ FATAL ERROR - {$errorMsg}");
            error_log("GDPE ReverseConnect: Exception type: " . get_class($e));
            error_log("GDPE ReverseConnect: File: " . $e->getFile() . ':' . $e->getLine());
            error_log("GDPE ReverseConnect: Stack trace: " . $e->getTraceAsString());
        }

        error_log("=====================================================");
        error_log("GDPE ReverseConnect: END processing new parent #{$postId}");
        error_log("=====================================================");

        return $result;
    }

    /**
     * Resolves children where this dog is the father (sire).
     *
     * @param DogId   $parentId      The parent dog's identifier.
     * @param string  $normalizedName The normalized parent name.
     * @param Dog     $parentDog     The parent dog entity.
     *
     * @return ResolveChildrenResult Partial result for father connections.
     */
    private function resolveAsFather(DogId $parentId, string $normalizedName, Dog $parentDog): ResolveChildrenResult
    {
        $result = new ResolveChildrenResult($parentId->toInt());

        // Find children waiting for a father with this name
        $waitingChildren = $this->dogRepository->findChildrenWaitingForParent(
            $normalizedName,
            'father',
            $parentId->toInt()
        );

        error_log("GDPE ReverseConnect [FATHER]: Found " . count($waitingChildren) . " child(ren) waiting");

        foreach ($waitingChildren as $child) {
            $childId = $child->id();

            error_log("GDPE ReverseConnect [FATHER]: Connecting to child #{$childId->toInt()} ('{$child->name()}')");

            // Connect this parent as sire to the child
            $connected = $this->parentConnection->connectSire($childId, $parentId);

            if ($connected) {
                error_log("GDPE ReverseConnect [FATHER]: ✅ SUCCESS - Connected to child #{$childId->toInt()}");
                $result->addConnectedChild($child, 'sire');
            } else {
                $msg = "Failed to connect sire to child #{$childId->toInt()}";
                error_log("GDPE ReverseConnect [FATHER]: ❌ FAIL - {$msg}");
                $result->addError($msg);
            }
        }

        return $result;
    }

    /**
     * Resolves children where this dog is the mother (dam).
     *
     * @param DogId   $parentId       The parent dog's identifier.
     * @param string  $normalizedName The normalized parent name.
     * @param Dog     $parentDog      The parent dog entity.
     *
     * @return ResolveChildrenResult Partial result for mother connections.
     */
    private function resolveAsMother(DogId $parentId, string $normalizedName, Dog $parentDog): ResolveChildrenResult
    {
        $result = new ResolveChildrenResult($parentId->toInt());

        // Find children waiting for a mother with this name
        $waitingChildren = $this->dogRepository->findChildrenWaitingForParent(
            $normalizedName,
            'mother',
            $parentId->toInt()
        );

        error_log("GDPE ReverseConnect [MOTHER]: Found " . count($waitingChildren) . " child(ren) waiting");

        foreach ($waitingChildren as $child) {
            $childId = $child->id();

            error_log("GDPE ReverseConnect [MOTHER]: Connecting to child #{$childId->toInt()} ('{$child->name()}')");

            // Connect this parent as dam to the child
            $connected = $this->parentConnection->connectDam($childId, $parentId);

            if ($connected) {
                error_log("GDPE ReverseConnect [MOTHER]: ✅ SUCCESS - Connected to child #{$childId->toInt()}");
                $result->addConnectedChild($child, 'dam');
            } else {
                $msg = "Failed to connect dam to child #{$childId->toInt()}";
                error_log("GDPE ReverseConnect [MOTHER]: ❌ FAIL - {$msg}");
                $result->addError($msg);
            }
        }

        return $result;
    }

    /**
     * Normalizes a dog name for safe comparison.
     *
     * Uses the same logic as JetEngineDogRepository::normalizeName().
     *
     * @param string $name The raw name to normalize.
     *
     * @return string The normalized name.
     */
    private function normalizeName(string $name): string
    {
        $normalized = trim($name);
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return $normalized;
    }

    /**
     * Determines if a sex value could indicate a male dog.
     *
     * Handles various possible JetEngine field values:
     * - 'male', 'Male', 'MALE'
     * - 'm', 'M'
     * - 'boy', 'Boy', 'BOY'
     * - '1' (if using numeric)
     * - Empty/unknown (search both directions)
     *
     * @param string $sex The raw sex value from meta.
     *
     * @return bool True if this could be male.
     */
    private function couldBeMale(string $sex): bool
    {
        // Empty means unknown - search both
        if (empty($sex) || trim($sex) === '') {
            return true;
        }

        $sexLower = strtolower(trim($sex));

        // Common male indicators
        $maleIndicators = ['male', 'm', 'boy', 'he', 'him', 'father', 'sire', '1'];

        return in_array($sexLower, $maleIndicators, true);
    }

    /**
     * Determines if a sex value could indicate a female dog.
     *
     * Handles various possible JetEngine field values:
     * - 'female', 'Female', 'FEMALE'
     * - 'f', 'F'
     * - 'girl', 'Girl', 'GIRL'
     * - '2' (if using numeric)
     * - Empty/unknown (search both directions)
     *
     * @param string $sex The raw sex value from meta.
     *
     * @return bool True if this could be female.
     */
    private function couldBeFemale(string $sex): bool
    {
        // Empty means unknown - search both
        if (empty($sex) || trim($sex) === '') {
            return true;
        }

        $sexLower = strtolower(trim($sex));

        // Common female indicators
        $femaleIndicators = ['female', 'f', 'girl', 'she', 'her', 'mother', 'dam', '2'];

        return in_array($sexLower, $femaleIndicators, true);
    }
}

/**
 * Value object representing the result of a reverse parent resolution operation.
 */
class ResolveChildrenResult
{
    /** @var int The post ID of the newly-published parent. */
    public readonly int $parentId;

    /** @var bool Whether the overall operation succeeded. */
    public bool $success = false;

    /** @var list<string> Errors encountered during processing. */
    public array $errors = [];

    /** @var list<array{dog: Dog, role: string}> Children that were successfully connected. */
    public array $connectedChildren = [];

    /** @var string|null Skip reason if operation was skipped. */
    public ?string $skipReason = null;

    public function __construct(int $parentId)
    {
        $this->parentId = $parentId;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function markSuccess(): void
    {
        $this->success = true;
    }

    public function markSkipped(string $reason): void
    {
        $this->skipReason = $reason;
        $this->success = true; // Skipping is not a failure
    }

    /**
     * Records a successfully connected child.
     *
     * @param Dog    $child The child that was connected.
     * @param string $role  Either 'sire' or 'dam'.
     */
    public function addConnectedChild(Dog $child, string $role): void
    {
        $this->connectedChildren[] = [
            'dog' => $child,
            'role' => $role,
        ];
    }

    /**
     * Returns total number of children connected.
     */
    public function getTotalConnected(): int
    {
        return count($this->connectedChildren);
    }

    /**
     * Merges another result into this one.
     */
    public function mergeFrom(ResolveChildrenResult $other): void
    {
        $this->errors = array_merge($this->errors, $other->errors);
        $this->connectedChildren = array_merge($this->connectedChildren, $other->connectedChildren);
        
        if ($other->skipReason !== null && $this->skipReason === null) {
            $this->skipReason = $other->skipReason;
        }
    }

    /**
     * Returns a human-readable summary of the result.
     */
    public function getSummary(): string
    {
        $lines = ["GDPE Reverse-Connect Result for Parent Post #{$this->parentId}:"];

        if ($this->skipReason !== null) {
            $lines[] = "  Status: ⏭️ SKIPPED - {$this->skipReason}";
        } elseif ($this->success) {
            $lines[] = '  Status: ✅ SUCCESS';
        } else {
            $lines[] = '  Status: ⚠️ COMPLETED WITH ISSUES';
        }

        $lines[] = "  Children Connected: {$this->getTotalConnected()}";

        foreach ($this->connectedChildren as $connected) {
            $dog = $connected['dog'];
            $role = $connected['role'];
            $lines[] = "  → {$role}: {$dog->name()} (ID: {$dog->id()})";
        }

        foreach ($this->errors as $error) {
            $lines[] = "  Error: {$error}";
        }

        return implode("\n", $lines);
    }
}
