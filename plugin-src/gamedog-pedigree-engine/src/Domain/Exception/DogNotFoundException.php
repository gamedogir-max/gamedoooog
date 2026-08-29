<?php

declare(strict_types=1);

namespace GDPE\Domain\Exception;

use GDPE\Domain\ValueObject\DogId;
use RuntimeException;

/**
 * Thrown when a Dog aggregate cannot be located by the repository.
 */
final class DogNotFoundException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Creates the exception for a given missing DogId.
     */
    public static function forId(DogId $dogId): self
    {
        return new self(
            sprintf(
                'Dog with id "%s" was not found.',
                (string) $dogId
            )
        );
    }
}