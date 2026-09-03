<?php
/**
 * Single node inside a pedigree tree.
 *
 * @package GameDog\PedigreeEngine\Domain\Entity
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Entity;

use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

final class PedigreeNode
{
    /** @var DogId|null Null when the slot is empty / unknown. */
    private $dogId;

    /** @var string */
    private $name;

    /** @var int Generation index relative to the root (0 = subject). */
    private $generation;

    /** @var string Side label: subject|sire|dam|unknown */
    private $side;

    /** @var PedigreeNode|null */
    private $sireNode;

    /** @var PedigreeNode|null */
    private $damNode;

    /** @var CoiPercentage|null */
    private $coi;

    /** @var bool Whether this dog is a common ancestor in the current tree. */
    private $isCommonAncestor;

    /** @var string */
    private $thumbnailUrl;

    /** @var string */
    private $permalink;

    /** @var string */
    private $gender;

    public function __construct(
        ?DogId $dogId,
        string $name = '',
        int $generation = 0,
        string $side = 'unknown',
        ?PedigreeNode $sireNode = null,
        ?PedigreeNode $damNode = null,
        ?CoiPercentage $coi = null,
        bool $isCommonAncestor = false,
        string $thumbnailUrl = '',
        string $permalink = '',
        string $gender = ''
    ) {
        $this->dogId            = $dogId;
        $this->name             = $name;
        $this->generation       = max(0, $generation);
        $this->side             = $side;
        $this->sireNode         = $sireNode;
        $this->damNode          = $damNode;
        $this->coi              = $coi;
        $this->isCommonAncestor = $isCommonAncestor;
        $this->thumbnailUrl     = $thumbnailUrl;
        $this->permalink        = $permalink;
        $this->gender           = $gender;
    }

    public static function empty(int $generation = 0, string $side = 'unknown'): self
    {
        return new self(null, '', $generation, $side);
    }

    public function dogId(): ?DogId
    {
        return $this->dogId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function generation(): int
    {
        return $this->generation;
    }

    public function side(): string
    {
        return $this->side;
    }

    public function sireNode(): ?PedigreeNode
    {
        return $this->sireNode;
    }

    public function damNode(): ?PedigreeNode
    {
        return $this->damNode;
    }

    public function coi(): ?CoiPercentage
    {
        return $this->coi;
    }

    public function isCommonAncestor(): bool
    {
        return $this->isCommonAncestor;
    }

    public function thumbnailUrl(): string
    {
        return $this->thumbnailUrl;
    }

    public function permalink(): string
    {
        return $this->permalink;
    }

    public function gender(): string
    {
        return $this->gender;
    }

    public function isEmpty(): bool
    {
        return $this->dogId === null;
    }

    public function withSireNode(?PedigreeNode $node): self
    {
        $clone           = clone $this;
        $clone->sireNode = $node;

        return $clone;
    }

    public function withDamNode(?PedigreeNode $node): self
    {
        $clone          = clone $this;
        $clone->damNode = $node;

        return $clone;
    }

    public function markAsCommonAncestor(bool $flag = true): self
    {
        $clone                    = clone $this;
        $clone->isCommonAncestor  = $flag;

        return $clone;
    }

    public function withCoi(?CoiPercentage $coi): self
    {
        $clone      = clone $this;
        $clone->coi = $coi;

        return $clone;
    }

    /**
     * Collect every dog ID appearing in this subtree (including self).
     *
     * @return array<int, int> Map of dogId => dogId
     */
    public function collectDogIds(): array
    {
        $ids = [];

        if ($this->dogId !== null) {
            $id       = $this->dogId->toInt();
            $ids[$id] = $id;
        }

        if ($this->sireNode !== null) {
            foreach ($this->sireNode->collectDogIds() as $id => $_) {
                $ids[$id] = $id;
            }
        }

        if ($this->damNode !== null) {
            foreach ($this->damNode->collectDogIds() as $id => $_) {
                $ids[$id] = $id;
            }
        }

        return $ids;
    }
}
