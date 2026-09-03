<?php
/**
 * Dog aggregate root (domain entity).
 *
 * @package GameDog\PedigreeEngine\Domain\Entity
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Domain\Entity;

use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

final class Dog
{
    /** @var DogId */
    private $id;

    /** @var string */
    private $name;

    /** @var DogId|null */
    private $sireId;

    /** @var DogId|null */
    private $damId;

    /** @var CoiPercentage|null */
    private $coi;

    /** @var string */
    private $gender;

    /** @var string */
    private $thumbnailUrl;

    /** @var string */
    private $permalink;

    /**
     * @param DogId              $id
     * @param string             $name
     * @param DogId|null         $sireId
     * @param DogId|null         $damId
     * @param CoiPercentage|null $coi
     * @param string             $gender
     * @param string             $thumbnailUrl
     * @param string             $permalink
     */
    public function __construct(
        DogId $id,
        string $name,
        ?DogId $sireId = null,
        ?DogId $damId = null,
        ?CoiPercentage $coi = null,
        string $gender = '',
        string $thumbnailUrl = '',
        string $permalink = ''
    ) {
        $this->id           = $id;
        $this->name         = $name !== '' ? $name : ('Dog #' . $id->toInt());
        $this->sireId       = $sireId;
        $this->damId        = $damId;
        $this->coi          = $coi;
        $this->gender       = $gender;
        $this->thumbnailUrl = $thumbnailUrl;
        $this->permalink    = $permalink;
    }

    public function id(): DogId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function sireId(): ?DogId
    {
        return $this->sireId;
    }

    public function damId(): ?DogId
    {
        return $this->damId;
    }

    public function coi(): ?CoiPercentage
    {
        return $this->coi;
    }

    public function gender(): string
    {
        return $this->gender;
    }

    public function thumbnailUrl(): string
    {
        return $this->thumbnailUrl;
    }

    public function permalink(): string
    {
        return $this->permalink;
    }

    public function hasSire(): bool
    {
        return $this->sireId !== null;
    }

    public function hasDam(): bool
    {
        return $this->damId !== null;
    }

    public function withCoi(CoiPercentage $coi): self
    {
        $clone      = clone $this;
        $clone->coi = $coi;

        return $clone;
    }

    public function withParents(?DogId $sireId, ?DogId $damId): self
    {
        $clone         = clone $this;
        $clone->sireId = $sireId;
        $clone->damId  = $damId;

        return $clone;
    }
}
