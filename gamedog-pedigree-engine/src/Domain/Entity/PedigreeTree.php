<?php
/**
 * Pedigree tree aggregate for a subject dog.
 *
 * @package GameDog\PedigreeEngine\Domain\Entity
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Entity;

use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Domain\ValueObject\GenerationDepth;

final class PedigreeTree
{
    /** @var DogId */
    private $subjectId;

    /** @var PedigreeNode */
    private $root;

    /** @var GenerationDepth */
    private $depth;

    /** @var CoiPercentage|null */
    private $coi;

    /** @var array<int, int> Dog IDs that appear on both sire and dam sides. */
    private $commonAncestorIds;

    /**
     * @param DogId              $subjectId
     * @param PedigreeNode       $root
     * @param GenerationDepth    $depth
     * @param CoiPercentage|null $coi
     * @param array<int, int>    $commonAncestorIds
     */
    public function __construct(
        DogId $subjectId,
        PedigreeNode $root,
        GenerationDepth $depth,
        ?CoiPercentage $coi = null,
        array $commonAncestorIds = []
    ) {
        $this->subjectId         = $subjectId;
        $this->root              = $root;
        $this->depth             = $depth;
        $this->coi               = $coi;
        $this->commonAncestorIds = $commonAncestorIds;
    }

    public function subjectId(): DogId
    {
        return $this->subjectId;
    }

    public function root(): PedigreeNode
    {
        return $this->root;
    }

    public function depth(): GenerationDepth
    {
        return $this->depth;
    }

    public function coi(): ?CoiPercentage
    {
        return $this->coi;
    }

    /**
     * @return array<int, int>
     */
    public function commonAncestorIds(): array
    {
        return $this->commonAncestorIds;
    }

    public function withCoi(CoiPercentage $coi): self
    {
        $clone      = clone $this;
        $clone->coi = $coi;

        return $clone;
    }

    /**
     * @param array<int, int> $ids
     */
    public function withCommonAncestors(array $ids): self
    {
        $clone                     = clone $this;
        $clone->commonAncestorIds  = $ids;

        return $clone;
    }

    public function withRoot(PedigreeNode $root): self
    {
        $clone       = clone $this;
        $clone->root = $root;

        return $clone;
    }

    public function sireBranch(): ?PedigreeNode
    {
        return $this->root->sireNode();
    }

    public function damBranch(): ?PedigreeNode
    {
        return $this->root->damNode();
    }
}
