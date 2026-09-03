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

    /** @var string Registered name without game titles (parsed lazily). */
    private $registeredName = '';

    /** @var array<int, string> Game titles parsed from the name (lazily). */
    private $titles = [];

    /** @var bool Whether registered name / titles have been resolved. */
    private $nameParsed = false;

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

    /**
     * Registered name: the full name with any leading game titles removed.
     */
    public function registeredName(): string
    {
        $this->parseName();

        return $this->registeredName !== ''
            ? $this->registeredName
            : $this->name;
    }

    /**
     * Game titles / records present in the name (e.g. CH, GR CH, 1xW, ROM).
     *
     * @return array<int, string>
     */
    public function titles(): array
    {
        $this->parseName();

        return $this->titles;
    }

    /**
     * Split a dog name into titles and registered name.
     *
     * Titles are the leading achievement tokens (CH, GR CH, ROM, POR, BIS,
     * D.O.Y., G.I.S, NxW, NxLG ...) that precede the registered name.
     */
    private function parseName(): void
    {
        if ($this->nameParsed) {
            return;
        }

        $this->nameParsed = true;

        $name = trim($this->name);
        if ($name === '') {
            return;
        }

        $titles = [];

        // Strip a leading parenthesised titles group, e.g. "(CH, 1xW)".
        $remaining = (string) preg_replace('/^\s*\([^)]*\)\s*/u', '', $name);

        // Title patterns, longest / most specific first. The trailing
        // lookahead requires a separator or end-of-string so we never match
        // the middle of a name (e.g. "CH" inside "CHAMP").
        $patterns = [
            '/^(GRAND\s+CH(?:AMPION)?|GR\s+CH(?:AMPION)?|CHAMPION|CH)(?=[\s,.\/]|$)/iu',
            '/^(\d{1,2}\s*X\s*(?:W|LG))(?=[\s,.\/]|$)/iu',
            '/^(ROM|POR|BIS|GIS|G\.I\.S\.?|D\.O\.Y\.?|DOY)(?=[\s,.\/]|$)/iu',
        ];

        $matched = true;
        while ($matched && $remaining !== '') {
            $matched = false;

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $remaining, $m)) {
                    $titles[]   = strtoupper(trim($m[1]));
                    $remaining  = trim((string) substr($remaining, strlen($m[0])));
                    $remaining  = (string) preg_replace('/^[\s,.\/]+/u', '', $remaining);
                    $matched    = true;
                    break;
                }
            }
        }

        $this->registeredName = $remaining !== '' ? $remaining : $name;
        $this->titles         = array_values(array_unique($titles));
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
