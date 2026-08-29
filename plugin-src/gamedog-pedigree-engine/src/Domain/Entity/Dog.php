<?php

declare(strict_types=1);

namespace GDPE\Domain\Entity;

use GDPE\Domain\ValueObject\DogId;

/**
 * Represents a single dog, independent of how it is stored.
 *
 * This entity is produced by {@see \GDPE\namespace\DogRepositoryInterface}
 * implementations and consumed by domain services such as the pedigree
 * tree builder and sibling finder. It carries only the data those
 * services need — identity, display name, profile link, and parent
 * references — and has no knowledge of WordPress, JetEngine, or how it
 * was fetched.
 */
final readonly class Dog
{
    /**
     * @param DogId       $id         This dog's identifier.
     * @param string      $name       Display name (the post title).
     * @param string      $permalink  Link to this dog's profile page.
     * @param DogId|null  $fatherId   The father's identifier, or null if
     *                                 not recorded.
     * @param DogId|null  $motherId   The mother's identifier, or null if
     *                                 not recorded.
     */
    public function __construct(
        private DogId $id,
        private string $name,
        private string $permalink,
        private ?DogId $fatherId = null,
        private ?DogId $motherId = null
    ) {
    }

    /**
     * Returns this dog's identifier.
     *
     * @return DogId The dog ID.
     */
    public function id(): DogId
    {
        return $this->id;
    }

    /**
     * Returns this dog's display name.
     *
     * @return string The dog's name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the link to this dog's profile page.
     *
     * @return string The permalink.
     */
    public function permalink(): string
    {
        return $this->permalink;
    }

    /**
     * Returns the father's identifier, if recorded.
     *
     * @return DogId|null The father's DogId, or null if unknown.
     */
    public function fatherId(): ?DogId
    {
        return $this->fatherId;
    }

    /**
     * Returns the mother's identifier, if recorded.
     *
     * @return DogId|null The mother's DogId, or null if unknown.
     */
    public function motherId(): ?DogId
    {
        return $this->motherId;
    }

    /**
     * Determines whether this dog has a recorded father.
     *
     * @return bool True if fatherId is not null.
     */
    public function hasFather(): bool
    {
        return $this->fatherId !== null;
    }

    /**
     * Determines whether this dog has a recorded mother.
     *
     * @return bool True if motherId is not null.
     */
    public function hasMother(): bool
    {
        return $this->motherId !== null;
    }
}