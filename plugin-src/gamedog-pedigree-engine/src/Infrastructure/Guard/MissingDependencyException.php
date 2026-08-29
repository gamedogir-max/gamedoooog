<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Guard;

use RuntimeException;

/**
 * Thrown by DependencyGuard when a required dependency (PHP version,
 * Elementor, Elementor Pro, or JetEngine) is missing or outdated.
 *
 * Infrastructure-layer exception — never thrown from or caught by
 * Domain code.
 */
final class MissingDependencyException extends RuntimeException
{
    /**
     * @param array<int, string> $missingDependencies Human-readable list of unmet requirements.
     */
    public function __construct(private readonly array $missingDependencies)
    {
        parent::__construct(
            'GameDog Pedigree Engine cannot run: ' . implode(', ', $missingDependencies),
        );
    }

    /**
     * @return array<int, string>
     */
    public function getMissingDependencies(): array
    {
        return $this->missingDependencies;
    }
}
