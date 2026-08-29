<?php

declare(strict_types=1);

namespace GDPE\Domain\Service;

use GDPE\Domain\ValueObject\RelationConfig;

/**
 * Domain port that supplies the configured sire and dam relations.
 *
 * Infrastructure decides how relation IDs are obtained.
 */
interface RelationConfigProviderInterface
{
    /**
     * Relation configuration used to resolve sires.
     */
    public function sireRelationConfig(): RelationConfig;

    /**
     * Relation configuration used to resolve dams.
     */
    public function damRelationConfig(): RelationConfig;
}